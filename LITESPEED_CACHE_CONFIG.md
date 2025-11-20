# LiteSpeed Cache Configuration for Konstruct Form Builder

This document provides instructions for configuring LiteSpeed Cache to work properly with the Konstruct Form Builder plugin, especially during development and testing phases.

## Issues Addressed

- Admin panel changes not reflecting immediately due to aggressive caching
- AJAX requests being cached causing stale data
- Form deletion and updates not visible in real-time

## Automatic Cache Busting Features Added

### 1. Dynamic Asset Versioning

- CSS and JS files now use file modification time in development mode
- Production mode uses version + timestamp for cache busting
- Automatic cache headers added to admin assets

### 2. AJAX Cache Busting

- All AJAX requests now include cache-busting parameters
- Global AJAX setup prevents caching of API responses
- Individual requests have cache-control headers

### 3. Admin Page Headers

- Cache-control headers added to all admin pages
- Expires headers set to prevent browser caching
- Development mode detection for aggressive cache busting

## LiteSpeed Cache Plugin Settings

If you're using the LiteSpeed Cache WordPress plugin, configure these settings:

### 1. Exclude Admin Pages from Caching

Navigate to **LiteSpeed Cache > Cache > Excludes** and add:

```
/wp-admin/admin.php?page=form-builder*
/wp-admin/admin-ajax.php
```

### 2. Exclude REST API Endpoints

Navigate to **LiteSpeed Cache > Cache > Excludes** and add:

```
/wp-json/form-builder/*
```

### 3. Development Mode

If you're in development, enable:

- **LiteSpeed Cache > General > Development Mode**: ON
- **LiteSpeed Cache > Debug > Debug Log**: ON (for troubleshooting)

## Server-Level .htaccess Rules

Add these rules to your `.htaccess` file in the WordPress root directory:

```apache
# Konstruct Form Builder Cache Control
<IfModule mod_headers.c>
    # Don't cache admin pages
    <LocationMatch "^/wp-admin/admin\.php\?page=form-builder">
        Header always set Cache-Control "no-cache, no-store, must-revalidate, max-age=0"
        Header always set Pragma "no-cache"
        Header always set Expires "Thu, 01 Jan 1970 00:00:00 GMT"
    </LocationMatch>

    # Don't cache REST API responses
    <LocationMatch "^/wp-json/form-builder">
        Header always set Cache-Control "no-cache, no-store, must-revalidate, max-age=0"
        Header always set Pragma "no-cache"
        Header always set Expires "Thu, 01 Jan 1970 00:00:00 GMT"
    </LocationMatch>

    # Cache bust admin assets in development
    <LocationMatch "^/wp-content/plugins/konstruct/admin/">
        Header always set Cache-Control "no-cache, no-store, must-revalidate, max-age=0"
        Header always set Pragma "no-cache"
        Header always set Expires "Thu, 01 Jan 1970 00:00:00 GMT"
    </LocationMatch>
</IfModule>
```

## Quick Development Fixes

### 1. Force Refresh Admin Panel

- **Hard Refresh**: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)
- **Clear Browser Cache**: F12 > Application/Storage > Clear Storage

### 2. Purge LiteSpeed Cache

- **From Plugin**: LiteSpeed Cache > Toolbox > Purge All
- **From Server**: If you have server access: `lscache-purge /`

### 3. Disable Caching Temporarily

Add this to your `wp-config.php` for development:

```php
// Disable caching during development
define('WP_CACHE', false);
define('LITESPEED_DISABLE_ALL', true);
```

## Testing Cache Busting

### 1. Check Network Tab

- Open browser developer tools (F12)
- Go to Network tab
- Refresh admin page
- Look for cache-busting parameters (`?_cb=timestamp`) in requests

### 2. Verify Headers

Look for these headers in admin page responses:

```
Cache-Control: no-cache, no-store, must-revalidate, max-age=0
Pragma: no-cache
Expires: Thu, 01 Jan 1970 00:00:00 GMT
```

### 3. Test Form Operations

- Create a form and verify it appears immediately
- Delete a form and verify it disappears immediately
- Edit a form and verify changes save properly

## Troubleshooting

### If Changes Still Don't Show

1. Check if LiteSpeed Cache is enabled: **LiteSpeed Cache > General**
2. Purge all cache: **LiteSpeed Cache > Toolbox > Purge All**
3. Check server error logs for any PHP errors
4. Verify `.htaccess` rules are being applied

### If AJAX Requests Fail

1. Check browser console for JavaScript errors
2. Verify REST API endpoints are accessible: `/wp-json/form-builder/v1/forms`
3. Check nonce verification is working
4. Ensure server supports REST API

### If Admin Assets Don't Update

1. Check file permissions on plugin directory
2. Verify file modification times are updating
3. Force refresh browser cache
4. Check if CDN is caching admin assets

## Notes for Production

- The cache busting features are development-friendly but production-safe
- Asset versioning helps with browser cache invalidation
- Server-level caching should still be used for frontend performance
- Only admin areas are excluded from caching, frontend forms can still be cached

Remember to clear all caches after making these changes!
