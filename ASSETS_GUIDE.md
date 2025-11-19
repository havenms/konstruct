# Plugin Assets Guide

This guide explains what assets you need to create for WordPress.org plugin submission.

## Required Assets

### 1. Plugin Banner

**File**: `assets/banner-772x250.png`

- **Dimensions**: 772x250 pixels (exact)
- **Format**: PNG
- **Purpose**: Displayed on plugin directory page
- **Design Tips**:
  - Use your plugin branding
  - Include plugin name
  - Keep text readable at small sizes
  - Use professional design
  - Consider dark/light theme compatibility

**Example Tools**:
- Photoshop
- GIMP (free)
- Figma
- Canva

### 2. Plugin Icon

**File**: `assets/icon-256x256.png`

- **Dimensions**: 256x256 pixels (square)
- **Format**: PNG
- **Background**: Transparent or solid color
- **Purpose**: Plugin icon in directory and admin
- **Design Tips**:
  - Simple, recognizable design
  - Works at small sizes (16x16, 32x32)
  - High contrast
  - Represents plugin functionality

### 3. Screenshots

**Files**: `assets/screenshots/screenshot-1.png` through `screenshot-5.png`

- **Dimensions**: 1200x900 pixels (recommended)
- **Format**: PNG
- **Quantity**: 1-5 screenshots
- **Purpose**: Show plugin features on directory page
- **Content Suggestions**:
  1. Form builder interface
  2. Form rendering on frontend
  3. Submissions management page
  4. Email notification settings
  5. Webhook configuration

**Design Tips**:
- Use actual screenshots of your plugin
- Add captions if needed (in readme.txt)
- Show key features
- Keep them professional and clear

## Creating Assets

### Using Design Tools

1. **Figma** (Free, Web-based):
   - Create new file
   - Set canvas to required dimensions
   - Design your asset
   - Export as PNG

2. **GIMP** (Free, Desktop):
   - File → New → Set dimensions
   - Design your asset
   - File → Export As → PNG

3. **Photoshop**:
   - New document → Set dimensions
   - Design your asset
   - File → Export → Export As → PNG

### Quick Design Tips

**Banner**:
```
[Plugin Logo/Icon]  Konstruct Form Builder
                    Build Beautiful Forms with Ease
                    [Visual Element/Illustration]
```

**Icon**:
- Simple form icon
- Use your brand colors
- Ensure it's recognizable at 16x16

**Screenshots**:
- Use browser developer tools to capture clean screenshots
- Remove personal data
- Highlight key features with annotations if needed

## File Organization

After creating assets, organize them:

```
form-builder-plugin/
└── assets/
    ├── banner-772x250.png
    ├── icon-256x256.png
    └── screenshots/
        ├── screenshot-1.png
        ├── screenshot-2.png
        ├── screenshot-3.png
        ├── screenshot-4.png
        └── screenshot-5.png
```

## Uploading to WordPress.org

Assets are uploaded to the SVN repository in the `assets/` directory:

```bash
cd ~/wordpress-plugin-svn/konstruct-form-builder
mkdir -p assets/screenshots
cp banner-772x250.png assets/
cp icon-256x256.png assets/
cp screenshot-*.png assets/screenshots/
svn add assets/*
svn commit -m "Add plugin assets"
```

## Screenshot Captions

You can add captions in `readme.txt`:

```
== Screenshots ==

1. Form Builder Interface - Create and edit forms with an intuitive interface
2. Form Rendering - Beautiful, responsive forms on the frontend
3. Submissions Management - View and manage all form submissions
4. Email Notifications - Configure email notifications with placeholders
5. Webhook Configuration - Set up webhooks for each form page
```

## Resources

- [WordPress Plugin Assets Guidelines](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)
- [Design Inspiration](https://wordpress.org/plugins/browse/popular/)
- [Free Design Resources](https://unsplash.com/, https://pexels.com/)

## Notes

- Assets are optional but highly recommended
- Without assets, WordPress.org will use default placeholders
- Good assets improve plugin visibility and downloads
- Update assets when plugin design changes significantly

