<?php
// Enable error reporting only in development
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Set page-specific variables
$page_title = 'Split PDF - Triniva';
$page_description = 'Split PDF files into multiple documents. Split by individual pages, custom ranges, or fixed page count.';

// Include configuration and header
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Initialize variables
$errors = [];
$success = false;
$downloadLink = '';

// Function to get total pages in PDF using Ghostscript
function getTotalPDFPages($pdfFile) {
    global $errors;
    
    // Try using Ghostscript
    $gsPath = defined('GS_PATH') ? GS_PATH : '/usr/bin/gs';
    
    // Use a simpler approach - create a test PDF to count pages
    $tempFile = TEMP_DIR . uniqid('count_') . '.txt';
    $command = $gsPath . " -q -dNODISPLAY -dNOPAUSE -dBATCH -dNOSAFER -c \"(" . 
               escapeshellarg($pdfFile) . ") (r) file runpdfbegin pdfpagecount = quit\" > " . 
               escapeshellarg($tempFile) . " 2>&1";
    
    exec($command, $output, $returnVar);
    
    if (file_exists($tempFile)) {
        $pageCount = trim(file_get_contents($tempFile));
        @unlink($tempFile);
        
        if (is_numeric($pageCount) && $pageCount > 0) {
            return (int)$pageCount;
        }
    }
    
    // Alternative method - try to extract page info
    $command = $gsPath . " -q -dNODISPLAY -c \"(" . escapeshellarg($pdfFile) . 
               ") (r) file runpdfbegin pdfpagecount = quit\" 2>&1";
    exec($command, $output, $returnVar);
    
    if (!empty($output) && is_numeric($output[0])) {
        return (int)$output[0];
    }
    
    // If all else fails, try splitting and see what happens
    return false;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        verifyCSRFToken($_POST['csrf_token'] ?? '');
        
        if (!isset($_FILES['pdf_file'])) {
            throw new RuntimeException('No file uploaded.');
        }

        // Validate file
        validateFile($_FILES['pdf_file'], ['application/pdf']);
        
        $uploadedFile = $_FILES['pdf_file'];
        $splitMode = $_POST['split_mode'] ?? 'single';
        $pageRanges = $_POST['page_ranges'] ?? '';
        $pageCount = (int)($_POST['page_count'] ?? 1);
        
        // Create unique filename
        $inputFile = UPLOAD_DIR . uniqid('split_input_') . '.pdf';
        
        if (!move_uploaded_file($uploadedFile['tmp_name'], $inputFile)) {
            throw new RuntimeException('Failed to save uploaded file.');
        }
        
        // Register file for cleanup
        $_SESSION['temp_files'][] = $inputFile;
        
        // Get total pages
        $totalPages = getTotalPDFPages($inputFile);
        if ($totalPages === false) {
            // Assume at least 1 page if we can't determine
            $totalPages = 100; // Set a reasonable maximum
        }
        
        // Create temporary directory for split files
        $tempDir = TEMP_DIR . uniqid('split_') . '/';
        if (!mkdir($tempDir, 0777, true)) {
            throw new RuntimeException('Failed to create temporary directory.');
        }
        
        $splitFiles = [];
        $gsPath = defined('GS_PATH') ? GS_PATH : '/usr/bin/gs';
        
        if ($splitMode === 'single') {
            // Split into individual pages
            $actualPages = 0;
            for ($i = 1; $i <= $totalPages; $i++) {
                $outputFile = $tempDir . sprintf("page_%03d.pdf", $i);
                
                $command = $gsPath . " -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER " .
                          "-dFirstPage={$i} -dLastPage={$i} " .
                          "-sOutputFile=" . escapeshellarg($outputFile) . " " .
                          escapeshellarg($inputFile) . " 2>&1";
                
                exec($command, $output, $returnVar);
                
                if ($returnVar === 0 && file_exists($outputFile) && filesize($outputFile) > 0) {
                    $splitFiles[] = $outputFile;
                    $actualPages = $i;
                } else {
                    // No more pages
                    @unlink($outputFile);
                    break;
                }
            }
            
            if (empty($splitFiles)) {
                throw new RuntimeException('Failed to split PDF. The file might be corrupted or protected.');
            }
            
        } elseif ($splitMode === 'range' && !empty($pageRanges)) {
            // Split by custom ranges
            $ranges = array_map('trim', explode(',', $pageRanges));
            $rangeIndex = 1;
            
            foreach ($ranges as $range) {
                if (empty($range)) continue;
                
                $outputFile = $tempDir . sprintf("range_%03d.pdf", $rangeIndex);
                
                if (strpos($range, '-') !== false) {
                    // Handle range like "1-3"
                    list($start, $end) = array_map('intval', explode('-', $range));
                    if ($start < 1) $start = 1;
                    if ($end < $start) $end = $start;
                    
                    $command = $gsPath . " -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER " .
                              "-dFirstPage={$start} -dLastPage={$end} " .
                              "-sOutputFile=" . escapeshellarg($outputFile) . " " .
                              escapeshellarg($inputFile) . " 2>&1";
                } else {
                    // Single page
                    $page = (int)$range;
                    if ($page < 1) continue;
                    
                    $command = $gsPath . " -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER " .
                              "-dFirstPage={$page} -dLastPage={$page} " .
                              "-sOutputFile=" . escapeshellarg($outputFile) . " " .
                              escapeshellarg($inputFile) . " 2>&1";
                }
                
                exec($command, $output, $returnVar);
                
                if ($returnVar === 0 && file_exists($outputFile) && filesize($outputFile) > 0) {
                    $splitFiles[] = $outputFile;
                    $rangeIndex++;
                } else {
                    @unlink($outputFile);
                }
            }
            
            if (empty($splitFiles)) {
                throw new RuntimeException('Failed to split PDF. Please check your page ranges.');
            }
            
        } elseif ($splitMode === 'fixed' && $pageCount > 0) {
            // Split by fixed page count
            $partIndex = 1;
            $pageNum = 1;
            
            while ($pageNum <= $totalPages) {
                $start = $pageNum;
                $end = $pageNum + $pageCount - 1;
                
                $outputFile = $tempDir . sprintf("part_%03d.pdf", $partIndex);
                
                $command = $gsPath . " -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER " .
                          "-dFirstPage={$start} -dLastPage={$end} " .
                          "-sOutputFile=" . escapeshellarg($outputFile) . " " .
                          escapeshellarg($inputFile) . " 2>&1";
                
                exec($command, $output, $returnVar);
                
                if ($returnVar === 0 && file_exists($outputFile) && filesize($outputFile) > 0) {
                    $splitFiles[] = $outputFile;
                    $partIndex++;
                    $pageNum = $end + 1;
                } else {
                    @unlink($outputFile);
                    break;
                }
                
                // Safety check to prevent infinite loop
                if ($partIndex > 1000) break;
            }
            
            if (empty($splitFiles)) {
                throw new RuntimeException('Failed to split PDF into fixed page counts.');
            }
        } else {
            throw new RuntimeException('Invalid split mode or parameters.');
        }
        
        // Create ZIP file with all split PDFs
        if (count($splitFiles) === 1) {
            // Single file - provide direct download
            $outputFile = UPLOAD_DIR . uniqid('split_') . '.pdf';
            if (copy($splitFiles[0], $outputFile)) {
                $_SESSION['temp_files'][] = $outputFile;
                $downloadLink = 'download.php?file=' . urlencode(basename($outputFile));
                $success = true;
            }
        } else {
            // Multiple files - create ZIP
            $zipFile = UPLOAD_DIR . uniqid('split_') . '.zip';
            $zip = new ZipArchive();
            
            if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
                foreach ($splitFiles as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();
                
                $_SESSION['temp_files'][] = $zipFile;
                $downloadLink = 'download.php?file=' . urlencode(basename($zipFile));
                $success = true;
            } else {
                throw new RuntimeException('Failed to create ZIP file.');
            }
        }
        
        // Clean up temporary files
        foreach ($splitFiles as $file) {
            @unlink($file);
        }
        @rmdir($tempDir);
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        logError('Split PDF Error', ['error' => $e->getMessage()]);
    }
}

