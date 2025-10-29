/**
 * Form Builder Frontend JavaScript
 * Handles form pagination, validation, and webhook submission
 */

(function() {
    'use strict';
    
    // Initialize all forms on the page
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('.form-builder-container');
        forms.forEach(function(container) {
            new FormBuilderInstance(container);
        });
    });
    
    /**
     * Form Builder Instance
     */
    function FormBuilderInstance(container) {
        this.container = container;
        this.formElement = container.querySelector('.form-builder-form');
        this.instanceId = container.id;
        this.formId = container.dataset.formId;
        this.currentPage = 1;
        this.formData = {};
        this.submissionUuid = null;
        
        // Get form config from localized script
        const configKey = 'formBuilderData_' + this.instanceId;
        if (typeof window[configKey] === 'undefined') {
            console.error('Form config not found for instance:', this.instanceId);
            return;
        }
        
        this.config = window[configKey].formConfig;
        this.submissionUuid = window[configKey].submissionUuid;
        
        // Initialize first
        this.init();
        
        // Load saved data from localStorage (after DOM is ready)
        this.loadSavedData();
        
        // Show correct page after loading saved data
        this.showPage(this.currentPage);
    }
    
    FormBuilderInstance.prototype.init = function() {
        this.totalPages = this.config.pages.length;
        this.updateProgress();
        this.setupEventListeners();
    };
    
    FormBuilderInstance.prototype.setupEventListeners = function() {
        const self = this;
        
        // Form field changes
        this.formElement.addEventListener('change', function(e) {
            self.handleFieldChange(e);
        });
        
        this.formElement.addEventListener('input', function(e) {
            self.handleFieldChange(e);
        });
        
        // Navigation buttons
        const backBtn = this.formElement.querySelector('.form-builder-btn-back');
        const nextBtn = this.formElement.querySelector('.form-builder-btn-next');
        const submitBtn = this.formElement.querySelector('.form-builder-btn-submit');
        
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                self.goToPreviousPage();
            });
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                self.goToNextPage();
            });
        }
        
        if (submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                self.submitForm();
            });
        }
        
        // Form submit
        this.formElement.addEventListener('submit', function(e) {
            e.preventDefault();
            self.submitForm();
        });
    };
    
    FormBuilderInstance.prototype.handleFieldChange = function(e) {
        const field = e.target;
        const fieldName = field.name;
        
        let value;
        
        if (field.type === 'checkbox') {
            // Handle checkboxes as arrays
            const checkboxes = this.formElement.querySelectorAll('input[name="' + fieldName + '"]:checked');
            value = Array.from(checkboxes).map(cb => cb.value);
        } else if (field.type === 'radio') {
            value = field.value;
        } else if (field.type === 'file') {
            value = field.files[0] ? field.files[0].name : '';
        } else {
            value = field.value;
        }
        
        this.formData[fieldName] = value;
        this.saveData();
    };
    
    FormBuilderInstance.prototype.validateCurrentPage = function() {
        const currentPageConfig = this.config.pages[this.currentPage - 1];
        if (!currentPageConfig || !currentPageConfig.fields) {
            return true;
        }
        
        let isValid = true;
        const errors = [];
        
        currentPageConfig.fields.forEach(function(field) {
            const fieldElement = this.formElement.querySelector('[name="' + field.name + '"]');
            if (!fieldElement) return;
            
            // Skip hidden fields
            const fieldContainer = fieldElement.closest('.form-builder-field');
            if (fieldContainer && fieldContainer.style.display === 'none') {
                return;
            }
            
            // Check required fields
            if (field.required) {
                let value = this.formData[field.name];
                
                if (field.type === 'checkbox') {
                    const checkboxes = this.formElement.querySelectorAll('input[name="' + field.name + '[]"]:checked');
                    value = checkboxes.length > 0;
                } else if (field.type === 'radio') {
                    const radio = this.formElement.querySelector('input[name="' + field.name + '"]:checked');
                    value = radio ? radio.value : '';
                } else if (field.type === 'file') {
                    value = fieldElement.files.length > 0;
                }
                
                if (!value || (typeof value === 'string' && value.trim() === '')) {
                    isValid = false;
                    errors.push(field.label || field.name);
                    this.showFieldError(fieldElement, 'This field is required');
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
        }.bind(this));
        
        if (!isValid) {
            // Scroll to first error
            const firstError = this.formElement.querySelector('.form-builder-field.error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        
        return isValid;
    };
    
    FormBuilderInstance.prototype.showFieldError = function(fieldElement, message) {
        const fieldContainer = fieldElement.closest('.form-builder-field');
        if (fieldContainer) {
            fieldContainer.classList.add('error');
            const errorMsg = fieldContainer.querySelector('.form-builder-error-message');
            if (errorMsg) {
                errorMsg.textContent = message;
                errorMsg.style.display = 'block';
            }
        }
    };
    
    FormBuilderInstance.prototype.clearFieldError = function(fieldElement) {
        const fieldContainer = fieldElement.closest('.form-builder-field');
        if (fieldContainer) {
            fieldContainer.classList.remove('error');
            const errorMsg = fieldContainer.querySelector('.form-builder-error-message');
            if (errorMsg) {
                errorMsg.style.display = 'none';
            }
        }
    };
    
    FormBuilderInstance.prototype.goToNextPage = function() {
        if (!this.validateCurrentPage()) {
            return;
        }
        
        const currentPageConfig = this.config.pages[this.currentPage - 1];
        
        // Send webhook if enabled for current page
        if (currentPageConfig.webhook && currentPageConfig.webhook.enabled && currentPageConfig.webhook.url) {
            this.sendWebhook(currentPageConfig.webhook.url, this.currentPage);
        }
        
        // Execute custom JS if present
        if (currentPageConfig.customJS) {
            try {
                // Sandboxed execution
                const func = new Function('formData', currentPageConfig.customJS);
                func(this.formData);
            } catch (e) {
                console.error('Error executing custom JS:', e);
            }
        }
        
        if (this.currentPage < this.totalPages) {
            this.currentPage++;
            this.showPage(this.currentPage);
        }
    };
    
    FormBuilderInstance.prototype.goToPreviousPage = function() {
        if (this.currentPage > 1) {
            this.currentPage--;
            this.showPage(this.currentPage);
        }
    };
    
    FormBuilderInstance.prototype.showPage = function(pageNumber) {
        const pages = this.formElement.querySelectorAll('.form-builder-page');
        pages.forEach(function(page, index) {
            if (index + 1 === pageNumber) {
                page.style.display = 'block';
            } else {
                page.style.display = 'none';
            }
        });
        
        this.updateButtons();
        this.updateProgress();
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };
    
    FormBuilderInstance.prototype.updateButtons = function() {
        const backBtn = this.formElement.querySelector('.form-builder-btn-back');
        const nextBtn = this.formElement.querySelector('.form-builder-btn-next');
        const submitBtn = this.formElement.querySelector('.form-builder-btn-submit');
        
        if (backBtn) {
            backBtn.style.display = this.currentPage > 1 ? 'block' : 'none';
        }
        
        if (nextBtn) {
            nextBtn.style.display = this.currentPage < this.totalPages ? 'block' : 'none';
        }
        
        if (submitBtn) {
            submitBtn.style.display = this.currentPage === this.totalPages ? 'block' : 'none';
        }
    };
    
    FormBuilderInstance.prototype.updateProgress = function() {
        const currentSpan = this.formElement.querySelector('.form-builder-page-current');
        const totalSpan = this.formElement.querySelector('.form-builder-page-total');
        
        if (currentSpan) {
            currentSpan.textContent = this.currentPage;
        }
        if (totalSpan) {
            totalSpan.textContent = this.totalPages;
        }
    };
    
    FormBuilderInstance.prototype.submitForm = function() {
        if (!this.validateCurrentPage()) {
            return;
        }
        
        const currentPageConfig = this.config.pages[this.currentPage - 1];
        
        // Send webhook if enabled for last page
        if (currentPageConfig.webhook && currentPageConfig.webhook.enabled && currentPageConfig.webhook.url) {
            this.sendWebhook(currentPageConfig.webhook.url, this.currentPage, true);
        }
        
        // Execute custom JS if present
        if (currentPageConfig.customJS) {
            try {
                const func = new Function('formData', currentPageConfig.customJS);
                func(this.formData);
            } catch (e) {
                console.error('Error executing custom JS:', e);
            }
        }
        
        // Show success message
        this.showSuccess();
        
        // Clear saved data
        this.clearSavedData();
    };
    
    FormBuilderInstance.prototype.sendWebhook = function(url, pageNumber, isFinal = false) {
        const self = this;
        
        fetch(formBuilderFrontend.apiUrl + 'webhook', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': formBuilderFrontend.nonce
            },
            body: JSON.stringify({
                form_id: this.formId,
                submission_uuid: this.submissionUuid,
                page_number: pageNumber,
                webhook_url: url,
                formData: this.formData
            })
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (isFinal && data.submission_uuid) {
                // Mark as delivered
                self.submissionUuid = data.submission_uuid;
            }
        })
        .catch(function(error) {
            console.error('Webhook error:', error);
        });
    };
    
    FormBuilderInstance.prototype.showSuccess = function() {
        const form = this.formElement;
        const success = this.container.querySelector('.form-builder-success');
        
        if (form) {
            form.style.display = 'none';
        }
        if (success) {
            success.style.display = 'block';
        }
    };
    
    FormBuilderInstance.prototype.saveData = function() {
        const storageKey = 'form_builder_data_' + this.formId;
        try {
            localStorage.setItem(storageKey, JSON.stringify({
                formData: this.formData,
                currentPage: this.currentPage,
                submissionUuid: this.submissionUuid
            }));
        } catch (e) {
            console.error('Error saving to localStorage:', e);
        }
    };
    
    FormBuilderInstance.prototype.loadSavedData = function() {
        const storageKey = 'form_builder_data_' + this.formId;
        try {
            const saved = localStorage.getItem(storageKey);
            if (saved) {
                const data = JSON.parse(saved);
                if (data.formData) {
                    this.formData = data.formData;
                    // Restore field values
                    Object.keys(this.formData).forEach(function(key) {
                        const field = this.formElement.querySelector('[name="' + key + '"]');
                        if (field) {
                            if (field.type === 'checkbox') {
                                const values = Array.isArray(this.formData[key]) ? this.formData[key] : [this.formData[key]];
                                values.forEach(function(val) {
                                    const cb = this.formElement.querySelector('[name="' + key + '[]"][value="' + val + '"]');
                                    if (cb) cb.checked = true;
                                }.bind(this));
                            } else if (field.type === 'radio') {
                                const radio = this.formElement.querySelector('[name="' + key + '"][value="' + this.formData[key] + '"]');
                                if (radio) radio.checked = true;
                            } else {
                                field.value = this.formData[key];
                            }
                        }
                    }.bind(this));
                }
                if (data.currentPage) {
                    this.currentPage = data.currentPage;
                }
                if (data.submissionUuid) {
                    this.submissionUuid = data.submissionUuid;
                }
            }
        } catch (e) {
            console.error('Error loading from localStorage:', e);
        }
    };
    
    FormBuilderInstance.prototype.clearSavedData = function() {
        const storageKey = 'form_builder_data_' + this.formId;
        try {
            localStorage.removeItem(storageKey);
        } catch (e) {
            console.error('Error clearing localStorage:', e);
        }
    };
    
})();

