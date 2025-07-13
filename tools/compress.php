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
        
        $outputFile = TEMP_DIR . generateUniqueFileName('pdf');
        
        // Read PDF content
        $pdfContent = file_get_contents($uploadedFile);
        $originalSize = strlen($pdfContent);
        
        // Parse PDF structure
        $compressedContent = compressPDF($pdfContent, $compressionLevel);
        
        // Write compressed content
        file_put_contents($outputFile, $compressedContent);
        
        $finalSize = filesize($outputFile);
        
        // If compression didn't work well, use original
        if ($finalSize >= $originalSize * 0.98) {
            copy($uploadedFile, $outputFile);
            $finalSize = $originalSize;
            $reduction = 0;
            
            $success = sprintf(
                'PDF is already optimized. Original size maintained at %s.',
                formatFileSize($originalSize)
            );
        } else {
            $reduction = round((1 - $finalSize / $originalSize) * 100, 2);
            
            $success = sprintf(
                'PDF compressed successfully! Size reduced by %s%% (from %s to %s)',
                $reduction,
                formatFileSize($originalSize),
                formatFileSize($finalSize)
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

function compressPDF($content, $level = 'medium') {
    // Enhanced PDF compression with better parsing
    $objects = [];
    $xref = [];
    
    // Extract PDF version
    preg_match('/^%PDF-(\d+\.\d+)/', $content, $versionMatch);
    $pdfVersion = $versionMatch[1] ?? '1.4';
    
    // Find all objects with improved regex
    preg_match_all('/(\d+)\s+(\d+)\s+obj(.*?)endobj/s', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    
    foreach ($matches as $match) {
        $objNum = $match[1][0];
        $objGen = $match[2][0];
        $objContent = $match[3][0];
        
        // Process different object types
        if (strpos($objContent, 'stream') !== false) {
            // Handle stream objects with enhanced compression
            $objContent = compressStreamObject($objContent, $level);
        } elseif (strpos($objContent, '/Type /Font') !== false || strpos($objContent, '/Type/Font') !== false) {
            // Skip font objects to avoid corruption
            // Keep as is
        } elseif (strpos($objContent, '/Type /XObject') !== false || strpos($objContent, '/Type/XObject') !== false) {
            // Handle image objects
            $objContent = compressImageObject($objContent, $level);
        } else {
            // Compress content strings
            $objContent = compressContentStrings($objContent);
        }
        
        $objects[$objNum] = [
            'num' => $objNum,
            'gen' => $objGen,
            'content' => $objContent
        ];
    }
    
    // Extract linearization info if present
    $linearized = false;
    if (preg_match('/\/Linearized\s+1/', $content)) {
        $linearized = true;
    }
    
    // Rebuild PDF with optimization
    $output = "%PDF-$pdfVersion\n";
    if (!$linearized) {
        // Add binary comment for better compatibility
        $output .= "%âÉåÒ\n";
    }
    
    $offset = strlen($output);
    $newXref = [];
    
    // Write objects in optimized order
    $sortedObjects = $objects;
    ksort($sortedObjects, SORT_NUMERIC);
    
    foreach ($sortedObjects as $obj) {
        $newXref[$obj['num']] = $offset;
        $objStr = $obj['num'] . ' ' . $obj['gen'] . " obj" . $obj['content'] . "endobj\n";
        $output .= $objStr;
        $offset += strlen($objStr);
    }
    
    // Write xref table
    $xrefOffset = $offset;
    $output .= "xref\n";
    $output .= "0 " . (count($newXref) + 1) . "\n";
    $output .= "0000000000 65535 f \n";
    
    foreach ($newXref as $num => $pos) {
        $output .= sprintf("%010d 00000 n \n", $pos);
    }
    
    // Write trailer with proper references
    $output .= "trailer\n";
    $output .= "<<\n";
    $output .= "/Size " . (count($newXref) + 1) . "\n";
    
    // Extract and preserve important references
    if (preg_match('/\/Root\s+(\d+)\s+\d+\s+R/', $content, $rootMatch)) {
        $output .= "/Root " . $rootMatch[1] . " 0 R\n";
    }
    if (preg_match('/\/Info\s+(\d+)\s+\d+\s+R/', $content, $infoMatch)) {
        $output .= "/Info " . $infoMatch[1] . " 0 R\n";
    }
    if (preg_match('/\/ID\s*\[(.*?)\]/', $content, $idMatch)) {
        $output .= "/ID [" . $idMatch[1] . "]\n";
    }
    
    $output .= ">>\n";
    $output .= "startxref\n";
    $output .= $xrefOffset . "\n";
    $output .= "%%EOF\n";
    
    return $output;
}

function compressStreamObject($objContent, $level) {
    // Extract stream content and dictionary
    if (!preg_match('/<<(.*?)>>\s*stream\s*\n(.*?)\nendstream/s', $objContent, $matches)) {
        return $objContent;
    }
    
    $dict = $matches[1];
    $streamData = $matches[2];
    
    // Parse existing filters
    $filters = [];
    if (preg_match('/\/Filter\s*\[(.*?)\]/', $dict, $filterMatch)) {
        // Array of filters
        preg_match_all('/\/(\w+)/', $filterMatch[1], $filterNames);
        $filters = $filterNames[1];
    } elseif (preg_match('/\/Filter\s*\/(\w+)/', $dict, $filterMatch)) {
        // Single filter
        $filters = [$filterMatch[1]];
    }
    
    // Decompress if needed
    $decompressed = $streamData;
    $wasCompressed = false;
    
    if (in_array('FlateDecode', $filters)) {
        $temp = @gzuncompress($streamData);
        if ($temp !== false) {
            $decompressed = $temp;
            $wasCompressed = true;
        }
    }
    
    // Set compression level based on setting
    $compressionLevel = match($level) {
        'low' => 1,      // Maximum compression (slowest)
        'medium' => 6,   // Balanced
        'high' => 9,     // Minimum compression (fastest, preserves quality)
        default => 6
    };
    
    // Recompress data
    $compressed = gzcompress($decompressed, $compressionLevel);
    
    // Only use compressed version if it's actually smaller
    if (!$wasCompressed || strlen($compressed) < strlen($streamData) * 0.95) {
        $streamData = $compressed;
        
        // Update dictionary
        if (!in_array('FlateDecode', $filters)) {
            // Add FlateDecode filter
            if (empty($filters)) {
                $dict = preg_replace('/\s*$/', "\n/Filter /FlateDecode", $dict);
            } else {
                // Add to existing filters
                $dict = preg_replace('/\/Filter\s*\/\w+/', '/Filter [/$1 /FlateDecode]', $dict);
            }
        }
    }
    
    // Update length
    $newLength = strlen($streamData);
    $dict = preg_replace('/\/Length\s+\d+/', "/Length $newLength", $dict);
    
    // If no length field exists, add it
    if (!preg_match('/\/Length/', $dict)) {
        $dict = preg_replace('/\s*$/', "\n/Length $newLength", $dict);
    }
    
    // Rebuild object content
    $objContent = "\n<<$dict>>\nstream\n$streamData\nendstream";
    
    return $objContent;
}

function compressImageObject($objContent, $level) {
    // Check if this is an image XObject
    if (!preg_match('/\/Subtype\s*\/Image/', $objContent)) {
        return $objContent;
    }
    
    // For images, we can try to optimize the compression
    // but we need to be careful not to corrupt the image data
    
    // Check current image format
    if (preg_match('/\/Filter\s*\/DCTDecode/', $objContent)) {
        // JPEG image - already compressed, don't recompress
        return $objContent;
    }
    
    // For other image types, use the general stream compression
    return compressStreamObject($objContent, $level);
}

function compressContentStrings($objContent) {
    // Remove unnecessary whitespace from content strings
    // But be careful not to break the PDF structure
    
    // Remove extra spaces in dictionaries
    $objContent = preg_replace('/\s+/', ' ', $objContent);
    
    // Remove trailing spaces
    $objContent = preg_replace('/ +\n/', "\n", $objContent);
    
    // Compress number sequences
    $objContent = preg_replace('/(\d) +(\d)/', '$1 $2', $objContent);
    
    return $objContent;
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
                                <i class="fas fa-info-circle"></i> Using PHP-based compression. Results depend on PDF content.
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