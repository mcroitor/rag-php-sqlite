'use strict';

const App = (() => {
    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&')
            .replaceAll('<', '<')
            .replaceAll('>', '>')
            .replaceAll('"', '"')
            .replaceAll("'", '&#039;');
    }

    async function api(path, options = {}) {
        const response = await fetch(path, options);
        let data = null;

        try {
            data = await response.json();
        } catch (err) {
            data = null;
        }

        if (!response.ok) {
            const message = data?.error?.message || `Request failed (${response.status})`;
            throw new Error(message);
        }

        return data;
    }

    function formatPercent(value) {
        return Number(value).toFixed(1) + '%';
    }

    async function initBaseSelector() {
        const select = document.getElementById('base-select');
        if (!select) {
            return;
        }

        try {
            const data = await api('/api/bases');
            select.innerHTML = (data.bases || [])
                .map((base) => `<option value="${escapeHtml(base.name)}">${escapeHtml(base.name)}</option>`)
                .join('');

            select.value = data.active || '';
            select.addEventListener('change', async () => {
                select.disabled = true;
                try {
                    await api('/api/bases', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ base: select.value }),
                    });
                    window.location.reload();
                } catch (err) {
                    select.disabled = false;
                    // Restore previous selection on failure.
                    select.value = select.dataset.active || '';
                    alert(err.message);
                }
            });
        } catch (err) {
            select.innerHTML = '<option value="">-</option>';
        }
    }

    function initCreateBaseModal() {
        const modal = document.getElementById('create-base-modal');
        const btn = document.getElementById('create-base-btn');
        const cancelBtn = document.getElementById('cancel-create-base');
        const form = document.getElementById('create-base-form');
        const input = document.getElementById('new-base-name');

        if (!modal || !btn || !form || !input) {
            return;
        }

        btn.addEventListener('click', () => {
            modal.style.display = 'flex';
            input.value = '';
            input.focus();
        });

        cancelBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const base = input.value.trim();
            if (!base) {
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating...';

            try {
                await api('/api/bases/create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ base }),
                });
                modal.style.display = 'none';
                window.location.reload();
            } catch (err) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create';
                alert(err.message);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initBaseSelector();
        initCreateBaseModal();
    });

    return { escapeHtml, api, formatPercent };
})();