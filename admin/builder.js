/**
 * Form Builder Admin JavaScript
 */

(function ($) {
    'use strict';

    let formData = {};
    let currentPageIndex = 0;
    let currentFieldIndex = null;

    // Initialize
    $(document).ready(function () {
        if ($('#form-data').length) {
            // Builder mode
            initBuilder();
        } else {
            // List mode
            loadFormsList();
        }
    });

    /**
     * Initialize builder
     */
    function initBuilder() {
        formData = JSON.parse($('#form-data').text());

        if (!formData.pages || formData.pages.length === 0) {
            formData.pages = [{
                pageNumber: 1,
                fields: [],
                webhook: { enabled: false, url: '', method: 'POST' },
                customJS: ''
            }];
        }

        // Initialize notification settings if not present
        if (!formData.notifications) {
            formData.notifications = {
                step_notifications: {
                    enabled: false,
                    recipients: '',
                    recipient_field: '',
                    include_admin: true,
                    subject: 'Form Step Completed - {{form_name}}',
                    message: 'Hello,\n\nA step has been completed in the form "{{form_name}}".\n\nStep {{page_number}} was completed on {{date}}.\n\nSubmission ID: {{submission_uuid}}\n\nBest regards,\n{{site_name}}'
                },
                submission_notifications: {
                    enabled: false,
                    recipients: '',
                    recipient_field: '',
                    include_admin: true,
                    subject: 'New Form Submission - {{form_name}}',
                    message: 'Hello,\n\nA new form submission has been received for "{{form_name}}".\n\nSubmitted on: {{date}}\nSubmission ID: {{submission_uuid}}\n\nPlease review the form data below.\n\nBest regards,\n{{site_name}}'
                }
            };
        }

        // Normalize form data - ensure all fields have required properties
        formData.pages.forEach(function (page, pageIndex) {
            if (!page.fields) page.fields = [];

            page.fields.forEach(function (field, fieldIndex) {
                // Ensure all required properties exist
                if (typeof field.id === 'undefined') field.id = 'field_' + Date.now() + '_' + fieldIndex;
                if (typeof field.name === 'undefined') field.name = '';
                if (typeof field.label === 'undefined') field.label = field.type ? 'New ' + field.type.charAt(0).toUpperCase() + field.type.slice(1) + ' Field' : 'Unnamed Field';
                if (typeof field.type === 'undefined') field.type = 'text';
                if (typeof field.required === 'undefined') field.required = false;
                if (typeof field.placeholder === 'undefined') field.placeholder = '';
                if (typeof field.options === 'undefined') field.options = [];

                // Ensure webhook settings exist for page
                if (typeof page.webhook === 'undefined') {
                    page.webhook = { enabled: false, url: '', method: 'POST' };
                }
                if (typeof page.customJS === 'undefined') {
                    page.customJS = '';
                }
            });
        });

        renderPages();
        renderCurrentPage();
        setupEventListeners();
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Save form
        $('#save-form').on('click', saveForm);

        // Add page
        $('#add-page').on('click', addPage);

        // Field type buttons
        $('.field-type-btn').on('click', function () {
            addField($(this).data('type'));
        });

        // Form name generates slug
        $('#form-name').on('input', function () {
            if (!$('#form-slug').val()) {
                $('#form-slug').val(slugify($(this).val()));
            }
        });

        // Copy shortcode button (for form editor)
        $(document).on('click', '#copy-shortcode', function() {
            const formId = $(this).data('form-id');
            copyShortcodeToClipboard(formId);
        });
    }

    /**
     * Render pages list
     */
    function renderPages() {
        const $list = $('#pages-list');
        $list.empty();

        formData.pages.forEach((page, index) => {
            const $page = $('<div class="page-item" data-index="' + index + '">');
            $page.append('<span>Page ' + (index + 1) + '</span>');
            $page.append('<button type="button" class="button-small delete-page" title="Delete page">×</button>');

            // Page selection click handler
            $page.on('click', function (e) {
                if (!$(e.target).hasClass('delete-page')) {
                    if (!$(this).hasClass('active')) {
                        currentPageIndex = index;
                        currentFieldIndex = null;
                        renderPages();
                        renderCurrentPage();
                    }
                }
            });

            if (index === currentPageIndex) {
                $page.addClass('active');
            }

            $list.append($page);
        });

        // Attach delete handlers after rendering
        setTimeout(() => {
            $('.delete-page').on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const pageIdx = $(this).closest('.page-item').data('index');
                if (confirm('Are you sure you want to delete this page? All fields will be removed.')) {
                    if (formData.pages.length === 1) {
                        alert('You must have at least one page');
                        return;
                    }
                    formData.pages.splice(pageIdx, 1);
                    if (currentPageIndex >= formData.pages.length) {
                        currentPageIndex = formData.pages.length - 1;
                    }
                    currentFieldIndex = null;
                    renderPages();
                    renderCurrentPage();
                }
            });
        }, 0);
    }

    /**
     * Render current page editor
     */
    function renderCurrentPage() {
        const $editor = $('#pages-editor');
        $editor.empty();

        const page = formData.pages[currentPageIndex];
        if (!page) return;

        const $pageEditor = $('<div class="page-editor">');
        $pageEditor.append('<h3>Page ' + (currentPageIndex + 1) + '</h3>');

        const $fieldsList = $('<div class="fields-list">');

        if (page.fields.length === 0) {
            $fieldsList.append('<p class="no-fields">No fields yet. Click a field type to add one.</p>');
        } else {
            page.fields.forEach((field, index) => {
                $fieldsList.append(renderField(field, index));
            });
        }

        $pageEditor.append($fieldsList);
        $editor.append($pageEditor);

        renderPageProperties();
        
        // Update recipient field dropdowns after rendering (delayed to ensure DOM is ready)
        setTimeout(function() {
            if (typeof populateRecipientFields === 'function') {
                populateRecipientFields();
            }
        }, 0);
    }

    /**
     * Render field
     */
    function renderField(field, index) {
        const page = formData.pages[currentPageIndex];
        const totalFields = page.fields.length;
        const isFirst = index === 0;
        const isLast = index === totalFields - 1;

        const $field = $('<div class="field-item" data-index="' + index + '">');

        const $header = $('<div class="field-header">');
        
        // Move buttons container
        const $moveButtons = $('<div class="field-move-buttons">');
        $moveButtons.append('<button type="button" class="button-icon move-up-btn" title="Move Up" ' + (isFirst ? 'disabled' : '') + '><svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 4L3 8H11L7 4Z" fill="currentColor"/></svg></button>');
        $moveButtons.append('<button type="button" class="button-icon move-down-btn" title="Move Down" ' + (isLast ? 'disabled' : '') + '><svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 10L11 6H3L7 10Z" fill="currentColor"/></svg></button>');
        
        $header.append($moveButtons);
        $header.append('<span class="field-label">' + escapeHtml(field.label || 'Unnamed Field') + '</span>');
        $header.append('<span class="field-type">' + field.type + '</span>');
        $header.append('<button type="button" class="button-small edit-field">Edit</button>');
        $header.append('<button type="button" class="button-small delete-field">Delete</button>');

        $field.append($header);

        $field.find('.edit-field').on('click', function () {
            currentFieldIndex = index;
            renderFieldProperties();
        });

        $field.find('.delete-field').on('click', function () {
            formData.pages[currentPageIndex].fields.splice(index, 1);
            currentFieldIndex = null;
            renderCurrentPage();
        });

        // Move up handler
        $field.find('.move-up-btn').on('click', function () {
            if (index > 0) {
                moveField(index, index - 1);
            }
        });

        // Move down handler
        $field.find('.move-down-btn').on('click', function () {
            if (index < totalFields - 1) {
                moveField(index, index + 1);
            }
        });

        return $field;
    }

    /**
     * Move field from one position to another
     */
    function moveField(fromIndex, toIndex) {
        const page = formData.pages[currentPageIndex];
        const fields = page.fields;
        
        // Remove field from original position
        const field = fields.splice(fromIndex, 1)[0];
        
        // Insert at new position
        fields.splice(toIndex, 0, field);
        
        // Update currentFieldIndex if it was affected
        if (currentFieldIndex === fromIndex) {
            currentFieldIndex = toIndex;
        } else if (currentFieldIndex === toIndex) {
            currentFieldIndex = fromIndex;
        } else if (currentFieldIndex > fromIndex && currentFieldIndex <= toIndex) {
            currentFieldIndex--;
        } else if (currentFieldIndex < fromIndex && currentFieldIndex >= toIndex) {
            currentFieldIndex++;
        }
        
        renderCurrentPage();
        
        // Re-select the moved field if it was selected
        if (currentFieldIndex !== null) {
            renderFieldProperties();
        }
    }

    /**
     * Render page properties
     */
    function renderPageProperties() {
        if (currentFieldIndex === null) {
            renderPageSettings();
        } else {
            renderFieldProperties();
        }
    }

    /**
     * Render page settings
     */
    function renderPageSettings() {
        const $props = $('#page-properties');
        $props.empty();

        const page = formData.pages[currentPageIndex];
        if (!page) return;

        // Add tabs for different settings sections
        const $tabs = $('<div class="form-builder-tabs">');
        $tabs.append('<div class="tab-nav"><a href="#webhook-tab" class="tab-link active">Webhook</a><a href="#email-tab" class="tab-link">Email Notifications</a><a href="#js-tab" class="tab-link">Custom JS</a></div>');
        $props.append($tabs);

        // Webhook Settings Tab
        const $webhookTab = $('<div id="webhook-tab" class="tab-content active">');
        const $webhook = $('<div class="property-group">');
        $webhook.append('<h4>Webhook Settings</h4>');
        $webhook.append('<label><input type="checkbox" id="webhook-enabled" ' + (page.webhook.enabled ? 'checked' : '') + '> Enable Webhook</label>');
        $webhook.append('<input type="url" id="webhook-url" class="regular-text" placeholder="https://example.com/webhook" value="' + escapeHtml(page.webhook.url || '') + '">');
        $webhookTab.append($webhook);
        $props.append($webhookTab);

        // Email Notifications Tab
        const $emailTab = $('<div id="email-tab" class="tab-content">');
        
        // Step Notifications
        const $stepNotifications = $('<div class="property-group">');
        $stepNotifications.append('<h4>Step Completion Notifications</h4>');
        $stepNotifications.append('<label><input type="checkbox" id="step-notifications-enabled" ' + (formData.notifications.step_notifications.enabled ? 'checked' : '') + '> Enable step completion emails</label>');
        
        const $stepConfig = $('<div class="notification-config" style="' + (formData.notifications.step_notifications.enabled ? '' : 'display:none') + '">');
        $stepConfig.append('<label>Recipients (comma-separated emails):<br><input type="text" id="step-recipients" class="regular-text" value="' + escapeHtml(formData.notifications.step_notifications.recipients || '') + '" placeholder="admin@example.com, user@example.com"></label>');
        $stepConfig.append('<label>Or get recipient from field:<br><select id="step-recipient-field"><option value="">Select field...</option></select></label>');
        $stepConfig.append('<label><input type="checkbox" id="step-include-admin" ' + (formData.notifications.step_notifications.include_admin ? 'checked' : '') + '> Include site admin email</label>');
        $stepConfig.append('<label>Subject:<br><input type="text" id="step-subject" class="regular-text" value="' + escapeHtml(formData.notifications.step_notifications.subject || '') + '"></label>');
        $stepConfig.append('<label>Message:<br><textarea id="step-message" class="large-text" rows="4">' + escapeHtml(formData.notifications.step_notifications.message || '') + '</textarea></label>');
        $stepNotifications.append($stepConfig);
        $emailTab.append($stepNotifications);

        // Submission Notifications
        const $submissionNotifications = $('<div class="property-group">');
        $submissionNotifications.append('<h4>Final Submission Notifications</h4>');
        $submissionNotifications.append('<label><input type="checkbox" id="submission-notifications-enabled" ' + (formData.notifications.submission_notifications.enabled ? 'checked' : '') + '> Enable final submission emails</label>');
        
        const $submissionConfig = $('<div class="notification-config" style="' + (formData.notifications.submission_notifications.enabled ? '' : 'display:none') + '">');
        $submissionConfig.append('<label>Recipients (comma-separated emails):<br><input type="text" id="submission-recipients" class="regular-text" value="' + escapeHtml(formData.notifications.submission_notifications.recipients || '') + '" placeholder="admin@example.com, user@example.com"></label>');
        $submissionConfig.append('<label>Or get recipient from field:<br><select id="submission-recipient-field"><option value="">Select field...</option></select></label>');
        $submissionConfig.append('<label><input type="checkbox" id="submission-include-admin" ' + (formData.notifications.submission_notifications.include_admin ? 'checked' : '') + '> Include site admin email</label>');
        $submissionConfig.append('<label>Subject:<br><input type="text" id="submission-subject" class="regular-text" value="' + escapeHtml(formData.notifications.submission_notifications.subject || '') + '"></label>');
        $submissionConfig.append('<label>Message:<br><textarea id="submission-message" class="large-text" rows="4">' + escapeHtml(formData.notifications.submission_notifications.message || '') + '</textarea></label>');
        $submissionNotifications.append($submissionConfig);
        $emailTab.append($submissionNotifications);

        // Email placeholders help
        const $placeholders = $('<div class="property-group">');
        $placeholders.append('<h4>Available Placeholders</h4>');
        $placeholders.append('<p><small>{{form_name}}, {{page_number}}, {{submission_uuid}}, {{date}}, {{site_name}}, {{site_url}}, {{admin_email}}, {{field_name}} (for any form field)</small></p>');
        $emailTab.append($placeholders);

        $props.append($emailTab);

        // Custom JavaScript Tab
        const $jsTab = $('<div id="js-tab" class="tab-content">');
        const $customJS = $('<div class="property-group">');
        $customJS.append('<h4>Custom JavaScript (Optional)</h4>');
        $customJS.append('<textarea id="custom-js" class="large-text code" rows="5" placeholder="// Custom JS code here">' + escapeHtml(page.customJS || '') + '</textarea>');
        $jsTab.append($customJS);
        $props.append($jsTab);

        // Populate recipient field dropdowns with email fields from all pages
        populateRecipientFields();

        // Tab switching
        $('.tab-link').off('click').on('click', function(e) {
            e.preventDefault();
            const target = $(this).attr('href');
            $('.tab-link').removeClass('active');
            $('.tab-content').removeClass('active');
            $(this).addClass('active');
            $(target).addClass('active');
        });

        // Bind webhook events
        $('#webhook-enabled').off('change').on('change', function () {
            page.webhook.enabled = $(this).is(':checked');
        });
        $('#webhook-url').off('input').on('input', function () {
            page.webhook.url = $(this).val();
        });

        // Bind email notification events
        $('#step-notifications-enabled').off('change').on('change', function () {
            formData.notifications.step_notifications.enabled = $(this).is(':checked');
            $(this).closest('.property-group').find('.notification-config').toggle($(this).is(':checked'));
        });

        $('#submission-notifications-enabled').off('change').on('change', function () {
            formData.notifications.submission_notifications.enabled = $(this).is(':checked');
            $(this).closest('.property-group').find('.notification-config').toggle($(this).is(':checked'));
        });

        // Step notification field bindings
        $('#step-recipients').off('input').on('input', function () {
            formData.notifications.step_notifications.recipients = $(this).val();
        });
        $('#step-recipient-field').off('change').on('change', function () {
            formData.notifications.step_notifications.recipient_field = $(this).val();
        });
        $('#step-include-admin').off('change').on('change', function () {
            formData.notifications.step_notifications.include_admin = $(this).is(':checked');
        });
        $('#step-subject').off('input').on('input', function () {
            formData.notifications.step_notifications.subject = $(this).val();
        });
        $('#step-message').off('input').on('input', function () {
            formData.notifications.step_notifications.message = $(this).val();
        });

        // Submission notification field bindings
        $('#submission-recipients').off('input').on('input', function () {
            formData.notifications.submission_notifications.recipients = $(this).val();
        });
        $('#submission-recipient-field').off('change').on('change', function () {
            formData.notifications.submission_notifications.recipient_field = $(this).val();
        });
        $('#submission-include-admin').off('change').on('change', function () {
            formData.notifications.submission_notifications.include_admin = $(this).is(':checked');
        });
        $('#submission-subject').off('input').on('input', function () {
            formData.notifications.submission_notifications.subject = $(this).val();
        });
        $('#submission-message').off('input').on('input', function () {
            formData.notifications.submission_notifications.message = $(this).val();
        });

        // Bind custom JS events
        $('#custom-js').off('input').on('input', function () {
            page.customJS = $(this).val();
        });
    }

    /**
     * Populate recipient field dropdowns with email fields
     */
    function populateRecipientFields() {
        const $stepField = $('#step-recipient-field');
        const $submissionField = $('#submission-recipient-field');
        
        $stepField.empty().append('<option value="">Select field...</option>');
        $submissionField.empty().append('<option value="">Select field...</option>');
        
        formData.pages.forEach(function(page) {
            page.fields.forEach(function(field) {
                if (field.type === 'email') {
                    $stepField.append('<option value="' + escapeHtml(field.name) + '">' + escapeHtml(field.label) + '</option>');
                    $submissionField.append('<option value="' + escapeHtml(field.name) + '">' + escapeHtml(field.label) + '</option>');
                }
            });
        });
        
        // Set current values
        $stepField.val(formData.notifications.step_notifications.recipient_field || '');
        $submissionField.val(formData.notifications.submission_notifications.recipient_field || '');
    }

    /**
     * Render field properties
     */
    function renderFieldProperties() {
        if (currentFieldIndex === null) return;

        const field = formData.pages[currentPageIndex].fields[currentFieldIndex];
        if (!field) return;

        const $props = $('#page-properties');
        $props.empty();

        $props.append('<h4>Field Properties</h4>');

        // Label (required)
        const $label = $('<div class="property-item">');
        $label.append('<label>Label <span style="color: #ff3b30;">*</span></label>');
        $label.append('<input type="text" id="field-label" class="regular-text" value="' + escapeHtml(field.label || '') + '">');
        $props.append($label);
        // Bind after append
        $('#field-label').off('input').on('input', function () {
            field.label = $(this).val();
            // Update label text in the grid without full re-render to keep focus
            const $gridItem = $('.field-item[data-index="' + currentFieldIndex + '"] .field-header .field-label');
            if ($gridItem.length) {
                $gridItem.text(field.label || 'Unnamed Field');
            }
        });

        // Field Name (for form submission key)
        const $name = $('<div class="property-item">');
        $name.append('<label>Field Name <span style="font-size: 12px; color: #666;">(for submissions)</span></label>');
        $name.append('<input type="text" id="field-name" class="regular-text" value="' + escapeHtml(field.name || '') + '" placeholder="auto-generated if empty">');
        $props.append($name);
        $('#field-name').off('input').on('input', function () {
            field.name = $(this).val();
        });

        // Type (read-only)
        const $type = $('<div class="property-item">');
        $type.append('<label>Type</label>');
        $type.append('<input type="text" class="regular-text" value="' + field.type + '" disabled>');
        $props.append($type);

        // Required checkbox
        const $required = $('<div class="property-item">');
        $required.append('<label><input type="checkbox" id="field-required" ' + (field.required ? 'checked' : '') + '> Required</label>');
        $props.append($required);
        $('#field-required').off('change').on('change', function () {
            field.required = $(this).is(':checked');
        });

        // Placeholder
        const $placeholder = $('<div class="property-item">');
        $placeholder.append('<label>Placeholder Text</label>');
        $placeholder.append('<input type="text" id="field-placeholder" class="regular-text" value="' + escapeHtml(field.placeholder || '') + '">');
        $props.append($placeholder);
        $('#field-placeholder').off('input').on('input', function () {
            field.placeholder = $(this).val();
        });

        // Options (for select, radio, checkbox)
        if (['select', 'radio', 'checkbox'].includes(field.type)) {
            const $options = $('<div class="property-item">');
            $options.append('<label>Options <span style="font-size: 12px; color: #666;">(one per line)</span></label>');
            const optionsText = (field.options || []).join('\n');
            $options.append('<textarea id="field-options" class="large-text" rows="5" placeholder="Option 1&#10;Option 2&#10;Option 3">' + escapeHtml(optionsText) + '</textarea>');
            $props.append($options);
            $('#field-options').off('input').on('input', function () {
                field.options = $(this).val().split('\n').filter(o => o.trim()).map(o => o.trim());
            });
        }

        // Helper text
        const $helper = $('<p class="property-helper">💡 Changes to label and options update immediately. Click Save Form to persist changes.</p>');
        $props.append($helper);
    }

    /**
     * Add field
     */
    function addField(type) {
        const page = formData.pages[currentPageIndex];
        if (!page) return;

        const field = {
            id: 'field_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
            name: '',
            label: 'New ' + type.charAt(0).toUpperCase() + type.slice(1) + ' Field',
            type: type,
            required: false,
            placeholder: '',
            options: type === 'select' || type === 'radio' || type === 'checkbox' ? ['Option 1', 'Option 2', 'Option 3'] : []
        };

        page.fields.push(field);
        currentFieldIndex = page.fields.length - 1;
        renderCurrentPage();
        renderFieldProperties();
    }

    /**
     * Add page
     */
    function addPage() {
        const newPage = {
            pageNumber: formData.pages.length + 1,
            fields: [],
            webhook: { enabled: false, url: '', method: 'POST' },
            customJS: ''
        };

        formData.pages.push(newPage);
        currentPageIndex = formData.pages.length - 1;
        currentFieldIndex = null;
        renderPages();
        renderCurrentPage();
    }

    /**
     * Save form
     */
    function saveForm() {
        const formName = $('#form-name').val().trim();
        const formSlug = $('#form-slug').val().trim();

        if (!formName) {
            alert('Please enter a form name');
            return;
        }

        if (!formSlug) {
            alert('Please enter a form slug');
            return;
        }

        // Ensure top-level name in form_config to align with backend expectations
        formData.name = formName;

        // Validate at least one page with fields
        let totalFields = 0;
        formData.pages.forEach(page => totalFields += (page.fields || []).length);

        if (totalFields === 0) {
            alert('Please add at least one field to the form');
            return;
        }

        // Sanitize all field properties before saving
        formData.pages.forEach(function (page, pageIndex) {
            page.fields.forEach(function (field, fieldIndex) {
                field.label = field.label || '';
                field.name = field.name || '';
                field.placeholder = field.placeholder || '';
                field.required = !!field.required;
                field.options = Array.isArray(field.options) ? field.options : [];
            });
        });

        const data = {
            form_name: formName,
            form_slug: formSlug,
            form_config: formData,
            nonce: formBuilderAdmin.saveNonce
        };

        // Check if editing
        const urlParams = new URLSearchParams(window.location.search);
        const formId = urlParams.get('form_id');
        if (formId) {
            data.id = parseInt(formId);
        }

        $.ajax({
            url: formBuilderAdmin.apiUrl + 'forms',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', formBuilderAdmin.nonce);
            },
            success: function (response) {
                alert('Form saved successfully!');
                if (!formId) {
                    window.location.href = formBuilderAdmin.adminUrl + '?page=form-builder&action=edit&form_id=' + response.id;
                } else {
                    // Update the copy shortcode button if it exists
                    const $copyBtn = $('#copy-shortcode');
                    if ($copyBtn.length && response.id) {
                        $copyBtn.attr('data-form-id', response.id);
                    }
                }
            },
            error: function (xhr) {
                const errorMsg = xhr.responseJSON?.message || xhr.responseText || 'Unknown error';
                alert('Error saving form: ' + errorMsg);
            }
        });
    }

    /**
     * Load forms list
     */
    function loadFormsList() {
        $.ajax({
            url: formBuilderAdmin.apiUrl + 'forms',
            method: 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', formBuilderAdmin.nonce);
            },
            success: function (response) {
                const $tbody = $('#forms-list');
                $tbody.empty();

                if (response.forms && response.forms.length > 0) {
                    response.forms.forEach(function (form) {
                        const pagesCount = form.form_config?.pages?.length || 0;
                        let fieldsCount = 0;
                        if (form.form_config?.pages) {
                            form.form_config.pages.forEach(p => fieldsCount += (p.fields || []).length);
                        }

                        const $row = $('<tr>');
                        $row.append('<td>' + escapeHtml(form.form_name) + '</td>');
                        $row.append('<td>' + escapeHtml(form.form_slug) + '</td>');
                        $row.append('<td>' + pagesCount + ' page(s)</td>');
                        $row.append('<td>' + fieldsCount + ' field(s)</td>');
                        $row.append('<td>' + new Date(form.updated_at).toLocaleDateString() + '</td>');
                        const actionsHtml = '<a href="' + formBuilderAdmin.adminUrl + '?page=form-builder&action=edit&form_id=' + form.id + '">Edit</a> | <a href="#" class="delete-form" data-id="' + form.id + '">Delete</a> | <button type="button" class="copy-shortcode-link" data-form-id="' + form.id + '">Copy Shortcode</button>';
                        $row.append('<td>' + actionsHtml + '</td>');
                        $tbody.append($row);
                    });

                    // Delete handler
                    $('.delete-form').on('click', function (e) {
                        e.preventDefault();
                        if (confirm('Are you sure you want to delete this form? This action cannot be undone.')) {
                            const formId = $(this).data('id');
                            $.ajax({
                                url: formBuilderAdmin.apiUrl + 'forms/' + formId,
                                method: 'DELETE',
                                beforeSend: function (xhr) {
                                    xhr.setRequestHeader('X-WP-Nonce', formBuilderAdmin.nonce);
                                },
                                success: function () {
                                    loadFormsList();
                                },
                                error: function () {
                                    alert('Error deleting form');
                                }
                            });
                        }
                    });

                    // Copy shortcode handler (for forms list)
                    $(document).on('click', '.copy-shortcode-link', function (e) {
                        e.preventDefault();
                        const formId = $(this).data('form-id');
                        copyShortcodeToClipboard(formId);
                    });
                } else {
                    $tbody.append('<tr><td colspan="6">No forms found. <a href="' + formBuilderAdmin.adminUrl + '?page=form-builder-new">Create one</a></td></tr>');
                }
            },
            error: function () {
                $('#forms-list').html('<tr><td colspan="6">Error loading forms</td></tr>');
            }
        });
    }

    /**
     * Slugify string
     */
    function slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * Copy shortcode to clipboard
     */
    function copyShortcodeToClipboard(formId) {
        const shortcode = '[form_builder id="' + formId + '"]';
        
        // Try modern clipboard API first
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(shortcode).then(function() {
                showCopySuccess();
            }).catch(function(err) {
                // Fallback to older method
                fallbackCopyToClipboard(shortcode);
            });
        } else {
            // Fallback for older browsers
            fallbackCopyToClipboard(shortcode);
        }
    }

    /**
     * Fallback copy method for older browsers
     */
    function fallbackCopyToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showCopySuccess();
            } else {
                showCopyError(text);
            }
        } catch (err) {
            showCopyError(text);
        }
        
        document.body.removeChild(textArea);
    }

    /**
     * Show copy success feedback
     */
    function showCopySuccess() {
        // Create or update success message
        let $message = $('#copy-shortcode-message');
        if ($message.length === 0) {
            $message = $('<div id="copy-shortcode-message" style="position: fixed; top: 32px; right: 32px; background: var(--color-success); color: white; padding: 12px 20px; border-radius: var(--radius-md); box-shadow: var(--shadow-lg); z-index: 10000; font-weight: 500; font-size: 14px; display: flex; align-items: center; gap: 8px;"></div>');
            $('body').append($message);
        }
        
        $message.html('✓ Shortcode copied to clipboard!').fadeIn();
        
        setTimeout(function() {
            $message.fadeOut(function() {
                $message.remove();
            });
        }, 2000);
    }

    /**
     * Show copy error feedback
     */
    function showCopyError(shortcode) {
        alert('Failed to copy shortcode. Please copy manually: ' + shortcode);
    }

})(jQuery);
