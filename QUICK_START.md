# Form Builder Plugin - Quick Start Guide

## 🎯 Getting Started

### Creating a Form

1. **Go to Form Builder**
   - Navigate to WordPress Admin → Form Builder

2. **Click "Add New Form"**
   - Enter a form name (e.g., "Contact Us")
   - The slug auto-generates from the name (auto-populated)

3. **Add Your First Page**
   - Pages are shown in the left sidebar
   - Each page can have multiple fields

4. **Add Fields to the Page**
   - Click any field type button in the sidebar:
     - Text, Email, Number, Phone
     - Textarea, Select, Radio, Checkbox
     - File Upload, Date, URL, etc.

5. **Edit Field Properties**
   - Click "Edit" on any field
   - Set the Label (visible to users)
   - Set the Field Name (for form submissions)
   - Mark as Required if needed
   - Add placeholder text
   - For select/radio/checkbox: add options (one per line)

6. **Add More Pages**
   - Click "Add Page" in the sidebar
   - Repeat steps 4-5 for each page

7. **Configure Page Settings** (Optional)
   - Select a page in the sidebar
   - Scroll to "Page Settings" panel on the right
   - Enable Webhook if you want form submissions sent to external URLs
   - Add Custom JavaScript if needed

8. **Save Your Form**
   - Click the "Save Form" button
   - Your form is now ready to use!

---

## 📝 Using Your Form on Pages

### Add Form to Your Page

```
[form_builder id="FORM_ID"]
```

Or use the form slug:
```
[form_builder id="contact-us"]
```

Find your Form ID in the Forms list or check the URL when editing.

---

## 🎨 Customization Tips

### Field Labels vs Field Names
- **Label:** What users see (e.g., "Your Email Address")
- **Field Name:** Used in submissions data (e.g., "email")

### Making Fields Required
- Edit the field
- Check the "Required" checkbox
- The form will prevent submission if this field is empty

### Better Autofill
Use these Field Name keywords for smart autofill:
- `email` - Email address
- `name` / `first_name` / `last_name` - Name fields  
- `phone` / `phone_number` - Phone
- `address` / `street` - Address
- `city` - City
- `state` / `province` - State/Province
- `zip` / `postal_code` - Zip code
- `country` - Country
- `company` - Company name
- `url` / `website` - Website URL
- `username` - Username
- `password` - Password

---

## 📊 Viewing Submissions

1. **Go to Submissions Dashboard**
   - WordPress Admin → Form Builder → Submissions

2. **Filter Submissions**
   - By Form: Select form from dropdown
   - By Date: Set date range
   - By Keyword: Search form name or UUID

3. **View Details**
   - Click "View" on any submission
   - See all form data in a formatted view
   - Each field is clearly labeled

4. **Export Data**
   - Click "Export to CSV" button
   - Downloads all filtered submissions as CSV

---

## ✨ New Features

### ✅ Fixed Issues

**✅ Data Now Persists**
- Edit a field's label, placeholder, or name
- Changes are saved when you click "Save Form"
- No more data loss on reload!

**✅ Pages Can Be Deleted**
- Click the × button next to any page
- Safeguard prevents deleting the last page

**✅ Required Fields Validated**
- Frontend prevents form submission with empty required fields
- Clear error messages show which fields need filling

### 🎨 Design Improvements

**Admin Dashboard**
- Modern Apple-inspired design
- Clean, organized layout
- Sticky sidebar for easy navigation
- Better visual hierarchy

**Frontend Forms**
- Google Forms-inspired design
- Clean, minimal, professional look
- Smooth page transitions
- Mobile-optimized with touch-friendly buttons
- Responsive across all devices

**Submissions View**
- Better field separation with dividers
- Color-coded field names
- Improved data display
- Better modal for viewing details

---

## 🔧 Webhooks (Optional)

### Set Up Webhook

1. Edit a form page
2. Scroll to "Page Settings"
3. Enable "Webhook"
4. Enter your webhook URL
5. Save form

### What Happens
When users submit the form on that page, a POST request is sent to your webhook with:
```json
{
  "form_id": 1,
  "page_number": 1,
  "formData": {
    "email": "user@example.com",
    "name": "John Doe",
    "message": "Hello!"
  }
}
```

---

## 🛡️ Best Practices

### Field Naming
- Use lowercase with underscores: `first_name`, `phone_number`
- Avoid spaces and special characters
- Use descriptive names for clarity

### Required Fields
- Only mark truly required fields
- Don't mark every field as required
- Remember: Users may not want to fill optional fields

### Field Labels
- Be clear and descriptive
- Use proper grammar and capitalization
- Add helper text if needed (e.g., "Format: (123) 456-7890")

### Multi-Page Forms
- Logical grouping of fields (e.g., contact info on page 1, details on page 2)
- Use webhooks to validate/process each page
- Keep forms under 5 pages for best UX

### Mobile First
- Assume users might fill on mobile
- Use larger input fields
- Keep labels short
- Test on different devices

---

## ❓ Troubleshooting

### Form not appearing
- Check shortcode syntax: `[form_builder id="slug-or-id"]`
- Verify form exists in Forms list
- Clear WordPress cache if using caching plugin

### Data not saving
- Click "Save Form" after making changes
- Look for success message
- Check browser console for errors

### Validation not working
- Ensure field has "Required" checkbox enabled
- Check field name is not empty
- Verify frontend styles are loading

### Submissions not appearing
- Check Submissions dashboard filters
- Verify form receives submissions by checking page
- Look for webhook errors if enabled

---

## 📞 Support

For issues or questions:
1. Check the IMPROVEMENTS.md file for detailed technical info
2. Review browser console for error messages
3. Test in incognito/private mode to rule out caching
4. Verify form was saved properly

---

**Happy form building! 🚀**
