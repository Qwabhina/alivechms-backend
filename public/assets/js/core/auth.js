/**
 * AliveChMS Authentication Manager
 * 
 * Handles user authentication, authorization, and session management
 * @version 1.0.0
 */

const Auth = {
    
    /**
     * Login user
     * @param {string} username - Username
     * @param {string} password - Password
     * @param {boolean} remember - Remember me
     * @returns {Promise<Object>} User data
     */
    async login(username, password, remember = false) {
        try {
            const response = await api.post('auth/login', {
                userid: username,
                passkey: password
            });
            
            if (response.status === 'success') {
                // Store tokens
                localStorage.setItem(Config.TOKEN_KEY, response.access_token);
                localStorage.setItem(Config.REFRESH_TOKEN_KEY, response.refresh_token);
                
                // Store user data
                const userData = {
                    ...response.user,
                    permissions: this.extractPermissions(response.user)
                };
                localStorage.setItem(Config.USER_KEY, JSON.stringify(userData));
                
                // Set up auto-refresh
                this.setupTokenRefresh();
                
                Config.log('Login successful', userData);
                return userData;
            } else {
                throw new Error(response.message || 'Login failed');
            }
        } catch (error) {
            Config.error('Login error', error);
            throw error;
        }
    },
    
    /**
     * Logout user
     * @returns {Promise<void>}
     */
    async logout() {
        try {
            const refreshToken = localStorage.getItem(Config.REFRESH_TOKEN_KEY);
            
            if (refreshToken) {
                // Call backend logout (best effort)
                try {
                    await api.post('auth/logout', {
                        refresh_token: refreshToken
                    });
                } catch (e) {
                    Config.warn('Logout API call failed', e);
                }
            }
        } catch (error) {
            Config.warn('Logout error', error);
        } finally {
            // Clear local storage
            localStorage.removeItem(Config.TOKEN_KEY);
            localStorage.removeItem(Config.REFRESH_TOKEN_KEY);
            localStorage.removeItem(Config.USER_KEY);
            
            // Stop auto-refresh
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
            
            Config.log('Logged out');
            
            // Redirect to login
            window.location.href = '/public/login/';
        }
    },
    
    /**
     * Check if user is authenticated
     * @returns {boolean} Is authenticated
     */
    isAuthenticated() {
        const token = localStorage.getItem(Config.TOKEN_KEY);
        const user = this.getUser();
        return Boolean(token && user);
    },
    
    /**
     * Get current user
     * @returns {Object|null} User data
     */
    getUser() {
        const userData = localStorage.getItem(Config.USER_KEY);
        if (!userData) return null;
        
        try {
            return JSON.parse(userData);
        } catch (e) {
            Config.error('Failed to parse user data', e);
            return null;
        }
    },
    
    /**
     * Get user's full name
     * @returns {string} Full name
     */
    getUserName() {
        const user = this.getUser();
        if (!user) return 'Guest';
        return `${user.MbrFirstName || ''} ${user.MbrFamilyName || ''}`.trim() || 'User';
    },
    
    /**
     * Get user's initials
     * @returns {string} Initials
     */
    getUserInitials() {
        const name = this.getUserName();
        return Utils.getInitials(name);
    },
    
    /**
     * Get user's role
     * @returns {string|Array} Role(s)
     */
    getUserRole() {
        const user = this.getUser();
        return user?.Role || user?.RoleName || 'Member';
    },
    
    /**
     * Get access token
     * @returns {string|null} Access token
     */
    getToken() {
        return localStorage.getItem(Config.TOKEN_KEY);
    },
    
    /**
     * Extract permissions from user data
     * @param {Object} user - User object
     * @returns {Array} Permissions array
     */
    extractPermissions(user) {
        // Permissions may come from different sources
        if (user.permissions && Array.isArray(user.permissions)) {
            return user.permissions;
        }
        
        // Role-based permissions might be embedded
        if (user.Role) {
            // This would need to match your backend role structure
            return this.getRolePermissions(user.Role);
        }
        
        return [];
    },
    
    /**
     * Get permissions for a role (fallback)
     * @param {string} role - Role name
     * @returns {Array} Permissions
     */
    getRolePermissions(role) {
        // Define default role permissions
        const rolePermissions = {
            'Admin': Object.values(Config.PERMISSIONS),
            'Pastor': [
                Config.PERMISSIONS.VIEW_MEMBERS,
                Config.PERMISSIONS.VIEW_CONTRIBUTION,
                Config.PERMISSIONS.VIEW_EXPENSES,
                Config.PERMISSIONS.VIEW_EVENTS,
                Config.PERMISSIONS.VIEW_GROUPS,
                Config.PERMISSIONS.VIEW_FINANCIAL_REPORTS,
                Config.PERMISSIONS.VIEW_DASHBOARD
            ],
            'Treasurer': [
                Config.PERMISSIONS.VIEW_CONTRIBUTION,
                Config.PERMISSIONS.CREATE_CONTRIBUTION,
                Config.PERMISSIONS.VIEW_EXPENSES,
                Config.PERMISSIONS.CREATE_EXPENSE,
                Config.PERMISSIONS.VIEW_FINANCIAL_REPORTS,
                Config.PERMISSIONS.VIEW_DASHBOARD
            ],
            'Secretary': [
                Config.PERMISSIONS.VIEW_MEMBERS,
                Config.PERMISSIONS.EDIT_MEMBERS,
                Config.PERMISSIONS.VIEW_EVENTS,
                Config.PERMISSIONS.MANAGE_EVENTS,
                Config.PERMISSIONS.VIEW_GROUPS,
                Config.PERMISSIONS.VIEW_DASHBOARD
            ],
            'Member': [
                Config.PERMISSIONS.VIEW_EVENTS,
                Config.PERMISSIONS.VIEW_GROUPS
            ]
        };
        
        return rolePermissions[role] || rolePermissions['Member'];
    },
    
    /**
     * Check if user has permission
     * @param {string} permission - Permission name
     * @returns {boolean} Has permission
     */
    hasPermission(permission) {
        const user = this.getUser();
        if (!user) return false;
        
        // Admin has all permissions
        const role = this.getUserRole();
        if (role === 'Admin' || role === 'Administrator') return true;
        
        const permissions = user.permissions || this.extractPermissions(user);
        return permissions.includes(permission);
    },
    
    /**
     * Check if user has any of the permissions
     * @param {Array<string>} permissions - Permission names
     * @returns {boolean} Has any permission
     */
    hasAnyPermission(permissions) {
        return permissions.some(permission => this.hasPermission(permission));
    },
    
    /**
     * Check if user has all permissions
     * @param {Array<string>} permissions - Permission names
     * @returns {boolean} Has all permissions
     */
    hasAllPermissions(permissions) {
        return permissions.every(permission => this.hasPermission(permission));
    },
    
    /**
     * Require authentication (call on page load)
     * @param {string} redirectUrl - URL to redirect after login
     */
    requireAuth(redirectUrl = null) {
        if (!this.isAuthenticated()) {
            Config.log('Authentication required, redirecting to login');
            
            // Store intended destination
            if (redirectUrl) {
                sessionStorage.setItem('redirect_after_login', redirectUrl);
            }
            
            window.location.href = '/public/login/';
            return false;
        }
        
        // Set up token refresh
        this.setupTokenRefresh();
        return true;
    },
    
    /**
     * Require permission (call on page load or action)
     * @param {string|Array<string>} permission - Required permission(s)
     * @param {string} message - Error message
     * @returns {boolean} Has permission
     */
    requirePermission(permission, message = null) {
        const hasPermission = Array.isArray(permission)
            ? this.hasAnyPermission(permission)
            : this.hasPermission(permission);
        
        if (!hasPermission) {
            const msg = message || 'You do not have permission to perform this action.';
            Alerts.error(msg);
            Config.warn('Permission denied:', permission);
            return false;
        }
        
        return true;
    },
    
    /**
     * Set up automatic token refresh
     */
    setupTokenRefresh() {
        // Clear existing interval
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        
        // Refresh token every 25 minutes (before 30 min expiry)
        this.refreshInterval = setInterval(async () => {
            try {
                const refreshToken = localStorage.getItem(Config.REFRESH_TOKEN_KEY);
                if (!refreshToken) {
                    throw new Error('No refresh token');
                }
                
                Config.log('Auto-refreshing token...');
                
                const response = await api.post('auth/refresh', {
                    refresh_token: refreshToken
                });
                
                if (response.access_token) {
                    localStorage.setItem(Config.TOKEN_KEY, response.access_token);
                    localStorage.setItem(Config.REFRESH_TOKEN_KEY, response.refresh_token);
                    Config.log('Token auto-refreshed');
                }
            } catch (error) {
                Config.error('Auto token refresh failed', error);
                this.logout();
            }
        }, 25 * 60 * 1000); // 25 minutes
        
        Config.log('Token auto-refresh enabled');
    },
    
    /**
     * Update user data
     * @param {Object} updates - User data updates
     */
    updateUser(updates) {
        const user = this.getUser();
        if (!user) return;
        
        const updatedUser = { ...user, ...updates };
        localStorage.setItem(Config.USER_KEY, JSON.stringify(updatedUser));
        
        Config.log('User data updated', updatedUser);
    },
    
    /**
     * Handle redirect after login
     */
    handleRedirectAfterLogin() {
        const redirectUrl = sessionStorage.getItem('redirect_after_login');
        if (redirectUrl) {
            sessionStorage.removeItem('redirect_after_login');
            window.location.href = redirectUrl;
        } else {
            window.location.href = '/public/dashboard/';
        }
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // Set up token refresh if authenticated
    if (Auth.isAuthenticated()) {
        Auth.setupTokenRefresh();
    }
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Auth;
}