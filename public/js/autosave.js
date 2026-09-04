// Autosave engine for the candidate profile: debounced PATCH with the
// current version; a non-intrusive status badge and a 409-conflict toast.
(function () {
    'use strict';

    document.querySelectorAll('[data-autosave]').forEach(function (form) {
        const badge = document.querySelector(form.dataset.badge || '[data-autosave-badge]');
        let timer = null;
        let inFlight = false;

        function setBadge(state) {
            if (!badge) {
                return;
            }
            const labels = badge.dataset;
            if (state === 'saving') {
                badge.className = 'badge text-bg-warning autosave-badge';
                badge.textContent = labels.saving;
            } else {
                badge.className = 'badge text-bg-success autosave-badge';
                badge.textContent = labels.saved;
            }
        }

        function collect() {
            const payload = { attributeValues: [] };
            form.querySelectorAll('[data-autosave-field]').forEach(function (input) {
                const name = input.dataset.autosaveField;
                payload[name] = input.value;
            });

            // Group inputs by attribute; date-range parts merge into {start, end}.
            const byAttr = {};
            form.querySelectorAll('[data-attribute-id]').forEach(function (input) {
                const id = input.dataset.attributeId;
                const part = input.dataset.attrPart;
                const value = input.value === '' ? null : input.value;
                if (part) {
                    byAttr[id] = byAttr[id] || {};
                    byAttr[id][part] = value;
                } else {
                    byAttr[id] = value;
                }
            });
            payload.attributeValues = Object.keys(byAttr).map(function (id) {
                return { attributeId: parseInt(id, 10), value: byAttr[id], version: null };
            });
            payload.expectedVersion = parseInt(form.dataset.version || '1', 10);
            return payload;
        }

        function showConflictToast(serverVersion) {
            const container = document.getElementById('toast-container');
            if (!container) {
                return;
            }
            const toast = document.createElement('div');
            toast.className = 'toast text-bg-danger';
            toast.innerHTML =
                '<div class="toast-header"><strong class="me-auto">' +
                (badge ? badge.dataset.conflictTitle : 'Conflict') + '</strong>' +
                '<button type="button" class="btn-close" data-bs-dismiss="toast"></button></div>' +
                '<div class="toast-body">' +
                (badge ? badge.dataset.conflictBody : '') +
                '<button type="button" class="btn btn-sm btn-light mt-2" id="reload-data">' +
                (badge ? badge.dataset.reload : 'Reload') + '</button></div>';
            container.appendChild(toast);
            toast.querySelector('#reload-data').addEventListener('click', function () {
                window.location.reload();
            });
            new bootstrap.Toast(toast).show();
            if (form.dataset) {
                form.dataset.version = String(serverVersion);
            }
        }

        function scheduleSave() {
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(save, 6000); // debounce 6s
            if (badge) {
                badge.className = 'badge text-bg-secondary autosave-badge';
                badge.textContent = badge.dataset.waiting || '…';
            }
        }

        function save() {
            if (inFlight) {
                return;
            }
            inFlight = true;
            setBadge('saving');
            fetch('/api/profile/autosave', {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(collect())
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, status: response.status, data: data };
                });
            }).then(function (result) {
                inFlight = false;
                if (result.ok) {
                    // Sync every autosave form on the page with the new version
                    // so parallel edits on different tabs do not conflict.
                    document.querySelectorAll('[data-autosave]').forEach(function (other) {
                        other.dataset.version = String(result.data.newVersion);
                    });
                    setBadge('saved');
                } else if (result.status === 409) {
                    showConflictToast(result.data.serverVersion);
                } else {
                    setBadge('saved');
                }
            }).catch(function () {
                inFlight = false;
                setBadge('saved');
            });
        }

        form.addEventListener('input', scheduleSave);
        form.addEventListener('change', scheduleSave);
    });
})();
