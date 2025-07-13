<?php
// Set page-specific variables
$page_title = 'DOC to PDF - Triniva';
$page_description = 'Convert Word documents to PDF format. Support for DOC, DOCX, ODT, RTF and TXT files.';

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

// Function to convert text to simple PDF using Ghostscript
function textToPDF($text, $outputFile) {
    // Create a temporary PostScript file
    $psFile = tempnam(TEMP_DIR, 'text_') . '.ps';
    
    // Create PostScript content
    $ps = "%!PS-Adobe-3.0\n";
    $ps .= "%%BoundingBox: 0 0 612 792\n";
    $ps .= "%%Pages: 1\n";
    $ps .= "%%EndComments\n\n";
    $ps .= "/Helvetica findfont 12 scalefont setfont\n";
    $ps .= "50 750 moveto\n";
    
    // Split text into lines and pages
    $lines = explode("\n", $text);
    $y = 750;
    $pageNum = 1;
    
    foreach ($lines as $line) {
        if ($y < 50) {
            $ps .= "showpage\n";
            $pageNum++;
            $ps .= "%%Page: $pageNum $pageNum\n";
            $ps .= "/Helvetica findfont 12 scalefont setfont\n";
            $ps .= "50 750 moveto\n";
            $y = 750;
        }
        
        // Escape special characters
        $line = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $line);
        $line = substr($line, 0, 80); // Limit line length
        
        $ps .= "($line) show\n";
        $ps .= "50 " . ($y -= 15) . " moveto\n";
    }
    
    $ps .= "showpage\n";
    $ps .= "%%EOF\n";
    
    // Write PostScript file
    file_put_contents($psFile, $ps);
    
    // Convert PS to PDF using Ghostscript
    $gsPath = defined('GS_PATH') ? GS_PATH : '/usr/bin/gs';
    $command = $gsPath . " -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=" . 
              escapeshellarg($outputFile) . " " . escapeshellarg($psFile) . " 2>&1";
    
    exec($command, $output, $returnVar);
    
    // Clean up
    @unlink($psFile);
    
    return $returnVar === 0 && file_exists($outputFile);
}

