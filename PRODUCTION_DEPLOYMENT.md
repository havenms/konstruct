# Production Deployment Guide

## Pre-Deployment Checklist

### 1. Security Hardening

#### Remove Debug Tools

```bash
# Remove entire debug directory
rm -rf /path/to/konstruct/debug/

# Or rename to prevent access
mv debug debug-disabled
```

#### Disable Debug Mode

```php
// wp-config.php - CRITICAL for production
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);
```

#### File Permissions

```bash
# Set proper file permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Protect sensitive files
chmod 600 wp-config.php
```

### 2. Performance Optimization

#### Enable Caching

- **LiteSpeed Cache**: Forms automatically bypass caching ✅
- **WP Rocket**: Compatible with form functionality ✅
- **W3 Total Cache**: Page caching safe for forms ✅

#### Asset Optimization

- CSS/JS files include automatic cache busting
- No manual intervention required for updates

### 3. Server Configuration

#### Apache (.htaccess)

Plugin automatically creates protected directories with proper .htaccess rules:

```apache
# /wp-content/uploads/form_data/.htaccess
# Deny direct access to uploaded form files
Deny from all
```

#### Nginx

Add to server configuration:

```nginx
# Block direct access to form uploads
location ~* /wp-content/uploads/form_data/ {
    deny all;
    return 403;
}

# Allow WordPress to handle protected downloads
location ~ /wp-json/form-builder/v1/file {
    try_files $uri $uri/ /index.php?$args;
}
```

#### LiteSpeed

```apache
# In .htaccess at site root
<IfModule Litespeed>
    # Exclude form pages from cache
    RewriteCond %{THE_REQUEST} form_builder
    RewriteRule .* - [E=Cache-Control:no-cache]
</IfModule>
```

## Deployment Process

### Step 1: Backup Current Installation

```bash
# Create full backup
tar -czf konstruct-backup-$(date +%Y%m%d).tar.gz konstruct/

# Database backup
mysqldump -u user -p database > konstruct-db-backup-$(date +%Y%m%d).sql
```

### Step 2: Upload Refactored Plugin

```bash
# Upload new plugin files (excluding debug directory)
rsync -avz --exclude='debug/' konstruct/ /path/to/wp-content/plugins/konstruct/
```

### Step 3: Verify Installation

1. **Plugin Activation**: Ensure plugin activates without errors
2. **Form Rendering**: Test existing forms display correctly
3. **Submissions**: Test form submission process
4. **File Uploads**: Verify file upload functionality (if used)
5. **Email Notifications**: Test email delivery
6. **Admin Interface**: Check all admin pages load

### Step 4: Performance Testing

```bash
# Test form page loading speed
curl -w "@curl-format.txt" -o /dev/null -s "https://yoursite.com/form-page/"

# Where curl-format.txt contains:
#     time_namelookup:  %{time_namelookup}\n
#        time_connect:  %{time_connect}\n
#     time_appconnect:  %{time_appconnect}\n
#    time_pretransfer:  %{time_pretransfer}\n
#       time_redirect:  %{time_redirect}\n
#  time_starttransfer:  %{time_starttransfer}\n
#                     ----------\n
#          time_total:  %{time_total}\n
```

## Security Validation

### 1. File Access Testing

```bash
# These should return 403 Forbidden
curl -I https://yoursite.com/wp-content/plugins/konstruct/debug/
curl -I https://yoursite.com/wp-content/uploads/form_data/test-file.pdf
```

### 2. Admin Access Testing

- Verify only administrators can access form management
- Test file download URLs require admin authentication
- Confirm REST API endpoints have proper permission checks

### 3. Imunify360 Compatibility

After deployment, check Imunify360 logs for any flags:

```bash
# Check Imunify360 logs (path may vary)
tail -f /var/log/imunify360/console.log
grep -i "konstruct\|form-builder" /var/log/imunify360/console.log
```

## Monitoring Setup

### 1. Error Monitoring

```php
// Add to wp-config.php for production error logging
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/logs/php_errors.log');
```

### 2. Performance Monitoring

- Monitor form submission completion rates
- Track email delivery success rates
- Watch for file upload errors

### 3. Security Monitoring

- Monitor failed login attempts on admin endpoints
- Watch for unusual file access patterns
- Set up alerts for security plugin flags

## Rollback Plan

If issues occur after deployment:

### Quick Rollback

```bash
# Restore from backup
tar -xzf konstruct-backup-YYYYMMDD.tar.gz -C /path/to/wp-content/plugins/

# Restore database if needed
mysql -u user -p database < konstruct-db-backup-YYYYMMDD.sql
```

### Partial Rollback

If only specific functionality is affected:

1. Keep new main plugin structure
2. Restore specific class files from backup
3. Debug individual components

## Post-Deployment Checklist

- [ ] Debug tools removed/disabled
- [ ] WP_DEBUG disabled
- [ ] File permissions correct
- [ ] Caching properly configured
- [ ] Forms render correctly
- [ ] Submissions work properly
- [ ] File uploads functional (if used)
- [ ] Email notifications sending
- [ ] Admin interface accessible
- [ ] No security plugin alerts
- [ ] Performance benchmarks met
- [ ] Backup system tested

## Maintenance Schedule

### Weekly

- Check error logs for any issues
- Verify email delivery rates
- Monitor form submission success

### Monthly

- Review file storage usage
- Check for WordPress/PHP updates
- Validate security configurations

### Quarterly

- Full security audit
- Performance optimization review
- Backup restoration testing

---

**Deployment Checklist Version**: 1.0
**Compatible Plugin Version**: 1.2.0+
**Last Updated**: February 2026
