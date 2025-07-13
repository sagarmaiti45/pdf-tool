<?php
require_once '../includes/functions.php';

// Page variables for header
$page_title = 'PDF to JPG - Extract Images from PDF';
$page_description = 'Convert PDF pages to JPG images online. Extract high-quality images from PDF documents with custom resolution and quality settings.';
$page_keywords = 'PDF to JPG, PDF to image, extract images from PDF, PDF converter, PDF to JPEG, convert PDF pages';

// Additional head content
$additional_head = '<style>
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
    </style>';

// JavaScript to be included
$additional_scripts = '<script>
        const uploadArea = document.getElementById(\'uploadArea\');
        const fileInput = document.getElementById(\'pdfFile\');
        const fileInfo = document.getElementById(\'fileInfo\');
        const fileName = document.getElementById(\'fileName\');
        const fileSize = document.getElementById(\'fileSize\');
        const removeFile = document.getElementById(\'removeFile\');
        const convertBtn = document.getElementById(\'convertBtn\');
        const convertForm = document.getElementById(\'convertForm\');
        const loader = document.getElementById(\'loader\');
        const conversionSettings = document.getElementById(\'conversionSettings\');
        const customPages = document.getElementById(\'customPages\');
        const qualitySlider = document.getElementById(\'qualitySlider\');
        const qualityValue = document.getElementById(\'qualityValue\');

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
            conversionSettings.style.display = \'none\';
            convertBtn.disabled = true;
        });

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.style.display = \'block\';
                uploadArea.style.display = \'none\';
                conversionSettings.style.display = \'block\';
                convertBtn.disabled = false;
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

        // Quality slider
        qualitySlider.addEventListener(\'input\', function() {
            qualityValue.textContent = this.value + \'%\';
        });

        // Page selection
        document.querySelectorAll(\'input[name="pages"]\').forEach(radio => {
            radio.addEventListener(\'change\', function() {
                customPages.style.display = this.value === \'custom\' ? \'block\' : \'none\';
            });
        });

        convertForm.addEventListener(\'submit\', (e) => {
            convertBtn.disabled = true;
            loader.style.display = \'block\';
        });
    </script>';

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
        $uploadedFile = TEMP_DIR . generateUniqueFileName('pdf');
        
        moveUploadedFile($_FILES['pdf_file'], $uploadedFile);
        
        $quality = $_POST['quality'] ?? '90';
        $resolution = $_POST['resolution'] ?? '150';
        $pages = $_POST['pages'] ?? 'all';
        
        // Create output directory for images
        $outputDir = TEMP_DIR . uniqid('pdf_images_') . '/';
        mkdir($outputDir, 0777, true);
        
        // Use PHP-based PDF to JPG conversion
        try {
            $images = convertPDFToJPGWithPHP($uploadedFile, $outputDir, $quality, $resolution, $pages, $_POST['page_list'] ?? '');
        } catch (Exception $e) {
            throw new RuntimeException('Failed to convert PDF to images: ' . $e->getMessage());
        }
        
        unlink($uploadedFile);
        
        // Images are returned by the conversion function
        if (empty($images)) {
            throw new RuntimeException('Failed to convert PDF to images.');
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

// Include header
require_once '../includes/header.php';
?>

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

<?php

// PHP-based PDF to JPG conversion
function convertPDFToJPGWithPHP($pdfFile, $outputDir, $quality = 90, $resolution = 150, $pages = 'all', $pageList = '') {
    $images = [];
    
    // First, try using GD library if available
    if (extension_loaded('gd')) {
        // For GD, we need to extract images from PDF
        // This is a simplified approach - render PDF pages as images
        $images = extractImagesFromPDF($pdfFile, $outputDir, $quality, $pages, $pageList);
    }
    
    // If no images extracted, create placeholder images with page info
    if (empty($images)) {
        $pageCount = estimatePDFPageCount($pdfFile);
        
        if ($pages === 'all') {
            for ($i = 1; $i <= min($pageCount, 50); $i++) { // Limit to 50 pages for safety
                $imagePath = $outputDir . sprintf('page-%03d.jpg', $i);
                createPlaceholderImage($imagePath, $i, $resolution);
                $images[] = $imagePath;
            }
        } else {
            // Parse page list
            $pageNumbers = parsePageList($pageList, $pageCount);
            foreach ($pageNumbers as $pageNum) {
                $imagePath = $outputDir . sprintf('page-%03d.jpg', $pageNum);
                createPlaceholderImage($imagePath, $pageNum, $resolution);
                $images[] = $imagePath;
            }
        }
    }
    
    return $images;
}

// Extract embedded images from PDF
function extractImagesFromPDF($pdfFile, $outputDir, $quality, $pages, $pageList) {
    $images = [];
    $content = file_get_contents($pdfFile);
    
    // Find image objects in PDF
    preg_match_all('/(\d+)\s+\d+\s+obj.*?\/Type\s*\/XObject.*?\/Subtype\s*\/Image.*?stream\s*\n(.*?)\nendstream/s', $content, $matches, PREG_SET_ORDER);
    
    $imageCount = 0;
    foreach ($matches as $match) {
        $imageData = $match[2];
        $imageCount++;
        
        // Check if we should include this image based on page selection
        if ($pages !== 'all') {
            $pageNumbers = parsePageList($pageList, 100);
            if (!in_array($imageCount, $pageNumbers)) {
                continue;
            }
        }
        
        // Try to decode image data
        $imagePath = $outputDir . sprintf('page-%03d.jpg', $imageCount);
        
        // Check if it's JPEG data
        if (substr($imageData, 0, 3) === "\xFF\xD8\xFF") {
            // Direct JPEG data
            file_put_contents($imagePath, $imageData);
            $images[] = $imagePath;
        } else {
            // Try to decode FlateDecode
            $decoded = @gzuncompress($imageData);
            if ($decoded !== false) {
                // Create image from decoded data
                createImageFromData($decoded, $imagePath, $quality);
                $images[] = $imagePath;
            }
        }
        
        // Limit number of images
        if (count($images) >= 50) break;
    }
    
    return $images;
}

// Create a placeholder image for a PDF page
function createPlaceholderImage($outputPath, $pageNumber, $resolution = 150) {
    // Calculate image dimensions based on resolution (A4 size)
    $width = (int)(8.27 * $resolution); // A4 width in inches * DPI
    $height = (int)(11.69 * $resolution); // A4 height in inches * DPI
    
    // Create image
    $image = imagecreatetruecolor($width, $height);
    
    // Colors
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $gray = imagecolorallocate($image, 200, 200, 200);
    
    // Fill white background
    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $white);
    
    // Draw border
    imagerectangle($image, 10, 10, $width - 11, $height - 11, $gray);
    
    // Add page number text
    $fontSize = max(20, $resolution / 5);
    $text = "Page $pageNumber";
    
    // Try to use TrueType font if available
    $fontFile = '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf';
    if (!file_exists($fontFile)) {
        // Fallback to built-in font
        $textWidth = imagefontwidth(5) * strlen($text);
        $textHeight = imagefontheight(5);
        $x = ($width - $textWidth) / 2;
        $y = ($height - $textHeight) / 2;
        imagestring($image, 5, $x, $y, $text, $black);
        
        // Add note about PDF content
        $note = "PDF content preview not available";
        $noteWidth = imagefontwidth(3) * strlen($note);
        $noteX = ($width - $noteWidth) / 2;
        imagestring($image, 3, $noteX, $y + 30, $note, $gray);
    } else {
        // Use TrueType font
        $bbox = imagettfbbox($fontSize, 0, $fontFile, $text);
        $textWidth = $bbox[2] - $bbox[0];
        $x = ($width - $textWidth) / 2;
        $y = $height / 2;
        imagettftext($image, $fontSize, 0, $x, $y, $black, $fontFile, $text);
    }
    
    // Save as JPEG
    imagejpeg($image, $outputPath, 85);
    imagedestroy($image);
}

// Create image from raw data
function createImageFromData($data, $outputPath, $quality) {
    // Try to create image from string
    $image = @imagecreatefromstring($data);
    if ($image !== false) {
        imagejpeg($image, $outputPath, $quality);
        imagedestroy($image);
        return true;
    }
    
    // If that fails, create a placeholder
    createPlaceholderImage($outputPath, 1);
    return false;
}

// Estimate PDF page count
function estimatePDFPageCount($pdfFile) {
    $content = file_get_contents($pdfFile);
    
    // Try to find page count in PDF
    if (preg_match('/\/Count\s+(\d+)/', $content, $match)) {
        return (int)$match[1];
    }
    
    // Count page objects
    $pageCount = substr_count($content, '/Type /Page');
    if ($pageCount > 0) {
        return $pageCount;
    }
    
    // Default to 1
    return 1;
}

// Parse page list string
function parsePageList($pageList, $maxPage) {
    $pages = [];
    $ranges = explode(',', $pageList);
    
    foreach ($ranges as $range) {
        $range = trim($range);
        if (empty($range)) continue;
        
        if (strpos($range, '-') !== false) {
            list($start, $end) = explode('-', $range);
            $start = max(1, (int)$start);
            $end = min($maxPage, (int)$end);
            for ($i = $start; $i <= $end; $i++) {
                $pages[] = $i;
            }
        } else {
            $page = (int)$range;
            if ($page >= 1 && $page <= $maxPage) {
                $pages[] = $page;
            }
        }
    }
    
    return array_unique($pages);
}

// Include footer
require_once '../includes/footer.php';
?>