/**
 * AliveChMS Frontend Configuration
 * 
 * Core configuration and constants for the frontend application
 * @version 1.0.0
 */

const Config = {
    // API Configuration
   //  API_BASE_URL: window.location.origin + '/alivechms-backend',
      API_BASE_URL: 'http://www.onechurch.com',
    API_TIMEOUT: 30000, // 30 seconds
    
    // Authentication
    TOKEN_KEY: 'alive_access_token',
    REFRESH_TOKEN_KEY: 'alive_refresh_token',
    USER_KEY: 'alive_user',
    TOKEN_EXPIRY_BUFFER: 5 * 60 * 1000, // Refresh 5 minutes before expiry
    
    // Pagination
    DEFAULT_PAGE_SIZE: 10,
    PAGE_SIZE_OPTIONS: [10, 25, 50, 100],
    
    // UI
    TOAST_DURATION: 3000,
    MODAL_FADE_DURATION: 150,
    DEBOUNCE_DELAY: 300,
    
    // Date Formats
    DATE_FORMAT: 'Y-m-d',
    DATETIME_FORMAT: 'Y-m-d H:i',
    DISPLAY_DATE_FORMAT: 'M d, Y',
    DISPLAY_DATETIME_FORMAT: 'M d, Y h:i K',
    
    // File Upload
    MAX_FILE_SIZE: 5 * 1024 * 1024, // 5MB
    ALLOWED_IMAGE_TYPES: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    ALLOWED_DOCUMENT_TYPES: [
        'application/pdf', 
        'application/msword', 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ],
    
    // Church Specific
    GHANA_PHONE_REGEX: /^(\+?233|0)[2-5][0-9]{8}$/,
    CURRENCY: 'GHS',
    CURRENCY_SYMBOL: '₵',
    
    // Permissions (should match backend)
    PERMISSIONS: {
        VIEW_MEMBERS: 'view_members',
        EDIT_MEMBERS: 'edit_members',
        DELETE_MEMBERS: 'delete_members',
        CREATE_MEMBERS: 'create_members',
        
        VIEW_CONTRIBUTION: 'view_contribution',
        CREATE_CONTRIBUTION: 'create_contribution',
        EDIT_CONTRIBUTION: 'edit_contribution',
        DELETE_CONTRIBUTION: 'delete_contribution',
        
        VIEW_EXPENSES: 'view_expenses',
        CREATE_EXPENSE: 'create_expense',
        APPROVE_EXPENSES: 'approve_expenses',
        
        VIEW_EVENTS: 'view_events',
        MANAGE_EVENTS: 'manage_events',
        RECORD_ATTENDANCE: 'record_attendance',
        
        VIEW_GROUPS: 'view_groups',
        MANAGE_GROUPS: 'manage_groups',
        
        VIEW_FINANCIAL_REPORTS: 'view_financial_reports',
        VIEW_DASHBOARD: 'view_dashboard',
        
        MANAGE_ROLES: 'manage_roles',
        MANAGE_PERMISSIONS: 'manage_permissions'
    },
    
    // Status Options
    STATUS: {
        ACTIVE: 'Active',
        INACTIVE: 'Inactive',
        PENDING: 'Pending',
        APPROVED: 'Approved',
        REJECTED: 'Rejected',
        CANCELLED: 'Cancelled'
    },
    
    // Member Status
    MEMBER_STATUS: {
        ACTIVE: 'Active',
        INACTIVE: 'Inactive',
        SUSPENDED: 'Suspended',
        DECEASED: 'Deceased'
    },
    
    // Gender Options
    GENDER: {
        MALE: 'Male',
        FEMALE: 'Female',
        OTHER: 'Other'
    },
    
    // Attendance Status
    ATTENDANCE: {
        PRESENT: 'Present',
        ABSENT: 'Absent',
        LATE: 'Late',
        EXCUSED: 'Excused'
    },
    
    // Chart Colors
    CHART_COLORS: {
        primary: '#0d6efd',
        secondary: '#6c757d',
        success: '#198754',
        danger: '#dc3545',
        warning: '#ffc107',
        info: '#0dcaf0',
        light: '#f8f9fa',
        dark: '#212529',
        
        // Custom church colors (customize as needed)
        church1: '#2c5282',
        church2: '#4a5568',
        church3: '#ed8936',
        church4: '#38b2ac',
        church5: '#9f7aea'
    },
    
    // DataTable Configuration
    DATATABLE_CONFIG: {
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        ordering: true,
        searching: true,
        processing: true,
        responsive: true,
        language: {
            emptyTable: "No data available",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            lengthMenu: "Show _MENU_ entries",
            loadingRecords: "Loading...",
            processing: "Processing...",
            search: "Search:",
            zeroRecords: "No matching records found",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    },
    
    // SweetAlert2 Configuration
    SWAL_CONFIG: {
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Confirm',
        cancelButtonText: 'Cancel',
        showCancelButton: true,
        reverseButtons: true
    },
    
    // Development Mode
    DEBUG: window.location.hostname === 'localhost',
    
    // Helper Methods
    log: function(...args) {
        if (this.DEBUG) {
            console.log('[AliveChMS]', ...args);
        }
    },
    
    error: function(...args) {
        console.error('[AliveChMS Error]', ...args);
    },
    
    warn: function(...args) {
        if (this.DEBUG) {
            console.warn('[AliveChMS Warning]', ...args);
        }
    }
};

// Freeze config to prevent modifications
Object.freeze(Config);
Object.freeze(Config.PERMISSIONS);
Object.freeze(Config.STATUS);
Object.freeze(Config.CHART_COLORS);

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Config;
}