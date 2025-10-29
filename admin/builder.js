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
            $page.append('<button type="button" class="button-small delete-page">×</button>');
            $page.on('click', function () {
                if (!$(this).hasClass('active')) {
                    currentPageIndex = index;
                    renderPages();
                    renderCurrentPage();
                }
            });

            if (index === currentPageIndex) {
                $page.addClass('active');
            }

            $list.append($page);
        });
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
    }

    /**
     * Render field
     */
    function renderField(field, index) {
        const $field = $('<div class="field-item" data-index="' + index + '">');

        const $header = $('<div class="field-header">');
        $header.append('<span class="field-label">' + (field.label || 'Unnamed Field') + '</span>');
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
            renderCurrentPage();
        });

        return $field;
    }

    /**
     * Render page properties
     */
    function renderPageProperties() {
        const $props = $('#page-properties');
        $props.empty();

        const page = formData.pages[currentPageIndex];
        if (!page) return;

        const $webhook = $('<div class="property-group">');
        $webhook.append('<h4>Webhook</h4>');
        $webhook.append('<label><input type="checkbox" id="webhook-enabled" ' + (page.webhook.enabled ? 'checked' : '') + '> Enable Webhook</label>');
        $webhook.append('<input type="url" id="webhook-url" class="regular-text" placeholder="https://example.com/webhook" value="' + (page.webhook.url || '') + '">');

        $('#webhook-enabled').on('change', function () {
            page.webhook.enabled = $(this).is(':checked');
        });

        $('#webhook-url').on('input', function () {
            page.webhook.url = $(this).val();
        });

        $props.append($webhook);

        const $customJS = $('<div class="property-group">');
        $customJS.append('<h4>Custom JavaScript (Optional)</h4>');
        $customJS.append('<textarea id="custom-js" class="large-text code" rows="5" placeholder="// Custom JS code here">' + (page.customJS || '') + '</textarea>');

        $('#custom-js').on('input', function () {
            page.customJS = $(this).val();
        });

        $props.append($customJS);
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

        // Label
        const $label = $('<div class="property-item">');
        $label.append('<label>Label</label>');
        $label.append('<input type="text" id="field-label" class="regular-text" value="' + (field.label || '') + '">');
        $('#field-label').on('input', function () {
            console.log('Updating field label to:', $(this).val());
            field.label = $(this).val();
            console.log('Field label is now:', field.label);
            renderCurrentPage();
        });
        $props.append($label);

        // Name
        const $name = $('<div class="property-item">');
        $name.append('<label>Field Name</label>');
        $name.append('<input type="text" id="field-name" class="regular-text" value="' + (field.name || '') + '">');
        $('#field-name').on('input', function () {
            field.name = $(this).val();
        });
        $props.append($name);

        // Required
        const $required = $('<div class="property-item">');
        $required.append('<label><input type="checkbox" id="field-required" ' + (field.required ? 'checked' : '') + '> Required</label>');
        $('#field-required').on('change', function () {
            field.required = $(this).is(':checked');
        });
        $props.append($required);

        // Placeholder
        const $placeholder = $('<div class="property-item">');
        $placeholder.append('<label>Placeholder</label>');
        $placeholder.append('<input type="text" id="field-placeholder" class="regular-text" value="' + (field.placeholder || '') + '">');
        $('#field-placeholder').on('input', function () {
            field.placeholder = $(this).val();
        });
        $props.append($placeholder);

        // Options (for select, radio, checkbox)
        if (['select', 'radio', 'checkbox'].includes(field.type)) {
            const $options = $('<div class="property-item">');
            $options.append('<label>Options (one per line)</label>');
            const optionsText = (field.options || []).join('\n');
            $options.append('<textarea id="field-options" class="large-text" rows="5">' + optionsText + '</textarea>');
            $('#field-options').on('input', function () {
                field.options = $(this).val().split('\n').filter(o => o.trim());
            });
            $props.append($options);
        }
    }

    /**
     * Add field
     */
    function addField(type) {
        const page = formData.pages[currentPageIndex];
        if (!page) return;

        const field = {
            id: 'field_' + Date.now(),
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

        // Ensure all field properties are properly saved
        formData.pages.forEach(function (page, pageIndex) {
            console.log('Processing page', pageIndex, 'with', page.fields.length, 'fields');
            page.fields.forEach(function (field, fieldIndex) {
                console.log('Field', fieldIndex, 'before save:', JSON.stringify(field));
                // Make sure all properties exist and are strings/booleans as expected
                field.label = field.label || '';
                field.name = field.name || '';
                field.placeholder = field.placeholder || '';
                field.required = !!field.required;
                field.options = field.options || [];
                console.log('Field', fieldIndex, 'after save:', JSON.stringify(field));
            });
        });

        const data = {
            form_name: formName,
            form_slug: formSlug,
            form_config: formData,
            nonce: formBuilderAdmin.saveNonce
        };

        console.log('Saving form data:', JSON.stringify(data, null, 2));

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
                }
            },
            error: function (xhr) {
                alert('Error saving form: ' + (xhr.responseJSON?.message || 'Unknown error'));
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
                        const $row = $('<tr>');
                        $row.append('<td>' + form.form_name + '</td>');
                        $row.append('<td>' + form.form_slug + '</td>');
                        $row.append('<td>' + pagesCount + '</td>');
                        $row.append('<td>' + form.updated_at + '</td>');
                        $row.append('<td><a href="' + formBuilderAdmin.adminUrl + '?page=form-builder&action=edit&form_id=' + form.id + '">Edit</a> | <a href="#" class="delete-form" data-id="' + form.id + '">Delete</a></td>');
                        $tbody.append($row);
                    });
                } else {
                    $tbody.append('<tr><td colspan="5">No forms found. <a href="' + formBuilderAdmin.adminUrl + '?page=form-builder-new">Create one</a></td></tr>');
                }

                // Delete handler
                $('.delete-form').on('click', function (e) {
                    e.preventDefault();
                    if (confirm('Are you sure you want to delete this form?')) {
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
            },
            error: function () {
                $('#forms-list').html('<tr><td colspan="5">Error loading forms</td></tr>');
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

})(jQuery);

