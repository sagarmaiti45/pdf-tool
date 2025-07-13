<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Initialize variables
$uploadSuccess = false;
$errors = [];
$processedFile = '';

// Function to convert DOC/DOCX to PDF using LibreOffice
function convertDocToPDF($inputFile) {
    global $errors;
    
    $outputDir = dirname($inputFile);
    $outputFile = $outputDir . '/' . pathinfo($inputFile, PATHINFO_FILENAME) . '.pdf';
    
    // Check if LibreOffice/soffice is available
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
    
    if (empty($sofficeCommand)) {
        // Try using unoconv as fallback
        exec("which unoconv 2>/dev/null", $output, $returnVar);
        if ($returnVar === 0) {
            $command = "unoconv -f pdf -o " . escapeshellarg($outputFile) . " " . escapeshellarg($inputFile) . " 2>&1";
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0 && file_exists($outputFile)) {
                return $outputFile;
            }
        }
        
        $errors[] = "LibreOffice or unoconv is not installed. Please install LibreOffice to use this feature.";
        return false;
    }
    
    // Convert using LibreOffice
    $command = $sofficeCommand . " --headless --convert-to pdf --outdir " . 
               escapeshellarg($outputDir) . " " . escapeshellarg($inputFile) . " 2>&1";
    
    exec($command, $output, $returnVar);
    
    if ($returnVar === 0 && file_exists($outputFile)) {
        return $outputFile;
    } else {
        $errors[] = "Failed to convert document to PDF. Error: " . implode("\n", $output);
        return false;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        if (!empty($_FILES['doc_files']['tmp_name'][0])) {
            $uploadedFiles = $_FILES['doc_files'];
            $convertedFiles = [];
            
            for ($i = 0; $i < count($uploadedFiles['tmp_name']); $i++) {
                if ($uploadedFiles['error'][$i] !== UPLOAD_ERR_OK) {
                    $errors[] = 'File upload failed for ' . $uploadedFiles['name'][$i];
                    continue;
                }
                
                if ($uploadedFiles['size'][$i] > MAX_FILE_SIZE) {
                    $errors[] = 'File ' . $uploadedFiles['name'][$i] . ' exceeds the maximum size limit.';
                    continue;
                }
                
                // Check file type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $uploadedFiles['tmp_name'][$i]);
                finfo_close($finfo);
                
                $allowedMimes = [
                    'application/msword', // .doc
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
                    'application/vnd.oasis.opendocument.text', // .odt
                    'application/rtf', // .rtf
                    'text/plain' // .txt
                ];
                
                $extension = strtolower(pathinfo($uploadedFiles['name'][$i], PATHINFO_EXTENSION));
                $allowedExtensions = ['doc', 'docx', 'odt', 'rtf', 'txt'];
                
                if (!in_array($mimeType, $allowedMimes) && !in_array($extension, $allowedExtensions)) {
                    $errors[] = 'File ' . $uploadedFiles['name'][$i] . ' is not a supported document format.';
                    continue;
                }
                
                // Save uploaded file
                $tempFile = UPLOAD_DIR . uniqid('doc_') . '.' . $extension;
                if (move_uploaded_file($uploadedFiles['tmp_name'][$i], $tempFile)) {
                    // Convert to PDF
                    $pdfFile = convertDocToPDF($tempFile);
                    
                    if ($pdfFile !== false) {
                        $convertedFiles[] = [
                            'original' => $uploadedFiles['name'][$i],
                            'pdf' => $pdfFile
                        ];
                        $_SESSION['temp_files'][] = $pdfFile;
                    }
                    
                    // Clean up temp file
                    @unlink($tempFile);
                }
            }
            
            if (!empty($convertedFiles)) {
                if (count($convertedFiles) === 1) {
                    // Single file - provide direct download
                    $uploadSuccess = true;
                    $processedFile = $convertedFiles[0]['pdf'];
                } else {
                    // Multiple files - create ZIP
                    $zipFile = UPLOAD_DIR . 'converted_pdfs_' . uniqid() . '.zip';
                    $zip = new ZipArchive();
                    
                    if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
                        foreach ($convertedFiles as $file) {
                            $pdfName = pathinfo($file['original'], PATHINFO_FILENAME) . '.pdf';
                            $zip->addFile($file['pdf'], $pdfName);
                        }
                        $zip->close();
                        
                        $uploadSuccess = true;
                        $processedFile = $zipFile;
                        $_SESSION['temp_files'][] = $zipFile;
                    }
                }
            }
            
            if (empty($convertedFiles) && empty($errors)) {
                $errors[] = 'No files were successfully converted.';
            }
        } else {
            $errors[] = 'Please select at least one document file to convert.';
        }
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<div class="container">
    <div class="tool-header">
        <h1><i class="fas fa-file-word"></i> DOC to PDF</h1>
        <p>Convert Word documents and other text files to PDF</p>
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
            Document(s) converted to PDF successfully!
        </div>
        
        <div class="result-section">
            <h3>Download Converted PDF</h3>
            <div class="download-section">
                <a href="download.php?file=<?php echo urlencode(basename($processedFile)); ?>" 
                   class="btn btn-primary btn-lg">
                    <i class="fas fa-download"></i> Download <?php echo (strpos($processedFile, '.zip') !== false) ? 'ZIP File' : 'PDF'; ?>
                </a>
            </div>
            <div class="action-buttons">
                <a href="doc-to-pdf.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Convert More Documents
                </a>
                <a href="../index.php" class="btn btn-outline">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        </div>
    <?php else: ?>
        <form method="POST" enctype="multipart/form-data" class="upload-form" id="docForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="upload-area" id="uploadArea">
                <input type="file" name="doc_files[]" id="docFiles" 
                       accept=".doc,.docx,.odt,.rtf,.txt" multiple required>
                <label for="docFiles">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Click to upload or drag and drop</span>
                    <small>DOC, DOCX, ODT, RTF, TXT files (Max <?php echo MAX_FILE_SIZE / 1048576; ?>MB each)</small>
                </label>
                <div class="file-list" id="fileList"></div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" id="convertButton">
                <i class="fas fa-file-pdf"></i> Convert to PDF
            </button>
        </form>

        <div class="info-section">
            <h3>Supported Formats:</h3>
            <ul>
                <li><strong>DOC/DOCX:</strong> Microsoft Word documents</li>
                <li><strong>ODT:</strong> OpenDocument Text files</li>
                <li><strong>RTF:</strong> Rich Text Format files</li>
                <li><strong>TXT:</strong> Plain text files</li>
            </ul>
            
            <h3>Features:</h3>
            <ul>
                <li>Preserves document formatting and layout</li>
                <li>Supports multiple file conversion</li>
                <li>Maintains images and tables</li>
                <li>Fast and secure conversion</li>
            </ul>
            
            <div class="alert alert-info" style="margin-top: 20px;">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> This feature requires LibreOffice to be installed on the server.
                If conversions fail, please contact the administrator.
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('docFiles');
    const uploadArea = document.getElementById('uploadArea');
    const fileList = document.getElementById('fileList');
    const docForm = document.getElementById('docForm');
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
        fileList.innerHTML = '';
        
        if (files.length > 0) {
            uploadArea.classList.add('has-file');
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <i class="fas fa-file-word"></i>
                    <span>${file.name}</span>
                    <small>(${formatFileSize(file.size)})</small>
                `;
                fileList.appendChild(fileItem);
            }
        } else {
            uploadArea.classList.remove('has-file');
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
    docForm.addEventListener('submit', function(e) {
        if (!fileInput.files || fileInput.files.length === 0) {
            e.preventDefault();
            alert('Please select at least one document file');
            return;
        }

        convertButton.disabled = true;
        convertButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Converting to PDF...';
    });
});
</script>

<style>
.file-list {
    margin-top: 15px;
}

.file-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: var(--gray-50);
    border-radius: var(--radius-md);
    margin-bottom: 8px;
}

.file-item i {
    color: #2b5797;
    font-size: 1.2rem;
}

.file-item span {
    flex: 1;
    font-weight: 500;
}

.file-item small {
    color: var(--gray-600);
}
</style>

<?php require_once '../includes/footer.php'; ?>