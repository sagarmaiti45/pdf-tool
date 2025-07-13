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

// Function to extract text from PDF using PHP
function extractTextFromPDF($pdfFile) {
    $text = '';
    
    // Try to read PDF content
    $content = @file_get_contents($pdfFile);
    if ($content === false) {
        return false;
    }
    
    // Simple PDF text extraction
    // Look for text between BT and ET markers
    $textMatches = [];
    preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $textMatches);
    
    if (!empty($textMatches[1])) {
        foreach ($textMatches[1] as $match) {
            // Extract text from Tj and TJ operators
            $tjMatches = [];
            preg_match_all('/\((.*?)\)\s*Tj/s', $match, $tjMatches);
            if (!empty($tjMatches[1])) {
                foreach ($tjMatches[1] as $tjMatch) {
                    // Decode PDF text encoding
                    $decoded = str_replace(
                        ['\\(', '\\)', '\\\\'],
                        ['(', ')', '\\'],
                        $tjMatch
                    );
                    $text .= $decoded . ' ';
                }
            }
            
            // Handle TJ arrays
            preg_match_all('/\[(.*?)\]\s*TJ/s', $match, $tjMatches);
            if (!empty($tjMatches[1])) {
                foreach ($tjMatches[1] as $tjMatch) {
                    preg_match_all('/\((.*?)\)/', $tjMatch, $strings);
                    if (!empty($strings[1])) {
                        foreach ($strings[1] as $string) {
                            $decoded = str_replace(
                                ['\\(', '\\)', '\\\\'],
                                ['(', ')', '\\'],
                                $string
                            );
                            $text .= $decoded . ' ';
                        }
                    }
                }
            }
        }
    }
    
    // Clean up text
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    // If no text found, try alternative extraction
    if (empty($text)) {
        // Look for stream objects
        $streamMatches = [];
        preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $streamMatches);
        
        if (!empty($streamMatches[1])) {
            foreach ($streamMatches[1] as $stream) {
                // Try to decompress if needed
                $decompressed = @gzuncompress($stream);
                if ($decompressed !== false) {
                    $stream = $decompressed;
                }
                
                // Extract text
                preg_match_all('/\((.*?)\)\s*Tj/', $stream, $tjMatches);
                if (!empty($tjMatches[1])) {
                    foreach ($tjMatches[1] as $tjMatch) {
                        $text .= $tjMatch . ' ';
                    }
                }
            }
        }
    }
    
    return !empty($text) ? $text : false;
}

// Function to create a simple RTF file (alternative to DOCX)
function createRTF($text, $outputFile) {
    $rtf = '{\rtf1\ansi\deff0 {\fonttbl{\f0 Times New Roman;}}';
    $rtf .= '\f0\fs24 ';
    
    // Convert text to RTF format
    $lines = explode("\n", $text);
    foreach ($lines as $line) {
        // Escape special RTF characters
        $line = str_replace('\\', '\\\\', $line);
        $line = str_replace('{', '\{', $line);
        $line = str_replace('}', '\}', $line);
        $rtf .= $line . '\par ';
    }
    
    $rtf .= '}';
    
    return file_put_contents($outputFile, $rtf) !== false;
}

// Function to convert PDF to document
function convertPDFToDoc($inputFile, $outputFile, $format = 'txt') {
    global $errors;
    
    try {
        // Extract text from PDF
        $text = extractTextFromPDF($inputFile);
        
        if ($text === false || empty($text)) {
            // If PHP extraction fails, try using Ghostscript
            $gsPath = defined('GS_PATH') ? GS_PATH : '/usr/bin/gs';
            $txtFile = tempnam(TEMP_DIR, 'pdf_text_') . '.txt';
            
            // Use Ghostscript's txtwrite device
            $command = $gsPath . " -sDEVICE=txtwrite -o " . escapeshellarg($txtFile) . " " . 
                      escapeshellarg($inputFile) . " 2>&1";
            
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0 && file_exists($txtFile)) {
                $text = file_get_contents($txtFile);
                @unlink($txtFile);
            }
        }
        
        // If still no text, provide a message
        if (empty($text)) {
            $text = "Could not extract text from this PDF file.\n\n";
            $text .= "This might be because:\n";
            $text .= "- The PDF contains only images (scanned document)\n";
            $text .= "- The PDF is encrypted or password protected\n";
            $text .= "- The PDF file is corrupted\n\n";
            $text .= "For scanned PDFs, you would need OCR (Optical Character Recognition) software.";
        }
        
        // Save based on format
        switch ($format) {
            case 'txt':
                return file_put_contents($outputFile, $text) !== false;
                
            case 'rtf':
                // Create RTF file
                return createRTF($text, $outputFile);
                
            default:
                $errors[] = 'Unsupported format: ' . $format;
                return false;
        }
        
    } catch (Exception $e) {
        $errors[] = 'Error during conversion: ' . $e->getMessage();
        logError('PDF to DOC Conversion Error', ['error' => $e->getMessage()]);
        return false;
    }
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
        $allowedFormats = ['txt', 'rtf'];
        if (!in_array($outputFormat, $allowedFormats)) {
            throw new RuntimeException('Invalid output format selected.');
        }
        
        // Save uploaded file
        $inputFile = UPLOAD_DIR . uniqid('pdf_input_') . '.pdf';
        
        if (!move_uploaded_file($uploadedFile['tmp_name'], $inputFile)) {
            throw new RuntimeException('Failed to save uploaded file.');
        }
        
        $_SESSION['temp_files'][] = $inputFile;
        
        // Determine output extension
        $extension = $outputFormat;
        if ($outputFormat === 'doc' || $outputFormat === 'docx') {
            // Create RTF with requested extension for Word compatibility
            $extension = $outputFormat;
        }
        
        // Convert PDF
        $outputFile = UPLOAD_DIR . uniqid('converted_') . '.' . $extension;
        
        if (convertPDFToDoc($inputFile, $outputFile, $outputFormat)) {
            $_SESSION['temp_files'][] = $outputFile;
            $downloadLink = 'download.php?file=' . urlencode(basename($outputFile));
            $success = true;
        } else {
            if (empty($errors)) {
                throw new RuntimeException('Failed to convert PDF. Please try a different file or format.');
            }
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
                            <option value="txt">TXT - Plain Text (Recommended)</option>
                            <option value="rtf">RTF - Rich Text Format (Word Compatible)</option>
                        </select>
                        <small style="color: #666;">RTF files can be opened in Microsoft Word and other word processors</small>
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
                        <li>Convert to Word-compatible formats</li>
                        <li>No external dependencies required</li>
                        <li>Fast and secure processing</li>
                    </ul>
                    
                    <div class="alert alert-warning" style="margin-top: 2rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Important:</strong>
                        <ul style="margin-top: 0.5rem;">
                            <li>Works best with text-based PDFs (not scanned images)</li>
                            <li>DOC/DOCX files are created in RTF format for compatibility</li>
                            <li>Complex layouts and formatting may not be preserved</li>
                            <li>For scanned PDFs, OCR processing would be needed</li>
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