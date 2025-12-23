/**
 * AliveChMS Utility Functions
 * 
 * Common helper functions used throughout the application
 * @version 1.0.0
 */

const Utils = {
    
    /**
     * Debounce function calls
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in milliseconds
     * @returns {Function} Debounced function
     */
    debounce(func, wait = Config.DEBOUNCE_DELAY) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    /**
     * Throttle function calls
     * @param {Function} func - Function to throttle
     * @param {number} limit - Time limit in milliseconds
     * @returns {Function} Throttled function
     */
    throttle(func, limit = 1000) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },
    
    /**
     * Format currency
     * @param {number} amount - Amount to format
     * @param {string} currency - Currency code
     * @returns {string} Formatted currency
     */
    formatCurrency(amount, currency = Config.CURRENCY) {
        if (amount === null || amount === undefined) return '-';
        const num = parseFloat(amount);
        if (isNaN(num)) return '-';
        return `${Config.CURRENCY_SYMBOL}${num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')}`;
    },
    
    /**
     * Format date
     * @param {string} date - Date string
     * @param {string} format - Format string
     * @returns {string} Formatted date
     */
    formatDate(date, format = Config.DISPLAY_DATE_FORMAT) {
        if (!date) return '-';
        const d = new Date(date);
        if (isNaN(d)) return '-';
        
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                       'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        const pad = (n) => String(n).padStart(2, '0');
        
        return format
            .replace('Y', d.getFullYear())
            .replace('m', pad(d.getMonth() + 1))
            .replace('d', pad(d.getDate()))
            .replace('M', months[d.getMonth()])
            .replace('H', pad(d.getHours()))
            .replace('h', pad(d.getHours() % 12 || 12))
            .replace('i', pad(d.getMinutes()))
            .replace('s', pad(d.getSeconds()))
            .replace('K', d.getHours() >= 12 ? 'PM' : 'AM');
    },
    
    /**
     * Format relative time
     * @param {string} date - Date string
     * @returns {string} Relative time (e.g., "2 hours ago")
     */
    timeAgo(date) {
        if (!date) return '-';
        const d = new Date(date);
        const now = new Date();
        const seconds = Math.floor((now - d) / 1000);
        
        const intervals = {
            year: 31536000,
            month: 2592000,
            week: 604800,
            day: 86400,
            hour: 3600,
            minute: 60
        };
        
        for (const [name, seconds_in] of Object.entries(intervals)) {
            const interval = Math.floor(seconds / seconds_in);
            if (interval >= 1) {
                return interval === 1 
                    ? `1 ${name} ago` 
                    : `${interval} ${name}s ago`;
            }
        }
        
        return 'Just now';
    },
    
    /**
     * Validate Ghana phone number
     * @param {string} phone - Phone number
     * @returns {boolean} Valid or not
     */
    isValidPhone(phone) {
        return Config.GHANA_PHONE_REGEX.test(phone.replace(/[\s\-\(\)]/g, ''));
    },
    
    /**
     * Format Ghana phone number
     * @param {string} phone - Phone number
     * @returns {string} Formatted phone
     */
    formatPhone(phone) {
        if (!phone) return '-';
        const cleaned = phone.replace(/[\s\-\(\)]/g, '');
        
        // Convert to +233 format
        if (cleaned.startsWith('0')) {
            return '+233' + cleaned.substring(1);
        }
        if (cleaned.startsWith('233')) {
            return '+' + cleaned;
        }
        if (cleaned.startsWith('+233')) {
            return cleaned;
        }
        return phone;
    },
    
    /**
     * Validate email
     * @param {string} email - Email address
     * @returns {boolean} Valid or not
     */
    isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    /**
     * Truncate text
     * @param {string} text - Text to truncate
     * @param {number} length - Maximum length
     * @param {string} suffix - Suffix to add
     * @returns {string} Truncated text
     */
    truncate(text, length = 50, suffix = '...') {
        if (!text || text.length <= length) return text || '';
        return text.substring(0, length).trim() + suffix;
    },
    
    /**
     * Escape HTML
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    },
    
    /**
     * Generate random ID
     * @param {number} length - Length of ID
     * @returns {string} Random ID
     */
    randomId(length = 8) {
        return Math.random().toString(36).substring(2, length + 2);
    },
    
    /**
     * Copy to clipboard
     * @param {string} text - Text to copy
     * @returns {Promise<boolean>} Success or failure
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (err) {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            const success = document.execCommand('copy');
            document.body.removeChild(textarea);
            return success;
        }
    },
    
    /**
     * Download data as file
     * @param {string} data - Data to download
     * @param {string} filename - Filename
     * @param {string} type - MIME type
     */
    downloadFile(data, filename, type = 'text/plain') {
        const blob = new Blob([data], { type });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    },
    
    /**
     * Parse query string
     * @param {string} search - Query string
     * @returns {Object} Parsed parameters
     */
    parseQueryString(search = window.location.search) {
        const params = new URLSearchParams(search);
        const result = {};
        for (const [key, value] of params) {
            result[key] = value;
        }
        return result;
    },
    
    /**
     * Build query string
     * @param {Object} params - Parameters object
     * @returns {string} Query string
     */
    buildQueryString(params) {
        return Object.keys(params)
            .filter(key => params[key] !== null && params[key] !== undefined && params[key] !== '')
            .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(params[key])}`)
            .join('&');
    },
    
    /**
     * Serialize form data
     * @param {HTMLFormElement} form - Form element
     * @returns {Object} Form data as object
     */
    serializeForm(form) {
        const formData = new FormData(form);
        const data = {};
        for (const [key, value] of formData.entries()) {
            // Handle multiple values (checkboxes, multi-selects)
            if (data[key]) {
                if (Array.isArray(data[key])) {
                    data[key].push(value);
                } else {
                    data[key] = [data[key], value];
                }
            } else {
                data[key] = value;
            }
        }
        return data;
    },
    
    /**
     * Populate form from data
     * @param {HTMLFormElement} form - Form element
     * @param {Object} data - Data object
     */
    populateForm(form, data) {
        Object.keys(data).forEach(key => {
            const input = form.elements[key];
            if (!input) return;
            
            if (input.type === 'checkbox') {
                input.checked = Boolean(data[key]);
            } else if (input.type === 'radio') {
                const radio = form.querySelector(`input[name="${key}"][value="${data[key]}"]`);
                if (radio) radio.checked = true;
            } else {
                input.value = data[key] || '';
            }
        });
    },
    
    /**
     * Clear form
     * @param {HTMLFormElement} form - Form element
     */
    clearForm(form) {
        form.reset();
        // Clear validation states
        form.querySelectorAll('.is-invalid, .is-valid').forEach(el => {
            el.classList.remove('is-invalid', 'is-valid');
        });
        form.querySelectorAll('.invalid-feedback').forEach(el => {
            el.textContent = '';
        });
    },
    
    /**
     * Show/hide element
     * @param {HTMLElement} element - Element to toggle
     * @param {boolean} show - Show or hide
     */
    toggle(element, show = null) {
        if (show === null) {
            element.classList.toggle('d-none');
        } else if (show) {
            element.classList.remove('d-none');
        } else {
            element.classList.add('d-none');
        }
    },
    
    /**
     * Capitalize first letter
     * @param {string} str - String to capitalize
     * @returns {string} Capitalized string
     */
    capitalize(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    },
    
    /**
     * Get initials from name
     * @param {string} name - Full name
     * @returns {string} Initials
     */
    getInitials(name) {
        if (!name) return '?';
        return name
            .split(' ')
            .map(word => word.charAt(0))
            .join('')
            .toUpperCase()
            .substring(0, 2);
    },
    
    /**
     * Generate color from string (for avatars)
     * @param {string} str - String to generate color from
     * @returns {string} Hex color
     */
    stringToColor(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        const color = (hash & 0x00FFFFFF).toString(16).toUpperCase();
        return '#' + '00000'.substring(0, 6 - color.length) + color;
    },
    
    /**
     * Deep clone object
     * @param {*} obj - Object to clone
     * @returns {*} Cloned object
     */
    deepClone(obj) {
        return JSON.parse(JSON.stringify(obj));
    },
    
    /**
     * Check if object is empty
     * @param {Object} obj - Object to check
     * @returns {boolean} Is empty
     */
    isEmpty(obj) {
        return Object.keys(obj).length === 0;
    },
    
    /**
     * Merge objects deeply
     * @param {Object} target - Target object
     * @param {Object} source - Source object
     * @returns {Object} Merged object
     */
    deepMerge(target, source) {
        const output = Object.assign({}, target);
        if (Utils.isObject(target) && Utils.isObject(source)) {
            Object.keys(source).forEach(key => {
                if (Utils.isObject(source[key])) {
                    if (!(key in target)) {
                        Object.assign(output, { [key]: source[key] });
                    } else {
                        output[key] = Utils.deepMerge(target[key], source[key]);
                    }
                } else {
                    Object.assign(output, { [key]: source[key] });
                }
            });
        }
        return output;
    },
    
    /**
     * Check if value is object
     * @param {*} item - Item to check
     * @returns {boolean} Is object
     */
    isObject(item) {
        return item && typeof item === 'object' && !Array.isArray(item);
    },
    
    /**
     * Wait for specified time
     * @param {number} ms - Milliseconds to wait
     * @returns {Promise} Promise that resolves after wait
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    },
    
    /**
     * Retry async function
     * @param {Function} fn - Async function to retry
     * @param {number} retries - Number of retries
     * @param {number} delay - Delay between retries
     * @returns {Promise} Function result
     */
    async retry(fn, retries = 3, delay = 1000) {
        try {
            return await fn();
        } catch (error) {
            if (retries <= 0) throw error;
            await Utils.sleep(delay);
            return Utils.retry(fn, retries - 1, delay);
        }
    }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Utils;
}