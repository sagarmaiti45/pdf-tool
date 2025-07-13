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
        
        // Use PHP-based PDF splitting
        try {
            $splitFiles = splitPDFWithPHP($inputFile, $tempDir, $splitMode, $pageRanges, $pageCount);
        } catch (Exception $e) {
            throw new RuntimeException('Failed to split PDF: ' . $e->getMessage());
        }
        
        if (empty($splitFiles)) {
            throw new RuntimeException('Failed to split PDF. The file might be corrupted or protected.');
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
            // Multiple files - create archive
            if (class_exists('ZipArchive')) {
                // Use ZipArchive if available
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
            } else {
                // Alternative: Create a TAR archive using PHP
                $tarFile = UPLOAD_DIR . uniqid('split_') . '.tar';
                
                try {
                    $tar = new PharData($tarFile);
                    foreach ($splitFiles as $file) {
                        $tar->addFile($file, basename($file));
                    }
                    
                    $_SESSION['temp_files'][] = $tarFile;
                    $downloadLink = 'download.php?file=' . urlencode(basename($tarFile));
                    $success = true;
                } catch (Exception $e) {
                    // If TAR also fails, create a simple concatenated PDF with page markers
                    $mergedFile = UPLOAD_DIR . uniqid('split_all_') . '.pdf';
                    $gsPath = defined('GS_PATH') ? GS_PATH : '/usr/bin/gs';
                    
                    $command = $gsPath . " -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite " .
                              "-sOutputFile=" . escapeshellarg($mergedFile) . " ";
                    
                    foreach ($splitFiles as $file) {
                        $command .= escapeshellarg($file) . " ";
                    }
                    
                    $command .= "2>&1";
                    exec($command, $output, $returnVar);
                    
                    if ($returnVar === 0 && file_exists($mergedFile)) {
                        $_SESSION['temp_files'][] = $mergedFile;
                        $downloadLink = 'download.php?file=' . urlencode(basename($mergedFile));
                        $success = true;
                        $errors[] = 'Note: Split files have been merged into a single PDF. Each original page is preserved in order.';
                    } else {
                        // Last resort: provide the first file
                        $outputFile = UPLOAD_DIR . uniqid('split_') . '.pdf';
                        if (copy($splitFiles[0], $outputFile)) {
                            $_SESSION['temp_files'][] = $outputFile;
                            $downloadLink = 'download.php?file=' . urlencode(basename($outputFile));
                            $success = true;
                            $errors[] = 'Note: Only the first split file is available for download.';
                        }
                    }
                }
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
            <?php endif; ?>
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

                    <div class="form-group" style="margin-top: 2rem;">
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
        </div>
    </div>
</div>

<?php

// PHP-based PDF splitting function
function splitPDFWithPHP($inputFile, $outputDir, $splitMode, $pageRanges = '', $pageCount = 1) {
    $splitFiles = [];
    $content = file_get_contents($inputFile);
    
    // Parse PDF structure
    $pdfData = parsePDFStructure($content);
    $pages = $pdfData['pages'];
    $totalPages = count($pages);
    
    if ($splitMode === 'single') {
        // Split into individual pages
        foreach ($pages as $index => $pageObj) {
            $pageNum = $index + 1; // Convert to 1-based numbering
            $outputFile = $outputDir . sprintf("page_%03d.pdf", $pageNum);
            $pdfContent = createSinglePagePDF($content, $pdfData, $pageNum);
            file_put_contents($outputFile, $pdfContent);
            $splitFiles[] = $outputFile;
        }
    } elseif ($splitMode === 'range' && !empty($pageRanges)) {
        // Split by custom ranges
        $ranges = array_map('trim', explode(',', $pageRanges));
        $rangeIndex = 1;
        
        foreach ($ranges as $range) {
            if (empty($range)) continue;
            
            $outputFile = $outputDir . sprintf("range_%03d.pdf", $rangeIndex);
            $pageNumbers = [];
            
            if (strpos($range, '-') !== false) {
                // Handle range like "1-3"
                list($start, $end) = array_map('intval', explode('-', $range));
                $start = max(1, min($start, $totalPages));
                $end = max($start, min($end, $totalPages));
                
                for ($i = $start; $i <= $end; $i++) {
                    $pageNumbers[] = $i;
                }
            } else {
                // Single page
                $page = (int)$range;
                if ($page >= 1 && $page <= $totalPages) {
                    $pageNumbers[] = $page;
                }
            }
            
            if (!empty($pageNumbers)) {
                $pdfContent = createMultiPagePDF($content, $pdfData, $pageNumbers);
                file_put_contents($outputFile, $pdfContent);
                $splitFiles[] = $outputFile;
                $rangeIndex++;
            }
        }
    } elseif ($splitMode === 'fixed' && $pageCount > 0) {
        // Split by fixed page count
        $partIndex = 1;
        
        for ($start = 1; $start <= $totalPages; $start += $pageCount) {
            $end = min($start + $pageCount - 1, $totalPages);
            $pageNumbers = range($start, $end);
            
            $outputFile = $outputDir . sprintf("part_%03d.pdf", $partIndex);
            $pdfContent = createMultiPagePDF($content, $pdfData, $pageNumbers);
            file_put_contents($outputFile, $pdfContent);
            $splitFiles[] = $outputFile;
            $partIndex++;
        }
    }
    
    return $splitFiles;
}

// Parse PDF structure to extract pages and objects
function parsePDFStructure($content) {
    $data = [
        'version' => '1.4',
        'objects' => [],
        'pages' => [],
        'catalog' => null,
        'pagesObj' => null,
        'info' => null
    ];
    
    // Extract PDF version
    if (preg_match('/^%PDF-(\d+\.\d+)/', $content, $match)) {
        $data['version'] = $match[1];
    }
    
    // Extract all objects
    preg_match_all('/(\d+)\s+(\d+)\s+obj(.*?)endobj/s', $content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $objNum = (int)$match[1];
        $objGen = (int)$match[2];
        $objContent = $match[3];
        
        $data['objects'][$objNum] = [
            'num' => $objNum,
            'gen' => $objGen,
            'content' => $objContent
        ];
        
        // Identify special objects
        if (strpos($objContent, '/Type /Catalog') !== false || strpos($objContent, '/Type/Catalog') !== false) {
            $data['catalog'] = $objNum;
        } elseif (strpos($objContent, '/Type /Pages') !== false || strpos($objContent, '/Type/Pages') !== false) {
            $data['pagesObj'] = $objNum;
        } elseif (strpos($objContent, '/Type /Page') !== false || strpos($objContent, '/Type/Page') !== false) {
            if (strpos($objContent, '/Kids') === false) { // Not a Pages object
                $data['pages'][] = $objNum;
            }
        }
    }
    
    // Extract trailer info
    if (preg_match('/trailer\s*<<(.*?)>>/s', $content, $match)) {
        if (preg_match('/\/Root\s+(\d+)\s+\d+\s+R/', $match[1], $rootMatch)) {
            $data['catalog'] = (int)$rootMatch[1];
        }
        if (preg_match('/\/Info\s+(\d+)\s+\d+\s+R/', $match[1], $infoMatch)) {
            $data['info'] = (int)$infoMatch[1];
        }
    }
    
    return $data;
}

// Create a single-page PDF
function createSinglePagePDF($originalContent, $pdfData, $pageNum) {
    return createMultiPagePDF($originalContent, $pdfData, [$pageNum]);
}

// Create a multi-page PDF with selected pages
function createMultiPagePDF($originalContent, $pdfData, $pageNumbers) {
    // Start building new PDF
    $pdf = "%PDF-" . $pdfData['version'] . "\n%âÉåÒ\n";
    
    $objects = [];
    $objMapping = [];
    $currentObjNum = 1;
    
    // Create catalog object
    $catalogNum = $currentObjNum++;
    $objects[$catalogNum] = "$catalogNum 0 obj\n<< /Type /Catalog /Pages $currentObjNum 0 R >>\nendobj";
    
    // Create pages object
    $pagesNum = $currentObjNum++;
    $pageRefs = [];
    
    // Copy required pages and their resources
    $copiedObjects = [];
    foreach ($pageNumbers as $pageIndex) {
        if (!isset($pdfData['pages'][$pageIndex - 1])) continue;
        
        $origPageNum = $pdfData['pages'][$pageIndex - 1];
        $newPageNum = $currentObjNum++;
        $objMapping[$origPageNum] = $newPageNum;
        $pageRefs[] = "$newPageNum 0 R";
        
        // Copy page object and update parent reference
        $pageContent = $pdfData['objects'][$origPageNum]['content'];
        $pageContent = preg_replace('/\/Parent\s+\d+\s+\d+\s+R/', "/Parent $pagesNum 0 R", $pageContent);
        
        // Find and copy referenced objects (Resources, Contents, etc.)
        preg_match_all('/(\d+)\s+\d+\s+R/', $pageContent, $refs);
        foreach ($refs[1] as $refNum) {
            $refNum = (int)$refNum;
            if (!isset($copiedObjects[$refNum]) && isset($pdfData['objects'][$refNum])) {
                $copiedObjects[$refNum] = true;
            }
        }
        
        $objects[$newPageNum] = "$newPageNum 0 obj$pageContent\nendobj";
    }
    
    // Copy referenced objects
    foreach ($copiedObjects as $origNum => $dummy) {
        if (!isset($objMapping[$origNum])) {
            $newNum = $currentObjNum++;
            $objMapping[$origNum] = $newNum;
            $objects[$newNum] = "$newNum 0 obj" . $pdfData['objects'][$origNum]['content'] . "\nendobj";
        }
    }
    
    // Update all object references
    foreach ($objects as $num => &$obj) {
        foreach ($objMapping as $oldNum => $newNum) {
            $obj = preg_replace("/\b$oldNum 0 R\b/", "$newNum 0 R", $obj);
        }
    }
    
    // Create pages object with collected page references
    $objects[$pagesNum] = "$pagesNum 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $pageRefs) . "] /Count " . count($pageRefs) . " >>\nendobj";
    
    // Write all objects
    $xrefPositions = [];
    $currentPos = strlen($pdf);
    
    foreach ($objects as $num => $objContent) {
        $xrefPositions[$num] = $currentPos;
        $pdf .= $objContent . "\n";
        $currentPos = strlen($pdf);
    }
    
    // Write xref table
    $xrefOffset = $currentPos;
    $pdf .= "xref\n0 " . ($currentObjNum) . "\n";
    $pdf .= "0000000000 65535 f \n";
    
    for ($i = 1; $i < $currentObjNum; $i++) {
        if (isset($xrefPositions[$i])) {
            $pdf .= sprintf("%010d 00000 n \n", $xrefPositions[$i]);
        } else {
            $pdf .= "0000000000 00000 f \n";
        }
    }
    
    // Write trailer
    $pdf .= "trailer\n<< /Size $currentObjNum /Root $catalogNum 0 R >>\n";
    $pdf .= "startxref\n$xrefOffset\n%%EOF";
    
    return $pdf;
}

require_once '../includes/footer.php';
?>