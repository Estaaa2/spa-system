<div id="toast-container" class="fixed top-4 right-4 z-[9999] space-y-2"></div>

<script>
window.showToast = function (message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');

    const styles = {
        success: 'bg-green-100 text-green-800 border-green-200',
        error: 'bg-red-100 text-red-800 border-red-200',
        info: 'bg-blue-100 text-blue-800 border-blue-200'
    };

    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle'
    };

    toast.className = `
        flex items-center gap-3 px-4 py-3 border rounded-lg shadow-lg
        transition-all duration-300 translate-x-full opacity-0
        ${styles[type] ?? styles.info}
    `;

    toast.innerHTML = `
        <i class="fa-solid ${icons[type] ?? icons.info}"></i>
        <span class="text-sm font-medium">${message}</span>
    `;

    container.appendChild(toast);

    // Animate in
    requestAnimationFrame(() => {
        toast.classList.remove('translate-x-full', 'opacity-0');
    });

    // Auto remove
    setTimeout(() => {
        toast.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
};
</script>
