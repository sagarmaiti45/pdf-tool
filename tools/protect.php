<?php
require_once '../includes/functions.php';

// Page variables for header
$page_title = 'Protect PDF - Add Password Protection';
$page_description = 'Password protect your PDF files online. Add security and permissions to prevent unauthorized access, copying, or editing.';
$page_keywords = 'protect PDF, password protect PDF, secure PDF, PDF encryption, PDF security, lock PDF';

// Additional head content
$additional_head = '<style>
        .password-group {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 2.5rem;
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 1.25rem;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
        
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .permission-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: var(--bg-light);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .permission-item:hover {
            background: var(--primary-light);
        }
        
        .permission-item input[type="checkbox"] {
            margin-right: 0.75rem;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .permission-item label {
            cursor: pointer;
            flex: 1;
        }
        
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
        }
        
        .strength-weak { background: #f44336; width: 33%; }
        .strength-medium { background: #FF9800; width: 66%; }
        .strength-strong { background: #4CAF50; width: 100%; }
    </style>';

// JavaScript to be included
$additional_scripts = '<script>
        const uploadArea = document.getElementById(\'uploadArea\');
        const fileInput = document.getElementById(\'pdfFile\');
        const fileInfo = document.getElementById(\'fileInfo\');
        const fileName = document.getElementById(\'fileName\');
        const fileSize = document.getElementById(\'fileSize\');
        const removeFile = document.getElementById(\'removeFile\');
        const protectBtn = document.getElementById(\'protectBtn\');
        const protectForm = document.getElementById(\'protectForm\');
        const loader = document.getElementById(\'loader\');
        const protectionSettings = document.getElementById(\'protectionSettings\');
        const password = document.getElementById(\'password\');
        const confirmPassword = document.getElementById(\'confirmPassword\');
        const strengthBar = document.getElementById(\'strengthBar\');
        const strengthText = document.getElementById(\'strengthText\');

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
            protectionSettings.style.display = \'none\';
            protectBtn.disabled = true;
        });

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.style.display = \'block\';
                uploadArea.style.display = \'none\';
                protectionSettings.style.display = \'block\';
                validateForm();
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

        // Password strength checker
        password.addEventListener(\'input\', function() {
            const value = this.value;
            let strength = 0;
            
            if (value.length >= 6) strength++;
            if (value.length >= 10) strength++;
            if (/[a-z]/.test(value) && /[A-Z]/.test(value)) strength++;
            if (/[0-9]/.test(value)) strength++;
            if (/[^a-zA-Z0-9]/.test(value)) strength++;
            
            strengthBar.className = \'password-strength-bar\';
            
            if (strength <= 2) {
                strengthBar.classList.add(\'strength-weak\');
                strengthText.textContent = \'Weak password\';
                strengthText.style.color = \'#f44336\';
            } else if (strength <= 3) {
                strengthBar.classList.add(\'strength-medium\');
                strengthText.textContent = \'Medium strength\';
                strengthText.style.color = \'#FF9800\';
            } else {
                strengthBar.classList.add(\'strength-strong\');
                strengthText.textContent = \'Strong password\';
                strengthText.style.color = \'#4CAF50\';
            }
            
            validateForm();
        });

        confirmPassword.addEventListener(\'input\', validateForm);

        function validateForm() {
            const hasFile = fileInput.files.length > 0;
            const hasPassword = password.value.length >= 6;
            const passwordsMatch = password.value === confirmPassword.value;
            
            protectBtn.disabled = !(hasFile && hasPassword && passwordsMatch);
            
            if (confirmPassword.value && !passwordsMatch) {
                confirmPassword.style.borderColor = \'#f44336\';
            } else if (confirmPassword.value && passwordsMatch) {
                confirmPassword.style.borderColor = \'#4CAF50\';
            } else {
                confirmPassword.style.borderColor = \'\';
            }
        }

        // Password visibility toggle
        document.getElementById(\'togglePassword\').addEventListener(\'click\', function() {
            const type = password.type === \'password\' ? \'text\' : \'password\';
            password.type = type;
            this.querySelector(\'i\').className = type === \'password\' ? \'fas fa-eye\' : \'fas fa-eye-slash\';
        });

        document.getElementById(\'toggleConfirmPassword\').addEventListener(\'click\', function() {
            const type = confirmPassword.type === \'password\' ? \'text\' : \'password\';
            confirmPassword.type = type;
            this.querySelector(\'i\').className = type === \'password\' ? \'fas fa-eye\' : \'fas fa-eye-slash\';
        });

        // Permission checkboxes
        document.querySelectorAll(\'.permission-item\').forEach(item => {
            item.addEventListener(\'click\', function(e) {
                if (e.target.tagName !== \'INPUT\') {
                    const checkbox = this.querySelector(\'input[type="checkbox"]\');
                    checkbox.checked = !checkbox.checked;
                }
            });
        });

        protectForm.addEventListener(\'submit\', (e) => {
            protectBtn.disabled = true;
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
        
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($password)) {
            throw new RuntimeException('Please enter a password.');
        }
        
        if ($password !== $confirmPassword) {
            throw new RuntimeException('Passwords do not match.');
        }
        
        if (strlen($password) < 6) {
            throw new RuntimeException('Password must be at least 6 characters long.');
        }
        
        $permissions = $_POST['permissions'] ?? [];
        $outputFile = TEMP_DIR . generateUniqueFileName('pdf');
        
        // Check if qpdf is available (best option for password protection)
        exec('which qpdf 2>&1', $qpdfCheck, $qpdfReturn);
        
        if ($qpdfReturn === 0) {
            // Build qpdf command with permissions
            $restrictOptions = [];
            
            // By default, restrict everything
            $allowPrint = in_array('print', $permissions) ? 'y' : 'n';
            $allowModify = in_array('modify', $permissions) ? 'y' : 'n';
            $allowCopy = in_array('copy', $permissions) ? 'y' : 'n';
            $allowAnnotate = in_array('annotate', $permissions) ? 'y' : 'n';
            
            $command = sprintf(
                'qpdf --encrypt %s %s 256 --print=%s --modify=%s --extract=%s --annotate=%s -- %s %s 2>&1',
                escapeshellarg($password),
                escapeshellarg($password),
                $allowPrint,
                $allowModify,
                $allowCopy,
                $allowAnnotate,
                escapeshellarg($uploadedFile),
                escapeshellarg($outputFile)
            );
        } else {
            // Fall back to Ghostscript (less secure but works)
            $gsPath = '/opt/homebrew/bin/gs';
            if (!file_exists($gsPath)) {
                $gsPath = 'gs';
            }
            
            $gsTempDir = TEMP_DIR;
            putenv("TMPDIR=$gsTempDir");
            
            // Note: Ghostscript password protection is limited
            $command = sprintf(
                'TMPDIR=%s %s -sDEVICE=pdfwrite -sOwnerPassword=%s -sUserPassword=%s -dEncryptionR=3 -dKeyLength=128 -dNOPAUSE -dBATCH -sOutputFile=%s %s 2>&1',
                escapeshellarg($gsTempDir),
                $gsPath,
                escapeshellarg($password),
                escapeshellarg($password),
                escapeshellarg($outputFile),
                escapeshellarg($uploadedFile)
            );
        }
        
        exec($command, $output, $returnCode);
        
        unlink($uploadedFile);
        
        if ($returnCode !== 0 || !file_exists($outputFile)) {
            throw new RuntimeException('Failed to protect PDF. ' . implode(' ', $output));
        }
        
        $_SESSION['download_file'] = $outputFile;
        $_SESSION['download_name'] = $originalName . '_protected.pdf';
        
        $success = 'PDF protected successfully! Your file is now password-protected.';
        
        $downloadLink = 'download.php?file=' . basename($outputFile);
        
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        logError('Protect PDF Error', ['error' => $e->getMessage()]);
        
        if (isset($uploadedFile) && file_exists($uploadedFile)) {
            unlink($uploadedFile);
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
                <h1><i class="fas fa-lock"></i> Protect PDF</h1>
                <p>Add password protection and permissions to your PDF</p>
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
                    <div class="alert alert-info" style="margin-top: 1rem;">
                        <i class="fas fa-key"></i> <strong>Important:</strong> Remember your password! You'll need it to open the protected PDF.
                    </div>
                    <div style="text-align: center; margin: 2rem 0;">
                        <a href="<?php echo htmlspecialchars($downloadLink); ?>" class="btn btn-primary">
                            <i class="fas fa-download"></i> Download Protected PDF
                        </a>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="protectForm">
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

                    <div id="protectionSettings" style="display: none;">
                        <h3>Set Password:</h3>
                        
                        <div class="form-group password-group">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" id="password" class="form-control" 
                                   placeholder="Enter a strong password" required>
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="strengthBar"></div>
                            </div>
                            <small id="strengthText" style="color: #666;"></small>
                        </div>
                        
                        <div class="form-group password-group">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" id="confirmPassword" 
                                   class="form-control" placeholder="Re-enter password" required>
                            <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>

                        <h3>Set Permissions:</h3>
                        <p style="color: #666; margin-bottom: 1rem;">Choose what users can do with the password-protected PDF:</p>
                        
                        <div class="permissions-grid">
                            <div class="permission-item">
                                <input type="checkbox" name="permissions[]" value="print" id="allowPrint" checked>
                                <label for="allowPrint">
                                    <strong>Allow Printing</strong><br>
                                    <small>Users can print the document</small>
                                </label>
                            </div>
                            
                            <div class="permission-item">
                                <input type="checkbox" name="permissions[]" value="copy" id="allowCopy">
                                <label for="allowCopy">
                                    <strong>Allow Copying</strong><br>
                                    <small>Users can copy text and images</small>
                                </label>
                            </div>
                            
                            <div class="permission-item">
                                <input type="checkbox" name="permissions[]" value="modify" id="allowModify">
                                <label for="allowModify">
                                    <strong>Allow Modification</strong><br>
                                    <small>Users can edit the document</small>
                                </label>
                            </div>
                            
                            <div class="permission-item">
                                <input type="checkbox" name="permissions[]" value="annotate" id="allowAnnotate">
                                <label for="allowAnnotate">
                                    <strong>Allow Annotations</strong><br>
                                    <small>Users can add comments and notes</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="loader" id="loader"></div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" id="protectBtn" disabled>
                            <i class="fas fa-lock"></i> Protect PDF
                        </button>
                    </div>
                </form>

                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e0e0e0;">
                    <h3>How it works:</h3>
                    <ol style="line-height: 2;">
                        <li>Upload your PDF file</li>
                        <li>Set a strong password</li>
                        <li>Choose permissions for the protected PDF</li>
                        <li>Click "Protect PDF" to secure your document</li>
                        <li>Download your password-protected PDF</li>
                    </ol>
                    
                    <p style="margin-top: 1rem; color: #757575;">
                        <i class="fas fa-shield-alt"></i> Your files and passwords are never stored. All processing happens securely and files are deleted immediately.
                    </p>
                </div>
            </div>
        </div>
    </div>

<?php
// Include footer
require_once '../includes/footer.php';
?>