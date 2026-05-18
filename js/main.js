// ==========================================
// Smart Restaurant & Robotics System
// Common JavaScript Utilities
// ==========================================

const API_BASE = './backend/';

// ==========================================
// Utility Functions
// ==========================================

/**
 * Make API calls with timeout
 */
async function apiCall(endpoint, method = 'GET', data = null, timeoutMs = 15000) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        }
    };

    let url = `${API_BASE}${endpoint}`;

    if (method === 'GET' && data) {
        const params = new URLSearchParams(data);
        url += '?' + params.toString();
    } else if (method === 'POST' && data) {
        const params = new URLSearchParams();
        for (const key in data) {
            if (data[key] !== null && data[key] !== undefined) {
                if (Array.isArray(data[key])) {
                    params.append(key, JSON.stringify(data[key]));
                } else {
                    params.append(key, data[key]);
                }
            }
        }
        options.body = params.toString();
    }

    try {
        // Create abort controller for timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeoutMs);
        options.signal = controller.signal;

        const response = await fetch(url, options);
        clearTimeout(timeoutId);
        
        if (!response.ok) {
            console.error('API Error:', response.status, response.statusText);
            return { success: false, message: `Server error: ${response.status} ${response.statusText}` };
        }
        
        const result = await response.json();
        return result;
    } catch (error) {
        if (error.name === 'AbortError') {
            console.error('API Timeout:', endpoint);
            return { success: false, message: 'Request timeout - server took too long to respond' };
        }
        console.error('API Error:', error);
        return { success: false, message: 'Network error: ' + error.message };
    }
}

/**
 * Show alert message
 */
function showAlert(message, type = 'success', duration = 4000) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '80px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '1001';
    alertDiv.style.maxWidth = '400px';

    document.body.appendChild(alertDiv);

    setTimeout(() => {
        alertDiv.remove();
    }, duration);
}

/**
 * Format currency to KES
 */
function formatCurrency(value) {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES'
    }).format(value);
}

/**
 * Format date and time
 */
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('en-KE');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-KE');
}

function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString('en-KE');
}

/**
 * Get status badge HTML
 */
function getStatusBadge(status) {
    const statusMap = {
        'Pending': 'warning',
        'Cooking': 'info',
        'Ready': 'success',
        'Assigned to robot': 'primary',
        'Delivering': 'primary',
        'Delivered': 'success',
        'Idle': 'success',
        'Moving': 'info',
        'Charging': 'warning',
        'Out of Service': 'danger',
        'Active': 'success',
        'Inactive': 'danger',
        'Completed': 'success',
        'Failed': 'danger',
        'Maintenance': 'warning'
    };

    const type = statusMap[status] || 'info';
    return `<span class="badge badge-${type}">${status}</span>`;
}

/**
 * Open modal
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
    }
}

/**
 * Close modal
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
    }
}

/**
 * Clear form
 */
function clearForm(formId) {
    const form = document.getElementById(formId);
    if (form) {
        form.reset();
    }
}

/**
 * Show loading spinner
 */
function showLoading(container) {
    if (typeof container === 'string') {
        container = document.getElementById(container);
    }
    if (container) {
        container.innerHTML = '<div class="spinner"></div><p class="loading-text">Loading...</p>';
    }
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Get table ID from URL or session
 */
function getTableId() {
    const params = new URLSearchParams(window.location.search);
    const tableId = params.get('table') || sessionStorage.getItem('tableId') || null;
    if (tableId) {
        sessionStorage.setItem('tableId', tableId);
    }
    return tableId;
}

/**
 * Store data in local storage
 */
function storeData(key, value) {
    localStorage.setItem(key, JSON.stringify(value));
}

/**
 * Retrieve data from local storage
 */
function retrieveData(key) {
    const data = localStorage.getItem(key);
    return data ? JSON.parse(data) : null;
}

/**
 * Remove data from local storage
 */
function removeData(key) {
    localStorage.removeItem(key);
}

/**
 * Calculate distance between two coordinates
 */
function calculateDistance(x1, y1, x2, y2) {
    const dx = x2 - x1;
    const dy = y2 - y1;
    return Math.sqrt(dx * dx + dy * dy);
}

/**
 * Simulate robot movement animation
 */
function animateRobotMovement(robotElement, fromX, fromY, toX, toY, duration = 3000) {
    const startTime = Date.now();
    
    function animate() {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const currentX = fromX + (toX - fromX) * progress;
        const currentY = fromY + (toY - fromY) * progress;
        
        if (robotElement) {
            robotElement.style.left = currentX + 'px';
            robotElement.style.top = currentY + 'px';
        }
        
        if (progress < 1) {
            requestAnimationFrame(animate);
        }
    }
    
    animate();
}

/**
 * Close modals when clicking outside
 */
document.addEventListener('click', function(event) {
    const modals = document.querySelectorAll('.modal.show');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.classList.remove('show');
        }
    });
});

/**
 * Close modals and alerts on Escape key
 */
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            modal.classList.remove('show');
        });
    }
});

/**
 * Initialize navigation
 */
function initializeNavigation() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('nav a');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (currentPath.includes(href)) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
}

// Initialize navigation on page load
document.addEventListener('DOMContentLoaded', initializeNavigation);

/**
 * Validate email
 */
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Validate phone number (Kenya)
 */
function isValidPhoneNumber(phone) {
    const re = /^(?:\+254|0)[17][0-9]{8}$/;
    return re.test(phone.replace(/\s/g, ''));
}

/**
 * Get greeting based on time of day
 */
function getGreeting() {
    const hour = new Date().getHours();
    if (hour < 12) {
        return 'Good Morning';
    } else if (hour < 18) {
        return 'Good Afternoon';
    } else {
        return 'Good Evening';
    }
}

/**
 * Redirect to login
 */
function redirectToLogin() {
    window.location.href = './index.html';
}

/**
 * Print page
 */
function printPage() {
    window.print();
}

/**
 * Download as PDF (requires jsPDF library)
 */
function downloadAsTablePDF(elementId, filename) {
    const element = document.getElementById(elementId);
    if (element) {
        // For simplicity, using browser print functionality
        window.print();
    }
}
