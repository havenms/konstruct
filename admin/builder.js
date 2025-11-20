/**
 * Form Builder Admin JavaScript
 */

(function ($) {
  "use strict";

  console.log("Form Builder: JavaScript loaded - Version 1.1.0");

  let formData = {};
  let currentPageIndex = 0;
  let currentFieldIndex = null;

  /**
   * Cache Busting Utilities
   */
  const CacheBuster = {
    getTimestamp: function () {
      return Date.now();
    },

    addCacheBusterToUrl: function (url) {
      const separator = url.includes("?") ? "&" : "?";
      return url + separator + "_cb=" + this.getTimestamp();
    },

    getAjaxDefaults: function () {
      return {
        cache: false,
        headers: {
          "Cache-Control": "no-cache, no-store, must-revalidate",
          Pragma: "no-cache",
          Expires: "0",
        },
      };
    },
  };

  /**
   * Global AJAX Setup for Cache Busting
   */
  $.ajaxSetup({
    cache: false,
    beforeSend: function (xhr, settings) {
      // Add cache busting to all AJAX requests
      if (settings.url.indexOf(formBuilderAdmin.apiUrl) !== -1) {
        settings.url = CacheBuster.addCacheBusterToUrl(settings.url);
        xhr.setRequestHeader(
          "Cache-Control",
          "no-cache, no-store, must-revalidate"
        );
        xhr.setRequestHeader("Pragma", "no-cache");
        xhr.setRequestHeader("Expires", "0");
      }
    },
  });

  /**
   * Notification System
   */
  const FormBuilderNotifications = {
    container: null,

    init: function () {
      this.container = document.getElementById("form-builder-notifications");
      if (!this.container) {
        // Create container if it doesn't exist
        this.container = document.createElement("div");
        this.container.id = "form-builder-notifications";
        this.container.className = "form-builder-notifications";
        document.body.appendChild(this.container);
      }
    },

    show: function (message, type = "info", title = null, duration = 5000) {
      this.init();

      const notification = document.createElement("div");
      notification.className = `form-builder-notification ${type}`;

      const icon = this.getIcon(type);
      const hasTitle = title && title.trim() !== "";

      notification.innerHTML = `
        <div class="form-builder-notification-icon">${icon}</div>
        <div class="form-builder-notification-content">
          ${
            hasTitle
              ? `<div class="form-builder-notification-title">${this.escapeHtml(
                  title
                )}</div>`
              : ""
          }
          <div class="form-builder-notification-message">${this.escapeHtml(
            message
          )}</div>
        </div>
        <button type="button" class="form-builder-notification-close">&times;</button>
        ${
          duration > 0
            ? `<div class="form-builder-notification-progress">
          <div class="form-builder-notification-progress-bar" style="animation-duration: ${duration}ms;"></div>
        </div>`
            : ""
        }
      `;

      // Add close handler
      const closeBtn = notification.querySelector(
        ".form-builder-notification-close"
      );
      closeBtn.addEventListener("click", () => this.remove(notification));

      // Add to container
      this.container.appendChild(notification);

      // Auto remove after duration
      if (duration > 0) {
        setTimeout(() => this.remove(notification), duration);
      }

      return notification;
    },

    success: function (message, title = "Success", duration = 4000) {
      return this.show(message, "success", title, duration);
    },

    error: function (message, title = "Error", duration = 8000) {
      return this.show(message, "error", title, duration);
    },

    warning: function (message, title = "Warning", duration = 6000) {
      return this.show(message, "warning", title, duration);
    },

    info: function (message, title = null, duration = 5000) {
      return this.show(message, "info", title, duration);
    },

    remove: function (notification) {
      if (notification && notification.parentNode) {
        notification.style.animation = "slideOutRight 0.3s ease-out forwards";
        setTimeout(() => {
          if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
          }
        }, 300);
      }
    },

    clear: function () {
      if (this.container) {
        this.container.innerHTML = "";
      }
    },

    getIcon: function (type) {
      const icons = {
        success: "✓",
        error: "✕",
        warning: "⚠",
        info: "ℹ",
      };
      return icons[type] || icons.info;
    },

    escapeHtml: function (text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    },
  };

  /**
   * Confirmation Dialog System
   */
  const FormBuilderConfirm = {
    dialog: null,

    init: function () {
      this.dialog = document.getElementById("form-builder-confirm-dialog");
      if (!this.dialog) {
        // Create dialog if it doesn't exist
        this.dialog = document.createElement("div");
        this.dialog.id = "form-builder-confirm-dialog";
        this.dialog.className = "form-builder-confirm-dialog";
        this.dialog.style.display = "none";
        this.dialog.innerHTML = `
          <div class="form-builder-confirm-content">
            <div class="form-builder-confirm-header">
              <h3 id="form-builder-confirm-title" class="form-builder-confirm-title">Confirm Action</h3>
            </div>
            <div class="form-builder-confirm-body">
              <p id="form-builder-confirm-message" class="form-builder-confirm-message">Are you sure?</p>
            </div>
            <div class="form-builder-confirm-footer">
              <button type="button" id="form-builder-confirm-cancel" class="button">Cancel</button>
              <button type="button" id="form-builder-confirm-ok" class="button button-primary">Confirm</button>
            </div>
          </div>
        `;
        document.body.appendChild(this.dialog);
      }
    },

    show: function (
      message,
      title = "Confirm Action",
      okText = "Confirm",
      cancelText = "Cancel"
    ) {
      return new Promise((resolve) => {
        this.init();

        const titleEl = document.getElementById("form-builder-confirm-title");
        const messageEl = document.getElementById(
          "form-builder-confirm-message"
        );
        const okBtn = document.getElementById("form-builder-confirm-ok");
        const cancelBtn = document.getElementById(
          "form-builder-confirm-cancel"
        );

        titleEl.textContent = title;
        messageEl.textContent = message;
        okBtn.textContent = okText;
        cancelBtn.textContent = cancelText;

        // Show dialog
        this.dialog.style.display = "flex";

        // Handle buttons
        const handleOk = () => {
          this.hide();
          resolve(true);
        };

        const handleCancel = () => {
          this.hide();
          resolve(false);
        };

        // Remove existing listeners
        okBtn.removeEventListener("click", handleOk);
        cancelBtn.removeEventListener("click", handleCancel);

        // Add new listeners
        okBtn.addEventListener("click", handleOk);
        cancelBtn.addEventListener("click", handleCancel);

        // Handle ESC key and outside click
        const handleKeydown = (e) => {
          if (e.key === "Escape") {
            document.removeEventListener("keydown", handleKeydown);
            handleCancel();
          }
        };

        const handleOutsideClick = (e) => {
          if (e.target === this.dialog) {
            this.dialog.removeEventListener("click", handleOutsideClick);
            handleCancel();
          }
        };

        document.addEventListener("keydown", handleKeydown);
        this.dialog.addEventListener("click", handleOutsideClick);
      });
    },

    hide: function () {
      if (this.dialog) {
        this.dialog.style.display = "none";
      }
    },
  };

  // Initialize
  $(document).ready(function () {
    if ($("#form-data").length) {
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
    console.log("Form Builder: Initializing...");
    formData = JSON.parse($("#form-data").text());
    console.log("Form Builder: Loaded form data:", formData);

    if (!formData.pages || formData.pages.length === 0) {
      formData.pages = [
        {
          pageNumber: 1,
          fields: [],
          webhook: { enabled: false, url: "", method: "POST" },
          customJS: "",
        },
      ];
    }

    // Initialize notification settings if not present
    console.log(
      "Form Builder: Checking notifications...",
      formData.notifications
    );
    if (!formData.notifications) {
      console.log("Form Builder: Initializing notification settings...");
      formData.notifications = {
        step_notifications: {
          enabled: true,
          recipients: "",
          recipient_field: "",
          include_admin: true,
          subject: "Form Step Completed - {{form_name}}",
          message: `Hello,

A step has been completed in the form "{{form_name}}".

Step {{page_number}} was completed on {{date}}.

Submission ID: {{submission_uuid}}

{{dynamic_fields}}

Best regards,
{{site_name}}`,
        },
        submission_notifications: {
          enabled: true,
          recipients: "",
          recipient_field: "",
          include_admin: true,
          subject: "New Form Submission - {{form_name}}",
          message: `Hello,

A new form submission has been received for "{{form_name}}".

Submitted on: {{date}}.

Submission ID: {{submission_uuid}}

{{dynamic_fields}}

Best regards,
{{site_name}}`,
        },
      };
    }

    // Ensure all notification properties exist (for backward compatibility)
    if (
      formData.notifications.step_notifications &&
      !formData.notifications.step_notifications.hasOwnProperty("include_admin")
    ) {
      formData.notifications.step_notifications.include_admin = true;
    }
    if (
      formData.notifications.submission_notifications &&
      !formData.notifications.submission_notifications.hasOwnProperty(
        "include_admin"
      )
    ) {
      formData.notifications.submission_notifications.include_admin = true;
    }

    console.log(
      "Form Builder: Final notification config:",
      formData.notifications
    );

    // Normalize form data - ensure all fields have required properties
    formData.pages.forEach(function (page, pageIndex) {
      if (!page.fields) page.fields = [];

      page.fields.forEach(function (field, fieldIndex) {
        // Ensure all required properties exist
        if (typeof field.id === "undefined")
          field.id = "field_" + Date.now() + "_" + fieldIndex;
        if (typeof field.name === "undefined") field.name = "";
        if (typeof field.label === "undefined")
          field.label = field.type
            ? "New " +
              field.type.charAt(0).toUpperCase() +
              field.type.slice(1) +
              " Field"
            : "Unnamed Field";
        if (typeof field.type === "undefined") field.type = "text";
        if (typeof field.required === "undefined") field.required = false;
        if (typeof field.placeholder === "undefined") field.placeholder = "";
        if (typeof field.options === "undefined") field.options = [];

        // Ensure webhook settings exist for page
        if (typeof page.webhook === "undefined") {
          page.webhook = { enabled: false, url: "", method: "POST" };
        }
        if (typeof page.customJS === "undefined") {
          page.customJS = "";
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
    $("#save-form").on("click", saveForm);

    // Add page
    $("#add-page").on("click", addPage);

    // Field type buttons
    $(".field-type-btn").on("click", function () {
      addField($(this).data("type"));
    });

    // Form name generates slug
    $("#form-name").on("input", function () {
      if (!$("#form-slug").val()) {
        $("#form-slug").val(slugify($(this).val()));
      }
    });

    // Copy shortcode button (for form editor)
    $(document).on("click", "#copy-shortcode", function () {
      const formId = $(this).data("form-id");
      copyShortcodeToClipboard(formId);
    });
  }

  /**
   * Render pages list
   */
  function renderPages() {
    const $list = $("#pages-list");
    $list.empty();

    formData.pages.forEach((page, index) => {
      const $page = $('<div class="page-item" data-index="' + index + '">');
      $page.append("<span>Page " + (index + 1) + "</span>");
      $page.append(
        '<button type="button" class="button-small delete-page" title="Delete page">×</button>'
      );

      // Page selection click handler
      $page.on("click", function (e) {
        if (!$(e.target).hasClass("delete-page")) {
          if (!$(this).hasClass("active")) {
            currentPageIndex = index;
            currentFieldIndex = null;
            renderPages();
            renderCurrentPage();
          }
        }
      });

      if (index === currentPageIndex) {
        $page.addClass("active");
      }

      $list.append($page);
    });

    // Attach delete handlers after rendering
    setTimeout(() => {
      $(".delete-page").on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        const pageIdx = $(this).closest(".page-item").data("index");
        if (formData.pages.length === 1) {
          FormBuilderNotifications.error(
            "You must have at least one page",
            "Cannot Delete Page"
          );
          return;
        }

        FormBuilderConfirm.show(
          "Are you sure you want to delete this page? All fields will be removed.",
          "Delete Page",
          "Delete",
          "Cancel"
        ).then((confirmed) => {
          if (confirmed) {
            formData.pages.splice(pageIdx, 1);
            if (currentPageIndex >= formData.pages.length) {
              currentPageIndex = formData.pages.length - 1;
            }
            currentFieldIndex = null;
            renderPages();
            renderCurrentPage();
          }
        });
      });
    }, 0);
  }

  /**
   * Render current page editor
   */
  function renderCurrentPage() {
    const $editor = $("#pages-editor");
    $editor.empty();

    const page = formData.pages[currentPageIndex];
    if (!page) return;

    const $pageEditor = $('<div class="page-editor">');
    $pageEditor.append("<h3>Page " + (currentPageIndex + 1) + "</h3>");

    const $fieldsList = $('<div class="fields-list">');

    if (page.fields.length === 0) {
      $fieldsList.append(
        '<p class="no-fields">No fields yet. Click a field type to add one.</p>'
      );
    } else {
      page.fields.forEach((field, index) => {
        $fieldsList.append(renderField(field, index));
      });
    }

    $pageEditor.append($fieldsList);
    $editor.append($pageEditor);

    renderPageProperties();

    // Update recipient field dropdowns after rendering (delayed to ensure DOM is ready)
    setTimeout(function () {
      populateRecipientFields();
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
    $moveButtons.append(
      '<button type="button" class="button-icon move-up-btn" title="Move Up" ' +
        (isFirst ? "disabled" : "") +
        '><svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 4L3 8H11L7 4Z" fill="currentColor"/></svg></button>'
    );
    $moveButtons.append(
      '<button type="button" class="button-icon move-down-btn" title="Move Down" ' +
        (isLast ? "disabled" : "") +
        '><svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 10L11 6H3L7 10Z" fill="currentColor"/></svg></button>'
    );

    $header.append($moveButtons);
    $header.append(
      '<span class="field-label">' +
        escapeHtml(field.label || "Unnamed Field") +
        "</span>"
    );
    $header.append('<span class="field-type">' + field.type + "</span>");
    $header.append(
      '<button type="button" class="button-small edit-field">Edit</button>'
    );
    $header.append(
      '<button type="button" class="button-small delete-field">Delete</button>'
    );

    $field.append($header);

    $field.find(".edit-field").on("click", function () {
      currentFieldIndex = index;
      renderFieldProperties();
    });

    $field.find(".delete-field").on("click", function () {
      formData.pages[currentPageIndex].fields.splice(index, 1);
      currentFieldIndex = null;
      renderCurrentPage();
    });

    // Move up handler
    $field.find(".move-up-btn").on("click", function () {
      if (index > 0) {
        moveField(index, index - 1);
      }
    });

    // Move down handler
    $field.find(".move-down-btn").on("click", function () {
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
    const $props = $("#page-properties");
    $props.empty();

    const page = formData.pages[currentPageIndex];
    if (!page) return;

    // Add accordions for different settings sections
    const $accordions = $('<div class="form-builder-accordions">');

    // Webhook Settings Accordion
    const $webhookAccordion = $('<div class="accordion-item">');
    const $webhookHeader = $(
      '<button type="button" class="accordion-header" aria-expanded="true">'
    );
    $webhookHeader.append('<span class="accordion-title">Webhook</span>');
    $webhookHeader.append(
      '<svg class="accordion-icon" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 9L1 4H11L6 9Z" fill="currentColor"/></svg>'
    );
    $webhookAccordion.append($webhookHeader);
    const $webhookContent = $(
      '<div class="accordion-content" style="display: block;">'
    );
    const $webhook = $('<div class="property-group">');
    $webhook.append("<h4>Webhook Settings</h4>");
    $webhook.append(
      '<label><input type="checkbox" id="webhook-enabled" ' +
        (page.webhook.enabled ? "checked" : "") +
        "> Enable Webhook</label>"
    );
    $webhook.append(
      '<input type="url" id="webhook-url" class="regular-text" placeholder="https://example.com/webhook" value="' +
        escapeHtml(page.webhook.url || "") +
        '">'
    );
    $webhookContent.append($webhook);
    $webhookAccordion.append($webhookContent);
    $accordions.append($webhookAccordion);

    // Email Notifications Accordion
    const $emailAccordion = $('<div class="accordion-item">');
    const $emailHeader = $(
      '<button type="button" class="accordion-header" aria-expanded="false">'
    );
    $emailHeader.append(
      '<span class="accordion-title">Email Notifications</span>'
    );
    $emailHeader.append(
      '<svg class="accordion-icon" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 9L1 4H11L6 9Z" fill="currentColor"/></svg>'
    );
    $emailAccordion.append($emailHeader);
    const $emailContent = $(
      '<div class="accordion-content" style="display: none;">'
    );

    // Step Notifications
    const $stepNotifications = $('<div class="property-group">');
    $stepNotifications.append("<h4>Step Completion Notifications</h4>");
    $stepNotifications.append(
      '<label><input type="checkbox" id="step-notifications-enabled" ' +
        (formData.notifications.step_notifications.enabled ? "checked" : "") +
        "> <span>Enable step completion emails</span></label>"
    );

    const $stepConfig = $(
      '<div class="notification-config" style="' +
        (formData.notifications.step_notifications.enabled
          ? ""
          : "display:none") +
        '">'
    );

    // Recipients section
    $stepConfig.append(
      '<label>Email Recipients:<br><input type="text" id="step-recipients" class="regular-text" value="' +
        escapeHtml(formData.notifications.step_notifications.recipients || "") +
        '" placeholder="admin@example.com, manager@example.com"><small>Enter multiple emails separated by commas</small></label>'
    );

    $stepConfig.append(
      '<label>Or use email from form field:<br><select id="step-recipient-field"><option value="">Select field...</option></select></label>'
    );

    $stepConfig.append(
      '<label><input type="checkbox" id="step-include-admin" ' +
        (formData.notifications.step_notifications.include_admin
          ? "checked"
          : "") +
        "> <span>Also send to site administrator</span></label>"
    );

    // Email content section
    $stepConfig.append(
      '<label>Email Subject:<br><input type="text" id="step-subject" class="regular-text" value="' +
        escapeHtml(formData.notifications.step_notifications.subject || "") +
        '" placeholder="Form Step Completed"></label>'
    );

    $stepConfig.append(
      '<label>Email Message:<br><textarea id="step-message" class="large-text" rows="5" placeholder="Your custom message here...">' +
        escapeHtml(formData.notifications.step_notifications.message || "") +
        "</textarea><small>Use placeholders like {{form_name}}, {{page_number}}, {{date}} for dynamic content</small></label>"
    );
    $stepNotifications.append($stepConfig);
    $emailContent.append($stepNotifications);

    // Submission Notifications
    const $submissionNotifications = $('<div class="property-group">');
    $submissionNotifications.append("<h4>Final Submission Notifications</h4>");
    $submissionNotifications.append(
      '<label><input type="checkbox" id="submission-notifications-enabled" ' +
        (formData.notifications.submission_notifications.enabled
          ? "checked"
          : "") +
        "> <span>Enable final submission emails</span></label>"
    );

    const $submissionConfig = $(
      '<div class="notification-config" style="' +
        (formData.notifications.submission_notifications.enabled
          ? ""
          : "display:none") +
        '">'
    );

    // Recipients section
    $submissionConfig.append(
      '<label>Email Recipients:<br><input type="text" id="submission-recipients" class="regular-text" value="' +
        escapeHtml(
          formData.notifications.submission_notifications.recipients || ""
        ) +
        '" placeholder="admin@example.com, manager@example.com"><small>Enter multiple emails separated by commas</small></label>'
    );

    $submissionConfig.append(
      '<label>Or use email from form field:<br><select id="submission-recipient-field"><option value="">Select field...</option></select></label>'
    );

    $submissionConfig.append(
      '<label><input type="checkbox" id="submission-include-admin" ' +
        (formData.notifications.submission_notifications.include_admin
          ? "checked"
          : "") +
        "> <span>Also send to site administrator</span></label>"
    );

    // Email content section
    $submissionConfig.append(
      '<label>Email Subject:<br><input type="text" id="submission-subject" class="regular-text" value="' +
        escapeHtml(
          formData.notifications.submission_notifications.subject || ""
        ) +
        '" placeholder="New Form Submission"></label>'
    );

    $submissionConfig.append(
      '<label>Email Message:<br><textarea id="submission-message" class="large-text" rows="5" placeholder="Your custom message here...">' +
        escapeHtml(
          formData.notifications.submission_notifications.message || ""
        ) +
        "</textarea><small>Use placeholders like {{form_name}}, {{submission_uuid}}, {{date}} for dynamic content</small></label>"
    );
    $submissionNotifications.append($submissionConfig);
    $emailContent.append($submissionNotifications);

    // Email placeholders help
    const $placeholders = $('<div class="property-group">');
    $placeholders.append("<h4>Available Placeholders</h4>");
    $placeholders.append(
      "<p><small><strong>System Placeholders:</strong> {{form_name}}, {{page_number}}, {{submission_uuid}}, {{date}}, {{site_name}}, {{site_url}}, {{admin_email}}</small></p>"
    );
    $placeholders.append(
      "<p><small><strong>Dynamic Fields:</strong> Use {{dynamic_fields}} to automatically include all form fields from the current step, or use individual field placeholders like {{first_name}}, {{email}}, etc.</small></p>"
    );

    // Generate current page field placeholders
    const currentFields = getCurrentPageFields();
    if (currentFields.length > 0) {
      let fieldPlaceholders =
        "<p><small><strong>Current Page Field Placeholders:</strong> ";
      fieldPlaceholders += currentFields
        .map((field) => `{{${field.name || field.id}}}`)
        .join(", ");
      fieldPlaceholders += "</small></p>";
      $placeholders.append(fieldPlaceholders);
    }

    $emailContent.append($placeholders);

    // Test email functionality
    const $testEmail = $('<div class="property-group">');
    $testEmail.append("<h4>Test Email Configuration</h4>");
    $testEmail.append(
      '<label>Send test email to:<br><input type="email" id="test-email-address" class="regular-text" placeholder="your@email.com"></label>'
    );
    $testEmail.append(
      '<button type="button" id="send-test-email" class="button">Send Test Email</button>'
    );
    $testEmail.append(
      '<button type="button" id="debug-form-config" class="button" style="margin-left: 10px;">Debug Form Config</button>'
    );
    $testEmail.append(
      '<div id="test-email-result" style="margin-top: 10px;"></div>'
    );
    $emailContent.append($testEmail);
    $emailAccordion.append($emailContent);
    $accordions.append($emailAccordion);

    // Custom JavaScript Accordion
    const $jsAccordion = $('<div class="accordion-item">');
    const $jsHeader = $(
      '<button type="button" class="accordion-header" aria-expanded="false">'
    );
    $jsHeader.append('<span class="accordion-title">Custom JS</span>');
    $jsHeader.append(
      '<svg class="accordion-icon" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 9L1 4H11L6 9Z" fill="currentColor"/></svg>'
    );
    $jsAccordion.append($jsHeader);
    const $jsContent = $(
      '<div class="accordion-content" style="display: none;">'
    );
    const $customJS = $('<div class="property-group">');
    $customJS.append("<h4>Custom JavaScript (Optional)</h4>");
    $customJS.append(
      '<textarea id="custom-js" class="large-text code" rows="5" placeholder="// Custom JS code here">' +
        (page.customJS || "") +
        "</textarea>"
    );
    $jsContent.append($customJS);
    $jsAccordion.append($jsContent);
    $accordions.append($jsAccordion);

    $props.append($accordions);

    // Populate recipient field dropdowns with email fields from all pages
    populateRecipientFields();

    // Accordion toggle functionality
    $(".accordion-header")
      .off("click")
      .on("click", function (e) {
        e.preventDefault();
        const $header = $(this);
        const $item = $header.closest(".accordion-item");
        const $content = $item.find(".accordion-content");
        const $icon = $header.find(".accordion-icon");
        const isExpanded = $header.attr("aria-expanded") === "true";

        // Toggle current accordion
        if (isExpanded) {
          $content.slideUp(200);
          $header.attr("aria-expanded", "false");
          $icon.css("transform", "rotate(0deg)");
        } else {
          $content.slideDown(200);
          $header.attr("aria-expanded", "true");
          $icon.css("transform", "rotate(180deg)");
        }
      });

    // Bind webhook events
    $("#webhook-enabled")
      .off("change")
      .on("change", function () {
        page.webhook.enabled = $(this).is(":checked");
      });
    $("#webhook-url")
      .off("input")
      .on("input", function () {
        page.webhook.url = $(this).val();
      });

    // Bind email notification events
    $("#step-notifications-enabled")
      .off("change")
      .on("change", function () {
        formData.notifications.step_notifications.enabled =
          $(this).is(":checked");
        $(this)
          .closest(".property-group")
          .find(".notification-config")
          .toggle($(this).is(":checked"));
      });

    $("#submission-notifications-enabled")
      .off("change")
      .on("change", function () {
        formData.notifications.submission_notifications.enabled =
          $(this).is(":checked");
        $(this)
          .closest(".property-group")
          .find(".notification-config")
          .toggle($(this).is(":checked"));
      });

    // Step notification field bindings
    $("#step-recipients")
      .off("input")
      .on("input", function () {
        formData.notifications.step_notifications.recipients = $(this).val();
      });
    $("#step-recipient-field")
      .off("change")
      .on("change", function () {
        formData.notifications.step_notifications.recipient_field =
          $(this).val();
      });
    $("#step-include-admin")
      .off("change")
      .on("change", function () {
        formData.notifications.step_notifications.include_admin =
          $(this).is(":checked");
      });
    $("#step-subject")
      .off("input")
      .on("input", function () {
        formData.notifications.step_notifications.subject = $(this).val();
      });
    $("#step-message")
      .off("input")
      .on("input", function () {
        formData.notifications.step_notifications.message = $(this).val();
      });

    // Submission notification field bindings
    $("#submission-recipients")
      .off("input")
      .on("input", function () {
        formData.notifications.submission_notifications.recipients =
          $(this).val();
      });
    $("#submission-recipient-field")
      .off("change")
      .on("change", function () {
        formData.notifications.submission_notifications.recipient_field =
          $(this).val();
      });
    $("#submission-include-admin")
      .off("change")
      .on("change", function () {
        formData.notifications.submission_notifications.include_admin =
          $(this).is(":checked");
      });
    $("#submission-subject")
      .off("input")
      .on("input", function () {
        formData.notifications.submission_notifications.subject = $(this).val();
      });
    $("#submission-message")
      .off("input")
      .on("input", function () {
        formData.notifications.submission_notifications.message = $(this).val();
      });

    // Bind custom JS events
    $("#custom-js")
      .off("input")
      .on("input", function () {
        page.customJS = $(this).val();
      });

    // Test email functionality
    $("#send-test-email")
      .off("click")
      .on("click", function () {
        const email = $("#test-email-address").val().trim();
        const $result = $("#test-email-result");
        const $button = $(this);

        if (!email) {
          $result.html(
            '<span style="color: #d63638;">Please enter an email address</span>'
          );
          return;
        }

        $button.prop("disabled", true).text("Sending...");
        $result.html(
          '<span style="color: #646970;">Sending test email...</span>'
        );

        $.ajax(
          $.extend(CacheBuster.getAjaxDefaults(), {
            url: CacheBuster.addCacheBusterToUrl(
              formBuilderAdmin.apiUrl + "test-email"
            ),
            method: "POST",
            headers: {
              "X-WP-Nonce": formBuilderAdmin.nonce,
              "Cache-Control": "no-cache, no-store, must-revalidate",
              Pragma: "no-cache",
            },
            contentType: "application/json",
            data: JSON.stringify({
              email: email,
            }),
            success: function (response) {
              console.log("Test email success:", response);
              $result.html(
                '<span style="color: #00a32a;">✓ Test email sent successfully!</span>'
              );
            },
            error: function (xhr, status, error) {
              console.error("Test email failed:", xhr.responseText);
              let message = "Failed to send test email";
              if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
              }
              $result.html(
                '<span style="color: #d63638;">✗ ' + message + "</span>"
              );
            },
            complete: function () {
              $button.prop("disabled", false).text("Send Test Email");
            },
          })
        );
      });

    // Debug form config functionality
    $("#debug-form-config")
      .off("click")
      .on("click", function () {
        const $result = $("#test-email-result");
        const $button = $(this);

        // Get form ID from URL or form data
        const urlParams = new URLSearchParams(window.location.search);
        const formId = urlParams.get("form_id");

        if (!formId) {
          $result.html('<span style="color: #d63638;">No form ID found</span>');
          return;
        }

        $button.prop("disabled", true).text("Debugging...");
        $result.html(
          '<span style="color: #646970;">Checking form configuration...</span>'
        );

        $.ajax({
          url: formBuilderAdmin.apiUrl + "debug-form/" + formId,
          method: "GET",
          headers: {
            "X-WP-Nonce": formBuilderAdmin.nonce,
          },
          success: function (response) {
            console.log("Debug form config:", response);
            let html = "<strong>Form Debug Info:</strong><br>";
            html += "Form ID: " + response.form_id + "<br>";
            html += "Form Name: " + response.form_name + "<br>";
            html +=
              "Notifications Config: " +
              (response.notifications !== "Not set" ? "Found" : "Missing") +
              "<br>";
            if (
              response.notifications !== "Not set" &&
              response.notifications.step_notifications
            ) {
              html +=
                "Step Notifications Enabled: " +
                (response.notifications.step_notifications.enabled
                  ? "Yes"
                  : "No") +
                "<br>";
            }
            $result.html(
              '<div style="font-size: 12px; background: #f0f0f1; padding: 10px; border-radius: 4px;">' +
                html +
                "</div>"
            );
          },
          error: function (xhr, status, error) {
            console.error("Debug failed:", xhr.responseText);
            $result.html(
              '<span style="color: #d63638;">Debug failed: ' + error + "</span>"
            );
          },
          complete: function () {
            $button.prop("disabled", false).text("Debug Form Config");
          },
        });
      });
  }

  /**
   * Populate recipient field dropdowns with email fields
   */
  function populateRecipientFields() {
    const $stepField = $("#step-recipient-field");
    const $submissionField = $("#submission-recipient-field");

    $stepField.empty().append('<option value="">Select field...</option>');
    $submissionField
      .empty()
      .append('<option value="">Select field...</option>');

    formData.pages.forEach(function (page) {
      page.fields.forEach(function (field) {
        if (field.type === "email") {
          $stepField.append(
            '<option value="' +
              escapeHtml(field.name) +
              '">' +
              escapeHtml(field.label) +
              "</option>"
          );
          $submissionField.append(
            '<option value="' +
              escapeHtml(field.name) +
              '">' +
              escapeHtml(field.label) +
              "</option>"
          );
        }
      });
    });

    // Set current values
    $stepField.val(
      formData.notifications.step_notifications.recipient_field || ""
    );
    $submissionField.val(
      formData.notifications.submission_notifications.recipient_field || ""
    );
  }

  /**
   * Render field properties
   */
  function renderFieldProperties() {
    if (currentFieldIndex === null) return;

    const field = formData.pages[currentPageIndex].fields[currentFieldIndex];
    if (!field) return;

    const $props = $("#page-properties");
    $props.empty();

    $props.append("<h4>Field Properties</h4>");

    // Label (required)
    const $label = $('<div class="property-item">');
    $label.append(
      '<label>Label <span style="color: #ff3b30;">*</span></label>'
    );
    $label.append(
      '<input type="text" id="field-label" class="regular-text" value="' +
        escapeHtml(field.label || "") +
        '">'
    );
    $props.append($label);
    // Bind after append
    $("#field-label")
      .off("input")
      .on("input", function () {
        field.label = $(this).val();
        // Update label text in the grid without full re-render to keep focus
        const $gridItem = $(
          '.field-item[data-index="' +
            currentFieldIndex +
            '"] .field-header .field-label'
        );
        if ($gridItem.length) {
          $gridItem.text(field.label || "Unnamed Field");
        }
      });

    // Field Name (for form submission key) - skip for label fields
    if (field.type !== "label") {
      const $name = $('<div class="property-item">');
      $name.append(
        '<label>Field Name <span style="font-size: 12px; color: #666;">(for submissions)</span></label>'
      );
      $name.append(
        '<input type="text" id="field-name" class="regular-text" value="' +
          escapeHtml(field.name || "") +
          '" placeholder="auto-generated if empty">'
      );
      $props.append($name);
      $("#field-name")
        .off("input")
        .on("input", function () {
          const oldName = field.name;
          field.name = $(this).val();
          if (formBuilderAdmin.isDev) {
            console.log('[Form Builder] Field name updated from "' + oldName + '" to "' + field.name + '"');
          }
        });
    }

    // Type (read-only)
    const $type = $('<div class="property-item">');
    $type.append("<label>Type</label>");
    $type.append(
      '<input type="text" class="regular-text" value="' +
        field.type +
        '" disabled>'
    );
    $props.append($type);

    // Required checkbox - skip for label fields
    if (field.type !== "label") {
      const $required = $('<div class="property-item">');
      $required.append(
        '<label><input type="checkbox" id="field-required" ' +
          (field.required ? "checked" : "") +
          "> Required</label>"
      );
      $props.append($required);
      $("#field-required")
        .off("change")
        .on("change", function () {
          field.required = $(this).is(":checked");
        });
    }

    // Placeholder - skip for label fields
    if (field.type !== "label") {
      const $placeholder = $('<div class="property-item">');
      $placeholder.append("<label>Placeholder Text</label>");
      $placeholder.append(
        '<input type="text" id="field-placeholder" class="regular-text" value="' +
          escapeHtml(field.placeholder || "") +
          '">'
      );
      $props.append($placeholder);
      $("#field-placeholder")
        .off("input")
        .on("input", function () {
          field.placeholder = $(this).val();
        });
    }

    // Options (for select, radio, checkbox)
    if (["select", "radio", "checkbox"].includes(field.type)) {
      const $options = $('<div class="property-item">');
      $options.append(
        '<label>Options <span style="font-size: 12px; color: #666;">(one per line)</span></label>'
      );
      const optionsText = (field.options || []).join("\n");
      $options.append(
        '<textarea id="field-options" class="large-text" rows="5" placeholder="Option 1&#10;Option 2&#10;Option 3">' +
          escapeHtml(optionsText) +
          "</textarea>"
      );
      $props.append($options);
      $("#field-options")
        .off("input")
        .on("input", function () {
          field.options = $(this)
            .val()
            .split("\n")
            .filter((o) => o.trim())
            .map((o) => o.trim());
        });
    }

    // Link field specific properties
    if (field.type === "link") {
      // URL
      const $url = $('<div class="property-item">');
      $url.append("<label>URL</label>");
      $url.append(
        '<input type="url" id="field-url" class="regular-text" value="' +
          escapeHtml(field.url || "") +
          '" placeholder="https://example.com">'
      );
      $props.append($url);
      $("#field-url")
        .off("input")
        .on("input", function () {
          field.url = $(this).val();
        });

      // Button Text
      const $buttonText = $('<div class="property-item">');
      $buttonText.append("<label>Button Text</label>");
      $buttonText.append(
        '<input type="text" id="field-button-text" class="regular-text" value="' +
          escapeHtml(field.button_text || "") +
          '" placeholder="Click Here">'
      );
      $props.append($buttonText);
      $("#field-button-text")
        .off("input")
        .on("input", function () {
          field.button_text = $(this).val();
        });

      // Target
      const $target = $('<div class="property-item">');
      $target.append("<label>Open Link In</label>");
      const targetValue = field.target || "same";
      $target.append(
        '<select id="field-target" class="regular-text"><option value="same"' +
          (targetValue === "same" ? " selected" : "") +
          '>Same Tab</option><option value="new"' +
          (targetValue === "new" ? " selected" : "") +
          ">New Tab</option></select>"
      );
      $props.append($target);
      $("#field-target")
        .off("change")
        .on("change", function () {
          field.target = $(this).val();
        });

      // Button Style
      const $style = $('<div class="property-item">');
      $style.append("<label>Button Style</label>");
      const styleValue = field.button_style || "primary";
      $style.append(
        '<select id="field-button-style" class="regular-text"><option value="primary"' +
          (styleValue === "primary" ? " selected" : "") +
          '>Primary (Blue)</option><option value="secondary"' +
          (styleValue === "secondary" ? " selected" : "") +
          '>Secondary (Gray)</option><option value="success"' +
          (styleValue === "success" ? " selected" : "") +
          '>Success (Green)</option><option value="outline"' +
          (styleValue === "outline" ? " selected" : "") +
          ">Outline</option></select>"
      );
      $props.append($style);
      $("#field-button-style")
        .off("change")
        .on("change", function () {
          field.button_style = $(this).val();
        });
    }

    // Label field specific properties
    if (field.type === "label") {
      // HTML Tag
      const $tag = $('<div class="property-item">');
      $tag.append("<label>HTML Tag</label>");
      const tagValue = field.label_tag || "h3";
      $tag.append(
        '<select id="field-label-tag" class="regular-text">' +
          '<option value="h1"' +
          (tagValue === "h1" ? " selected" : "") +
          ">Heading 1 (h1)</option>" +
          '<option value="h2"' +
          (tagValue === "h2" ? " selected" : "") +
          ">Heading 2 (h2)</option>" +
          '<option value="h3"' +
          (tagValue === "h3" ? " selected" : "") +
          ">Heading 3 (h3)</option>" +
          '<option value="h4"' +
          (tagValue === "h4" ? " selected" : "") +
          ">Heading 4 (h4)</option>" +
          '<option value="h5"' +
          (tagValue === "h5" ? " selected" : "") +
          ">Heading 5 (h5)</option>" +
          '<option value="h6"' +
          (tagValue === "h6" ? " selected" : "") +
          ">Heading 6 (h6)</option>" +
          '<option value="p"' +
          (tagValue === "p" ? " selected" : "") +
          ">Paragraph (p)</option>" +
          '<option value="div"' +
          (tagValue === "div" ? " selected" : "") +
          ">Division (div)</option>" +
          "</select>"
      );
      $props.append($tag);
      $("#field-label-tag")
        .off("change")
        .on("change", function () {
          field.label_tag = $(this).val();
        });

      // Style
      const $labelStyle = $('<div class="property-item">');
      $labelStyle.append("<label>Style</label>");
      const styleValue = field.label_style || "";
      $labelStyle.append(
        '<select id="field-label-style" class="regular-text">' +
          '<option value=""' +
          (styleValue === "" ? " selected" : "") +
          ">Default</option>" +
          '<option value="center"' +
          (styleValue === "center" ? " selected" : "") +
          ">Centered</option>" +
          '<option value="large"' +
          (styleValue === "large" ? " selected" : "") +
          ">Large Text</option>" +
          '<option value="small"' +
          (styleValue === "small" ? " selected" : "") +
          ">Small Text</option>" +
          '<option value="bold"' +
          (styleValue === "bold" ? " selected" : "") +
          ">Bold</option>" +
          '<option value="muted"' +
          (styleValue === "muted" ? " selected" : "") +
          ">Muted</option>" +
          "</select>"
      );
      $props.append($labelStyle);
      $("#field-label-style")
        .off("change")
        .on("change", function () {
          field.label_style = $(this).val();
        });
    }

    // Helper text
    const $helper = $(
      '<p class="property-helper">💡 Changes to label and options update immediately. Click Save Form to persist changes.</p>'
    );
    $props.append($helper);
  }

  /**
   * Add field
   */
  function addField(type) {
    const page = formData.pages[currentPageIndex];
    if (!page) return;

    const field = {
      id: "field_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9),
      name: "",
      label: "New " + type.charAt(0).toUpperCase() + type.slice(1) + " Field",
      type: type,
      required: false,
      placeholder: "",
      options:
        type === "select" || type === "radio" || type === "checkbox"
          ? ["Option 1", "Option 2", "Option 3"]
          : [],
    };

    // Add label-specific defaults
    if (type === "label") {
      field.label = "Enter your heading text here";
      field.label_tag = "h3";
      field.label_style = "";
    }

    // Add link-specific defaults
    if (type === "link") {
      field.url = "https://example.com";
      field.button_text = "Click Here";
      field.target = "same";
      field.button_style = "primary";
    }

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
      webhook: { enabled: false, url: "", method: "POST" },
      customJS: "",
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
    const formName = $("#form-name").val().trim();
    const formSlug = $("#form-slug").val().trim();

    if (!formName) {
      FormBuilderNotifications.error(
        "Please enter a form name",
        "Validation Error"
      );
      $("#form-name").focus();
      return;
    }

    if (!formSlug) {
      FormBuilderNotifications.error(
        "Please enter a form slug",
        "Validation Error"
      );
      $("#form-slug").focus();
      return;
    }

    // Ensure top-level name in form_config to align with backend expectations
    formData.name = formName;

    // Validate at least one page with fields
    let totalFields = 0;
    formData.pages.forEach(
      (page) => (totalFields += (page.fields || []).length)
    );

    if (totalFields === 0) {
      FormBuilderNotifications.error(
        "Please add at least one field to the form",
        "Validation Error"
      );
      return;
    }

    // Sanitize all field properties before saving
    formData.pages.forEach(function (page, pageIndex) {
      page.fields.forEach(function (field, fieldIndex) {
        field.label = field.label || "";
        field.name = field.name || "";
        field.placeholder = field.placeholder || "";
        field.required = !!field.required;
        field.options = Array.isArray(field.options) ? field.options : [];
        
        // Log field names in dev mode
        if (formBuilderAdmin.isDev && field.name) {
          console.log('[Form Builder] Saving field on page ' + (pageIndex + 1) + ': name="' + field.name + '", label="' + field.label + '"');
        }
      });
    });

    const data = {
      form_name: formName,
      form_slug: formSlug,
      form_config: formData,
      nonce: formBuilderAdmin.saveNonce,
    };

    // Check if editing
    const urlParams = new URLSearchParams(window.location.search);
    const formId = urlParams.get("form_id");
    if (formId) {
      data.id = parseInt(formId);
    }

    // Show loading state
    const $saveBtn = $("#save-form");
    const originalText = $saveBtn.text();
    $saveBtn.prop("disabled", true).text("Saving...");

    $.ajax({
      url: formBuilderAdmin.apiUrl + "forms",
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify(data),
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", formBuilderAdmin.nonce);
      },
      success: function (response) {
        FormBuilderNotifications.success("Form saved successfully!", "Success");
        if (!formId) {
          // Show redirect notification
          FormBuilderNotifications.info(
            "Redirecting to form editor...",
            "Info",
            2000
          );
          setTimeout(() => {
            window.location.href =
              formBuilderAdmin.adminUrl +
              "?page=form-builder&action=edit&form_id=" +
              response.id;
          }, 1000);
        } else {
          // Update the copy shortcode button if it exists
          const $copyBtn = $("#copy-shortcode");
          if ($copyBtn.length && response.id) {
            $copyBtn.attr("data-form-id", response.id);
          }
        }
      },
      error: function (xhr) {
        const errorMsg =
          xhr.responseJSON?.message || xhr.responseText || "Unknown error";
        FormBuilderNotifications.error(
          "Error saving form: " + errorMsg,
          "Save Failed"
        );
      },
      complete: function () {
        // Restore button state
        $saveBtn.prop("disabled", false).text(originalText);
      },
    });
  }

  /**
   * Load forms list
   */
  function loadFormsList() {
    console.log("Loading forms list...");
    const $tbody = $("#forms-list");

    // Show loading state
    $tbody.html('<tr><td colspan="6">Loading forms...</td></tr>');

    $.ajax({
      url: formBuilderAdmin.apiUrl + "forms",
      method: "GET",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", formBuilderAdmin.nonce);
      },
      success: function (response) {
        console.log("Forms loaded:", response);
        $tbody.empty();

        if (response.forms && response.forms.length > 0) {
          response.forms.forEach(function (form) {
            const pagesCount = form.form_config?.pages?.length || 0;
            let fieldsCount = 0;
            if (form.form_config?.pages) {
              form.form_config.pages.forEach(
                (p) => (fieldsCount += (p.fields || []).length)
              );
            }

            const $row = $("<tr>");
            $row.append("<td>" + escapeHtml(form.form_name) + "</td>");
            $row.append("<td>" + escapeHtml(form.form_slug) + "</td>");
            $row.append("<td>" + pagesCount + " page(s)</td>");
            $row.append("<td>" + fieldsCount + " field(s)</td>");
            $row.append(
              "<td>" + new Date(form.updated_at).toLocaleDateString() + "</td>"
            );
            const actionsHtml =
              '<a href="' +
              formBuilderAdmin.adminUrl +
              "?page=form-builder&action=edit&form_id=" +
              form.id +
              '">Edit</a> | <a href="#" class="export-form" data-id="' +
              form.id +
              '" data-slug="' +
              escapeHtml(form.form_slug) +
              '">Export</a> | <a href="#" class="delete-form" data-id="' +
              form.id +
              '">Delete</a> | <button type="button" class="copy-shortcode-link" data-form-id="' +
              form.id +
              '">Copy Shortcode</button>';
            $row.append("<td>" + actionsHtml + "</td>");
            $tbody.append($row);
          });

          // Delete handler - use event delegation to avoid duplicate bindings
          $tbody
            .off("click", ".delete-form")
            .on("click", ".delete-form", function (e) {
              e.preventDefault();
              const $deleteBtn = $(this);
              const formId = $deleteBtn.data("id");
              const formName = $deleteBtn.closest("tr").find("td:first").text();
              const $row = $deleteBtn.closest("tr");

              FormBuilderConfirm.show(
                `Are you sure you want to delete the form "${formName}"? This action cannot be undone.`,
                "Delete Form",
                "Delete",
                "Cancel"
              ).then((confirmed) => {
                if (confirmed) {
                  // Add loading state to the row
                  $row.addClass("deleting").css("opacity", "0.5");
                  $deleteBtn.text("Deleting...");

                  $.ajax({
                    url: formBuilderAdmin.apiUrl + "forms/" + formId,
                    method: "DELETE",
                    beforeSend: function (xhr) {
                      xhr.setRequestHeader(
                        "X-WP-Nonce",
                        formBuilderAdmin.nonce
                      );
                    },
                    success: function (response) {
                      console.log("Form deleted successfully:", formId);

                      // Remove the row immediately for better UX
                      $row.fadeOut(300, function () {
                        $row.remove();

                        // Check if no forms left
                        const remainingRows = $tbody.find(
                          "tr:not(.no-forms-row)"
                        );
                        if (remainingRows.length === 0) {
                          $tbody.append(
                            '<tr class="no-forms-row"><td colspan="6">No forms found. <a href="' +
                              formBuilderAdmin.adminUrl +
                              '?page=form-builder-new">Create one</a></td></tr>'
                          );
                        }
                      });

                      FormBuilderNotifications.success(
                        `Form "${formName}" deleted successfully`,
                        "Success"
                      );

                      // Fallback: refresh the entire list after a delay to ensure consistency
                      setTimeout(() => {
                        console.log("Refreshing forms list as fallback...");
                        loadFormsList();
                      }, 2000);
                    },
                    error: function (xhr) {
                      // Restore row state on error
                      $row.removeClass("deleting").css("opacity", "1");
                      $deleteBtn.text("Delete");

                      const errorMsg =
                        xhr.responseJSON?.message || "Error deleting form";
                      FormBuilderNotifications.error(errorMsg, "Delete Failed");
                    },
                  });
                }
              });
            });

          // Copy shortcode handler (for forms list)
          $(document).on("click", ".copy-shortcode-link", function (e) {
            e.preventDefault();
            const formId = $(this).data("form-id");
            copyShortcodeToClipboard(formId);
          });

          // Export handler - use event delegation to avoid duplicate bindings
          $tbody
            .off("click", ".export-form")
            .on("click", ".export-form", function (e) {
              e.preventDefault();
              const formId = $(this).data("id");
              const formSlug = $(this).data("slug");
              exportForm(formId, formSlug);
            });
        } else {
          $tbody.append(
            '<tr><td colspan="6">No forms found. <a href="' +
              formBuilderAdmin.adminUrl +
              '?page=form-builder-new">Create one</a></td></tr>'
          );
        }
      },
      error: function (xhr, status, error) {
        console.error("Error loading forms:", { xhr, status, error });
        const errorMsg = xhr.responseJSON?.message || "Error loading forms";
        $tbody.html(
          `<tr><td colspan="6">Error loading forms: ${errorMsg}</td></tr>`
        );
        FormBuilderNotifications.error(
          "Failed to load forms list",
          "Load Error"
        );
      },
    });
  }

  /**
   * Slugify string
   */
  function slugify(text) {
    return text
      .toString()
      .toLowerCase()
      .replace(/\s+/g, "-")
      .replace(/[^\w\-]+/g, "")
      .replace(/\-\-+/g, "-")
      .replace(/^-+/, "")
      .replace(/-+$/, "");
  }

  /**
   * Escape HTML to prevent XSS
   */
  function escapeHtml(text) {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return String(text).replace(/[&<>"']/g, (m) => map[m]);
  }

  /**
   * Copy shortcode to clipboard
   */
  function copyShortcodeToClipboard(formId) {
    const shortcode = '[form_builder id="' + formId + '"]';

    // Try modern clipboard API first
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard
        .writeText(shortcode)
        .then(function () {
          showCopySuccess();
        })
        .catch(function (err) {
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
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    textArea.style.top = "-999999px";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
      const successful = document.execCommand("copy");
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
    FormBuilderNotifications.success(
      "Shortcode copied to clipboard!",
      "Copy Successful",
      3000
    );
  }

  /**
   * Show copy error feedback
   */
  function showCopyError(shortcode) {
    FormBuilderNotifications.error(
      "Failed to copy shortcode. Please copy manually: " + shortcode,
      "Copy Failed",
      8000
    );
  }

  /**
   * Get fields from the current page
   */
  function getCurrentPageFields() {
    if (!formData.pages || !formData.pages[currentPageIndex]) {
      return [];
    }
    return formData.pages[currentPageIndex].fields || [];
  }

  /**
   * Export form
   */
  function exportForm(formId, formSlug) {
    $.ajax({
      url: formBuilderAdmin.apiUrl + "forms/" + formId + "/export",
      method: "GET",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", formBuilderAdmin.nonce);
      },
      success: function (response) {
        // Create downloadable file
        const dataStr = JSON.stringify(response, null, 2);
        const dataBlob = new Blob([dataStr], { type: "application/json" });

        const link = document.createElement("a");
        link.href = window.URL.createObjectURL(dataBlob);
        link.download =
          "form-" +
          formSlug +
          "-" +
          new Date().toISOString().split("T")[0] +
          ".json";
        link.style.display = "none";

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Clean up
        window.URL.revokeObjectURL(link.href);
      },
      error: function (xhr) {
        let errorMessage = "An error occurred while exporting the form.";
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMessage = xhr.responseJSON.message;
        }
        FormBuilderNotifications.error(errorMessage, "Export Failed");
      },
    });
  }

  /**
   * Setup import modal handlers
   */
  function setupImportHandlers() {
    // Import button click
    $("#import-form-btn").on("click", function () {
      $("#import-form-modal").show();
    });

    // Modal close handlers
    $(".form-builder-modal-close").on("click", function () {
      $("#import-form-modal").hide();
      resetImportForm();
    });

    // Click outside modal to close
    $("#import-form-modal").on("click", function (e) {
      if (e.target === this) {
        $(this).hide();
        resetImportForm();
      }
    });

    // File input change
    $("#import-file-input").on("change", function () {
      const file = this.files[0];
      const $submitBtn = $("#import-form-submit");
      const $status = $("#import-status");

      if (file) {
        if (file.type !== "application/json" && !file.name.endsWith(".json")) {
          $status.html(
            '<div class="notice notice-error"><p>Please select a valid JSON file.</p></div>'
          );
          $submitBtn.prop("disabled", true);
          return;
        }

        $status.html(
          '<div class="notice notice-info"><p>File selected: ' +
            escapeHtml(file.name) +
            "</p></div>"
        );
        $submitBtn.prop("disabled", false);
      } else {
        $status.empty();
        $submitBtn.prop("disabled", true);
      }
    });

    // Import submit
    $("#import-form-submit").on("click", function () {
      const fileInput = document.getElementById("import-file-input");
      const file = fileInput.files[0];

      if (!file) {
        return;
      }

      const formData = new FormData();
      formData.append("import_file", file);

      const $submitBtn = $(this);
      const $status = $("#import-status");

      $submitBtn.prop("disabled", true).text("Importing...");
      $status.html(
        '<div class="notice notice-info"><p>Importing form...</p></div>'
      );

      $.ajax({
        url: formBuilderAdmin.apiUrl + "forms/import",
        method: "POST",
        data: formData,
        processData: false,
        contentType: false,
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-WP-Nonce", formBuilderAdmin.nonce);
        },
        success: function (response) {
          $status.html(
            '<div class="notice notice-success"><p>Form imported successfully! Redirecting to editor...</p></div>'
          );

          // Redirect to edit the imported form
          setTimeout(function () {
            window.location.href =
              formBuilderAdmin.adminUrl +
              "?page=form-builder&action=edit&form_id=" +
              response.form.id;
          }, 1500);
        },
        error: function (xhr) {
          let errorMessage = "An error occurred while importing the form.";

          if (xhr.responseJSON && xhr.responseJSON.message) {
            errorMessage = xhr.responseJSON.message;
          }

          $status.html(
            '<div class="notice notice-error"><p>' +
              escapeHtml(errorMessage) +
              "</p></div>"
          );
          $submitBtn.prop("disabled", false).text("Import");
        },
      });
    });
  }

  /**
   * Reset import form
   */
  function resetImportForm() {
    $("#import-file-input").val("");
    $("#import-status").empty();
    $("#import-form-submit").prop("disabled", true).text("Import");
  }

  // Initialize import handlers when in list mode
  $(document).ready(function () {
    if (!$("#form-data").length) {
      setupImportHandlers();
    }
  });
})(jQuery);
