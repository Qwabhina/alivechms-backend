/**
 * AliveChMS Alerts & Notifications
 * 
 * Handles toast notifications, SweetAlert modals, and inline alerts
 * @version 1.0.0
 */

const Alerts = {
    
    /**
     * Toast notification container
     */
    toastContainer: null,
    
    /**
     * Initialize toast container
     */
    initToastContainer() {
        if (this.toastContainer) return;
        
        this.toastContainer = document.createElement('div');
        this.toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        this.toastContainer.style.zIndex = '9999';
        document.body.appendChild(this.toastContainer);
    },
    
    /**
     * Show toast notification
     * @param {string} message - Message text
     * @param {string} type - Toast type (success, error, warning, info)
     * @param {number} duration - Duration in ms
     */
    toast(message, type = 'info', duration = Config.TOAST_DURATION) {
        this.initToastContainer();
        
        const colors = {
            success: { bg: 'bg-success', icon: 'bi-check-circle-fill' },
            error: { bg: 'bg-danger', icon: 'bi-x-circle-fill' },
            warning: { bg: 'bg-warning', icon: 'bi-exclamation-triangle-fill' },
            info: { bg: 'bg-info', icon: 'bi-info-circle-fill' }
        };
        
        const color = colors[type] || colors.info;
        
        const toastHtml = `
            <div class="toast align-items-center text-white ${color.bg} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi ${color.icon} me-2"></i>
                        ${Utils.escapeHtml(message)}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        const toastElement = document.createElement('div');
        toastElement.innerHTML = toastHtml;
        const toast = toastElement.firstElementChild;
        
        this.toastContainer.appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast, { delay: duration });
        bsToast.show();
        
        // Remove from DOM after hidden
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    },
    
    /**
     * Success toast
     * @param {string} message - Message text
     * @param {number} duration - Duration in ms
     */
    success(message, duration = Config.TOAST_DURATION) {
        this.toast(message, 'success', duration);
    },
    
    /**
     * Error toast
     * @param {string} message - Message text
     * @param {number} duration - Duration in ms
     */
    error(message, duration = Config.TOAST_DURATION + 2000) {
        this.toast(message, 'error', duration);
    },
    
    /**
     * Warning toast
     * @param {string} message - Message text
     * @param {number} duration - Duration in ms
     */
    warning(message, duration = Config.TOAST_DURATION) {
        this.toast(message, 'warning', duration);
    },
    
    /**
     * Info toast
     * @param {string} message - Message text
     * @param {number} duration - Duration in ms
     */
    info(message, duration = Config.TOAST_DURATION) {
        this.toast(message, 'info', duration);
    },
    
    /**
     * Show SweetAlert confirmation
     * @param {Object} options - SweetAlert options
     * @returns {Promise<boolean>} Confirmed or not
     */
    async confirm(options = {}) {
        const defaults = {
            title: 'Are you sure?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: Config.SWAL_CONFIG.confirmButtonColor,
            cancelButtonColor: Config.SWAL_CONFIG.cancelButtonColor,
            confirmButtonText: 'Yes, proceed',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        };
        
        const result = await Swal.fire({ ...defaults, ...options });
        return result.isConfirmed;
    },
    
    /**
     * Confirm delete action
     * @param {string} itemName - Name of item to delete
     * @returns {Promise<boolean>} Confirmed or not
     */
    async confirmDelete(itemName = 'this item') {
        return await this.confirm({
            title: 'Delete Confirmation',
            text: `Are you sure you want to delete ${itemName}? This action cannot be undone.`,
            icon: 'warning',
            confirmButtonText: 'Yes, delete it',
            confirmButtonColor: '#dc3545'
        });
    },
    
    /**
     * Show loading alert
     * @param {string} title - Loading title
     * @param {string} text - Loading text
     */
    loading(title = 'Processing...', text = 'Please wait') {
        Swal.fire({
            title,
            text,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    },
    
    /**
     * Close loading alert
     */
    closeLoading() {
        Swal.close();
    },
    
    /**
     * Show success modal
     * @param {string} title - Title
     * @param {string} text - Message
     */
    async successModal(title, text = '') {
        return await Swal.fire({
            icon: 'success',
            title,
            text,
            confirmButtonText: 'OK',
            confirmButtonColor: Config.SWAL_CONFIG.confirmButtonColor
        });
    },
    
    /**
     * Show error modal
     * @param {string} title - Title
     * @param {string} text - Message
     */
    async errorModal(title, text = '') {
        return await Swal.fire({
            icon: 'error',
            title,
            text,
            confirmButtonText: 'OK',
            confirmButtonColor: Config.SWAL_CONFIG.confirmButtonColor
        });
    },
    
    /**
     * Show input dialog
     * @param {Object} options - SweetAlert input options
     * @returns {Promise<string|null>} Input value or null
     */
    async input(options = {}) {
        const defaults = {
            title: 'Enter value',
            input: 'text',
            inputPlaceholder: 'Type here...',
            showCancelButton: true,
            confirmButtonText: 'Submit',
            cancelButtonText: 'Cancel',
            confirmButtonColor: Config.SWAL_CONFIG.confirmButtonColor,
            cancelButtonColor: Config.SWAL_CONFIG.cancelButtonColor,
            reverseButtons: true,
            inputValidator: (value) => {
                if (!value) {
                    return 'This field is required';
                }
            }
        };
        
        const result = await Swal.fire({ ...defaults, ...options });
        return result.isConfirmed ? result.value : null;
    },
    
    /**
     * Handle API error and show appropriate message
     * @param {Error} error - Error object
     * @param {string} defaultMessage - Default error message
     */
    handleApiError(error, defaultMessage = 'An error occurred. Please try again.') {
        Config.error('API Error:', error);
        
        let message = defaultMessage;
        
        if (error instanceof APIError) {
            if (error.data && error.data.message) {
                message = error.data.message;
            } else if (error.message) {
                message = error.message;
            }
            
            // Handle specific error codes
            if (error.status === 401) {
                message = 'Session expired. Please login again.';
                setTimeout(() => Auth.logout(), 2000);
            } else if (error.status === 403) {
                message = 'You do not have permission to perform this action.';
            } else if (error.status === 404) {
                message = 'The requested resource was not found.';
            } else if (error.status === 422) {
                // Validation errors
                if (error.data && error.data.errors) {
                    message = Object.values(error.data.errors).flat().join('<br>');
                }
            } else if (error.status === 429) {
                message = 'Too many requests. Please try again later.';
            } else if (error.isServerError()) {
                message = 'Server error. Please contact support if the problem persists.';
            } else if (error.isNetworkError()) {
                message = 'Network error. Please check your internet connection.';
            }
        } else if (error.message) {
            message = error.message;
        }
        
        this.error(message);
    },
    
    /**
     * Show inline alert in container
     * @param {HTMLElement} container - Container element
     * @param {string} message - Alert message
     * @param {string} type - Alert type
     */
    inline(container, message, type = 'info') {
        const alertClass = `alert-${type}`;
        const icons = {
            success: 'bi-check-circle-fill',
            error: 'bi-x-circle-fill',
            danger: 'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill'
        };
        
        const icon = icons[type] || icons.info;
        
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="bi ${icon} me-2"></i>
                ${Utils.escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Remove existing alerts
        container.querySelectorAll('.alert').forEach(el => el.remove());
        
        // Add new alert
        const alertElement = document.createElement('div');
        alertElement.innerHTML = alertHtml;
        container.insertBefore(alertElement.firstElementChild, container.firstChild);
    },
    
    /**
     * Clear inline alerts from container
     * @param {HTMLElement} container - Container element
     */
    clearInline(container) {
        container.querySelectorAll('.alert').forEach(el => el.remove());
    }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Alerts;
}