// Additional scripts for this page
$additional_scripts = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('pdfFile');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const removeFile = document.getElementById('removeFile');
    const splitBtn = document.getElementById('splitBtn');
    const splitForm = document.getElementById('splitForm');
    const loader = document.getElementById('loader');
    
    const splitModeRadios = document.querySelectorAll('input[name="split_mode"]');
    const rangeInput = document.getElementById('rangeInput');
    const countInput = document.getElementById('countInput');
    
    // Handle split mode changes
    splitModeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            rangeInput.style.display = 'none';
            countInput.style.display = 'none';
            
            if (this.value === 'range') {
                rangeInput.style.display = 'block';
            } else if (this.value === 'fixed') {
                countInput.style.display = 'block';
            }
        });
    });
    
    // Drag and drop functionality
    uploadArea.addEventListener('click', () => fileInput.click());
    
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0 && files[0].type === 'application/pdf') {
            fileInput.files = files;
            handleFileSelect();
        }
    });
    
    fileInput.addEventListener('change', handleFileSelect);
    
    removeFile.addEventListener('click', () => {
        fileInput.value = '';
        fileInfo.style.display = 'none';
        uploadArea.style.display = 'block';
        splitBtn.disabled = true;
    });
    
    function handleFileSelect() {
        const file = fileInput.files[0];
        if (file) {
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.style.display = 'block';
            uploadArea.style.display = 'none';
            splitBtn.disabled = false;
        }
    }
    
    function formatFileSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (bytes >= 1024 && i < units.length - 1) {
            bytes /= 1024;
            i++;
        }
        return bytes.toFixed(2) + ' ' + units[i];
    }
    
    splitForm.addEventListener('submit', (e) => {
        const selectedMode = document.querySelector('input[name="split_mode"]:checked').value;
        
        if (selectedMode === 'range') {
            const ranges = document.getElementById('page_ranges').value.trim();
            if (!ranges) {
                e.preventDefault();
                alert('Please enter page ranges');
                return;
            }
        }
        
        splitBtn.disabled = true;
        loader.style.display = 'block';
    });
});
</script>
HTML;
?>

