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
            badge.classList.remove('d-none');
            if (state === 'saving') {
                badge.className = 'badge text-bg-warning autosave-badge';
                badge.textContent = labels.saving;
            } else if (state === 'error') {
                badge.className = 'badge text-bg-danger autosave-badge';
                badge.textContent = labels.error || 'Не удалось сохранить';
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
                '<div class="mt-2 d-flex gap-2">' +
                '<button type="button" class="btn btn-sm btn-light" id="reload-data">' +
                (badge ? badge.dataset.reload : 'Reload') + '</button>' +
                '<button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="toast">Оставить мои изменения</button>' +
                '</div></div>';
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
            timer = setTimeout(save, 600); // debounce input bursts
            if (badge) {
                badge.className = 'badge text-bg-secondary autosave-badge';
                badge.textContent = badge.dataset.waiting || '…';
            }
        }

        function save() {
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
            if (inFlight) {
                return Promise.resolve();
            }
            inFlight = true;
            setBadge('saving');
            if (!navigator.onLine) {
                inFlight = false;
                setOffline();
                return Promise.resolve({ ok: false, offline: true });
            }
            return fetch('/api/profile/autosave', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(collect())
            }).then(function (response) {
                return response.text().then(function (body) {
                    let data = {};
                    if (body.trim() !== '') {
                        try {
                            data = JSON.parse(body);
                        } catch (err) {
                            console.error('Autosave returned invalid JSON.', {
                                status: response.status,
                                body: body
                            });
                        }
                    }
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
                    setBadge('error');
                }
                return result;
            }).catch(function (err) {
                inFlight = false;
                console.error('Autosave request failed.', err);
                if (!navigator.onLine) {
                    setOffline();
                } else {
                    setBadge('error');
                }
                throw err;
            });
        }

        form.__autosave = {
            save: save,
            scheduleSave: scheduleSave,
            collect: collect
        };

        form.addEventListener('input', scheduleSave);
        form.addEventListener('change', scheduleSave);
        form.addEventListener('autosave:save', save);
    });

    function setOffline() {
        document.querySelectorAll('.autosave-badge').forEach(function (badge) {
            badge.classList.remove('d-none', 'text-bg-success', 'text-bg-warning', 'text-bg-secondary');
            badge.classList.add('badge', 'text-bg-danger', 'autosave-badge');
            badge.textContent = 'Офлайн...';
        });
    }

    function setOnline() {
        document.querySelectorAll('.autosave-badge').forEach(function (badge) {
            badge.classList.remove('d-none', 'text-bg-danger', 'text-bg-warning', 'text-bg-secondary');
            badge.classList.add('badge', 'text-bg-success', 'autosave-badge');
            badge.textContent = badge.dataset.saved || 'Сохранено';
        });
    }

    window.addEventListener('offline', setOffline);
    window.addEventListener('online', setOnline);
})();
