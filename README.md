# PDF Tools Pro

A professional, production-ready web application for PDF manipulation with a modern, mobile-responsive interface.

## Features

- **PDF Compression**: Reduce PDF file sizes with three compression levels
- **PDF Merging**: Combine multiple PDFs with page size normalization options
- **PDF Rotation**: Rotate PDF pages in any direction
- **JPG to PDF**: Convert JPEG/PNG images to PDF format
- **PDF to JPG**: Extract PDF pages as high-quality images
- **PDF Unlock**: Remove password protection from PDFs
- **PDF Protect**: Add password protection to PDFs

## Production Deployment Guide

### System Requirements

- PHP 7.4 or higher
- Apache web server with mod_rewrite enabled
- Ghostscript 9.x or higher
- ImageMagick 7.x or higher
- 2GB RAM minimum (4GB recommended)
- 10GB disk space for file processing

### Installation Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/pdf-tools-pro.git
   cd pdf-tools-pro
   ```

2. **Install dependencies**
   ```bash
   # Install Ghostscript
   # Ubuntu/Debian:
   sudo apt-get install ghostscript
   
   # macOS:
   brew install ghostscript
   
   # Install ImageMagick
   # Ubuntu/Debian:
   sudo apt-get install imagemagick
   
   # macOS:
   brew install imagemagick
   ```

3. **Configure permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 temp/
   chmod 755 logs/
   ```

4. **Update configuration**
   - Edit `includes/config.php`
   - Update site URL, email addresses
   - Configure Ghostscript and ImageMagick paths
   - Set environment to 'production'

5. **Configure Apache**
   - Ensure mod_rewrite is enabled
   - Point document root to the project directory
   - Ensure .htaccess files are processed

6. **Set up HTTPS**
   - Install SSL certificate
   - Update .htaccess to enable HSTS
   - Update config.php with HTTPS URLs

7. **Configure email (optional)**
   - Update SMTP settings in config.php
   - Or configure server's sendmail

### Security Checklist

- [ ] Change default admin email in config.php
- [ ] Enable HTTPS and uncomment HSTS in .htaccess
- [ ] Set strong permissions on directories
- [ ] Enable PHP error logging (disable display_errors)
- [ ] Configure firewall rules
- [ ] Set up regular backups
- [ ] Monitor disk space for temp files
- [ ] Configure rate limiting
- [ ] Set up monitoring/alerts

### Performance Optimization

1. **Enable caching**
   - Browser caching is configured in .htaccess
   - Consider adding Redis/Memcached for session storage

2. **Configure PHP**
   ```ini
   upload_max_filesize = 50M
   post_max_size = 50M
   max_execution_time = 300
   memory_limit = 256M
   ```

3. **Set up cron jobs**
   ```bash
   # Clean up old files every hour
   0 * * * * find /path/to/pdf/uploads -type f -mmin +60 -delete
   0 * * * * find /path/to/pdf/temp -type f -mmin +60 -delete
   ```

### Monitoring

1. **Log files**
   - PHP errors: `logs/php_errors.log`
   - Application logs: `logs/error.log`
   - Contact submissions: `logs/contact_submissions.log`

2. **Health checks**
   - Monitor disk space
   - Check Ghostscript/ImageMagick availability
   - Monitor response times

### Maintenance

1. **Regular tasks**
   - Clear old temporary files
   - Review error logs
   - Update dependencies
   - Check disk usage

2. **Backup strategy**
   - Daily backups of configuration
   - Weekly full backups
   - Store backups off-site

### Troubleshooting

**Common issues:**

1. **"Failed to move uploaded file"**
   - Check directory permissions
   - Verify PHP upload settings
   - Check disk space

2. **"Ghostscript not found"**
   - Update GS_PATH in config.php
   - Verify Ghostscript installation
   - Check PATH environment variable

3. **Large files timeout**
   - Increase max_execution_time
   - Adjust memory_limit
   - Consider chunked processing

### API Endpoints

The application uses standard HTTP POST for file uploads:
- `/tools/compress.php` - PDF compression
- `/tools/merge.php` - PDF merging
- `/tools/rotate.php` - PDF rotation
- `/tools/jpg-to-pdf.php` - Image to PDF
- `/tools/pdf-to-jpg.php` - PDF to images
- `/tools/unlock.php` - Remove PDF password
- `/tools/protect.php` - Add PDF password

### License

This project is licensed under the MIT License.

### Support

For issues or questions:
- Email: support@pdftoolspro.com
- Documentation: https://pdftoolspro.com/docs
- Issues: https://github.com/yourusername/pdf-tools-pro/issues

---

## Development

### Local Development Setup

1. Install XAMPP/MAMP/WAMP
2. Clone repository to htdocs
3. Set ENVIRONMENT to 'development' in config.php
4. Access via http://localhost/pdf

### Code Structure

```
pdf/
├── assets/           # CSS, JS, images
├── error/           # Error pages
├── includes/        # PHP includes and functions
├── logs/           # Application logs
├── temp/           # Temporary file storage
├── tools/          # Individual tool pages
├── uploads/        # User uploaded files
├── contact.php     # Contact form
├── index.php       # Homepage
├── privacy.php     # Privacy policy
├── terms.php       # Terms of service
└── .htaccess       # Apache configuration
```

### Contributing

1. Fork the repository
2. Create feature branch
3. Commit changes
4. Push to branch
5. Create Pull Request

### Testing

- Test all file upload scenarios
- Verify file size limits
- Test error handling
- Check mobile responsiveness
- Validate security headers