<div class="tool-page">
    <div class="container">
        <div class="tool-header">
            <h1><i class="fas fa-cut"></i> Split PDF</h1>
            <p>Split PDF files into multiple documents</p>
        </div>

        <div class="tool-content">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Your PDF has been split successfully!
                </div>
                
                <div style="text-align: center; margin: 2rem 0;">
                    <a href="<?php echo htmlspecialchars($downloadLink); ?>" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download Split PDFs
                    </a>
                </div>
                
                <div style="text-align: center;">
                    <a href="split.php" class="btn btn-secondary">Split Another PDF</a>
                </div>
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data" id="splitForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="upload-area" id="uploadArea">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <div class="upload-text">Drag & Drop your PDF here</div>
                        <div class="upload-subtext">or click to browse</div>
                        <input type="file" name="pdf_file" id="pdfFile" class="file-input" accept=".pdf" required>
                    </div>

                    <div id="fileInfo" style="display: none;">
                        <div class="file-list">
                            <div class="file-item">
                                <div class="file-info">
                                    <i class="fas fa-file-pdf file-icon"></i>
                                    <div>
                                        <div class="file-name" id="fileName"></div>
                                        <div class="file-size" id="fileSize"></div>
                                    </div>
                                </div>
                                <button type="button" class="file-remove" id="removeFile">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Split Mode</label>
                        <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="radio" name="split_mode" value="single" checked style="margin-right: 0.5rem;">
                                <span>Individual Pages</span>
                            </label>
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="radio" name="split_mode" value="range" style="margin-right: 0.5rem;">
                                <span>Page Ranges</span>
                            </label>
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="radio" name="split_mode" value="fixed" style="margin-right: 0.5rem;">
                                <span>Fixed Page Count</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group" id="rangeInput" style="display: none;">
                        <label class="form-label" for="page_ranges">Page Ranges</label>
                        <input type="text" name="page_ranges" id="page_ranges" class="form-control" 
                               placeholder="e.g., 1-3, 5, 7-9">
                        <small style="color: #666;">Enter page numbers or ranges separated by commas</small>
                    </div>

                    <div class="form-group" id="countInput" style="display: none;">
                        <label class="form-label" for="page_count">Pages per Split</label>
                        <input type="number" name="page_count" id="page_count" class="form-control" 
                               value="1" min="1" max="100">
                        <small style="color: #666;">Number of pages in each split PDF</small>
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" id="splitBtn" disabled>
                            <i class="fas fa-cut"></i> Split PDF
                        </button>
                    </div>

                    <div class="loader" id="loader"></div>
                </form>

                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e0e0e0;">
                    <h3>How it works:</h3>
                    <ol style="line-height: 2;">
                        <li>Upload your PDF file (up to <?php echo MAX_FILE_SIZE / 1048576; ?>MB)</li>
                        <li>Choose your split mode:
                            <ul style="margin-top: 0.5rem;">
                                <li><strong>Individual Pages:</strong> Creates one PDF for each page</li>
                                <li><strong>Page Ranges:</strong> Extract specific pages (e.g., 1-3, 5, 7-9)</li>
                                <li><strong>Fixed Page Count:</strong> Split into chunks of N pages each</li>
                            </ul>
                        </li>
                        <li>Click "Split PDF" and download your files</li>
                    </ol>
                    <p style="margin-top: 1rem; color: #757575;">
                        <i class="fas fa-shield-alt"></i> Your files are processed securely and deleted automatically after download.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>