const WPSD_State = (function() {
    let state = {
        activeTab: 'dashboard',
        activeSubtab: null,
        loading: {},
        data: {},
    };

    const listeners = {};

    function get(key) {
        return key ? state[key] : state;
    }

    function set(key, value) {
        const old = state[key];
        state[key] = value;
        if (old !== value && listeners[key]) {
            listeners[key].forEach(fn => fn(value, old));
        }
        document.dispatchEvent(new CustomEvent('wpsd-state-change', { detail: { key, value, old } }));
    }

    function on(key, fn) {
        if (!listeners[key]) listeners[key] = [];
        listeners[key].push(fn);
    }

    function setLoading(key, val) {
        if (!state.loading) state.loading = {};
        state.loading[key] = val;
        set('loading', { ...state.loading });
    }

    return { get, set, on, setLoading };
})();