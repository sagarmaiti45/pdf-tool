<?php
require_once '../includes/functions.php';

// Page variables for header
$page_title = 'Compress PDF - Reduce File Size';
$page_description = 'Compress and reduce the size of your PDF files online without losing quality. Free PDF compression tool with multiple compression levels.';
$page_keywords = 'compress PDF, reduce PDF size, PDF compressor, optimize PDF, shrink PDF, PDF compression online';

// JavaScript to be included
$additional_scripts = '<script>
        const uploadArea = document.getElementById(\'uploadArea\');
        const fileInput = document.getElementById(\'pdfFile\');
        const fileInfo = document.getElementById(\'fileInfo\');
        const fileName = document.getElementById(\'fileName\');
        const fileSize = document.getElementById(\'fileSize\');
        const removeFile = document.getElementById(\'removeFile\');
        const compressBtn = document.getElementById(\'compressBtn\');
        const compressForm = document.getElementById(\'compressForm\');
        const progressContainer = document.getElementById(\'progressContainer\');
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
            compressBtn.disabled = true;
        });

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.style.display = \'block\';
                uploadArea.style.display = \'none\';
                compressBtn.disabled = false;
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

        compressForm.addEventListener(\'submit\', (e) => {
            compressBtn.disabled = true;
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
        
        $compressionLevel = $_POST['compression_level'] ?? 'medium';
        
        // Try to check if FPDI is available
        $fpdiAvailable = false;
        if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
            require_once dirname(__DIR__) . '/vendor/autoload.php';
            $fpdiAvailable = class_exists('setasign\Fpdi\Tcpdf\Fpdi');
        }
        
        $outputFile = TEMP_DIR . generateUniqueFileName('pdf');
        
        if ($fpdiAvailable) {
            // Use FPDI for compression
            try {
                $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
                $pageCount = $pdf->setSourceFile($uploadedFile);
                
                // Set compression based on level
                $pdf->SetCompression(true);
                
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $pageId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($pageId);
                    
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($pageId);
                }
                
                // Output with compression
                $pdf->Output($outputFile, 'F');
                
                $compressSuccess = true;
            } catch (Exception $e) {
                $compressSuccess = false;
            }
        } else {
            $compressSuccess = false;
        }
        
        // If FPDI fails or is not available, try basic PHP compression
        if (!$compressSuccess) {
            // Read PDF content
            $pdfContent = file_get_contents($uploadedFile);
            
            // Basic PDF optimization - remove comments and compress streams
            $optimizedContent = $pdfContent;
            
            // Remove PDF comments
            $optimizedContent = preg_replace('/%%[^\r\n]*[\r\n]/', '', $optimizedContent);
            
            // Remove excessive whitespace
            $optimizedContent = preg_replace('/\s+/', ' ', $optimizedContent);
            
            // Try to compress using gzcompress if available
            if (function_exists('gzcompress')) {
                // Find and compress stream objects
                $pattern = '/stream\s*\n(.*?)\nendstream/s';
                $optimizedContent = preg_replace_callback($pattern, function($matches) use ($compressionLevel) {
                    $streamContent = $matches[1];
                    $level = match($compressionLevel) {
                        'low' => 9,
                        'medium' => 6,
                        'high' => 3,
                        default => 6
                    };
                    $compressed = gzcompress($streamContent, $level);
                    return "stream\n" . $compressed . "\nendstream";
                }, $optimizedContent);
            }
            
            file_put_contents($outputFile, $optimizedContent);
        }
        
        $originalSize = $_FILES['pdf_file']['size'];
        $finalCompressedSize = filesize($outputFile);
        
        // If compressed file is larger or not much smaller, use original
        if ($finalCompressedSize >= $originalSize * 0.95) {
            copy($uploadedFile, $outputFile);
            $finalCompressedSize = $originalSize;
            $reduction = 0;
            
            $success = sprintf(
                'PDF is already optimized. Original size maintained at %s. Limited compression available without Ghostscript.',
                formatFileSize($originalSize)
            );
        } else {
            $reduction = round((1 - $finalCompressedSize / $originalSize) * 100, 2);
            
            $success = sprintf(
                'PDF compressed successfully! Size reduced by %s%% (from %s to %s)',
                $reduction,
                formatFileSize($originalSize),
                formatFileSize($finalCompressedSize)
            );
        }
        
        unlink($uploadedFile);
        
        $_SESSION['download_file'] = $outputFile;
        $_SESSION['download_name'] = $originalName . '_compressed.pdf';
        
        $downloadLink = 'download.php?file=' . basename($outputFile);
        
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        logError('Compress PDF Error', ['error' => $e->getMessage()]);
    }
}

$csrfToken = generateCSRFToken();

// Include header
require_once '../includes/header.php';
?>

    <div class="tool-page">
        <div class="container">
            <div class="tool-header">
                <h1><i class="fas fa-compress"></i> Compress PDF</h1>
                <p>Reduce your PDF file size without compromising quality</p>
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
                            <i class="fas fa-download"></i> Download Compressed PDF
                        </a>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="compressForm">
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
                        
                        <div class="form-group" style="margin-top: 2rem;">
                            <label class="form-label">Compression Level</label>
                            <select name="compression_level" class="form-control">
                                <option value="low">Maximum Compression</option>
                                <option value="medium" selected>Balanced</option>
                                <option value="high">High Quality</option>
                            </select>
                            <small style="color: #666; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Basic compression without external tools. Results may vary.
                            </small>
                        </div>
                    </div>

                    <div class="progress-container" id="progressContainer" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-bar-fill" id="progressBar"></div>
                        </div>
                        <div class="progress-text">Compressing PDF...</div>
                    </div>

                    <div class="loader" id="loader"></div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" id="compressBtn" disabled>
                            <i class="fas fa-compress"></i> Compress PDF
                        </button>
                    </div>
                </form>

                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e0e0e0;">
                    <h3>How it works:</h3>
                    <ol style="line-height: 2;">
                        <li>Upload your PDF file using the upload area above</li>
                        <li>Select your desired compression level</li>
                        <li>Click "Compress PDF" to start the process</li>
                        <li>Download your compressed PDF file</li>
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