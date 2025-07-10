<?php
require_once '../includes/functions.php';
require_once '../includes/normalize-page.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';
$success = '';
$downloadLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCSRFToken($_POST['csrf_token'] ?? '');
        
        if (!isset($_FILES['pdf_files']) || count($_FILES['pdf_files']['name']) < 2) {
            throw new RuntimeException('Please select at least 2 PDF files to merge.');
        }

        $uploadedFiles = [];
        
        // Validate and upload all files
        for ($i = 0; $i < count($_FILES['pdf_files']['name']); $i++) {
            if ($_FILES['pdf_files']['error'][$i] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Error uploading file: ' . $_FILES['pdf_files']['name'][$i]);
            }
            
            $file = [
                'name' => $_FILES['pdf_files']['name'][$i],
                'type' => $_FILES['pdf_files']['type'][$i],
                'tmp_name' => $_FILES['pdf_files']['tmp_name'][$i],
                'error' => $_FILES['pdf_files']['error'][$i],
                'size' => $_FILES['pdf_files']['size'][$i]
            ];
            
            validateFile($file, ['application/pdf']);
            
            $uploadPath = UPLOAD_DIR . generateUniqueFileName('pdf');
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new RuntimeException('Failed to move uploaded file.');
            }
            
            $uploadedFiles[] = $uploadPath;
        }
        
        $outputFile = TEMP_DIR . generateUniqueFileName('pdf');
        
        // Build Ghostscript command to merge PDFs
        $gsPath = '/opt/homebrew/bin/gs';
        if (!file_exists($gsPath)) {
            $gsPath = 'gs';
        }
        
        $gsTempDir = TEMP_DIR;
        putenv("TMPDIR=$gsTempDir");
        
        $fileList = implode(' ', array_map('escapeshellarg', $uploadedFiles));
        
        // Check if page size normalization is requested
        $pageSizeOption = $_POST['page_size_option'] ?? 'original';
        
        if ($pageSizeOption === 'normalize') {
            $targetPageSize = $_POST['target_page_size'] ?? 'a4';
            
            // Create a temporary file for each PDF with normalized size
            $normalizedFiles = [];
            
            // Page sizes in PostScript points (1/72 inch)
            $pageDimensions = [
                'a4' => ['width' => 595, 'height' => 842],
                'letter' => ['width' => 612, 'height' => 792],
                'legal' => ['width' => 612, 'height' => 1008],
                'a3' => ['width' => 842, 'height' => 1191]
            ];
            
            $targetDimensions = $pageDimensions[$targetPageSize] ?? $pageDimensions['a4'];
            $pageWidth = $targetDimensions['width'];
            $pageHeight = $targetDimensions['height'];
            
            // Create PostScript commands for page size
            $psCommands = match($targetPageSize) {
                'a4' => '<< /PageSize [595 842] >> setpagedevice',
                'letter' => '<< /PageSize [612 792] >> setpagedevice',
                'legal' => '<< /PageSize [612 1008] >> setpagedevice',
                'a3' => '<< /PageSize [842 1191] >> setpagedevice',
                default => '<< /PageSize [595 842] >> setpagedevice'
            };
            
            // Use ImageMagick for better control
            $magickPath = '/opt/homebrew/bin/magick';
            if (!file_exists($magickPath)) {
                $magickPath = 'magick';
            }
            
            foreach ($uploadedFiles as $index => $pdfFile) {
                $normalizedFile = TEMP_DIR . 'normalized_' . $index . '_' . basename($pdfFile);
                
                // Log normalization attempt
                logError('Normalizing PDF', [
                    'input' => basename($pdfFile),
                    'output' => basename($normalizedFile),
                    'target_size' => $pageWidth . 'x' . $pageHeight
                ]);
                
                // Use the simplified normalization function
                if (normalizePdfPage($pdfFile, $normalizedFile, $pageWidth, $pageHeight)) {
                    $normalizedFiles[] = $normalizedFile;
                    unlink($pdfFile);
                } else {
                    // If normalization fails, keep the original
                    $normalizedFiles[] = $pdfFile;
                    logError('PDF normalization failed', ['file' => basename($pdfFile)]);
                }
            }
            
            // Now merge the normalized files with explicit page size enforcement
            $fileList = implode(' ', array_map('escapeshellarg', $normalizedFiles));
            $command = sprintf(
                'TMPDIR=%s %s -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite ' .
                '-dDEVICEWIDTHPOINTS=%d -dDEVICEHEIGHTPOINTS=%d ' .
                '-dFIXEDMEDIA -dPDFFitPage ' .
                '-dCompatibilityLevel=1.4 ' .
                '-sOutputFile=%s %s 2>&1',
                escapeshellarg($gsTempDir),
                $gsPath,
                $pageWidth,
                $pageHeight,
                escapeshellarg($outputFile),
                $fileList
            );
            
            // Update uploadedFiles for cleanup
            $uploadedFiles = $normalizedFiles;
        } else {
            // Original command without page size changes
            $command = sprintf(
                'TMPDIR=%s %s -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPDFSETTINGS=/prepress -sOutputFile=%s %s 2>&1',
                escapeshellarg($gsTempDir),
                $gsPath,
                escapeshellarg($outputFile),
                $fileList
            );
        }
        
        // Log the command for debugging
        logError('Merge PDF Command', ['command' => $command]);
        
        exec($command, $output, $returnCode);
        
        // Clean up uploaded files
        foreach ($uploadedFiles as $file) {
            unlink($file);
        }
        
        if ($returnCode !== 0 || !file_exists($outputFile)) {
            logError('Merge PDF Failed', ['output' => $output, 'return_code' => $returnCode]);
            throw new RuntimeException('PDF merge failed. ' . implode(' ', $output));
        }
        
        $_SESSION['download_file'] = $outputFile;
        $_SESSION['download_name'] = 'merged_' . date('Y-m-d_His') . '.pdf';
        
        $mergedSize = filesize($outputFile);
        $success = sprintf(
            'Successfully merged %d PDF files! Output size: %s',
            count($uploadedFiles),
            formatFileSize($mergedSize)
        );
        
        $downloadLink = 'download.php?file=' . basename($outputFile);
        
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        logError('Merge PDF Error', ['error' => $e->getMessage()]);
        
        // Clean up any uploaded files on error
        if (!empty($uploadedFiles)) {
            foreach ($uploadedFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merge PDF - Triniva</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <div class="logo">
                    <a href="../index.php" style="text-decoration: none; color: inherit;">
                        <i class="fas fa-file-pdf"></i>
                        <span>Triniva</span>
                    </a>
                </div>
                <ul class="nav-links">
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="../index.php#tools">All Tools</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="tool-page">
        <div class="container">
            <div class="tool-header">
                <h1><i class="fas fa-object-group"></i> Merge PDF</h1>
                <p>Combine multiple PDF files into one document</p>
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
                            <i class="fas fa-download"></i> Download Merged PDF
                        </a>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="mergeForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="upload-area" id="uploadArea">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <div class="upload-text">Drag & Drop your PDF files here</div>
                        <div class="upload-subtext">or click to browse (Select multiple files)</div>
                        <input type="file" name="pdf_files[]" id="pdfFiles" class="file-input" accept=".pdf" multiple required>
                    </div>

                    <div id="fileList" style="display: none;">
                        <h3 style="margin-bottom: 1rem;">Selected Files (Drag to reorder):</h3>
                        <div class="file-list" id="sortableList"></div>
                        <div style="margin-top: 1rem; color: #666;">
                            <i class="fas fa-info-circle"></i> Files will be merged in the order shown above
                        </div>
                        
                        <div style="margin-top: 2rem; padding: 1.5rem; background: #f5f5f5; border-radius: 8px;">
                            <h4 style="margin-bottom: 1rem;">Page Size Options:</h4>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; margin-bottom: 0.5rem;">
                                    <input type="radio" name="page_size_option" value="original" checked>
                                    <strong>Keep Original Sizes</strong> - Maintain each PDF's original page dimensions
                                </label>
                                <label style="display: block; margin-bottom: 0.5rem;">
                                    <input type="radio" name="page_size_option" value="normalize">
                                    <strong>Normalize to Standard Size</strong> - Center all content on uniform pages
                                </label>
                            </div>
                            
                            <div id="pageSizeSettings" style="display: none; margin-top: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Target Page Size</label>
                                    <select name="target_page_size" class="form-control">
                                        <option value="a4" selected>A4 (210 × 297 mm)</option>
                                        <option value="letter">Letter (8.5 × 11 inches)</option>
                                        <option value="legal">Legal (8.5 × 14 inches)</option>
                                        <option value="a3">A3 (297 × 420 mm)</option>
                                    </select>
                                    <small style="color: #666;">All pages will be centered on the selected size with white borders as needed</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="loader" id="loader"></div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" id="mergeBtn" disabled>
                            <i class="fas fa-object-group"></i> Merge PDFs
                        </button>
                    </div>
                </form>

                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e0e0e0;">
                    <h3>How it works:</h3>
                    <ol style="line-height: 2;">
                        <li>Select multiple PDF files (minimum 2 files)</li>
                        <li>Drag files to reorder them if needed</li>
                        <li>Click "Merge PDFs" to combine them</li>
                        <li>Download your merged PDF file</li>
                    </ol>
                    
                    <p style="margin-top: 1rem; color: #757575;">
                        <i class="fas fa-shield-alt"></i> Your files are automatically deleted after processing for your privacy and security.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; 2024 PDF Tools Pro. All rights reserved.</p>
        </div>
    </footer>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('pdfFiles');
        const fileList = document.getElementById('fileList');
        const sortableList = document.getElementById('sortableList');
        const mergeBtn = document.getElementById('mergeBtn');
        const mergeForm = document.getElementById('mergeForm');
        const loader = document.getElementById('loader');
        
        let selectedFiles = [];

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
            
            const files = Array.from(e.dataTransfer.files).filter(file => file.type === 'application/pdf');
            if (files.length > 0) {
                handleFiles(files);
            }
        });

        fileInput.addEventListener('change', (e) => {
            handleFiles(Array.from(e.target.files));
        });

        function handleFiles(files) {
            selectedFiles = files;
            displayFiles();
            mergeBtn.disabled = files.length < 2;
        }

        function displayFiles() {
            if (selectedFiles.length === 0) {
                fileList.style.display = 'none';
                uploadArea.style.display = 'block';
                return;
            }
            
            sortableList.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.draggable = true;
                fileItem.dataset.index = index;
                
                fileItem.innerHTML = `
                    <div class="file-info">
                        <i class="fas fa-grip-vertical" style="margin-right: 1rem; color: #999; cursor: move;"></i>
                        <i class="fas fa-file-pdf file-icon"></i>
                        <div>
                            <div class="file-name">${file.name}</div>
                            <div class="file-size">${formatFileSize(file.size)}</div>
                        </div>
                    </div>
                    <button type="button" class="file-remove" onclick="removeFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                fileItem.addEventListener('dragstart', handleDragStart);
                fileItem.addEventListener('dragover', handleDragOver);
                fileItem.addEventListener('drop', handleDrop);
                fileItem.addEventListener('dragend', handleDragEnd);
                
                sortableList.appendChild(fileItem);
            });
            
            fileList.style.display = 'block';
            uploadArea.style.display = 'none';
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            displayFiles();
            mergeBtn.disabled = selectedFiles.length < 2;
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

        let draggedIndex = null;
        
        // Handle page size options
        const pageSizeSettings = document.getElementById('pageSizeSettings');
        document.querySelectorAll('input[name="page_size_option"]').forEach(radio => {
            radio.addEventListener('change', function() {
                pageSizeSettings.style.display = this.value === 'normalize' ? 'block' : 'none';
            });
        });

        function handleDragStart(e) {
            draggedIndex = parseInt(e.target.dataset.index);
            e.target.style.opacity = '0.5';
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        }

        function handleDrop(e) {
            e.preventDefault();
            const droppedIndex = parseInt(e.target.closest('.file-item').dataset.index);
            
            if (draggedIndex !== droppedIndex) {
                const draggedFile = selectedFiles[draggedIndex];
                selectedFiles.splice(draggedIndex, 1);
                selectedFiles.splice(droppedIndex, 0, draggedFile);
                displayFiles();
            }
        }

        function handleDragEnd(e) {
            e.target.style.opacity = '';
        }

        mergeForm.addEventListener('submit', (e) => {
            // Don't prevent default - let the form submit normally
            mergeBtn.disabled = true;
            loader.style.display = 'block';
            
            // Update the form to use the selected files
            const dt = new DataTransfer();
            selectedFiles.forEach(file => {
                dt.items.add(file);
            });
            fileInput.files = dt.files;
        });
    </script>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Triniva</h3>
                    <p>Professional PDF tools that are fast, secure, and completely free.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="../index.php">Home</a></li>
                        <li><a href="../index.php#tools">All Tools</a></li>
                        <li><a href="../about.php">About</a></li>
                        <li><a href="../contact.php">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="../privacy.php">Privacy Policy</a></li>
                        <li><a href="../terms.php">Terms & Conditions</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Triniva. All rights reserved. A <a href="https://freshyportal.com" target="_blank" style="color: #fff; text-decoration: underline;">FreshyPortal</a> Product.</p>
            </div>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>
</html>