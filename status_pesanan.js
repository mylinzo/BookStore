// status_pesanan.js

// Global variables
let currentFilter = 'semua';
let currentOrders = [];

// Initialize status page
function initStatusPage() {
    loadOrders('semua');
    setupSearch();
    setupEventListeners();
}

// Setup event listeners
function setupEventListeners() {
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        const detailModal = document.getElementById('order-detail-modal');
        const uploadModal = document.getElementById('upload-proof-modal');
        
        if (event.target === detailModal) {
            closeModal('order-detail-modal');
        }
        
        if (event.target === uploadModal) {
            closeModal('upload-proof-modal');
        }
    });
    
    // Close modal buttons
    document.getElementById('close-detail-modal')?.addEventListener('click', function() {
        closeModal('order-detail-modal');
    });
    
    document.getElementById('close-upload-modal')?.addEventListener('click', function() {
        closeModal('upload-proof-modal');
    });
    
    // Logout button
    document.getElementById('logout-btn')?.addEventListener('click', function() {
        if (confirm('Apakah Anda yakin ingin logout?')) {
            window.location.href = 'status_pesanan.php?logout=true';
        }
    });
    
    // Auto-refresh every 60 seconds
    setInterval(() => {
        loadOrders(currentFilter);
    }, 60000);
}

// Export as module (if using modules)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initStatusPage,
        loadOrders,
        filterOrders,
        showOrderDetail,
        exportOrders
    };
}