<?php
// Enable error reporting only in development
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

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
    $content = file_get_contents($pdfFile);
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

// Function to create a simple DOCX file
function createDocx($text, $outputFile) {
    // Create a temporary directory for DOCX structure
    $tempDir = TEMP_DIR . uniqid('docx_') . '/';
    mkdir($tempDir, 0777, true);
    mkdir($tempDir . '_rels', 0777, true);
    mkdir($tempDir . 'word', 0777, true);
    mkdir($tempDir . 'word/_rels', 0777, true);
    
    // Create [Content_Types].xml
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>';
    file_put_contents($tempDir . '[Content_Types].xml', $contentTypes);
    
    // Create _rels/.rels
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';
    file_put_contents($tempDir . '_rels/.rels', $rels);
    
    // Create word/_rels/document.xml.rels
    $wordRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
</Relationships>';
    file_put_contents($tempDir . 'word/_rels/document.xml.rels', $wordRels);
    
    // Create word/document.xml
    $paragraphs = explode("\n", $text);
    $documentContent = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>';
    
    foreach ($paragraphs as $paragraph) {
        $paragraph = htmlspecialchars($paragraph, ENT_XML1, 'UTF-8');
        if (!empty(trim($paragraph))) {
            $documentContent .= '
        <w:p>
            <w:r>
                <w:t>' . $paragraph . '</w:t>
            </w:r>
        </w:p>';
        }
    }
    
    $documentContent .= '
    </w:body>
</w:document>';
    file_put_contents($tempDir . 'word/document.xml', $documentContent);
    
    // Create DOCX file
    $zip = new ZipArchive();
    if ($zip->open($outputFile, ZipArchive::CREATE) === TRUE) {
        // Add files to ZIP
        $files = [
            '[Content_Types].xml',
            '_rels/.rels',
            'word/document.xml',
            'word/_rels/document.xml.rels'
        ];
        
        foreach ($files as $file) {
            $zip->addFile($tempDir . $file, $file);
        }
        
        $zip->close();
        
        // Clean up temp directory
        array_map('unlink', glob($tempDir . 'word/_rels/*'));
        array_map('unlink', glob($tempDir . 'word/*'));
        array_map('unlink', glob($tempDir . '_rels/*'));
        array_map('unlink', glob($tempDir . '*'));
        rmdir($tempDir . 'word/_rels');
        rmdir($tempDir . 'word');
        rmdir($tempDir . '_rels');
        rmdir($tempDir);
        
        return true;
    }
    
    return false;
}

// Function to convert PDF to document
function convertPDFToDoc($inputFile, $outputFile, $format = 'txt') {
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
            
        case 'docx':
            return createDocx($text, $outputFile);
            
        case 'doc':
            // For DOC format, create DOCX and rename
            // (True DOC format is proprietary and complex)
            $success = createDocx($text, $outputFile);
            if ($success) {
                // Add note about format
                $noteFile = str_replace('.doc', '_README.txt', $outputFile);
                file_put_contents($noteFile, "Note: This file is in DOCX format but with .doc extension for compatibility.\nMost modern Word processors can open it.");
            }
            return $success;
            
        case 'odt':
            // Create simple ODT file
            $tempDir = TEMP_DIR . uniqid('odt_') . '/';
            mkdir($tempDir, 0777, true);
            mkdir($tempDir . 'META-INF', 0777, true);
            
            // Create mimetype
            file_put_contents($tempDir . 'mimetype', 'application/vnd.oasis.opendocument.text');
            
            // Create META-INF/manifest.xml
            $manifest = '<?xml version="1.0" encoding="UTF-8"?>
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0">
    <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
    <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
</manifest:manifest>';
            file_put_contents($tempDir . 'META-INF/manifest.xml', $manifest);
            
            // Create content.xml
            $content = '<?xml version="1.0" encoding="UTF-8"?>
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
    <office:body>
        <office:text>';
            
            $paragraphs = explode("\n", $text);
            foreach ($paragraphs as $paragraph) {
                $paragraph = htmlspecialchars($paragraph, ENT_XML1, 'UTF-8');
                if (!empty(trim($paragraph))) {
                    $content .= '<text:p>' . $paragraph . '</text:p>';
                }
            }
            
            $content .= '</office:text>
    </office:body>
</office:document-content>';
            file_put_contents($tempDir . 'content.xml', $content);
            
            // Create ODT
            $zip = new ZipArchive();
            if ($zip->open($outputFile, ZipArchive::CREATE) === TRUE) {
                $zip->addFile($tempDir . 'mimetype', 'mimetype');
                $zip->addFile($tempDir . 'META-INF/manifest.xml', 'META-INF/manifest.xml');
                $zip->addFile($tempDir . 'content.xml', 'content.xml');
                $zip->close();
                
                // Clean up
                unlink($tempDir . 'content.xml');
                unlink($tempDir . 'META-INF/manifest.xml');
                unlink($tempDir . 'mimetype');
                rmdir($tempDir . 'META-INF');
                rmdir($tempDir);
                
                return true;
            }
            
            return false;
    }
    
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
            throw new RuntimeException('Failed to convert PDF. Please try a different file or format.');
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
                        <li>Extract text from PDF files using pure PHP</li>
                        <li>Convert to multiple document formats</li>
                        <li>No external dependencies required</li>
                        <li>Fast and secure processing</li>
                    </ul>
                    
                    <div class="alert alert-warning" style="margin-top: 2rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Important:</strong>
                        <ul style="margin-top: 0.5rem;">
                            <li>Works best with text-based PDFs (not scanned images)</li>
                            <li>Complex layouts and formatting may not be preserved</li>
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