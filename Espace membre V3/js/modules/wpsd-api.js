const WPSD_API = (function() {
    function getConfig() {
        return {
            restBase: (window.WPSD && window.WPSD.restBase) ? window.WPSD.restBase : '',
            nonce: (window.WPSD && window.WPSD.nonce) ? window.WPSD.nonce : '',
        };
    }

        async function req(path, method, data) {
        const { restBase, nonce } = getConfig();
        if (!restBase) return { ok: false, error: 'WPSD not initialized' };

        // Si le path commence par /wpsd/v2, utiliser directement l'URL REST
        let url;
        if (path.startsWith('/wpsd/v2/') || path.startsWith('wpsd/v2/')) {
            url = window.location.origin + '/wp-json' + (path.startsWith('/') ? '' : '/') + path;
        } else {
            url = restBase + path;
        }

        try {
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
                body: data ? JSON.stringify(data) : undefined,
                credentials: 'same-origin',
            });
            const text = await res.text();
            try { return JSON.parse(text); }
            catch { return { ok: false, error: 'REST non JSON', raw: text, status: res.status }; }
        } catch (e) {
            return { ok: false, error: e.message };
        }
    }

        return {
        req,
        get: (path) => req(path, 'GET'),
        post: (path, data) => req(path, 'POST', data),
        put: (path, data) => req(path, 'PUT', data),
        delete: (path) => req(path, 'DELETE'),
        // Aliases pour clarté sémantique
        putRequest: (path, data) => req(path, 'PUT', data),
        deleteRequest: (path) => req(path, 'DELETE'),
    };
})();