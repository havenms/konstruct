# Quick Cache Busting Test for Production

## What Was Added

✅ **Dynamic Asset Versioning**: CSS/JS files now use timestamps for cache busting
✅ **AJAX Cache Busting**: All API requests include cache-busting parameters  
✅ **Server Headers**: Admin pages send no-cache headers
✅ **Development Mode**: Aggressive cache busting when WP_DEBUG is enabled

## Immediate Steps for Production Server

### 1. Clear All Caches First
```bash
# If using LiteSpeed Cache plugin
# Go to: WP Admin > LiteSpeed Cache > Toolbox > Purge All

# Or if you have server access:
# lscache-purge /
```

### 2. Quick LiteSpeed Cache Settings
Navigate to: **WP Admin > LiteSpeed Cache > Cache > Excludes**

Add these paths to the "Do Not Cache URIs" section:
```
/wp-admin/admin.php?page=form-builder*
/wp-json/form-builder/*
```

### 3. Test the Changes
1. Go to your form builder admin page
2. Open browser dev tools (F12) > Network tab  
3. Refresh the page
4. Look for `?_cb=` parameters in the requests
5. Try deleting a form - it should disappear immediately

### 4. If Still Having Issues

Add this to your `.htaccess` file (in WordPress root):
```apache
# Konstruct Form Builder - No Cache Admin
<LocationMatch "wp-admin/admin\.php\?page=form-builder">
    Header always set Cache-Control "no-cache, no-store, must-revalidate"
    Header always set Pragma "no-cache"
    Header always set Expires "Thu, 01 Jan 1970 00:00:00 GMT"
</LocationMatch>
```

### 5. Enable Development Mode (Optional)
Add this to `wp-config.php` for maximum cache busting during development:
```php
define('WP_DEBUG', true);
```

## What to Look For

✅ **Forms list updates immediately** when you delete/add forms
✅ **No cached API responses** (check Network tab)  
✅ **Admin assets reload** with new timestamps
✅ **Cache headers present** in response headers

The plugin now automatically handles cache busting, so LiteSpeed Cache shouldn't interfere with the admin panel anymore!