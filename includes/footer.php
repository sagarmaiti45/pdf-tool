    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-main">
                    <div class="footer-brand">
                        <i class="fas fa-file-pdf"></i>
                        <span><?php echo SITE_NAME; ?></span>
                    </div>
                    <p class="footer-tagline">Fast, secure, and free PDF tools for everyone</p>
                </div>
                <nav class="footer-nav">
                    <a href="<?php echo $is_tool_page ? '../' : ''; ?>privacy.php">Privacy</a>
                    <a href="<?php echo $is_tool_page ? '../' : ''; ?>terms.php">Terms</a>
                    <a href="<?php echo $is_tool_page ? '../' : ''; ?>contact.php">Contact</a>
                </nav>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                <p>A <a href="https://freshyportal.com" target="_blank" rel="noopener">FreshyPortal</a> Product</p>
            </div>
        </div>
    </footer>

    <script src="<?php echo $is_tool_page ? '../' : ''; ?>assets/js/main.js"></script>
    <?php if (isset($additional_scripts)) echo $additional_scripts; ?>
</body>
</html>