/**
 * AliveChMS API Wrapper
 * 
 * Handles all API requests with automatic token management,
 * error handling, and request/response interceptors
 * @version 1.0.0
 */

class API {
    constructor() {
        this.baseURL = Config.API_BASE_URL;
        this.timeout = Config.API_TIMEOUT;
        this.refreshing = false;
        this.refreshSubscribers = [];
    }
    
    /**
     * Get authorization header
     * @returns {Object} Headers object
     */
    getHeaders() {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        
        const token = localStorage.getItem(Config.TOKEN_KEY);
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        return headers;
    }
    
    /**
     * Make HTTP request
     * @param {string} endpoint - API endpoint
     * @param {Object} options - Fetch options
     * @returns {Promise} Response data
     */
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}/${endpoint}`;
        
        const config = {
            ...options,
            headers: {
                ...this.getHeaders(),
                ...options.headers
            }
        };
        
        // Add timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.timeout);
        config.signal = controller.signal;
        
        try {
            Config.log(`API Request: ${config.method || 'GET'} ${url}`);
            
            const response = await fetch(url, config);
            clearTimeout(timeoutId);
            
            // Handle 401 Unauthorized - Token expired
            if (response.status === 401 && !endpoint.includes('auth/')) {
                Config.log('Token expired, attempting refresh...');
                return await this.handleTokenExpiration(endpoint, options);
            }
            
            // Parse response
            const contentType = response.headers.get('content-type');
            let data;
            
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                data = await response.text();
            }
            
            // Handle error responses
            if (!response.ok) {
                throw new APIError(
                    data.message || `HTTP ${response.status}: ${response.statusText}`,
                    response.status,
                    data
                );
            }
            
            Config.log(`API Response: ${config.method || 'GET'} ${url}`, data);
            return data;
            
        } catch (error) {
            clearTimeout(timeoutId);
            
            // Handle network errors
            if (error.name === 'AbortError') {
                throw new APIError('Request timeout', 408);
            }
            
            if (error instanceof APIError) {
                throw error;
            }
            
            // Network error (no internet, CORS, etc.)
            throw new APIError(
                'Network error. Please check your internet connection.',
                0,
                error
            );
        }
    }
    
    /**
     * Handle token expiration and refresh
     * @param {string} endpoint - Original endpoint
     * @param {Object} options - Original options
     * @returns {Promise} Retry result
     */
    async handleTokenExpiration(endpoint, options) {
        if (!this.refreshing) {
            this.refreshing = true;
            
            try {
                const refreshToken = localStorage.getItem(Config.REFRESH_TOKEN_KEY);
                if (!refreshToken) {
                    throw new Error('No refresh token');
                }
                
                // Refresh the token
                const response = await fetch(`${this.baseURL}/auth/refresh`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ refresh_token: refreshToken })
                });
                
                if (!response.ok) {
                    throw new Error('Token refresh failed');
                }
                
                const data = await response.json();
                
                // Update tokens
                localStorage.setItem(Config.TOKEN_KEY, data.access_token);
                localStorage.setItem(Config.REFRESH_TOKEN_KEY, data.refresh_token);
                
                Config.log('Token refreshed successfully');
                
                // Retry all waiting requests
                this.refreshSubscribers.forEach(callback => callback(data.access_token));
                this.refreshSubscribers = [];
                
                // Retry original request
                return await this.request(endpoint, options);
                
            } catch (error) {
                Config.error('Token refresh failed', error);
                // Clear auth and redirect to login
                Auth.logout();
                return Promise.reject(new APIError('Session expired. Please login again.', 401));
            } finally {
                this.refreshing = false;
            }
        }
        
        // If already refreshing, wait for it to complete
        return new Promise((resolve, reject) => {
            this.refreshSubscribers.push((token) => {
                resolve(this.request(endpoint, options));
            });
        });
    }
    
    /**
     * GET request
     * @param {string} endpoint - API endpoint
     * @param {Object} params - Query parameters
     * @returns {Promise} Response data
     */
    async get(endpoint, params = null) {
        let url = endpoint;
        if (params) {
            const queryString = Utils.buildQueryString(params);
            url += `?${queryString}`;
        }
        
        return this.request(url, {
            method: 'GET'
        });
    }
    
    /**
     * POST request
     * @param {string} endpoint - API endpoint
     * @param {Object} data - Request body
     * @returns {Promise} Response data
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    /**
     * PUT request
     * @param {string} endpoint - API endpoint
     * @param {Object} data - Request body
     * @returns {Promise} Response data
     */
    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }
    
    /**
     * DELETE request
     * @param {string} endpoint - API endpoint
     * @returns {Promise} Response data
     */
    async delete(endpoint) {
        return this.request(endpoint, {
            method: 'DELETE'
        });
    }
    
    /**
     * Upload file
     * @param {string} endpoint - API endpoint
     * @param {FormData} formData - Form data with file
     * @returns {Promise} Response data
     */
    async upload(endpoint, formData) {
        const url = `${this.baseURL}/${endpoint}`;
        
        const token = localStorage.getItem(Config.TOKEN_KEY);
        const headers = {
            'Authorization': `Bearer ${token}`
        };
        // Don't set Content-Type for FormData - browser will set it with boundary
        
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers,
                body: formData
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new APIError(
                    data.message || 'Upload failed',
                    response.status,
                    data
                );
            }
            
            return data;
            
        } catch (error) {
            if (error instanceof APIError) {
                throw error;
            }
            throw new APIError('Upload failed', 0, error);
        }
    }
}

/**
 * Custom API Error class
 */
class APIError extends Error {
    constructor(message, status = 0, data = null) {
        super(message);
        this.name = 'APIError';
        this.status = status;
        this.data = data;
    }
    
    /**
     * Check if error is specific status
     * @param {number} status - HTTP status code
     * @returns {boolean} Is matching status
     */
    is(status) {
        return this.status === status;
    }
    
    /**
     * Check if error is client error (4xx)
     * @returns {boolean} Is client error
     */
    isClientError() {
        return this.status >= 400 && this.status < 500;
    }
    
    /**
     * Check if error is server error (5xx)
     * @returns {boolean} Is server error
     */
    isServerError() {
        return this.status >= 500 && this.status < 600;
    }
    
    /**
     * Check if error is network error
     * @returns {boolean} Is network error
     */
    isNetworkError() {
        return this.status === 0;
    }
}

// Create singleton instance
const api = new API();

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { API, APIError, api };
}