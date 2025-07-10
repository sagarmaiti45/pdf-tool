<?php
require_once '../includes/functions.php';

$error = '';
$success = '';
$downloadLinks = [];

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
        
        $quality = $_POST['quality'] ?? '90';
        $resolution = $_POST['resolution'] ?? '150';
        $pages = $_POST['pages'] ?? 'all';
        
        // Create output directory for images
        $outputDir = TEMP_DIR . uniqid('pdf_images_') . '/';
        mkdir($outputDir, 0777, true);
        
        // Configure ImageMagick with Ghostscript path
        $magickPath = '/opt/homebrew/bin/magick';
        if (!file_exists($magickPath)) {
            $magickPath = 'magick';
        }
        
        // Set Ghostscript path for ImageMagick
        $gsPath = '/opt/homebrew/bin/gs';
        if (!file_exists($gsPath)) {
            $gsPath = 'gs';
        }
        putenv("GS_PROG=$gsPath");
        
        // Configure ImageMagick temporary directory
        putenv("MAGICK_TMPDIR=" . TEMP_DIR);
        putenv("TMPDIR=" . TEMP_DIR);
        
        if ($pages === 'all') {
            // Convert all pages using Ghostscript
            $command = sprintf(
                'TMPDIR=%s %s -dNOPAUSE -dBATCH -sDEVICE=jpeg -dJPEGQ=%d -r%d -sOutputFile=%s %s 2>&1',
                escapeshellarg(TEMP_DIR),
                $gsPath,
                intval($quality),
                intval($resolution),
                escapeshellarg($outputDir . 'page-%d.jpg'),
                escapeshellarg($uploadedFile)
            );
            exec($command, $output, $returnCode);
        } else {
            // Convert specific pages
            $pageList = preg_replace('/[^0-9,\-]/', '', $_POST['page_list'] ?? '');
            if (empty($pageList)) {
                throw new RuntimeException('Please specify which pages to convert.');
            }
            
            // Convert page ranges to individual pages
            $pageNumbers = [];
            $ranges = explode(',', $pageList);
            foreach ($ranges as $range) {
                if (strpos($range, '-') !== false) {
                    list($start, $end) = explode('-', $range);
                    for ($i = intval($start); $i <= intval($end); $i++) {
                        $pageNumbers[] = $i - 1; // ImageMagick uses 0-based indexing
                    }
                } else {
                    $pageNumbers[] = intval($range) - 1;
                }
            }
            
            // Convert specific pages
            foreach ($pageNumbers as $pageNum) {
                $command = sprintf(
                    '%s convert -density %d %s[%d] -quality %d -background white -alpha remove %s 2>&1',
                    $magickPath,
                    intval($resolution),
                    escapeshellarg($uploadedFile),
                    $pageNum,
                    intval($quality),
                    escapeshellarg($outputDir . 'page-' . ($pageNum + 1) . '.jpg')
                );
                exec($command, $output, $returnCode);
            }
        }
        
        unlink($uploadedFile);
        
        // Check if any images were created
        $images = glob($outputDir . '*.jpg');
        if (empty($images)) {
            throw new RuntimeException('Failed to convert PDF to images. ' . implode(' ', $output));
        }
        
        // Create ZIP file if multiple images
        if (count($images) > 1) {
            $zipFile = TEMP_DIR . $originalName . '_images.zip';
            $zip = new ZipArchive();
            
            if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
                foreach ($images as $image) {
                    $zip->addFile($image, basename($image));
                }
                $zip->close();
                
                $_SESSION['download_file'] = $zipFile;
                $_SESSION['download_name'] = $originalName . '_images.zip';
                
                $success = sprintf(
                    'Successfully converted PDF to %d images! Resolution: %d DPI, Quality: %d%%',
                    count($images),
                    $resolution,
                    $quality
                );
                
                $downloadLinks[] = [
                    'url' => 'download.php?file=' . basename($zipFile),
                    'text' => 'Download All Images (ZIP)',
                    'primary' => true
                ];
            }
        } else {
            // Single image
            $image = $images[0];
            $newPath = TEMP_DIR . $originalName . '.jpg';
            rename($image, $newPath);
            
            $_SESSION['download_file'] = $newPath;
            $_SESSION['download_name'] = $originalName . '.jpg';
            
            $success = 'Successfully converted PDF to image!';
            
            $downloadLinks[] = [
                'url' => 'download.php?file=' . basename($newPath),
                'text' => 'Download Image',
                'primary' => true
            ];
        }
        
        // Clean up temp directory
        array_map('unlink', glob($outputDir . '*'));
        rmdir($outputDir);
        
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        logError('PDF to JPG Error', ['error' => $e->getMessage()]);
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF to JPG - PDF Tools Pro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .quality-slider {
            margin: 1.5rem 0;
        }
        
        .slider-container {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .slider {
            flex: 1;
            -webkit-appearance: none;
            height: 8px;
            border-radius: 4px;
            background: #ddd;
            outline: none;
        }
        
        .slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
        }
        
        .slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
        }
        
        .slider-value {
            min-width: 50px;
            text-align: center;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .settings-grid {
            display: grid;
            gap: 1.5rem;
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
                        <span>PDF Tools Pro</span>
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
                <h1><i class="fas fa-file-image"></i> PDF to JPG</h1>
                <p>Convert PDF pages to high-quality JPG images</p>
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
                        <?php foreach ($downloadLinks as $link): ?>
                            <a href="<?php echo htmlspecialchars($link['url']); ?>" 
                               class="btn <?php echo $link['primary'] ? 'btn-primary' : 'btn-secondary'; ?>"
                               style="margin: 0.5rem;">
                                <i class="fas fa-download"></i> <?php echo htmlspecialchars($link['text']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="convertForm">
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

                    <div id="conversionSettings" style="display: none;">
                        <h3>Conversion Settings:</h3>
                        
                        <div class="settings-grid">
                            <div class="form-group">
                                <label class="form-label">Image Quality</label>
                                <div class="slider-container">
                                    <span>Low</span>
                                    <input type="range" name="quality" id="qualitySlider" 
                                           class="slider" min="50" max="100" value="90">
                                    <span>High</span>
                                    <span class="slider-value" id="qualityValue">90%</span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Resolution (DPI)</label>
                                <select name="resolution" class="form-control">
                                    <option value="72">72 DPI (Screen)</option>
                                    <option value="150" selected>150 DPI (Standard)</option>
                                    <option value="300">300 DPI (High Quality)</option>
                                    <option value="600">600 DPI (Print Quality)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Page Selection</label>
                                <div style="margin: 0.5rem 0;">
                                    <label style="margin-right: 2rem;">
                                        <input type="radio" name="pages" value="all" checked> 
                                        Convert all pages
                                    </label>
                                    <label>
                                        <input type="radio" name="pages" value="custom"> 
                                        Convert specific pages
                                    </label>
                                </div>
                                <div id="customPages" style="display: none; margin-top: 1rem;">
                                    <input type="text" name="page_list" class="form-control" 
                                           placeholder="e.g., 1,3,5-8,10">
                                    <small style="color: #666;">Enter page numbers separated by commas. Use dash for ranges.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="loader" id="loader"></div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" id="convertBtn" disabled>
                            <i class="fas fa-file-image"></i> Convert to JPG
                        </button>
                    </div>
                </form>

                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e0e0e0;">
                    <h3>How it works:</h3>
                    <ol style="line-height: 2;">
                        <li>Upload your PDF file</li>
                        <li>Choose image quality and resolution</li>
                        <li>Select pages to convert (all or specific)</li>
                        <li>Click "Convert to JPG" to process</li>
                        <li>Download your images (ZIP for multiple pages)</li>
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
        const fileInput = document.getElementById('pdfFile');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const removeFile = document.getElementById('removeFile');
        const convertBtn = document.getElementById('convertBtn');
        const convertForm = document.getElementById('convertForm');
        const loader = document.getElementById('loader');
        const conversionSettings = document.getElementById('conversionSettings');
        const customPages = document.getElementById('customPages');
        const qualitySlider = document.getElementById('qualitySlider');
        const qualityValue = document.getElementById('qualityValue');

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
            conversionSettings.style.display = 'none';
            convertBtn.disabled = true;
        });

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.style.display = 'block';
                uploadArea.style.display = 'none';
                conversionSettings.style.display = 'block';
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

        // Quality slider
        qualitySlider.addEventListener('input', function() {
            qualityValue.textContent = this.value + '%';
        });

        // Page selection
        document.querySelectorAll('input[name="pages"]').forEach(radio => {
            radio.addEventListener('change', function() {
                customPages.style.display = this.value === 'custom' ? 'block' : 'none';
            });
        });

        convertForm.addEventListener('submit', (e) => {
            convertBtn.disabled = true;
            loader.style.display = 'block';
        });
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>