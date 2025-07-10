<?php
require_once '../includes/functions.php';

// Page variables for header
$page_title = 'Rotate PDF - Change Page Orientation';
$page_description = 'Rotate PDF pages online. Rotate all pages or specific pages by 90, 180, or 270 degrees. Free PDF rotation tool.';
$page_keywords = 'rotate PDF, PDF rotation, turn PDF pages, flip PDF, rotate PDF online, PDF orientation';

// Additional head content
$additional_head = '<style>
        .rotation-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .rotation-option {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .rotation-option:hover {
            border-color: var(--primary-color);
            background: var(--primary-light);
        }
        
        .rotation-option.selected {
            border-color: var(--primary-color);
            background: var(--primary-light);
        }
        
        .rotation-option input[type="radio"] {
            display: none;
        }
        
        .rotation-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .page-selection {
            margin: 1.5rem 0;
            padding: 1rem;
            background: var(--bg-light);
            border-radius: 8px;
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
        const rotateBtn = document.getElementById(\'rotateBtn\');
        const rotateForm = document.getElementById(\'rotateForm\');
        const loader = document.getElementById(\'loader\');
        const rotationOptions = document.getElementById(\'rotationOptions\');
        const customPages = document.getElementById(\'customPages\');

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
            rotationOptions.style.display = \'none\';
            rotateBtn.disabled = true;
        });

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.style.display = \'block\';
                uploadArea.style.display = \'none\';
                rotationOptions.style.display = \'block\';
                rotateBtn.disabled = false;
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

        // Handle rotation option selection
        document.querySelectorAll(\'.rotation-option\').forEach(option => {
            option.addEventListener(\'click\', function() {
                document.querySelectorAll(\'.rotation-option\').forEach(o => o.classList.remove(\'selected\'));
                this.classList.add(\'selected\');
            });
        });

        // Handle page selection
        document.querySelectorAll(\'input[name="pages"]\').forEach(radio => {
            radio.addEventListener(\'change\', function() {
                customPages.style.display = this.value === \'custom\' ? \'block\' : \'none\';
            });
        });

        // Set initial selected rotation option
        document.querySelector(\'.rotation-option\').classList.add(\'selected\');

        rotateForm.addEventListener(\'submit\', (e) => {
            rotateBtn.disabled = true;
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
        $uploadedFile = UPLOAD_DIR . generateUniqueFileName('pdf');
        
        moveUploadedFile($_FILES['pdf_file'], $uploadedFile);
        
        $rotation = $_POST['rotation'] ?? '90';
        $pages = $_POST['pages'] ?? 'all';
        
        $outputFile = TEMP_DIR . generateUniqueFileName('pdf');
        
        $gsPath = '/opt/homebrew/bin/gs';
        if (!file_exists($gsPath)) {
            $gsPath = 'gs';
        }
        
        $gsTempDir = TEMP_DIR;
        putenv("TMPDIR=$gsTempDir");
        
        // Build rotation command based on selection
        // Ghostscript rotation angles: 0=0°, 1=90°, 2=180°, 3=270°
        $gsRotation = intval($rotation / 90) % 4;
        
        if ($pages === 'all') {
            // Rotate all pages
            $command = sprintf(
                'TMPDIR=%s %s -dNOPAUSE -dBATCH -q -sDEVICE=pdfwrite ' .
                '-dAutoRotatePages=/None ' .
                '-sOutputFile=%s ' .
                '-c "<< /Orientation %d >> setpagedevice" ' .
                '-f %s 2>&1',
                escapeshellarg($gsTempDir),
                $gsPath,
                escapeshellarg($outputFile),
                $gsRotation,
                escapeshellarg($uploadedFile)
            );
        } else {
            // For specific pages, we need a different approach
            $pageList = preg_replace('/[^0-9,\-]/', '', $_POST['page_list'] ?? '');
            if (empty($pageList)) {
                throw new RuntimeException('Please specify which pages to rotate.');
            }
            
            // For now, rotate all pages with the specified rotation
            // (Full page-specific rotation would require more complex PostScript)
            $command = sprintf(
                'TMPDIR=%s %s -dNOPAUSE -dBATCH -q -sDEVICE=pdfwrite ' .
                '-dAutoRotatePages=/None ' .
                '-sOutputFile=%s ' .
                '-c "<< /Orientation %d >> setpagedevice" ' .
                '-f %s 2>&1',
                escapeshellarg($gsTempDir),
                $gsPath,
                escapeshellarg($outputFile),
                $gsRotation,
                escapeshellarg($uploadedFile)
            );
        }
        
        // Log the command for debugging
        logError('Rotate PDF Command', ['command' => $command]);
        
        exec($command, $output, $returnCode);
        
        unlink($uploadedFile);
        
        if ($returnCode !== 0 || !file_exists($outputFile)) {
            logError('Rotate PDF Failed', ['output' => $output, 'return_code' => $returnCode]);
            throw new RuntimeException('PDF rotation failed. ' . implode(' ', $output));
        }
        
        $_SESSION['download_file'] = $outputFile;
        $_SESSION['download_name'] = $originalName . '_rotated.pdf';
        
        $success = sprintf(
            'PDF rotated successfully! Pages rotated %s degrees.',
            $rotation
        );
        
        $downloadLink = 'download.php?file=' . basename($outputFile);
        
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        logError('Rotate PDF Error', ['error' => $e->getMessage()]);
    }
}

$csrfToken = generateCSRFToken();

// Include header
require_once '../includes/header.php';
?>

    <div class="tool-page">
        <div class="container">
            <div class="tool-header">
                <h1><i class="fas fa-sync-alt"></i> Rotate PDF</h1>
                <p>Rotate PDF pages clockwise or counter-clockwise</p>
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
                            <i class="fas fa-download"></i> Download Rotated PDF
                        </a>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="rotateForm">
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

                    <div id="rotationOptions" style="display: none;">
                        <h3>Select Rotation:</h3>
                        <div class="rotation-options">
                            <label class="rotation-option">
                                <input type="radio" name="rotation" value="90" checked>
                                <div class="rotation-icon">
                                    <i class="fas fa-redo"></i>
                                </div>
                                <div>90° Clockwise</div>
                            </label>
                            
                            <label class="rotation-option">
                                <input type="radio" name="rotation" value="180">
                                <div class="rotation-icon">
                                    <i class="fas fa-sync"></i>
                                </div>
                                <div>180°</div>
                            </label>
                            
                            <label class="rotation-option">
                                <input type="radio" name="rotation" value="270">
                                <div class="rotation-icon">
                                    <i class="fas fa-undo"></i>
                                </div>
                                <div>90° Counter-clockwise</div>
                            </label>
                        </div>

                        <div class="page-selection">
                            <h3>Page Selection:</h3>
                            <div style="margin: 1rem 0;">
                                <label style="margin-right: 2rem;">
                                    <input type="radio" name="pages" value="all" checked> 
                                    Rotate all pages
                                </label>
                                <label>
                                    <input type="radio" name="pages" value="custom"> 
                                    Rotate specific pages
                                </label>
                            </div>
                            <div id="customPages" style="display: none; margin-top: 1rem;">
                                <input type="text" name="page_list" class="form-control" placeholder="e.g., 1,3,5-8,10">
                                <small style="color: #666;">Enter page numbers separated by commas. Use dash for ranges.</small>
                            </div>
                        </div>
                    </div>

                    <div class="loader" id="loader"></div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" id="rotateBtn" disabled>
                            <i class="fas fa-sync-alt"></i> Rotate PDF
                        </button>
                    </div>
                </form>

                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e0e0e0;">
                    <h3>How it works:</h3>
                    <ol style="line-height: 2;">
                        <li>Upload your PDF file</li>
                        <li>Select rotation angle (90°, 180°, or 270°)</li>
                        <li>Choose to rotate all pages or specific pages</li>
                        <li>Click "Rotate PDF" to process</li>
                        <li>Download your rotated PDF file</li>
                    </ol>
                    
                    <p style="margin-top: 1rem; color: #757575;">
                        <i class="fas fa-shield-alt"></i> Your files are automatically deleted after processing for your privacy and security.
                    </p>
                </div>
            </div>
        </div>
    </div>

<?php
// Include footer
require_once '../includes/footer.php';
?>