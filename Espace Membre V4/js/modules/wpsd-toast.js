const WPSD_Toast = (function() {
    let container = null;

    function ensureContainer() {
        if (container) return container;
        container = document.createElement('div');
        container.id = 'wpsd-toast-container';
        container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;';
        document.body.appendChild(container);
        return container;
    }

    function show(message, type, duration) {
        type = type || 'info';
        duration = duration || 4000;

        var c = ensureContainer();
        var colors = { success: '#166534', error: '#7f1d1d', info: '#1e40af', warning: '#92400e' };
        var bgs = { success: '#dcfce7', error: '#fee2e2', info: '#dbeafe', warning: '#fef3c7' };
        var icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };

        var el = document.createElement('div');
        el.style.cssText = 'background:' + (bgs[type] || bgs.info) + ';color:' + (colors[type] || colors.info) + ';padding:12px 16px;border-radius:8px;font-family:Exo,sans-serif;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,0.15);display:flex;align-items:center;gap:8px;min-width:280px;animation:wpsd-slide-in 0.3s ease;';
        el.innerHTML = '<span>' + (icons[type] || '') + '</span><span>' + WPSD_Utils.escapeHtml(message) + '</span>';
        c.appendChild(el);

        setTimeout(function() {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.3s';
            setTimeout(function() { el.remove(); }, 300);
        }, duration);
    }

    return { show: show };
})();