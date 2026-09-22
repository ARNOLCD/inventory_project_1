// AC-TECHNOLOGY Inventory Management System - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initNavbar();
    initAnimations();
    initModals();
    initFileUploads();
    initAlerts();
});

// Navbar scroll effect
function initNavbar() {
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }

    // Mobile menu toggle
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');
    if (mobileMenuBtn && navLinks) {
        mobileMenuBtn.addEventListener('click', function() {
            navLinks.classList.toggle('active');
        });
    }

    // Sidebar toggle for dashboard
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
}

// Scroll animations
function initAnimations() {
    const animatedElements = document.querySelectorAll('[data-animate]');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const animation = entry.target.dataset.animate;
                entry.target.classList.add(`animate-${animation}`);
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    animatedElements.forEach(el => observer.observe(el));
}

// Modal functionality
function initModals() {
    // Open modal
    document.querySelectorAll('[data-modal]').forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalId = this.dataset.modal;
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
    });

    // Close modal
    document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
        el.addEventListener('click', function(e) {
            if (e.target === this) {
                const modal = this.closest('.modal-overlay') || this.parentElement.closest('.modal-overlay');
                if (modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }
        });
    });

    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
    });
}

// File upload preview
function initFileUploads() {
    document.querySelectorAll('.file-upload').forEach(upload => {
        const input = upload.querySelector('input[type="file"]');
        const preview = upload.querySelector('.file-preview');

        upload.addEventListener('click', () => input.click());

        upload.addEventListener('dragover', (e) => {
            e.preventDefault();
            upload.classList.add('dragover');
        });

        upload.addEventListener('dragleave', () => {
            upload.classList.remove('dragover');
        });

        upload.addEventListener('drop', (e) => {
            e.preventDefault();
            upload.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                handleFilePreview(input, preview);
            }
        });

        input.addEventListener('change', () => handleFilePreview(input, preview));
    });
}

function handleFilePreview(input, preview) {
    if (input.files && input.files[0] && preview) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Auto-dismiss alerts
function initAlerts() {
    document.querySelectorAll('.alert[data-dismiss]').forEach(alert => {
        const timeout = parseInt(alert.dataset.dismiss) || 5000;
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, timeout);
    });
}

// Utility functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-ZM', {
        style: 'currency',
        currency: 'ZMW'
    }).format(amount);
}

function formatDate(date) {
    return new Intl.DateTimeFormat('en-ZM', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    }).format(new Date(date));
}

function formatDateTime(date) {
    return new Intl.DateTimeFormat('en-ZM', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(new Date(date));
}

// Confirm delete
function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return confirm(message);
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'}"></i> ${message}`;
    notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; animation: slideInRight 0.3s ease;';
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// AJAX form submission
function submitForm(form, callback) {
    const formData = new FormData(form);
    
    fetch(form.action, {
        method: form.method || 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (callback) callback(data);
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'danger');
    });
}

// Search functionality
function initSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (input && table) {
        input.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
}

// Print functionality
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Print</title>
                <link rel="stylesheet" href="assets/css/style.css">
                <style>
                    body { padding: 20px; }
                    @media print {
                        body { padding: 0; }
                    }
                </style>
            </head>
            <body>
                ${element.innerHTML}
                <script>
                    window.onload = function() {
                        window.print();
                        window.close();
                    };
                </script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }
}

// Export to CSV
function exportToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;

    let csv = [];
    const rows = table.querySelectorAll('tr');

    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            let text = col.textContent.replace(/"/g, '""');
            rowData.push(`"${text}"`);
        });
        csv.push(rowData.join(','));
    });

    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
}

// Quantity controls
function initQuantityControls() {
    document.querySelectorAll('.qty-control').forEach(control => {
        const minusBtn = control.querySelector('.qty-minus');
        const plusBtn = control.querySelector('.qty-plus');
        const input = control.querySelector('input');

        minusBtn?.addEventListener('click', () => {
            const min = parseInt(input.min) || 1;
            if (parseInt(input.value) > min) {
                input.value = parseInt(input.value) - 1;
                input.dispatchEvent(new Event('change'));
            }
        });

        plusBtn?.addEventListener('click', () => {
            const max = parseInt(input.max) || 9999;
            if (parseInt(input.value) < max) {
                input.value = parseInt(input.value) + 1;
                input.dispatchEvent(new Event('change'));
            }
        });
    });
}

// Chart initialization helper
function initChart(canvasId, type, data, options = {}) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    };

    return new Chart(canvas, {
        type: type,
        data: data,
        options: { ...defaultOptions, ...options }
    });
}

// Date range picker helper
function getDateRange(period) {
    const now = new Date();
    let start, end = now;

    switch (period) {
        case 'today':
            start = new Date(now.setHours(0, 0, 0, 0));
            break;
        case 'week':
            start = new Date(now.setDate(now.getDate() - 7));
            break;
        case 'month':
            start = new Date(now.setMonth(now.getMonth() - 1));
            break;
        case 'year':
            start = new Date(now.setFullYear(now.getFullYear() - 1));
            break;
        default:
            start = new Date(now.setDate(now.getDate() - 30));
    }

    return { start, end: new Date() };
}

// Debounce function
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

// Throttle function
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}
