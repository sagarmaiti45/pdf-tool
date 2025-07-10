<?php
require_once '../includes/functions.php';

$error = '';
$success = '';
$downloadLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCSRFToken($_POST['csrf_token'] ?? '');
        
        if (!isset($_FILES['image_files']) || count($_FILES['image_files']['name']) < 1) {
            throw new RuntimeException('Please select at least one image file.');
        }

        $uploadedFiles = [];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        
        // Validate and upload all files
        for ($i = 0; $i < count($_FILES['image_files']['name']); $i++) {
            if ($_FILES['image_files']['error'][$i] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Error uploading file: ' . $_FILES['image_files']['name'][$i]);
            }
            
            $file = [
                'name' => $_FILES['image_files']['name'][$i],
                'type' => $_FILES['image_files']['type'][$i],
                'tmp_name' => $_FILES['image_files']['tmp_name'][$i],
                'error' => $_FILES['image_files']['error'][$i],
                'size' => $_FILES['image_files']['size'][$i]
            ];
            
            validateFile($file, $allowedTypes);
            
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $uploadPath = UPLOAD_DIR . generateUniqueFileName($extension);
            
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new RuntimeException('Failed to move uploaded file.');
            }
            
            $uploadedFiles[] = $uploadPath;
        }
        
        $outputFile = TEMP_DIR . generateUniqueFileName('pdf');
        $pageSize = $_POST['page_size'] ?? 'a4';
        $orientation = $_POST['orientation'] ?? 'portrait';
        
        // Check for available tools
        $gsPath = '/opt/homebrew/bin/gs';
        if (!file_exists($gsPath)) {
            $gsPath = 'gs';
        }
        
        $magickPath = '/opt/homebrew/bin/magick';
        if (!file_exists($magickPath)) {
            $magickPath = 'magick';
        }
        
        // Page size dimensions in pixels at 72 DPI
        $pageSizeMap = [
            'a4' => '595x842',
            'letter' => '612x792',
            'legal' => '612x1008',
            'a3' => '842x1191'
        ];
        
        $pageSize = $pageSizeMap[$pageSize] ?? '595x842';
        
        // Create a temporary directory for Ghostscript
        $gsTempDir = TEMP_DIR . 'gs_temp_' . uniqid() . '/';
        if (!mkdir($gsTempDir, 0777, true)) {
            throw new RuntimeException('Failed to create temporary directory.');
        }
        
        try {
            // Method 1: Direct ImageMagick to PDF conversion
            $fileList = implode(' ', array_map('escapeshellarg', $uploadedFiles));
            
            // Apply orientation if landscape
            $pageSizeForConvert = $pageSize;
            if ($orientation === 'landscape') {
                list($width, $height) = explode('x', $pageSize);
                $pageSizeForConvert = $height . 'x' . $width;
            }
            
            $command = sprintf(
                'MAGICK_TMPDIR=%s %s convert %s -resize %s\> -extent %s -gravity center -background white -quality 90 %s 2>&1',
                escapeshellarg($gsTempDir),
                $magickPath,
                $fileList,
                $pageSizeForConvert,
                $pageSizeForConvert,
                escapeshellarg($outputFile)
            );
            
            exec($command, $output, $returnCode);
            
            // If ImageMagick fails, try using Ghostscript directly
            if ($returnCode !== 0 || !file_exists($outputFile)) {
                // Method 2: Convert each image to PDF first, then merge
                $pdfFiles = [];
                foreach ($uploadedFiles as $index => $imageFile) {
                    $tempPdf = $gsTempDir . 'page_' . $index . '.pdf';
                    
                    // Convert image to PDF using ImageMagick
                    $imgCommand = sprintf(
                        'MAGICK_TMPDIR=%s %s convert %s -page %s -gravity center -background white %s 2>&1',
                        escapeshellarg($gsTempDir),
                        $magickPath,
                        escapeshellarg($imageFile),
                        $pageSizeForConvert,
                        escapeshellarg($tempPdf)
                    );
                    
                    exec($imgCommand, $imgOutput, $imgReturn);
                    
                    if ($imgReturn === 0 && file_exists($tempPdf)) {
                        $pdfFiles[] = $tempPdf;
                    }
                }
                
                // Merge PDFs if we have any
                if (!empty($pdfFiles)) {
                    $pdfList = implode(' ', array_map('escapeshellarg', $pdfFiles));
                    $mergeCommand = sprintf(
                        'TMPDIR=%s %s -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite ' .
                        '-dCompatibilityLevel=1.4 ' .
                        '-sOutputFile=%s %s 2>&1',
                        escapeshellarg($gsTempDir),
                        $gsPath,
                        escapeshellarg($outputFile),
                        $pdfList
                    );
                    
                    exec($mergeCommand, $output, $returnCode);
                    
                    // Clean up temporary PDFs
                    foreach ($pdfFiles as $pdfFile) {
                        if (file_exists($pdfFile)) {
                            unlink($pdfFile);
                        }
                    }
                }
            }
        } finally {
            // Clean up temporary directory
            if (is_dir($gsTempDir)) {
                array_map('unlink', glob($gsTempDir . '*'));
                rmdir($gsTempDir);
            }
        }
        
        // Log the command for debugging
        if (isset($command)) {
            logError('JPG to PDF Command', ['command' => $command]);
        }
        
        // Clean up uploaded files
        foreach ($uploadedFiles as $file) {
            unlink($file);
        }
        
        if ($returnCode !== 0 || !file_exists($outputFile)) {
            logError('JPG to PDF Failed', ['output' => $output, 'return_code' => $returnCode]);
            throw new RuntimeException('Failed to create PDF from images. ' . implode(' ', $output));
        }
        
        $_SESSION['download_file'] = $outputFile;
        $_SESSION['download_name'] = 'images_to_pdf_' . date('Y-m-d_His') . '.pdf';
        
        $pdfSize = filesize($outputFile);
        $success = sprintf(
            'Successfully converted %d image(s) to PDF! Output size: %s',
            count($uploadedFiles),
            formatFileSize($pdfSize)
        );
        
        $downloadLink = 'download.php?file=' . basename($outputFile);
        
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        logError('JPG to PDF Error', ['error' => $e->getMessage()]);
        
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
    <title>JPG to PDF - Triniva</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .image-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .image-preview {
            position: relative;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            aspect-ratio: 1;
        }
        
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-preview .remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(244, 67, 54, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .image-preview .image-number {
            position: absolute;
            bottom: 5px;
            left: 5px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin: 1.5rem 0;
        }
    </style>
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
                <ul class="nav-links" id="navLinks">
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="../index.php#tools">All Tools</a></li>
                </ul>
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </nav>
        </div>
    </header>

    <div class="tool-page">
        <div class="container">
            <div class="tool-header">
                <h1><i class="fas fa-image"></i> JPG to PDF</h1>
                <p>Convert JPG/PNG images to PDF documents</p>
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
                            <i class="fas fa-download"></i> Download PDF
                        </a>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="convertForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="upload-area" id="uploadArea">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <div class="upload-text">Drag & Drop your images here</div>
                        <div class="upload-subtext">JPG, JPEG, PNG (Select multiple files)</div>
                        <input type="file" name="image_files[]" id="imageFiles" class="file-input" accept=".jpg,.jpeg,.png" multiple required>
                    </div>

                    <div id="imagePreview" style="display: none;">
                        <h3>Selected Images:</h3>
                        <div class="image-preview-grid" id="previewGrid"></div>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('imageFiles').click()">
                            <i class="fas fa-plus"></i> Add More Images
                        </button>
                    </div>

                    <div id="pdfSettings" style="display: none;">
                        <h3>PDF Settings:</h3>
                        <div class="settings-grid">
                            <div class="form-group">
                                <label class="form-label">Page Size</label>
                                <select name="page_size" class="form-control">
                                    <option value="a4" selected>A4</option>
                                    <option value="letter">Letter</option>
                                    <option value="legal">Legal</option>
                                    <option value="a3">A3</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Orientation</label>
                                <select name="orientation" class="form-control">
                                    <option value="portrait" selected>Portrait</option>
                                    <option value="landscape">Landscape</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="loader" id="loader"></div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" id="convertBtn" disabled>
                            <i class="fas fa-file-pdf"></i> Convert to PDF
                        </button>
                    </div>
                </form>

                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e0e0e0;">
                    <h3>How it works:</h3>
                    <ol style="line-height: 2;">
                        <li>Select one or more image files (JPG, JPEG, PNG)</li>
                        <li>Preview and arrange your images</li>
                        <li>Choose page size and orientation</li>
                        <li>Click "Convert to PDF" to create your document</li>
                        <li>Download your PDF file</li>
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
            <p>&copy; 2024 Triniva. All rights reserved. A <a href="https://freshyportal.com" target="_blank" style="color: #fff; text-decoration: underline;">FreshyPortal</a> Product.</p>
        </div>
    </footer>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('imageFiles');
        const imagePreview = document.getElementById('imagePreview');
        const previewGrid = document.getElementById('previewGrid');
        const pdfSettings = document.getElementById('pdfSettings');
        const convertBtn = document.getElementById('convertBtn');
        const convertForm = document.getElementById('convertForm');
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
            
            const files = Array.from(e.dataTransfer.files).filter(file => 
                file.type === 'image/jpeg' || file.type === 'image/jpg' || file.type === 'image/png'
            );
            if (files.length > 0) {
                addFiles(files);
            }
        });

        fileInput.addEventListener('change', (e) => {
            addFiles(Array.from(e.target.files));
        });

        function addFiles(files) {
            selectedFiles = [...selectedFiles, ...files];
            displayPreviews();
            
            if (selectedFiles.length > 0) {
                uploadArea.style.display = 'none';
                imagePreview.style.display = 'block';
                pdfSettings.style.display = 'block';
                convertBtn.disabled = false;
            }
        }

        function displayPreviews() {
            previewGrid.innerHTML = '';
            
            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const preview = document.createElement('div');
                    preview.className = 'image-preview';
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="${file.name}">
                        <button type="button" class="remove-btn" onclick="removeImage(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="image-number">${index + 1}</div>
                    `;
                    previewGrid.appendChild(preview);
                };
                reader.readAsDataURL(file);
            });
        }

        function removeImage(index) {
            selectedFiles.splice(index, 1);
            displayPreviews();
            
            if (selectedFiles.length === 0) {
                uploadArea.style.display = 'block';
                imagePreview.style.display = 'none';
                pdfSettings.style.display = 'none';
                convertBtn.disabled = true;
            }
        }

        convertForm.addEventListener('submit', (e) => {
            // Don't prevent default - let the form submit normally
            convertBtn.disabled = true;
            loader.style.display = 'block';
            
            // Update the form to use the selected files
            const dt = new DataTransfer();
            selectedFiles.forEach(file => {
                dt.items.add(file);
            });
            fileInput.files = dt.files;
        });
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>