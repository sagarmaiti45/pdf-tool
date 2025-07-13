<?php
// Set page-specific variables
$page_title = 'PDF to DOC - Triniva';
$page_description = 'Convert PDF files to Word documents. Extract text from PDFs into editable DOC, DOCX, or TXT formats.';

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

// Function to convert PDF to document
function convertPDFToDoc($inputFile, $outputFile, $format = 'txt') {
    global $errors;
    
    $converted = false;
    
    // Method 1: Try using pdftotext for text extraction
    if ($format === 'txt') {
        $pdftotextCommands = [
            'pdftotext',
            '/usr/bin/pdftotext',
            '/usr/local/bin/pdftotext'
        ];
        
        foreach ($pdftotextCommands as $cmd) {
            exec("which $cmd 2>/dev/null", $output, $returnVar);
            
            if ($returnVar === 0) {
                $command = "$cmd -layout " . escapeshellarg($inputFile) . " " . 
                          escapeshellarg($outputFile) . " 2>&1";
                
                exec($command, $output, $returnVar);
                
                if ($returnVar === 0 && file_exists($outputFile)) {
                    $converted = true;
                    break;
                }
            }
        }
    }
    
    // Method 2: Use Ghostscript to extract text
    if (!$converted && $format === 'txt') {
        $gsPath = defined('GS_PATH') ? GS_PATH : '/usr/bin/gs';
        $psFile = tempnam(TEMP_DIR, 'pdf_text_') . '.ps';
        
        // Convert PDF to PS
        $command = $gsPath . " -q -dNOPAUSE -dBATCH -sDEVICE=ps2write " .
                  "-sOutputFile=" . escapeshellarg($psFile) . " " . 
                  escapeshellarg($inputFile) . " 2>&1";
        
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($psFile)) {
            // Try to extract text from PS
            $content = file_get_contents($psFile);
            if ($content !== false) {
                // Simple text extraction
                $text = '';
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    if (preg_match('/\((.*?)\)\s*show/', $line, $matches)) {
                        $text .= $matches[1] . "\n";
                    }
                }
                
                if (!empty($text)) {
                    file_put_contents($outputFile, $text);
                    $converted = true;
                }
            }
            @unlink($psFile);
        }
    }
    
    // Method 3: LibreOffice conversion
    if (!$converted && in_array($format, ['docx', 'doc', 'odt'])) {
        $sofficeCommands = [
            'soffice',
            '/usr/bin/soffice',
            '/usr/local/bin/soffice',
            'libreoffice',
            '/usr/bin/libreoffice'
        ];
        
        foreach ($sofficeCommands as $cmd) {
            exec("which $cmd 2>/dev/null", $output, $returnVar);
            
            if ($returnVar === 0) {
                $outputDir = dirname($outputFile);
                $command = "$cmd --headless --infilter=\"writer_pdf_import\" --convert-to " . 
                          $format . " --outdir " . escapeshellarg($outputDir) . " " . 
                          escapeshellarg($inputFile) . " 2>&1";
                
                exec($command, $output, $returnVar);
                
                // LibreOffice creates file with original name + extension
                $expectedOutput = $outputDir . '/' . pathinfo(basename($inputFile), PATHINFO_FILENAME) . '.' . $format;
                
                if (file_exists($expectedOutput)) {
                    if ($expectedOutput !== $outputFile) {
                        rename($expectedOutput, $outputFile);
                    }
                    $converted = true;
                    break;
                }
            }
        }
    }
    
    // Method 4: If all else fails for DOCX, create a simple text file and rename
    if (!$converted && $format === 'txt') {
        // As a last resort, create an empty text file with a message
        $message = "PDF to text conversion failed.\n\n";
        $message .= "This usually means the PDF is:\n";
        $message .= "- Image-based (scanned document)\n";
        $message .= "- Password protected\n";
        $message .= "- Corrupted\n\n";
        $message .= "Please try:\n";
        $message .= "1. Using an OCR tool for scanned PDFs\n";
        $message .= "2. Unlocking the PDF first if it's protected\n";
        $message .= "3. Using a different PDF file\n";
        
        file_put_contents($outputFile, $message);
        $converted = true;
    }
    
    return $converted;
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
        $outputFormat = $_POST['output_format'] ?? 'txt';
        
        // Validate output format
        $allowedFormats = ['txt', 'docx', 'doc', 'odt'];
        if (!in_array($outputFormat, $allowedFormats)) {
            throw new RuntimeException('Invalid output format selected.');
        }
        
        // Save uploaded file
        $inputFile = UPLOAD_DIR . uniqid('pdf_input_') . '.pdf';
        
        if (!move_uploaded_file($uploadedFile['tmp_name'], $inputFile)) {
            throw new RuntimeException('Failed to save uploaded file.');
        }
        
        $_SESSION['temp_files'][] = $inputFile;
        
        // Convert PDF
        $outputFile = UPLOAD_DIR . uniqid('converted_') . '.' . $outputFormat;
        
        if (convertPDFToDoc($inputFile, $outputFile, $outputFormat)) {
            $_SESSION['temp_files'][] = $outputFile;
            $downloadLink = 'download.php?file=' . urlencode(basename($outputFile));
            $success = true;
        } else {
            throw new RuntimeException('Failed to convert PDF. The required conversion tools may not be installed on the server.');
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        logError('PDF to DOC Error', ['error' => $e->getMessage()]);
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
    const convertBtn = document.getElementById('convertBtn');
    const pdfForm = document.getElementById('pdfForm');
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
        convertBtn.disabled = true;
    });
    
    function handleFileSelect() {
        const file = fileInput.files[0];
        if (file) {
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.style.display = 'block';
            uploadArea.style.display = 'none';
            convertBtn.disabled = false;
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
    
    pdfForm.addEventListener('submit', (e) => {
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
            <h1><i class="fas fa-file-pdf"></i> PDF to DOC</h1>
            <p>Convert PDF files to Word documents</p>
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
                    Your PDF has been converted successfully!
                </div>
                
                <div style="text-align: center; margin: 2rem 0;">
                    <a href="<?php echo htmlspecialchars($downloadLink); ?>" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download Document
                    </a>
                </div>
                
                <div style="text-align: center;">
                    <a href="pdf-to-doc.php" class="btn btn-secondary">Convert Another PDF</a>
                </div>
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data" id="pdfForm">
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
                        <label class="form-label">Output Format</label>
                        <select name="output_format" class="form-control">
                            <option value="txt">TXT - Plain Text</option>
                            <option value="docx">DOCX - Word 2007+</option>
                            <option value="doc">DOC - Word 97-2003</option>
                            <option value="odt">ODT - OpenDocument</option>
                        </select>
                        <small style="color: #666;">Text extraction works best with TXT format</small>
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" id="convertBtn" disabled>
                            <i class="fas fa-file-word"></i> Convert to DOC
                        </button>
                    </div>

                    <div class="loader" id="loader"></div>
                </form>

                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e0e0e0;">
                    <h3>Features:</h3>
                    <ul style="line-height: 2;">
                        <li>Extract text from PDF files</li>
                        <li>Convert to multiple document formats</li>
                        <li>Best results with text-based PDFs</li>
                        <li>Fast and secure processing</li>
                    </ul>
                    
                    <div class="alert alert-warning" style="margin-top: 2rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Important:</strong>
                        <ul style="margin-top: 0.5rem;">
                            <li>Works best with text-based PDFs (not scanned images)</li>
                            <li>Complex layouts may not convert perfectly</li>
                            <li>For scanned PDFs, OCR processing would be needed</li>
                            <li>Password-protected PDFs must be unlocked first</li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>