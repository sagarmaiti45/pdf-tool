<?php
require_once '../includes/functions.php';

// Page variables for header
$page_title = 'Unlock PDF - Remove Restrictions';
$page_description = 'Remove restrictions from PDF files online. Unlock PDFs to enable printing, copying, and editing without passwords.';
$page_keywords = 'unlock PDF, remove PDF restrictions, PDF unlocker, enable PDF editing, remove PDF protection, PDF restrictions remover';

// Additional head content
$additional_head = '<style>
        .info-box {
            background: var(--primary-light);
            border: 1px solid var(--primary-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        
        .info-box h4 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .restrictions-list {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
        }
        
        .restrictions-list li {
            padding: 0.5rem 0;
            padding-left: 2rem;
            position: relative;
        }
        
        .restrictions-list li:before {
            content: \'\\f00c\';
            font-family: \'Font Awesome 5 Free\';
            font-weight: 900;
            position: absolute;
            left: 0;
            color: #4CAF50;
        }
    </style>';

// JavaScript to be included
$additional_scripts = '<script>
        const uploadArea = document.getElementById(\'uploadArea\');
        const fileInput = document.getElementById(\'pdfFile\');
        const fileInfo = document.getElementById(\'fileInfo\');
        const fileName = document.getElementById(\'fileName\');
        const fileSize = document.getElementById(\'fileSize\');
        const removeFile = document.getElementById(\'removeFile\');
        const unlockBtn = document.getElementById(\'unlockBtn\');
        const unlockForm = document.getElementById(\'unlockForm\');
        const loader = document.getElementById(\'loader\');

        uploadArea.addEventListener(\'click\', () => fileInput.click());

        uploadArea.addEventListener(\'dragover\', (e) => {
            e.preventDefault();
            uploadArea.classList.add(\'dragover\');
        });

        uploadArea.addEventListener(\'dragleave\', () => {
            uploadArea.classList.remove(\'dragover\');
        });

        uploadArea.addEventListener(\'drop\', (e) => {
            e.preventDefault();
            uploadArea.classList.remove(\'dragover\');
            
            const files = e.dataTransfer.files;
            if (files.length > 0 && files[0].type === \'application/pdf\') {
                fileInput.files = files;
                handleFileSelect();
            }
        });

        fileInput.addEventListener(\'change\', handleFileSelect);

        removeFile.addEventListener(\'click\', () => {
            fileInput.value = \'\';
            fileInfo.style.display = \'none\';
            uploadArea.style.display = \'block\';
            unlockBtn.disabled = true;
        });

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.style.display = \'block\';
                uploadArea.style.display = \'none\';
                unlockBtn.disabled = false;
            }
        }

        function formatFileSize(bytes) {
            const units = [\'B\', \'KB\', \'MB\', \'GB\'];
            let i = 0;
            while (bytes >= 1024 && i < units.length - 1) {
                bytes /= 1024;
                i++;
            }
            return bytes.toFixed(2) + \' \' + units[i];
        }

        unlockForm.addEventListener(\'submit\', (e) => {
            unlockBtn.disabled = true;
            loader.style.display = \'block\';
        });
    </script>';

$error = '';
$success = '';
$downloadLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCSRFToken($_POST['csrf_token'] ?? '');
        
        if (!isset($_FILES['pdf_file'])) {
            throw new RuntimeException('No file uploaded.');
        }

        validateFile($_FILES['pdf_file'], ['application/pdf']);
        
        $originalName = pathinfo($_FILES['pdf_file']['name'], PATHINFO_FILENAME);
        $uploadedFile = TEMP_DIR . generateUniqueFileName('pdf');
        
        moveUploadedFile($_FILES['pdf_file'], $uploadedFile);
        
        // Use PHP-based PDF unlocking
        try {
            $unlockedContent = unlockPDFWithPHP($uploadedFile);
            $outputFile = TEMP_DIR . generateUniqueFileName('pdf');
            file_put_contents($outputFile, $unlockedContent);
            
            $success = 'PDF processed successfully! Any restrictions have been removed where possible.';
        } catch (Exception $e) {
            throw new RuntimeException('Failed to unlock PDF: ' . $e->getMessage());
        }
        
        unlink($uploadedFile);
        
        $_SESSION['download_file'] = $outputFile;
        $_SESSION['download_name'] = $originalName . '_unlocked.pdf';
        
        $downloadLink = 'download.php?file=' . basename($outputFile);
        
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        logError('Unlock PDF Error', ['error' => $e->getMessage()]);
    }
}

$csrfToken = generateCSRFToken();

// Include header
require_once '../includes/header.php';
?>

    <div class="tool-page">
        <div class="container">
            <div class="tool-header">
                <h1><i class="fas fa-unlock-alt"></i> Unlock PDF</h1>
                <p>Remove restrictions from PDF files (no password required)</p>
            </div>

            <div class="tool-content">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                    <div style="text-align: center; margin: 2rem 0;">
                        <a href="<?php echo htmlspecialchars($downloadLink); ?>" class="btn btn-primary">
                            <i class="fas fa-download"></i> Download Unlocked PDF
                        </a>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="unlockForm">
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

                    <div class="info-box">
                        <h4><i class="fas fa-info-circle"></i> What restrictions can be removed?</h4>
                        <ul class="restrictions-list">
                            <li>Printing restrictions</li>
                            <li>Content copying restrictions</li>
                            <li>Page extraction restrictions</li>
                            <li>Form filling restrictions</li>
                            <li>Document assembly restrictions</li>
                        </ul>
                        <p style="margin-top: 1rem; color: #666;">
                            <strong>Note:</strong> This tool can only remove restrictions from PDFs that don't require a password to open. 
                            If your PDF requires a password to open, you'll need to enter it first.
                        </p>
                    </div>

                    <div class="loader" id="loader"></div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" id="unlockBtn" disabled>
                            <i class="fas fa-unlock-alt"></i> Unlock PDF
                        </button>
                    </div>
                </form>

                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e0e0e0;">
                    <h3>How it works:</h3>
                    <ol style="line-height: 2;">
                        <li>Upload your restricted PDF file</li>
                        <li>Click "Unlock PDF" to remove restrictions</li>
                        <li>Download the unlocked PDF file</li>
                    </ol>
                    
                    <p style="margin-top: 1rem; color: #757575;">
                        <i class="fas fa-shield-alt"></i> Your files are automatically deleted after processing for your privacy and security.
                    </p>
                </div>
            </div>
        </div>
    </div>

<?php

// PHP-based PDF unlocking function
function unlockPDFWithPHP($pdfFile) {
    $content = file_get_contents($pdfFile);
    
    // Check if PDF has encryption
    $hasEncryption = false;
    if (preg_match('/\/Encrypt\s+\d+\s+\d+\s+R/', $content)) {
        $hasEncryption = true;
    }
    
    // Remove basic restrictions by modifying PDF structure
    // This is a simplified approach that works for some PDFs
    
    // Remove Encrypt reference from trailer
    $content = preg_replace('/\/Encrypt\s+\d+\s+\d+\s+R/', '', $content);
    
    // Try to remove permission flags
    $content = preg_replace('/\/P\s+-?\d+/', '/P -1', $content);
    
    // Remove encryption dictionary objects
    $content = preg_replace('/\d+\s+\d+\s+obj\s*<<[^>]*\/Filter\s*\/Standard[^>]*>>\s*endobj/s', '', $content);
    
    // Note: This is a basic implementation that may not work for all encrypted PDFs
    // Real PDF decryption requires implementing the PDF encryption algorithms
    // For heavily encrypted PDFs, external tools would be needed
    
    return $content;
}

// Include footer
require_once '../includes/footer.php';
?>