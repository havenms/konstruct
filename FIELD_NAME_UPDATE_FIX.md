# Field Name Update Fix

## Issue
Field names were not updating after the form was created. When a user changed a field name in the form builder and saved, the change would not be reflected on the frontend or in webhook submissions.

## Root Cause
The issue was caused by multiple layers of caching:

1. **MySQL Query Cache**: MySQL was caching the SELECT queries that retrieve form data, returning stale data even after updates
2. **WordPress Object Cache**: Sites using Redis, Memcached, or other persistent object caches were caching form data
3. **HTTP/Page Cache**: WordPress caching plugins (LiteSpeed Cache, WP Rocket, W3 Total Cache, etc.) were caching the rendered HTML with old field names
4. **CDN/Proxy Cache**: Edge caches (Cloudflare, etc.) were serving cached pages

## Solution
The fix implements comprehensive cache prevention at all levels:

### 1. Database Query Cache Prevention
- Added `SQL_NO_CACHE` hint to SELECT queries in `get_form_by_id()` and `get_form_by_slug()`
- Added `wp_cache_delete()` calls after form updates to clear WordPress object cache
- Located in: `includes/class-form-storage.php`

### 2. HTTP Cache Prevention
- Added no-cache headers when rendering forms
- Added `prevent_form_page_caching()` method that:
  - Detects pages containing the `[form_builder]` shortcode
  - Sends HTTP no-cache headers (including `X-Accel-Expires` for Nginx)
  - Sets WordPress cache prevention constants (`DONOTCACHEPAGE`, `DONOTCACHEDB`, `DONOTCACHEOBJECT`)
- Located in: `form-builder-plugin.php` and `includes/class-form-renderer.php`

### 3. Enhanced Logging
- Added debug logging when field names are changed (JavaScript)
- Added debug logging before/after database updates (PHP)
- Added logging when database update returns 0 rows (indicates identical data)
- Helps diagnose caching issues in production

## Testing the Fix

### Before the Fix
1. Create a form with a field named "email_address"
2. Save the form
3. View the form on the frontend (note the field name attribute)
4. Edit the form and change field name to "user_email"
5. Save the form
6. Refresh the frontend page
7. **BUG**: The field still has name="email_address"

### After the Fix
1. Same steps as above
2. **FIXED**: The field now has name="user_email" immediately after saving

### Verifying the Fix

#### Method 1: Check Field Names in HTML
1. Edit a form and change a field name
2. Save the form
3. View the form on the frontend
4. Right-click and "View Page Source"
5. Search for the field's input element
6. Verify the `name` attribute has the updated value

#### Method 2: Check Webhook Submissions
1. Configure a webhook URL for a form page
2. Add fields with specific names (e.g., "full_name", "email_address")
3. Save the form and submit it on the frontend
4. Check the webhook payload - should contain the correct field names
5. Edit the form and change field names (e.g., to "customer_name", "customer_email")
6. Save and submit again
7. Verify the webhook payload now uses the new field names

