# WordPress.org Publishing Guide

This guide walks you through the complete process of publishing the Konstruct Form Builder plugin to the WordPress.org plugin repository.

## Prerequisites

1. **WordPress.org Account**: Create an account at [wordpress.org](https://wordpress.org/support/register.php)
2. **Plugin Submission Request**: Submit a plugin submission request at [WordPress.org Plugin Directory](https://wordpress.org/plugins/developers/add/)
3. **SVN Access**: Wait for approval (typically 1-2 weeks) and receive SVN repository URL
4. **SVN Client**: Install Subversion (SVN) on your system
   - macOS: `brew install subversion`
   - Linux: `sudo apt-get install subversion` or `sudo yum install subversion`
   - Windows: Download from [Apache Subversion](https://subversion.apache.org/packages.html)

## Step 1: Prepare Your Plugin

### 1.1 Final Checklist

Before submitting, ensure:

- [ ] All code follows WordPress Coding Standards
- [ ] All strings are translatable (use `__()`, `_e()`, etc. with text domain)
- [ ] No hardcoded URLs or external dependencies
- [ ] Proper security measures (sanitization, escaping, nonces)
- [ ] `uninstall.php` handles cleanup
- [ ] `readme.txt` is complete and valid
- [ ] Plugin header is correct
- [ ] No debug code in production files
- [ ] All PHP files have proper PHPDoc blocks

### 1.2 Create Distribution Package

1. Remove development files:
   ```bash
   rm -rf dev-tools/
   rm -rf .git/
   rm -rf node_modules/
   ```

2. Ensure proper file structure:
   ```
   form-builder-plugin/
   ├── form-builder-plugin.php
   ├── readme.txt
   ├── uninstall.php
   ├── includes/
   ├── admin/
   ├── frontend/
   └── assets/ (if you have them)
   ```

## Step 2: SVN Repository Setup

### 2.1 Checkout Repository

After receiving your SVN URL (format: `https://plugins.svn.wordpress.org/your-plugin-slug/`):

```bash
# Create a directory for your SVN checkout
mkdir ~/wordpress-plugin-svn
cd ~/wordpress-plugin-svn

# Checkout the repository
svn checkout https://plugins.svn.wordpress.org/konstruct-form-builder/ konstruct-form-builder
cd konstruct-form-builder
```

### 2.2 Repository Structure

Your SVN repository has three main directories:

- **`trunk/`** - Development version (always latest)
- **`tags/`** - Version releases (e.g., `1.2.0/`)
- **`assets/`** - Plugin assets (banner, icon, screenshots)

## Step 3: Initial Upload

### 3.1 Copy Files to Trunk

```bash
# Copy all plugin files to trunk
cp -r /path/to/your/plugin/* trunk/

# Remove any files that shouldn't be in the repository
cd trunk
rm -rf dev-tools/
rm -rf .git/
rm -rf node_modules/
```

### 3.2 Add Files to SVN

```bash
cd ~/wordpress-plugin-svn/konstruct-form-builder/trunk

# Add all files
svn add --force .

# Check status
svn status
```

### 3.3 Commit to Trunk

```bash
# Commit with a descriptive message
svn commit -m "Initial plugin submission - Version 1.2.0"
```

You'll be prompted for your WordPress.org username and password.

## Step 4: Create First Release Tag

### 4.1 Copy Trunk to Tags

```bash
cd ~/wordpress-plugin-svn/konstruct-form-builder

# Copy trunk to tags/1.2.0
svn copy trunk/ tags/1.2.0

# Commit the tag
svn commit -m "Tagging version 1.2.0"
```

### 4.2 Update readme.txt Stable Tag

In `trunk/readme.txt`, ensure:
```
Stable tag: 1.2.0
```

Then commit:
```bash
cd trunk
svn commit -m "Update stable tag to 1.2.0"
```

## Step 5: Add Plugin Assets

### 5.1 Prepare Assets

Create the following assets:

1. **Banner**: `banner-772x250.png` (772x250 pixels)
2. **Icon**: `icon-256x256.png` (256x256 pixels, square)
3. **Screenshots**: `screenshot-1.png` through `screenshot-5.png` (1200x900 pixels recommended)

### 5.2 Upload Assets

```bash
cd ~/wordpress-plugin-svn/konstruct-form-builder

# Create assets directory if it doesn't exist
mkdir -p assets

# Copy your assets
cp /path/to/banner-772x250.png assets/
cp /path/to/icon-256x256.png assets/
cp /path/to/screenshot-*.png assets/

# Add to SVN
svn add assets/*

# Commit
svn commit -m "Add plugin assets (banner, icon, screenshots)"
```

## Step 6: Submit for Review

### 6.1 Complete Submission Form

1. Go to [WordPress.org Plugin Directory](https://wordpress.org/plugins/developers/add/)
2. Fill out the submission form:
   - Plugin name: Konstruct Form Builder
   - Plugin slug: konstruct-form-builder
   - SVN URL: Your repository URL
   - Description: Brief description
   - Support URL: Your support URL (if any)

### 6.2 Wait for Review

- Review typically takes 1-2 weeks
- Reviewers will check:
  - Code quality and standards
  - Security practices
  - Functionality
  - Documentation

## Step 7: Address Review Feedback

If reviewers request changes:

1. Make changes to your local files
2. Update trunk:
   ```bash
   cd ~/wordpress-plugin-svn/konstruct-form-builder/trunk
   # Make your changes
   svn commit -m "Address review feedback: [description]"
   ```
3. Respond to the review thread with details of changes

## Step 8: Plugin Approval

Once approved:

1. Your plugin will be live at: `https://wordpress.org/plugins/konstruct-form-builder/`
2. Users can install via WordPress admin
3. You'll receive email notifications for support requests

## Step 9: Ongoing Maintenance

### 9.1 Update Process

For each new version:

1. Update version numbers:
   - `form-builder-plugin.php` header
   - `readme.txt` stable tag
   - `FORM_BUILDER_VERSION` constant

2. Make changes in trunk:
   ```bash
   cd ~/wordpress-plugin-svn/konstruct-form-builder/trunk
   # Make your changes
   svn commit -m "Update to version X.X.X"
   ```

3. Create new tag:
   ```bash
   svn copy trunk/ tags/X.X.X
   svn commit -m "Tagging version X.X.X"
   ```

4. Update stable tag in readme.txt:
   ```bash
   # Edit readme.txt to update Stable tag
   svn commit -m "Update stable tag to X.X.X"
   ```

### 9.2 Support Forums

- Monitor support forums: `https://wordpress.org/support/plugin/konstruct-form-builder/`
- Respond to user questions promptly
- Address bug reports

### 9.3 Best Practices

- Test thoroughly before releasing
- Follow semantic versioning (MAJOR.MINOR.PATCH)
- Update changelog in readme.txt
- Keep code standards compliant
- Regular security updates

## Common SVN Commands

```bash
# Check status
svn status

# Update from repository
svn update

# View differences
svn diff

# Revert changes
svn revert filename

# View log
svn log

# Remove file
svn remove filename
```

## Troubleshooting

### Authentication Issues

If you have trouble with SVN authentication:
1. Clear SVN credentials: `rm -rf ~/.subversion/auth/`
2. Use your WordPress.org username (not email)
3. Use an application password if 2FA is enabled

### Merge Conflicts

If you have conflicts:
```bash
svn update
# Resolve conflicts manually
svn resolved filename
svn commit
```

## Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Plugin Developer FAQ](https://developer.wordpress.org/plugins/plugin-basics/faq/)
- [SVN Documentation](https://subversion.apache.org/docs/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)

## Notes

- **Never commit directly to tags**: Always update trunk first, then copy to tags
- **Test before tagging**: Always test your plugin before creating a release tag
- **Keep trunk updated**: Trunk should always reflect the latest development version
- **Version consistency**: Ensure version numbers match across all files

Good luck with your plugin submission!