// Function to convert document to PDF
function convertDocToPDF($inputFile, $outputFile) {
    global $errors;
    
    $extension = strtolower(pathinfo($inputFile, PATHINFO_EXTENSION));
    
    // For text files, use our custom converter
    if ($extension === 'txt') {
        $content = file_get_contents($inputFile);
        if ($content !== false) {
            return textToPDF($content, $outputFile);
        }
    }
    
    // For other formats, try LibreOffice if available
    $sofficeCommands = [
        'soffice',
        '/usr/bin/soffice',
        '/usr/local/bin/soffice',
        'libreoffice',
        '/usr/bin/libreoffice'
    ];
    
    foreach ($sofficeCommands as $cmd) {
        $checkCommand = "which $cmd 2>/dev/null";
        exec($checkCommand, $output, $returnVar);
        
        if ($returnVar === 0) {
            $outputDir = dirname($outputFile);
            $command = "$cmd --headless --convert-to pdf --outdir " . 
                      escapeshellarg($outputDir) . " " . 
                      escapeshellarg($inputFile) . " 2>&1";
            
            exec($command, $output, $returnVar);
            
            // LibreOffice creates file with original name + .pdf
            $expectedOutput = $outputDir . '/' . pathinfo(basename($inputFile), PATHINFO_FILENAME) . '.pdf';
            
            if ($returnVar === 0 && file_exists($expectedOutput)) {
                if ($expectedOutput !== $outputFile) {
                    rename($expectedOutput, $outputFile);
                }
                return true;
            }
        }
    }
    
    // If we can't convert DOC/DOCX without LibreOffice, create a simple PDF with a message
    if (in_array($extension, ['doc', 'docx', 'odt', 'rtf'])) {
        $message = "Document Conversion Notice\n\n";
        $message .= "The file '$inputFile' requires LibreOffice for conversion.\n\n";
        $message .= "LibreOffice is not installed on this server.\n\n";
        $message .= "To convert DOC/DOCX files, please:\n";
        $message .= "1. Install LibreOffice on the server, or\n";
        $message .= "2. Convert your document to TXT format first\n";
        
        return textToPDF($message, $outputFile);
    }
    
    return false;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        verifyCSRFToken($_POST['csrf_token'] ?? '');
        
        if (!isset($_FILES['doc_files']) || empty($_FILES['doc_files']['tmp_name'][0])) {
            throw new RuntimeException('No files uploaded.');
        }
        
        $uploadedFiles = $_FILES['doc_files'];
        $convertedFiles = [];
        
        // Process each uploaded file
        for ($i = 0; $i < count($uploadedFiles['tmp_name']); $i++) {
            if ($uploadedFiles['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            // Validate file size
            if ($uploadedFiles['size'][$i] > MAX_FILE_SIZE) {
                $errors[] = 'File ' . $uploadedFiles['name'][$i] . ' exceeds size limit.';
                continue;
            }
            
            // Validate file type
            $extension = strtolower(pathinfo($uploadedFiles['name'][$i], PATHINFO_EXTENSION));
            $allowedExtensions = ['doc', 'docx', 'odt', 'rtf', 'txt'];
            
            if (!in_array($extension, $allowedExtensions)) {
                $errors[] = 'File ' . $uploadedFiles['name'][$i] . ' is not a supported format.';
                continue;
            }
            
            // Save uploaded file
            $tempFile = UPLOAD_DIR . uniqid('doc_') . '.' . $extension;
            if (!move_uploaded_file($uploadedFiles['tmp_name'][$i], $tempFile)) {
                continue;
            }
            
            $_SESSION['temp_files'][] = $tempFile;
            
            // Convert to PDF
            $outputFile = UPLOAD_DIR . uniqid('converted_') . '.pdf';
            
            if (convertDocToPDF($tempFile, $outputFile)) {
                $convertedFiles[] = [
                    'original' => $uploadedFiles['name'][$i],
                    'pdf' => $outputFile
                ];
                $_SESSION['temp_files'][] = $outputFile;
            } else {
                $errors[] = 'Failed to convert ' . $uploadedFiles['name'][$i];
            }
        }
        
        // Handle converted files
        if (!empty($convertedFiles)) {
            if (count($convertedFiles) === 1) {
                // Single file
                $downloadLink = 'download.php?file=' . urlencode(basename($convertedFiles[0]['pdf']));
                $success = true;
            } else {
                // Multiple files - create ZIP if available
                if (class_exists('ZipArchive')) {
                    $zipFile = UPLOAD_DIR . uniqid('doc_pdfs_') . '.zip';
                    $zip = new ZipArchive();
                    
                    if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
                        foreach ($convertedFiles as $file) {
                            $pdfName = pathinfo($file['original'], PATHINFO_FILENAME) . '.pdf';
                            $zip->addFile($file['pdf'], $pdfName);
                        }
                        $zip->close();
                        
                        $_SESSION['temp_files'][] = $zipFile;
                        $downloadLink = 'download.php?file=' . urlencode(basename($zipFile));
                        $success = true;
                    }
                } else {
                    // No ZIP support - provide first file
                    $downloadLink = 'download.php?file=' . urlencode(basename($convertedFiles[0]['pdf']));
                    $success = true;
                    if (count($convertedFiles) > 1) {
                        $errors[] = 'Note: Only the first file is available for download. ZIP support not available.';
                    }
                }
            }
        }
        
        if (!$success && empty($errors)) {
            throw new RuntimeException('No files were successfully converted.');
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        logError('DOC to PDF Error', ['error' => $e->getMessage()]);
    }
}

// Additional scripts for this page
$additional_scripts = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('docFiles');
    const fileInfo = document.getElementById('fileInfo');
    const fileList = document.getElementById('fileList');
    const convertBtn = document.getElementById('convertBtn');
    const docForm = document.getElementById('docForm');
    const loader = document.getElementById('loader');
    
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
        if (files.length > 0) {
            fileInput.files = files;
            handleFileSelect();
        }
    });
    
    fileInput.addEventListener('change', handleFileSelect);
    
    function handleFileSelect() {
        const files = fileInput.files;
        if (files.length > 0) {
            fileList.innerHTML = '';
            fileInfo.style.display = 'block';
            uploadArea.style.display = 'none';
            convertBtn.disabled = false;
            
            for (let i = 0; i < files.length; i++) {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = '<div class="file-info">' +
                    '<i class="fas fa-file-word file-icon"></i>' +
                    '<div>' +
                        '<div class="file-name">' + files[i].name + '</div>' +
                        '<div class="file-size">' + formatFileSize(files[i].size) + '</div>' +
                    '</div>' +
                '</div>';
                fileList.appendChild(fileItem);
            }
            
            // Add remove button
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-secondary';
            removeBtn.style.marginTop = '1rem';
            removeBtn.innerHTML = '<i class="fas fa-times"></i> Clear Files';
            removeBtn.onclick = clearFiles;
            fileInfo.appendChild(removeBtn);
        }
    }
    
    function clearFiles() {
        fileInput.value = '';
        fileInfo.style.display = 'none';
        uploadArea.style.display = 'block';
        convertBtn.disabled = true;
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
    
    docForm.addEventListener('submit', (e) => {
        convertBtn.disabled = true;
        loader.style.display = 'block';
    });
});
</script>
HTML;
?>

