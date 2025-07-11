# Railway Environment Variables Setup

After deploying to Railway, you need to set the following environment variables for email functionality:

## Required Environment Variables

Go to your Railway project settings and add these variables:

1. **SMTP_PASSWORD** (REQUIRED)
   - Value: `Freshy@721151`
   - Description: Your email password for authentication

## Optional Environment Variables

These have defaults but can be customized:

2. **SMTP_HOST**
   - Default: `smtp.hostinger.com`
   - Description: SMTP server hostname

3. **SMTP_PORT**
   - Default: `587`
   - Description: SMTP server port (587 for TLS, 465 for SSL)

4. **SMTP_SECURE**
   - Default: `tls`
   - Options: `tls` or `ssl`

5. **SMTP_USERNAME**
   - Default: `info@freshyportal.com`
   - Description: SMTP username (usually your email)

6. **FROM_EMAIL**
   - Default: `info@freshyportal.com`
   - Description: Email address that sends the messages

7. **FROM_NAME**
   - Default: `Triniva PDF Tools`
   - Description: Name shown as sender

8. **RECIPIENT_EMAILS**
   - Default: `sagarmaiti488@gmail.com,info@triniva.com`
   - Description: Comma-separated list of recipient emails

## How to Set Environment Variables in Railway

1. Go to your Railway project dashboard
2. Click on your service
3. Go to the "Variables" tab
4. Click "Add Variable"
5. Add each variable with its name and value
6. Railway will automatically restart your service

## Testing

After setting the variables, test the contact form to ensure emails are being sent correctly.