<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Initialize variables
$uploadSuccess = false;
$errors = [];
$processedFile = '';

// Function to split PDF using Ghostscript
function splitPDF($inputFile, $splitMode, $pageRanges = '', $pageCount = 1) {
    global $errors;
    
    // Get total pages in PDF
    $totalPages = getTotalPDFPages($inputFile);
    if ($totalPages === false) {
        $errors[] = "Could not determine total pages in PDF.";
        return false;
    }
    
    $results = [];
    $tempDir = TEMP_DIR . uniqid('split_') . '/';
    
    if (!mkdir($tempDir, 0777, true)) {
        $errors[] = "Failed to create temporary directory.";
        return false;
    }
    
    try {
        if ($splitMode === 'single') {
            // Split into individual pages
            for ($i = 1; $i <= $totalPages; $i++) {
                $outputFile = $tempDir . "page_{$i}.pdf";
                $command = "gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER " .
                          "-dFirstPage={$i} -dLastPage={$i} " .
                          "-sOutputFile=" . escapeshellarg($outputFile) . " " .
                          escapeshellarg($inputFile) . " 2>&1";
                
                exec($command, $output, $returnVar);
                
                if ($returnVar === 0 && file_exists($outputFile)) {
                    $results[] = $outputFile;
                }
            }
        } elseif ($splitMode === 'range') {
            // Split by custom page ranges
            $ranges = explode(',', $pageRanges);
            $rangeIndex = 1;
            
            foreach ($ranges as $range) {
                $range = trim($range);
                if (empty($range)) continue;
                
                $outputFile = $tempDir . "range_{$rangeIndex}.pdf";
                
                if (strpos($range, '-') !== false) {
                    // Handle range like "1-3"
                    list($start, $end) = explode('-', $range);
                    $start = (int)trim($start);
                    $end = (int)trim($end);
                    
                    if ($start < 1) $start = 1;
                    if ($end > $totalPages) $end = $totalPages;
                    
                    $command = "gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER " .
                              "-dFirstPage={$start} -dLastPage={$end} " .
                              "-sOutputFile=" . escapeshellarg($outputFile) . " " .
                              escapeshellarg($inputFile) . " 2>&1";
                } else {
                    // Single page
                    $page = (int)trim($range);
                    if ($page < 1 || $page > $totalPages) continue;
                    
                    $command = "gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER " .
                              "-dFirstPage={$page} -dLastPage={$page} " .
                              "-sOutputFile=" . escapeshellarg($outputFile) . " " .
                              escapeshellarg($inputFile) . " 2>&1";
                }
                
                exec($command, $output, $returnVar);
                
                if ($returnVar === 0 && file_exists($outputFile)) {
                    $results[] = $outputFile;
                    $rangeIndex++;
                }
            }
        } elseif ($splitMode === 'fixed') {
            // Split by fixed page count
            $pageCount = (int)$pageCount;
            if ($pageCount < 1) $pageCount = 1;
            
            $partIndex = 1;
            for ($i = 1; $i <= $totalPages; $i += $pageCount) {
                $start = $i;
                $end = min($i + $pageCount - 1, $totalPages);
                
                $outputFile = $tempDir . "part_{$partIndex}.pdf";
                $command = "gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER " .
                          "-dFirstPage={$start} -dLastPage={$end} " .
                          "-sOutputFile=" . escapeshellarg($outputFile) . " " .
                          escapeshellarg($inputFile) . " 2>&1";
                
                exec($command, $output, $returnVar);
                
                if ($returnVar === 0 && file_exists($outputFile)) {
                    $results[] = $outputFile;
                    $partIndex++;
                }
            }
        }
        
        // Create ZIP file with all split PDFs
        if (!empty($results)) {
            $zipFile = UPLOAD_DIR . 'split_' . uniqid() . '.zip';
            $zip = new ZipArchive();
            
            if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
                foreach ($results as $index => $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();
                
                // Clean up temporary files
                foreach ($results as $file) {
                    @unlink($file);
                }
                @rmdir($tempDir);
                
                return $zipFile;
            }
        }
        
    } catch (Exception $e) {
        $errors[] = "Error during PDF split: " . $e->getMessage();
    }
    
    // Clean up on error
    foreach ($results as $file) {
        @unlink($file);
    }
    @rmdir($tempDir);
    
    return false;
}

