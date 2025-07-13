<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Initialize variables
$uploadSuccess = false;
$errors = [];
$processedFile = '';

// Function to convert PDF to DOCX using LibreOffice or other tools
function convertPDFToDoc($inputFile, $format = 'docx') {
    global $errors;
    
    $outputDir = dirname($inputFile);
    $outputFile = $outputDir . '/' . pathinfo($inputFile, PATHINFO_FILENAME) . '.' . $format;
    
    // Method 1: Try using LibreOffice/soffice
    $sofficeCommand = '';
    $possibleCommands = [
        'soffice',
        '/usr/bin/soffice',
        '/usr/local/bin/soffice',
        '/Applications/LibreOffice.app/Contents/MacOS/soffice',
        'libreoffice'
    ];
    
    foreach ($possibleCommands as $cmd) {
        exec("which $cmd 2>/dev/null", $output, $returnVar);
        if ($returnVar === 0) {
            $sofficeCommand = $cmd;
            break;
        }
    }
    
    if (!empty($sofficeCommand)) {
        $command = $sofficeCommand . " --headless --infilter=\"writer_pdf_import\" --convert-to " . 
                   $format . " --outdir " . escapeshellarg($outputDir) . " " . 
                   escapeshellarg($inputFile) . " 2>&1";
        
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($outputFile)) {
            return $outputFile;
        }
    }
    
    // Method 2: Try using pdf2docx (Python library)
    exec("which pdf2docx 2>/dev/null", $output, $returnVar);
    if ($returnVar === 0) {
        $command = "pdf2docx convert " . escapeshellarg($inputFile) . " " . 
                   escapeshellarg($outputFile) . " 2>&1";
        
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($outputFile)) {
            return $outputFile;
        }
    }
    
    // Method 3: Try using pdftotext for simple text extraction
    if ($format === 'txt') {
        exec("which pdftotext 2>/dev/null", $output, $returnVar);
        if ($returnVar === 0) {
            $command = "pdftotext " . escapeshellarg($inputFile) . " " . 
                       escapeshellarg($outputFile) . " 2>&1";
            
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0 && file_exists($outputFile)) {
                return $outputFile;
            }
        }
    }
    
    // Method 4: Use OCR if available (for scanned PDFs)
    exec("which ocrmypdf 2>/dev/null", $output, $returnVar);
    if ($returnVar === 0) {
        // First create a searchable PDF
        $ocrPdf = $outputDir . '/' . uniqid('ocr_') . '.pdf';
        $command = "ocrmypdf --force-ocr " . escapeshellarg($inputFile) . " " . 
                   escapeshellarg($ocrPdf) . " 2>&1";
        
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($ocrPdf)) {
            // Then try to convert the OCR'd PDF
            if (!empty($sofficeCommand)) {
                $command = $sofficeCommand . " --headless --infilter=\"writer_pdf_import\" --convert-to " . 
                           $format . " --outdir " . escapeshellarg($outputDir) . " " . 
                           escapeshellarg($ocrPdf) . " 2>&1";
                
                exec($command, $output, $returnVar);
                
                @unlink($ocrPdf);
                
                if ($returnVar === 0 && file_exists($outputFile)) {
                    return $outputFile;
                }
            }
        }
    }
    
    $errors[] = "PDF to DOC conversion tools are not available. Please install LibreOffice, pdf2docx, or pdftotext.";
    return false;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $outputFormat = $_POST['output_format'] ?? 'docx';
        
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
                    $tempFile = UPLOAD_DIR . uniqid('pdf_') . '.pdf';
                    if (move_uploaded_file($uploadedFile['tmp_name'], $tempFile)) {
                        // Convert PDF
                        $result = convertPDFToDoc($tempFile, $outputFormat);
                        
                        if ($result !== false) {
                            $uploadSuccess = true;
                            $processedFile = $result;
                            $_SESSION['temp_files'][] = $result;
                            $_SESSION['temp_files'][] = $tempFile;
                        } else {
                            $errors[] = 'Failed to convert PDF. The file might be protected or corrupted.';
                        }
                        
                        // Clean up temp file if processing failed
                        if (!$uploadSuccess) {
                            @unlink($tempFile);
                        }
                    } else {
                        $errors[] = 'Failed to save uploaded file.';
                    }
                }
            }
        } else {
            $errors[] = 'Please select a PDF file to convert.';
        }
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<div class="container">
    <div class="tool-header">
        <h1><i class="fas fa-file-pdf"></i> PDF to DOC</h1>
        <p>Convert PDF files to Word documents</p>
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
            PDF converted successfully!
        </div>
        
        <div class="result-section">
            <h3>Download Converted Document</h3>
            <div class="download-section">
                <a href="download.php?file=<?php echo urlencode(basename($processedFile)); ?>" 
                   class="btn btn-primary btn-lg">
                    <i class="fas fa-download"></i> Download <?php echo strtoupper(pathinfo($processedFile, PATHINFO_EXTENSION)); ?> File
                </a>
            </div>
            <div class="action-buttons">
                <a href="pdf-to-doc.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Convert Another PDF
                </a>
                <a href="../index.php" class="btn btn-outline">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        </div>
    <?php else: ?>
        <form method="POST" enctype="multipart/form-data" class="upload-form" id="pdfForm">
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
                <h3>Output Format</h3>
                <div class="format-options">
                    <label class="format-option">
                        <input type="radio" name="output_format" value="docx" checked>
                        <div class="format-card">
                            <i class="fas fa-file-word"></i>
                            <span>DOCX</span>
                            <small>Word 2007+</small>
                        </div>
                    </label>
                    <label class="format-option">
                        <input type="radio" name="output_format" value="doc">
                        <div class="format-card">
                            <i class="fas fa-file-word"></i>
                            <span>DOC</span>
                            <small>Word 97-2003</small>
                        </div>
                    </label>
                    <label class="format-option">
                        <input type="radio" name="output_format" value="odt">
                        <div class="format-card">
                            <i class="fas fa-file-alt"></i>
                            <span>ODT</span>
                            <small>OpenDocument</small>
                        </div>
                    </label>
                    <label class="format-option">
                        <input type="radio" name="output_format" value="txt">
                        <div class="format-card">
                            <i class="fas fa-file-alt"></i>
                            <span>TXT</span>
                            <small>Plain Text</small>
                        </div>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" id="convertButton">
                <i class="fas fa-file-word"></i> Convert to DOC
            </button>
        </form>

        <div class="info-section">
            <h3>Features:</h3>
            <ul>
                <li>Convert PDF to editable Word documents</li>
                <li>Multiple output formats supported</li>
                <li>Preserves text and basic formatting</li>
                <li>OCR support for scanned PDFs (if available)</li>
            </ul>
            
            <div class="alert alert-warning" style="margin-top: 20px;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Note:</strong> Complex layouts, images, and special formatting may not convert perfectly. 
                The quality of conversion depends on the PDF structure and available conversion tools.
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('pdfFile');
    const uploadArea = document.getElementById('uploadArea');
    const fileInfo = document.getElementById('fileInfo');
    const pdfForm = document.getElementById('pdfForm');
    const convertButton = document.getElementById('convertButton');

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
    pdfForm.addEventListener('submit', function(e) {
        if (!fileInput.files || fileInput.files.length === 0) {
            e.preventDefault();
            alert('Please select a PDF file');
            return;
        }

        convertButton.disabled = true;
        convertButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Converting PDF...';
    });
});
</script>

<style>
.format-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.format-option {
    cursor: pointer;
}

.format-option input[type="radio"] {
    display: none;
}

.format-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-lg);
    transition: all 0.3s ease;
    text-align: center;
}

.format-card i {
    font-size: 2rem;
    color: var(--gray-600);
    margin-bottom: 10px;
}

.format-card span {
    font-weight: 600;
    color: var(--gray-900);
}

.format-card small {
    font-size: 0.75rem;
    color: var(--gray-600);
    margin-top: 5px;
}

.format-option input[type="radio"]:checked + .format-card {
    border-color: var(--primary);
    background-color: var(--red-50);
}

.format-option input[type="radio"]:checked + .format-card i {
    color: var(--primary);
}

.format-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
}
</style>

<?php require_once '../includes/footer.php'; ?>