#### Method 3: Check with Debug Mode
1. Enable WordPress debug mode in `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
2. Edit a form and change a field name
3. Open browser console (F12) and check for logs like:
   ```
   [Form Builder] Field name updated from "old_name" to "new_name"
   [Form Builder] Saving field on page 1: name="new_name", label="Field Label"
   ```
4. Check WordPress debug log (`/wp-content/debug.log`) for:
   ```
   [Form_Builder_Storage] Updating form ID: 123
   [Form_Builder_Storage] Form config length: 5432
   ```

## Clearing Existing Caches

If you're still seeing old field names after applying this fix, you may need to manually clear existing caches:

### 1. WordPress Caching Plugins
- **LiteSpeed Cache**: Go to LiteSpeed Cache > Toolbox > Purge All
- **WP Rocket**: Go to WP Rocket > Clear Cache
- **W3 Total Cache**: Go to Performance > Dashboard > Empty All Caches
- **WP Super Cache**: Go to Settings > WP Super Cache > Delete Cache

### 2. CDN Cache (if applicable)
- **Cloudflare**: Go to Cloudflare dashboard > Caching > Purge Everything
- **Other CDNs**: Check their documentation for cache purging

### 3. Browser Cache
- Hard refresh: `Ctrl+F5` (Windows) or `Cmd+Shift+R` (Mac)
- Or clear browser cache completely

### 4. MySQL Query Cache (if enabled)
If you have server access, you can flush MySQL query cache:
```sql
FLUSH QUERY CACHE;
RESET QUERY CACHE;
```

Or add this to your MySQL configuration to disable query cache:
```
query_cache_type = 0
```

## Technical Details

### SQL_NO_CACHE
The `SQL_NO_CACHE` hint tells MySQL to not use the query cache for this specific query:
```php
"SELECT SQL_NO_CACHE * FROM {$this->forms_table} WHERE id = %d"
```

### WordPress Cache Prevention Constants
These constants tell WordPress and caching plugins to not cache the current page:
```php
define('DONOTCACHEPAGE', true);   // Don't cache this page
define('DONOTCACHEDB', true);     // Don't cache database queries
define('DONOTCACHEOBJECT', true); // Don't cache objects
```

### HTTP Cache-Control Headers
These headers tell browsers and proxies to not cache the response:
```php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('X-Accel-Expires: 0'); // For Nginx
```

## Performance Considerations

### Impact
Disabling caching for forms means:
- Each page load will query the database for form data
- Form HTML won't be served from cache
- Slightly increased server load

### Mitigation
- The impact is minimal for most sites
- Forms are typically interactive and benefit from fresh data
- Only affects pages with the `[form_builder]` shortcode
- Other pages remain cacheable

### Alternative Approach
If performance is critical, you can implement cache invalidation:
1. Remove the no-cache headers
2. Implement cache clearing when forms are updated
3. Clear cache for all pages containing the updated form's shortcode

This would require additional code to track which pages contain which forms.

## Future Improvements

Potential enhancements for a more sophisticated solution:

1. **Selective Cache Invalidation**: Track which pages contain which forms, and only clear cache for those specific pages when a form is updated

2. **Version-Based Caching**: Add a version number to forms, include in the rendered HTML, and only invalidate cache when version changes

3. **ETag Support**: Implement ETags for forms to enable conditional caching

4. **Cache Warming**: Pre-generate cached versions of forms after updates

5. **Admin Notice**: Show a notice to admins after updating a form, reminding them to clear caches if needed

## Troubleshooting

### Issue: Field names still showing old values
**Solution**: Clear all caches (plugin, CDN, browser, MySQL)

### Issue: Webhook still receiving old field names
**Solution**: 
1. Verify the form was saved correctly (check database)
2. Clear all caches
3. Test with a new form submission
4. Check webhook logs to see actual data sent

### Issue: Performance degradation after fix
**Solution**:
1. Monitor server resources
2. Consider using a CDN for static assets
3. Optimize database queries
4. Implement selective cache invalidation (future improvement)

### Issue: "Headers already sent" error
**Solution**:
1. Check for any output before the shortcode
2. Ensure no whitespace before `<?php` tags
3. The fix checks `!headers_sent()` to prevent this error

## Related Files

- `includes/class-form-storage.php` - Database operations and cache clearing
- `includes/class-form-renderer.php` - Form rendering and cache headers
- `form-builder-plugin.php` - Page-level cache prevention
- `admin/builder.js` - Debug logging for field name changes

## Support

If you continue to experience issues with field names not updating:

1. Enable debug mode and check logs
2. Verify all caches are cleared
3. Test with a simple form
4. Check browser console for JavaScript errors
5. Review WordPress debug log for PHP errors

For additional help, provide:
- WordPress version
- Caching plugins installed
- Server configuration (Apache/Nginx)
- Debug logs from both browser console and WordPress