// Function to get total pages in PDF
function getTotalPDFPages($pdfFile) {
    $command = "gs -q -dNODISPLAY -c \"(" . escapeshellarg($pdfFile) . ") (r) file runpdfbegin pdfpagecount = quit\"";
    exec($command, $output, $returnVar);
    
    if ($returnVar === 0 && !empty($output)) {
        return (int)$output[0];
    }
    
    // Fallback method using pdfinfo if available
    $command = "pdfinfo " . escapeshellarg($pdfFile) . " | grep 'Pages:' | awk '{print $2}'";
    exec($command, $output, $returnVar);
    
    if ($returnVar === 0 && !empty($output)) {
        return (int)$output[0];
    }
    
    return false;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $splitMode = $_POST['split_mode'] ?? 'single';
        $pageRanges = $_POST['page_ranges'] ?? '';
        $pageCount = $_POST['page_count'] ?? 1;
        
        if (!empty($_FILES['pdf_file']['tmp_name'])) {
            $uploadedFile = $_FILES['pdf_file'];
            
            // Validate file
            if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'File upload failed. Please try again.';
            } elseif ($uploadedFile['size'] > MAX_FILE_SIZE) {
                $errors[] = 'File size exceeds the maximum limit of ' . (MAX_FILE_SIZE / 1048576) . ' MB.';
            } else {
                // Check if file is PDF
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
                finfo_close($finfo);
                
                if ($mimeType !== 'application/pdf') {
                    $errors[] = 'Please upload a valid PDF file.';
                } else {
                    // Save uploaded file
                    $tempFile = UPLOAD_DIR . uniqid('split_') . '.pdf';
                    if (move_uploaded_file($uploadedFile['tmp_name'], $tempFile)) {
                        // Split PDF
                        $result = splitPDF($tempFile, $splitMode, $pageRanges, $pageCount);
                        
                        if ($result !== false) {
                            $uploadSuccess = true;
                            $processedFile = $result;
                            $_SESSION['temp_files'][] = $result;
                            $_SESSION['temp_files'][] = $tempFile;
                        } else {
                            $errors[] = 'Failed to split PDF. Please check your settings and try again.';
                        }
                        
                        // Clean up original temp file if processing failed
                        if (!$uploadSuccess) {
                            @unlink($tempFile);
                        }
                    } else {
                        $errors[] = 'Failed to save uploaded file.';
                    }
                }
            }
        } else {
            $errors[] = 'Please select a PDF file to split.';
        }
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<div class="container">
    <div class="tool-header">
        <h1><i class="fas fa-cut"></i> Split PDF</h1>
        <p>Split PDF files into multiple documents</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($uploadSuccess && $processedFile): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            PDF split successfully!
        </div>
        
        <div class="result-section">
            <h3>Download Split PDFs</h3>
            <p>Your PDF has been split into multiple files and packaged in a ZIP archive.</p>
            <div class="download-section">
                <a href="download.php?file=<?php echo urlencode(basename($processedFile)); ?>" 
                   class="btn btn-primary btn-lg">
                    <i class="fas fa-download"></i> Download ZIP File
                </a>
            </div>
            <div class="action-buttons">
                <a href="split.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Split Another PDF
                </a>
                <a href="../index.php" class="btn btn-outline">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        </div>
    <?php else: ?>
        <form method="POST" enctype="multipart/form-data" class="upload-form" id="splitForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="upload-area" id="uploadArea">
                <input type="file" name="pdf_file" id="pdfFile" accept=".pdf" required>
                <label for="pdfFile">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Click to upload or drag and drop</span>
                    <small>PDF files only (Max <?php echo MAX_FILE_SIZE / 1048576; ?>MB)</small>
                </label>
                <div class="file-info" id="fileInfo"></div>
            </div>

            <div class="options-section">
                <h3>Split Options</h3>
                
                <div class="form-group">
                    <label>Split Mode:</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="split_mode" value="single" checked>
                            <span>Split into individual pages</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="split_mode" value="range">
                            <span>Split by page ranges</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="split_mode" value="fixed">
                            <span>Split by fixed page count</span>
                        </label>
                    </div>
                </div>

                <div class="form-group" id="rangeInput" style="display: none;">
                    <label for="page_ranges">Page Ranges:</label>
                    <input type="text" name="page_ranges" id="page_ranges" 
                           placeholder="e.g., 1-3, 5, 7-9" class="form-control">
                    <small>Enter page numbers or ranges separated by commas</small>
                </div>

                <div class="form-group" id="countInput" style="display: none;">
                    <label for="page_count">Pages per split:</label>
                    <input type="number" name="page_count" id="page_count" 
                           value="1" min="1" class="form-control">
                    <small>Number of pages in each split PDF</small>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" id="splitButton">
                <i class="fas fa-cut"></i> Split PDF
            </button>
        </form>

        <div class="info-section">
            <h3>How to use:</h3>
            <ol>
                <li>Upload your PDF file</li>
                <li>Choose split mode:
                    <ul>
                        <li><strong>Individual pages:</strong> Creates one PDF file for each page</li>
                        <li><strong>Page ranges:</strong> Split specific pages (e.g., 1-3, 5, 7-9)</li>
                        <li><strong>Fixed page count:</strong> Split into files with specified number of pages</li>
                    </ul>
                </li>
                <li>Click "Split PDF" to process</li>
                <li>Download the ZIP file containing all split PDFs</li>
            </ol>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('pdfFile');
    const uploadArea = document.getElementById('uploadArea');
    const fileInfo = document.getElementById('fileInfo');
    const splitForm = document.getElementById('splitForm');
    const splitButton = document.getElementById('splitButton');
    const rangeInput = document.getElementById('rangeInput');
    const countInput = document.getElementById('countInput');
    const splitModeRadios = document.querySelectorAll('input[name="split_mode"]');

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

    // File input change
    fileInput.addEventListener('change', function(e) {
        handleFileSelect(e.target.files);
    });

    // Drag and drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.classList.add('drag-over');
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.classList.remove('drag-over');
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            handleFileSelect(files);
        }
    });

    function handleFileSelect(files) {
        if (files.length > 0) {
            const file = files[0];
            if (file.type === 'application/pdf') {
                fileInfo.innerHTML = `
                    <i class="fas fa-file-pdf"></i>
                    <span>${file.name}</span>
                    <small>(${formatFileSize(file.size)})</small>
                `;
                uploadArea.classList.add('has-file');
            } else {
                alert('Please select a valid PDF file');
                fileInput.value = '';
                fileInfo.innerHTML = '';
                uploadArea.classList.remove('has-file');
            }
        }
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Form submission
    splitForm.addEventListener('submit', function(e) {
        if (!fileInput.files || fileInput.files.length === 0) {
            e.preventDefault();
            alert('Please select a PDF file');
            return;
        }

        const selectedMode = document.querySelector('input[name="split_mode"]:checked').value;
        
        if (selectedMode === 'range') {
            const ranges = document.getElementById('page_ranges').value.trim();
            if (!ranges) {
                e.preventDefault();
                alert('Please enter page ranges');
                return;
            }
        }

        splitButton.disabled = true;
        splitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Splitting PDF...';
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>