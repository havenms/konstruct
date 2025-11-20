/**
 * Form Builder Frontend JavaScript
 * Handles form pagination, validation, and webhook submission
 */

(function () {
  "use strict";

  // Initialize all forms on the page
  document.addEventListener("DOMContentLoaded", function () {
    const forms = document.querySelectorAll(".form-builder-container");
    forms.forEach(function (container) {
      new FormBuilderInstance(container);
    });
  });

  /**
   * Form Builder Instance
   */
  function FormBuilderInstance(container) {
    this.container = container;
    this.formElement = container.querySelector(".form-builder-form");
    this.instanceId = container.id;
    this.formId = container.dataset.formId;
    this.currentPage = 1;
    this.formData = {};
    this.submissionUuid = null;

    // Get form config from localized script
    if (
      typeof window.formBuilderData === "undefined" ||
      typeof window.formBuilderData[this.instanceId] === "undefined"
    ) {
      console.error("Form config not found for instance:", this.instanceId);
      return;
    }

    const formData = window.formBuilderData[this.instanceId];
    this.config = formData.formConfig;
    this.submissionUuid = formData.submissionUuid;

    // Initialize first
    this.init();

    // Load saved data from localStorage (after DOM is ready)
    this.loadSavedData();

    // Show correct page after loading saved data (no scroll on initial load)
    this.showPage(this.currentPage, false);
  }

  FormBuilderInstance.prototype.init = function () {
    this.totalPages = this.config.pages.length;
    this.updateProgress();
    this.setupEventListeners();
  };

  FormBuilderInstance.prototype.setupEventListeners = function () {
    const self = this;

    // Form field changes
    this.formElement.addEventListener("change", function (e) {
      self.handleFieldChange(e);
    });

    this.formElement.addEventListener("input", function (e) {
      self.handleFieldChange(e);
    });

    // Navigation buttons - use event delegation for better reliability
    this.formElement.addEventListener("click", function (e) {
      const target = e.target;

      if (
        target.matches(".form-builder-btn-back") ||
        target.closest(".form-builder-btn-back")
      ) {
        e.preventDefault();
        self.goToPreviousPage();
      }

      if (
        target.matches(".form-builder-btn-next") ||
        target.closest(".form-builder-btn-next")
      ) {
        e.preventDefault();
        self.goToNextPage();
      }

      if (
        target.matches(".form-builder-btn-submit") ||
        target.closest(".form-builder-btn-submit")
      ) {
        e.preventDefault();
        e.stopPropagation();
        self.submitForm();
        return false;
      }
    });

    // Form submit
    this.formElement.addEventListener("submit", function (e) {
      e.preventDefault();
      e.stopPropagation();
      self.submitForm();
      return false;
    });
  };

  FormBuilderInstance.prototype.handleFieldChange = function (e) {
    const field = e.target;

    // Skip link and label fields (they're not form inputs)
    const fieldContainer = field.closest(".form-builder-field");
    if (
      fieldContainer &&
      (fieldContainer.classList.contains("form-builder-field-link") ||
        fieldContainer.classList.contains("form-builder-field-label"))
    ) {
      return;
    }

    let fieldName = field.name;

    // If field name is empty, try to find it in the current page config
    if (!fieldName) {
      const currentPageConfig = this.config.pages[this.currentPage - 1];
      if (currentPageConfig && currentPageConfig.fields) {
        // Find the field that matches this element
        for (let i = 0; i < currentPageConfig.fields.length; i++) {
          const pageField = currentPageConfig.fields[i];
          // Try to match by field ID if available
          if (field.id && field.id === pageField.id) {
            fieldName = pageField.name || pageField.id || "field_" + i;
            break;
          }
          // Fallback: use field index as name
          if (pageField.name) {
            // Check if this field matches by comparing with other fields of same type
            const fieldElements = this.formElement.querySelectorAll(
              'input[type="' + field.type + '"], textarea, select'
            );
            const fieldIndex = Array.from(fieldElements).indexOf(field);
            if (fieldIndex === i) {
              fieldName = pageField.name;
              break;
            }
          }
        }
      }

      // Ultimate fallback
      if (!fieldName) {
        fieldName = "unnamed_field_" + Date.now();
      }
    }

    let value;

    if (field.type === "checkbox") {
      // Handle checkboxes as arrays - check both name and name[] formats
      let checkboxes = this.formElement.querySelectorAll(
        'input[name="' + fieldName + '"]:checked'
      );
      if (checkboxes.length === 0) {
        checkboxes = this.formElement.querySelectorAll(
          'input[name="' + fieldName + '[]"]:checked'
        );
      }
      value = Array.from(checkboxes).map((cb) => cb.value);
    } else if (field.type === "radio") {
      value = field.value;
    } else if (field.type === "file") {
      value = field.files[0] ? field.files[0].name : "";
    } else {
      value = field.value;
    }

    console.log("Field change - name:", fieldName, "value:", value);
    this.formData[fieldName] = value;
    this.saveData();
  };

  FormBuilderInstance.prototype.validateCurrentPage = function () {
    const currentPageConfig = this.config.pages[this.currentPage - 1];
    if (!currentPageConfig || !currentPageConfig.fields) {
      return true;
    }

    let isValid = true;
    const errors = [];

    currentPageConfig.fields.forEach(
      function (field) {
        // Skip label fields - they're informational only
        if (field.type === "label") {
          return;
        }

        let fieldElement = this.formElement.querySelector(
          '[name="' + field.name + '"]'
        );
        if (!fieldElement && field.type === "checkbox") {
          // Try with [] suffix for checkboxes
          fieldElement = this.formElement.querySelector(
            '[name="' + field.name + '[]"]'
          );
        }
        if (!fieldElement) {
          // Try finding by field id if name doesn't match
          fieldElement = this.formElement.querySelector(
            '[data-field-id="' +
              field.id +
              '"] input, [data-field-id="' +
              field.id +
              '"] textarea, [data-field-id="' +
              field.id +
              '"] select'
          );
        }
        if (!fieldElement) {
          return;
        }

        // Skip hidden fields
        const fieldContainer = fieldElement.closest(".form-builder-field");
        if (fieldContainer && fieldContainer.style.display === "none") {
          return;
        }

        // Check required fields
        if (field.required) {
          let value = this.formData[field.name];

          if (field.type === "checkbox") {
            // Check both name formats for checkboxes
            let checkboxes = this.formElement.querySelectorAll(
              'input[name="' + field.name + '"]:checked'
            );
            if (checkboxes.length === 0) {
              checkboxes = this.formElement.querySelectorAll(
                'input[name="' + field.name + '[]"]:checked'
              );
            }
            value = checkboxes.length > 0;
          } else if (field.type === "radio") {
            const radio = this.formElement.querySelector(
              'input[name="' + field.name + '"]:checked'
            );
            value = radio ? radio.value : "";
          } else if (field.type === "file") {
            value = fieldElement.files.length > 0;
          } else {
            value = fieldElement.value;
          }

          if (!value || (typeof value === "string" && value.trim() === "")) {
            isValid = false;
            errors.push(field.label || field.name);
            this.showFieldError(fieldElement, "This field is required");
          } else {
            this.clearFieldError(fieldElement);
          }
        } else {
          this.clearFieldError(fieldElement);
        }

        // HTML5 validation
        if (!fieldElement.checkValidity()) {
          isValid = false;
          this.showFieldError(fieldElement, fieldElement.validationMessage);
        }
      }.bind(this)
    );

    if (!isValid) {
      // Scroll to first error
      const firstError = this.formElement.querySelector(
        ".form-builder-field.error"
      );
      if (firstError) {
        firstError.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    }

    return isValid;
  };

  FormBuilderInstance.prototype.showFieldError = function (
    fieldElement,
    message
  ) {
    const fieldContainer = fieldElement.closest(".form-builder-field");
    if (fieldContainer) {
      fieldContainer.classList.add("error");
      const errorMsg = fieldContainer.querySelector(
        ".form-builder-error-message"
      );
      if (errorMsg) {
        errorMsg.textContent = message;
        errorMsg.style.display = "block";
      }
    }
  };

  FormBuilderInstance.prototype.clearFieldError = function (fieldElement) {
    const fieldContainer = fieldElement.closest(".form-builder-field");
    if (fieldContainer) {
      fieldContainer.classList.remove("error");
      const errorMsg = fieldContainer.querySelector(
        ".form-builder-error-message"
      );
      if (errorMsg) {
        errorMsg.style.display = "none";
      }
    }
  };

  FormBuilderInstance.prototype.goToNextPage = function () {
    if (!this.validateCurrentPage()) {
      return;
    }

    const currentPageConfig = this.config.pages[this.currentPage - 1];

    // Send webhook if enabled for current page
    if (
      currentPageConfig.webhook &&
      currentPageConfig.webhook.enabled &&
      currentPageConfig.webhook.url
    ) {
      this.sendWebhook(currentPageConfig.webhook.url, this.currentPage);
    }

    // Send step notification email (independent of webhooks)
    this.sendStepNotification(this.currentPage);

    // Execute custom JS if present
    if (currentPageConfig.customJS && currentPageConfig.customJS.trim()) {
      // Add a small delay to ensure other scripts (like Facebook Pixel) are ready
      setTimeout(() => {
        try {
          // Create a helper function to decode HTML entities
          const decodeHtml = (html) => {
            const txt = document.createElement("textarea");
            txt.innerHTML = html;
            return txt.value;
          };

          // Clean and decode the JavaScript
          let cleanJS = currentPageConfig.customJS.trim();

          // Remove script tags if someone added them
          cleanJS = cleanJS.replace(/<\/?script[^>]*>/gi, "");

          // Decode HTML entities
          cleanJS = decodeHtml(cleanJS);

          console.log("Executing custom JS:", cleanJS);

          // Simple, generic execution with formData context
          const func = new Function("formData", cleanJS);
          func(this.formData);
        } catch (e) {
          console.error("Error executing custom JS:", e);
          console.error("JS code was:", currentPageConfig.customJS);
        }
      }, 100); // 100ms delay to ensure other scripts are loaded
    }

    if (this.currentPage < this.totalPages) {
      this.currentPage++;
      this.showPage(this.currentPage, true); // Scroll on navigation
    }
  };

  FormBuilderInstance.prototype.goToPreviousPage = function () {
    if (this.currentPage > 1) {
      this.currentPage--;
      this.showPage(this.currentPage, true); // Scroll on navigation
    }
  };

  FormBuilderInstance.prototype.showPage = function (pageNumber, shouldScroll) {
    const pages = this.formElement.querySelectorAll(".form-builder-page");
    pages.forEach(function (page, index) {
      if (index + 1 === pageNumber) {
        page.style.display = "block";
      } else {
        page.style.display = "none";
      }
    });

    this.updateButtons();
    this.updateProgress();

    // Only scroll when explicitly requested
    if (shouldScroll === true) {
      this.container.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  };

  FormBuilderInstance.prototype.updateButtons = function () {
    const backBtn = this.formElement.querySelector(".form-builder-btn-back");
    const nextBtn = this.formElement.querySelector(".form-builder-btn-next");
    const submitBtn = this.formElement.querySelector(
      ".form-builder-btn-submit"
    );

    if (backBtn) {
      backBtn.style.display = this.currentPage > 1 ? "block" : "none";
    }

    if (nextBtn) {
      nextBtn.style.display =
        this.currentPage < this.totalPages ? "block" : "none";
    }

    if (submitBtn) {
      submitBtn.style.display =
        this.currentPage === this.totalPages ? "block" : "none";
    }
  };

  FormBuilderInstance.prototype.updateProgress = function () {
    const currentSpan = this.formElement.querySelector(
      ".form-builder-page-current"
    );
    const totalSpan = this.formElement.querySelector(
      ".form-builder-page-total"
    );

    if (currentSpan) {
      currentSpan.textContent = this.currentPage;
    }
    if (totalSpan) {
      totalSpan.textContent = this.totalPages;
    }
  };

  FormBuilderInstance.prototype.submitForm = function () {
    if (!this.validateCurrentPage()) {
      return;
    }

    const currentPageConfig = this.config.pages[this.currentPage - 1];

    // Always save submission to database (regardless of webhook)
    this.saveSubmissionToDatabase();

    // Send webhook if enabled for last page
    if (
      currentPageConfig.webhook &&
      currentPageConfig.webhook.enabled &&
      currentPageConfig.webhook.url
    ) {
      this.sendWebhook(currentPageConfig.webhook.url, this.currentPage, true);
    }

    // Execute custom JS if present
    if (currentPageConfig.customJS) {
      try {
        const func = new Function("formData", currentPageConfig.customJS);
        func(this.formData);
      } catch (e) {
        console.error("Error executing custom JS:", e);
      }
    }

    // Show success message
    this.showSuccess();

    // Clear saved data
    this.clearSavedData();
  };

  FormBuilderInstance.prototype.saveSubmissionToDatabase = function () {
    const self = this;

    // Build multipart FormData to include actual files
    const fd = new FormData();
    fd.append("form_id", this.formId);
    if (this.submissionUuid) fd.append("submission_uuid", this.submissionUuid);

    // Append non-file data as a JSON blob for reliability
    fd.append("formData", JSON.stringify(this.formData));

    // Append files from the form
    const fileInputs = this.formElement.querySelectorAll('input[type="file"]');
    fileInputs.forEach(function (input) {
      if (input.name && input.files && input.files.length > 0) {
        // Only handle first file (single file field)
        fd.append(input.name, input.files[0]);
      }
    });

    fetch(formBuilderFrontend.apiUrl + "submissions", {
      method: "POST",
      headers: {
        "X-WP-Nonce": formBuilderFrontend.nonce,
      },
      body: fd,
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        if (data.submission_uuid) {
          self.submissionUuid = data.submission_uuid;
        }
      })
      .catch(function (error) {
        console.error("Database save error:", error);
      });
  };

  FormBuilderInstance.prototype.sendWebhook = function (
    url,
    pageNumber,
    isFinal = false
  ) {
    const self = this;

    // Include protected file URLs for file fields in webhook payload
    const payloadData = Object.assign({}, this.formData);
    const files = this.formElement.querySelectorAll('input[type="file"]');
    files.forEach(function (input) {
      if (!input.name) return;
      if (input.files && input.files.length > 0) {
        // Construct protected URL using REST route and current submission UUID
        if (!self.submissionUuid) return;
        const url =
          formBuilderFrontend.apiUrl +
          "file?submission_uuid=" +
          encodeURIComponent(self.submissionUuid) +
          "&field=" +
          encodeURIComponent(input.name);
        payloadData[input.name] = {
          url: url,
          name: input.files[0].name,
        };
      }
    });

    fetch(formBuilderFrontend.apiUrl + "webhook", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": formBuilderFrontend.nonce,
      },
      body: JSON.stringify({
        form_id: this.formId,
        submission_uuid: this.submissionUuid,
        page_number: pageNumber,
        webhook_url: url,
        formData: payloadData,
      }),
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        if (isFinal && data.submission_uuid) {
          // Mark as delivered
          self.submissionUuid = data.submission_uuid;
        }
      })
      .catch(function (error) {
        console.error("Webhook error:", error);
      });
  };

  FormBuilderInstance.prototype.sendStepNotification = function (pageNumber) {
    // Check if step notifications are enabled in form config
    if (
      !this.config.notifications ||
      !this.config.notifications.step_notifications ||
      !this.config.notifications.step_notifications.enabled
    ) {
      return; // Skip if step notifications not enabled
    }

    const self = this;
    const currentPageFields = this.getCurrentPageData();

    fetch(formBuilderFrontend.apiUrl + "step-notification", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": formBuilderFrontend.nonce,
      },
      body: JSON.stringify({
        form_id: parseInt(this.formId),
        page_number: pageNumber,
        form_data: currentPageFields,
        submission_uuid: this.submissionUuid,
      }),
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        if (data.success) {
          console.log("Step notification sent successfully");
          if (data.submission_uuid) {
            self.submissionUuid = data.submission_uuid;
          }
        } else {
          console.log("Step notification failed:", data);
        }
      })
      .catch(function (error) {
        console.error("Step notification error:", error);
      });
  };

  FormBuilderInstance.prototype.getCurrentPageData = function () {
    const currentPageFields = {};
    const currentPageConfig = this.config.pages[this.currentPage - 1];

    if (currentPageConfig && currentPageConfig.fields) {
      currentPageConfig.fields.forEach(
        function (field) {
          // Skip link fields (they don't have form data)
          if (field.type === "link") {
            return;
          }
          if (this.formData[field.name]) {
            currentPageFields[field.name] = this.formData[field.name];
          }
        }.bind(this)
      );
    }

    return currentPageFields;
  };

  FormBuilderInstance.prototype.showSuccess = function () {
    const form = this.formElement;
    const success = this.container.querySelector(".form-builder-success");

    if (form) {
      form.style.display = "none";
    }
    if (success) {
      success.style.display = "block";
      // Only scroll if success message is not already in viewport to prevent unwanted page scrolling
      const rect = this.container.getBoundingClientRect();
      const isVisible = rect.top >= 0 && rect.bottom <= window.innerHeight;
      if (!isVisible) {
        this.container.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    }
  };

  FormBuilderInstance.prototype.saveData = function () {
    const storageKey = "form_builder_data_" + this.formId;
    try {
      localStorage.setItem(
        storageKey,
        JSON.stringify({
          formData: this.formData,
          currentPage: this.currentPage,
          submissionUuid: this.submissionUuid,
        })
      );
    } catch (e) {
      console.error("Error saving to localStorage:", e);
    }
  };

  FormBuilderInstance.prototype.loadSavedData = function () {
    const storageKey = "form_builder_data_" + this.formId;
    try {
      const saved = localStorage.getItem(storageKey);
      if (saved) {
        const data = JSON.parse(saved);
        if (data.formData) {
          this.formData = data.formData;
          // Restore field values
          Object.keys(this.formData).forEach(
            function (key) {
              const field = this.formElement.querySelector(
                '[name="' + key + '"]'
              );
              if (field) {
                if (field.type === "checkbox") {
                  const values = Array.isArray(this.formData[key])
                    ? this.formData[key]
                    : [this.formData[key]];
                  values.forEach(
                    function (val) {
                      // Try both name formats for checkboxes
                      let cb = this.formElement.querySelector(
                        '[name="' + key + '"][value="' + val + '"]'
                      );
                      if (!cb) {
                        cb = this.formElement.querySelector(
                          '[name="' + key + '[]"][value="' + val + '"]'
                        );
                      }
                      if (cb) cb.checked = true;
                    }.bind(this)
                  );
                } else if (field.type === "radio") {
                  const radio = this.formElement.querySelector(
                    '[name="' + key + '"][value="' + this.formData[key] + '"]'
                  );
                  if (radio) radio.checked = true;
                } else {
                  field.value = this.formData[key];
                }
              }
            }.bind(this)
          );
        }
        if (data.currentPage) {
          this.currentPage = data.currentPage;
        }
        if (data.submissionUuid) {
          this.submissionUuid = data.submissionUuid;
        }
      }
    } catch (e) {
      console.error("Error loading from localStorage:", e);
    }
  };

  FormBuilderInstance.prototype.clearSavedData = function () {
    const storageKey = "form_builder_data_" + this.formId;
    try {
      localStorage.removeItem(storageKey);
    } catch (e) {
      console.error("Error clearing localStorage:", e);
    }
  };
})();
