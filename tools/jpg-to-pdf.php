<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Page variables for header
$page_title = 'JPG to PDF - Convert Images to PDF';
$page_description = 'Convert JPG, JPEG, and PNG images to PDF online. Create PDF documents from multiple images with custom page size and orientation.';
$page_keywords = 'JPG to PDF, image to PDF, PNG to PDF, photo to PDF, convert images to PDF, picture to PDF';

// Additional head content
$additional_head = '<style>
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
        
        #imagePreview {
            margin-bottom: 2rem;
        }
    </style>';

// JavaScript to be included
$additional_scripts = '<script>
        const uploadArea = document.getElementById(\'uploadArea\');
        const fileInput = document.getElementById(\'imageFiles\');
        const imagePreview = document.getElementById(\'imagePreview\');
        const previewGrid = document.getElementById(\'previewGrid\');
        const pdfSettings = document.getElementById(\'pdfSettings\');
        const convertBtn = document.getElementById(\'convertBtn\');
        const convertForm = document.getElementById(\'convertForm\');
        const loader = document.getElementById(\'loader\');
        
        let selectedFiles = [];

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
            
            const files = Array.from(e.dataTransfer.files).filter(file => 
                file.type === \'image/jpeg\' || file.type === \'image/jpg\' || file.type === \'image/png\'
            );
            if (files.length > 0) {
                addFiles(files);
            }
        });

        fileInput.addEventListener(\'change\', (e) => {
            addFiles(Array.from(e.target.files));
        });

        function addFiles(files) {
            selectedFiles = [...selectedFiles, ...files];
            displayPreviews();
            
            if (selectedFiles.length > 0) {
                uploadArea.style.display = \'none\';
                imagePreview.style.display = \'block\';
                pdfSettings.style.display = \'block\';
                convertBtn.disabled = false;
            }
        }

        function displayPreviews() {
            previewGrid.innerHTML = \'\';
            
            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const preview = document.createElement(\'div\');
                    preview.className = \'image-preview\';
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
                uploadArea.style.display = \'block\';
                imagePreview.style.display = \'none\';
                pdfSettings.style.display = \'none\';
                convertBtn.disabled = true;
            }
        }

        convertForm.addEventListener(\'submit\', (e) => {
            // Don\'t prevent default - let the form submit normally
            convertBtn.disabled = true;
            loader.style.display = \'block\';
            
            // Update the form to use the selected files
            const dt = new DataTransfer();
            selectedFiles.forEach(file => {
                dt.items.add(file);
            });
            fileInput.files = dt.files;
        });
    </script>';

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
        
        // Use PHP-based JPG to PDF conversion
        try {
            $pdfContent = convertImagesToPDFWithPHP($uploadedFiles, $pageSize, $orientation);
            file_put_contents($outputFile, $pdfContent);
        } catch (Exception $e) {
            throw new RuntimeException('Failed to create PDF from images: ' . $e->getMessage());
        }
        
        // Clean up uploaded files
        foreach ($uploadedFiles as $file) {
            unlink($file);
        }
        
        if (!file_exists($outputFile) || filesize($outputFile) < 100) {
            throw new RuntimeException('Failed to create PDF from images.');
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

// Include header
require_once '../includes/header.php';
?>

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

                    <div id="pdfSettings" style="display: none; margin-top: 2rem;">
                        <h3>PDF Settings:</h3>
                        <div class="settings-grid">
                            <div class="form-group">
                                <label class="form-label">Page Size</label>
                                <select name="page_size" class="form-control">
                                    <option value="original">Keep Original Size</option>
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

<?php

// PHP-based image to PDF conversion
function convertImagesToPDFWithPHP($imageFiles, $pageSize = 'a4', $orientation = 'portrait') {
    // Page size dimensions in points (1/72 inch)
    $pageSizes = [
        'a4' => ['width' => 595, 'height' => 842],
        'letter' => ['width' => 612, 'height' => 792],
        'legal' => ['width' => 612, 'height' => 1008],
        'a3' => ['width' => 842, 'height' => 1191],
        'original' => null
    ];
    
    $pageDimensions = $pageSizes[$pageSize] ?? $pageSizes['a4'];
    
    // Swap dimensions for landscape orientation
    if ($orientation === 'landscape' && $pageDimensions) {
        $temp = $pageDimensions['width'];
        $pageDimensions['width'] = $pageDimensions['height'];
        $pageDimensions['height'] = $temp;
    }
    
    // Start building PDF
    $pdf = "%PDF-1.4\n%âÉåÒ\n";
    
    $objects = [];
    $pages = [];
    $currentObjNum = 1;
    
    // Catalog object (1 0 obj)
    $objects[$currentObjNum++] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj";
    
    // Pages will be object 2, but we'll create it after processing images
    $pagesObjNum = $currentObjNum++;
    
    // Process each image
    foreach ($imageFiles as $index => $imageFile) {
        $imageInfo = getimagesize($imageFile);
        if (!$imageInfo) continue;
        
        $imageWidth = $imageInfo[0];
        $imageHeight = $imageInfo[1];
        $imageType = $imageInfo[2];
        
        // Determine page size for this image
        if ($pageSize === 'original') {
            // Use image dimensions as page size (converted to points)
            $pageWidth = $imageWidth * 72 / 96; // Assuming 96 DPI
            $pageHeight = $imageHeight * 72 / 96;
        } else {
            $pageWidth = $pageDimensions['width'];
            $pageHeight = $pageDimensions['height'];
        }
        
        // Create page object
        $pageObjNum = $currentObjNum++;
        $pages[] = "$pageObjNum 0 R";
        
        // Create XObject for image
        $xobjNum = $currentObjNum++;
        
        // Read image data
        $imageData = file_get_contents($imageFile);
        
        // Determine image format and filter
        $filter = '';
        if ($imageType == IMAGETYPE_JPEG) {
            $filter = '/DCTDecode';
        } elseif ($imageType == IMAGETYPE_PNG) {
            // For PNG, we need to convert to JPEG for simplicity
            $img = imagecreatefrompng($imageFile);
            ob_start();
            imagejpeg($img, null, 90);
            $imageData = ob_get_clean();
            imagedestroy($img);
            $filter = '/DCTDecode';
        }
        
        // Calculate scaling to fit image on page
        $scale = min($pageWidth / $imageWidth, $pageHeight / $imageHeight, 1);
        $scaledWidth = $imageWidth * $scale;
        $scaledHeight = $imageHeight * $scale;
        
        // Center image on page
        $xOffset = ($pageWidth - $scaledWidth) / 2;
        $yOffset = ($pageHeight - $scaledHeight) / 2;
        
        // Create image XObject
        $objects[$xobjNum] = "$xobjNum 0 obj\n" .
            "<< /Type /XObject /Subtype /Image " .
            "/Width $imageWidth /Height $imageHeight " .
            "/ColorSpace /DeviceRGB /BitsPerComponent 8 " .
            "/Filter $filter /Length " . strlen($imageData) . " >>\n" .
            "stream\n$imageData\nendstream\nendobj";
        
        // Create content stream for page
        $contentObjNum = $currentObjNum++;
        $contentStream = "q\n" .
            "$scaledWidth 0 0 $scaledHeight $xOffset $yOffset cm\n" .
            "/Im$index Do\n" .
            "Q";
        
        $objects[$contentObjNum] = "$contentObjNum 0 obj\n" .
            "<< /Length " . strlen($contentStream) . " >>\n" .
            "stream\n$contentStream\nendstream\nendobj";
        
        // Create resources object
        $resourcesObjNum = $currentObjNum++;
        $objects[$resourcesObjNum] = "$resourcesObjNum 0 obj\n" .
            "<< /XObject << /Im$index $xobjNum 0 R >> >>\nendobj";
        
        // Create page object
        $objects[$pageObjNum] = "$pageObjNum 0 obj\n" .
            "<< /Type /Page /Parent 2 0 R " .
            "/MediaBox [0 0 $pageWidth $pageHeight] " .
            "/Resources $resourcesObjNum 0 R " .
            "/Contents $contentObjNum 0 R >>\nendobj";
    }
    
    // Create Pages object
    $objects[$pagesObjNum] = "2 0 obj\n" .
        "<< /Type /Pages /Kids [" . implode(' ', $pages) . "] " .
        "/Count " . count($pages) . " >>\nendobj";
    
    // Write all objects
    $xrefPositions = [];
    $currentPos = strlen($pdf);
    
    foreach ($objects as $objNum => $objContent) {
        $xrefPositions[$objNum] = $currentPos;
        $pdf .= $objContent . "\n";
        $currentPos = strlen($pdf);
    }
    
    // Write xref table
    $xrefOffset = $currentPos;
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    
    for ($i = 1; $i <= count($objects); $i++) {
        if (isset($xrefPositions[$i])) {
            $pdf .= sprintf("%010d 00000 n \n", $xrefPositions[$i]);
        }
    }
    
    // Write trailer
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n$xrefOffset\n%%EOF";
    
    return $pdf;
}

// Include footer
require_once '../includes/footer.php';
?>