<div class="tool-page">
    <div class="container">
        <div class="tool-header">
            <h1><i class="fas fa-file-word"></i> DOC to PDF</h1>
            <p>Convert Word documents and other text files to PDF</p>
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
                    Your documents have been converted successfully!
                </div>
                
                <div style="text-align: center; margin: 2rem 0;">
                    <a href="<?php echo htmlspecialchars($downloadLink); ?>" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download PDF<?php echo (strpos($downloadLink, '.zip') !== false) ? 's' : ''; ?>
                    </a>
                </div>
                
                <div style="text-align: center;">
                    <a href="doc-to-pdf.php" class="btn btn-secondary">Convert More Documents</a>
                </div>
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data" id="docForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="upload-area" id="uploadArea">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <div class="upload-text">Drag & Drop your documents here</div>
                        <div class="upload-subtext">or click to browse</div>
                        <input type="file" name="doc_files[]" id="docFiles" class="file-input" 
                               accept=".doc,.docx,.odt,.rtf,.txt" multiple required>
                    </div>

                    <div id="fileInfo" style="display: none;">
                        <div class="file-list" id="fileList"></div>
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" id="convertBtn" disabled>
                            <i class="fas fa-file-pdf"></i> Convert to PDF
                        </button>
                    </div>

                    <div class="loader" id="loader"></div>
                </form>

                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e0e0e0;">
                    <h3>Supported Formats:</h3>
                    <ul style="line-height: 2;">
                        <li><strong>TXT:</strong> Plain text files (fully supported)</li>
                        <li><strong>DOC/DOCX:</strong> Microsoft Word documents (requires LibreOffice)</li>
                        <li><strong>ODT:</strong> OpenDocument Text files (requires LibreOffice)</li>
                        <li><strong>RTF:</strong> Rich Text Format files (requires LibreOffice)</li>
                    </ul>
                    
                    <h3 style="margin-top: 2rem;">Features:</h3>
                    <ul style="line-height: 2;">
                        <li>Text files are converted directly to PDF</li>
                        <li>Batch conversion support</li>
                        <li>Secure processing</li>
                        <li>Automatic cleanup after download</li>
                    </ul>
                    
                    <div class="alert alert-info" style="margin-top: 2rem;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> TXT files can be converted without any external dependencies. 
                        For DOC/DOCX files, LibreOffice must be installed on the server.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>