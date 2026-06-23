const WPSD_Utils = (function() {
    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[c]));
    }

    function safeText(v, fallback = '—') {
        if (v === null || v === undefined) return fallback;
        const s = String(v).trim();
        return s ? s : fallback;
    }

    function safeNum(v, fallback = 0) {
        const n = Number(v);
        return Number.isFinite(n) ? n : fallback;
    }

    function formatDate(dateStr, fallback = '—') {
        if (!dateStr) return fallback;
        try {
            const d = new Date(dateStr + 'T00:00:00');
            return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        } catch { return fallback; }
    }

    function formatAddress(p) {
        const parts = [];
        if (p?.address_line1) parts.push(p.address_line1);
        if (p?.address_line2) parts.push(p.address_line2);
        const cityLine = [p?.postal_code, p?.city].filter(Boolean).join(' ');
        if (cityLine) parts.push(cityLine);
        if (p?.country) parts.push(p.country);
        return parts.length ? parts.map(escapeHtml).join('<br>') : '—';
    }

    function statusBadge(s, label) {
        return `<span class="wpsd-badge wpsd-badge-${escapeHtml(s)}">${escapeHtml(label || s)}</span>`;
    }

    function $(id) { return document.getElementById(id); }

    // Validation functions
    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function validateRequired(value, label) {
        if (!value || !String(value).trim()) {
            return `${label} est requis.`;
        }
        return null;
    }

    function validateMaxLength(value, max, label) {
        if (value && String(value).length > max) {
            return `${label} ne doit pas dépasser ${max} caractères.`;
        }
        return null;
    }

    function showFieldError(inputId, message) {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.style.borderColor = '#dc2626';
        let errorEl = input.parentNode.querySelector('.wpsd-field-error');
        if (!errorEl) {
            errorEl = document.createElement('span');
            errorEl.className = 'wpsd-field-error';
            errorEl.style.cssText = 'color:#dc2626;font-size:12px;margin-top:4px;display:block;';
            input.parentNode.appendChild(errorEl);
        }
        errorEl.textContent = message;
    }

    function clearFieldErrors(prefix) {
        document.querySelectorAll(`[id^="wpsd_${prefix}_"]`).forEach(input => {
            input.style.borderColor = '';
        });
        document.querySelectorAll('.wpsd-field-error').forEach(el => el.remove());
    }

    // kindLabel — merged single definition (removed duplicate)
    function kindLabel(k) {
        if (k === 'activity') return 'Activité';
        if (k === 'accommodation') return 'Hébergement';
        if (k === 'both') return 'Activité + Hébergement';
        return k;
    }

    function validateModalFields(prefix, fields) {
        clearFieldErrors(prefix);
        let valid = true;

        for (const field of fields) {
            const value = WPSD_Modals.getVal(`wpsd_${prefix}_${field.id}`);
            if (field.required) {
                const err = validateRequired(value, field.label);
                if (err) {
                    showFieldError(`wpsd_${prefix}_${field.id}`, err);
                    valid = false;
                }
            }
            if (field.maxLength) {
                const err = validateMaxLength(value, field.maxLength, field.label);
                if (err) {
                    showFieldError(`wpsd_${prefix}_${field.id}`, err);
                    valid = false;
                }
            }
            if (field.type === 'email' && value) {
                if (!validateEmail(value)) {
                    showFieldError(`wpsd_${prefix}_${field.id}`, 'Email invalide.');
                    valid = false;
                }
            }
        }
        return valid;
    }

    return {
        escapeHtml,
        safeText,
        safeNum,
        formatDate,
        formatAddress,
        kindLabel,
        statusBadge,
        $,
        validateEmail,
        validateRequired,
        validateMaxLength,
        showFieldError,
        clearFieldErrors,
        validateModalFields
    };
})();