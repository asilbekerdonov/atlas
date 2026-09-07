// Action Toolbar: tables with a checkbox column and a toolbar above them.
// Row selection highlights the row (table-active), toolbar buttons enable
// based on selection and permission data attributes rendered by Twig.
(function () {
    'use strict';

    function postForm(url, callback) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.style.display = 'none';
        document.body.appendChild(form);
        form.submit();
    }

    document.querySelectorAll('[data-action-toolbar]').forEach(function (container) {
        const table = container.querySelector('table');
        const rows = Array.from(table.querySelectorAll('tbody tr[data-row-id]'));
        const buttons = Array.from(container.querySelectorAll('button[data-action]'));

        function selectedIds() {
            return rows.filter(function (row) { return row.classList.contains('selected'); })
                .map(function (row) { return row.dataset.rowId; });
        }

        function updateButtons() {
            const ids = selectedIds();
            buttons.forEach(function (btn) {
                const action = btn.dataset.action;
                if (action === 'create') {
                    btn.disabled = false;
                    return;
                }
                if (action === 'delete') {
                    btn.disabled = ids.length === 0;
                    return;
                }
                // Single-row actions (edit / duplicate / apply / block / promote /
                // demote): enabled only when exactly one row is selected.
                const single = ids.length === 1;
                if (!single) {
                    btn.disabled = true;
                    return;
                }
                // Role-gated actions: the selected row must carry the role the
                // button targets (e.g. promote needs ROLE_CANDIDATE, demote needs
                // ROLE_RECRUITER) — admins never match, so they stay disabled.
                if (btn.dataset.requiredRole) {
                    const row = rows.find(function (r) { return r.classList.contains('selected'); });
                    btn.disabled = !row || row.dataset.role !== btn.dataset.requiredRole;
                    return;
                }
                btn.disabled = false;
            });
        }

        rows.forEach(function (row) {
            const checkbox = row.querySelector('input[type="checkbox"]');
            row.addEventListener('click', function (event) {
                if (event.target.closest('a, button, input, form')) {
                    return;
                }
                row.classList.toggle('selected');
                if (checkbox) {
                    checkbox.checked = row.classList.contains('selected');
                }
                updateButtons();
            });
            if (checkbox) {
                checkbox.addEventListener('change', function () {
                    row.classList.toggle('selected', checkbox.checked);
                    updateButtons();
                });
            }
        });

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const action = btn.dataset.action;
                const ids = selectedIds();

                if (action === 'create') {
                    window.location = btn.dataset.url;
                    return;
                }

                const id = ids[0];
                if (!id) {
                    return;
                }

                if (action === 'edit' || action === 'view') {
                    window.location = btn.dataset.urlPrefix + id + (action === 'edit' ? '/edit' : '');
                } else if (action === 'block') {
                    // POST /admin/users/{id}/block (full page redirect on success).
                    postForm(btn.dataset.urlPrefix + id + '/block');
                } else if (action === 'promote' || action === 'demote') {
                    // POST to /admin/users/{id}/promote|demote after confirmation.
                    if (window.confirm(btn.dataset.confirm || 'Change this user\'s role?')) {
                        postForm(btn.dataset.urlPrefix + id + '/' + action);
                    }
                } else if (action === 'apply') {
                    window.location = btn.dataset.urlPrefix + id + '/apply';
                } else if (action === 'duplicate') {
                    postForm(btn.dataset.urlPrefix + id + '/duplicate');
                } else if (action === 'delete') {
                    // Single row: classic HTML form POST (full page redirect).
                    if (ids.length === 1) {
                        if (window.confirm(btn.dataset.confirm || 'Delete this item?')) {
                            postForm(btn.dataset.urlPrefix + id + '/delete');
                        }
                        return;
                    }
                    // Multiple rows: bulk soft-delete via JSON.
                    if (!window.confirm('Delete ' + ids.length + ' selected items?')) {
                        return;
                    }
                    fetch('/positions/bulk-delete', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ ids: ids })
                    }).then(function (r) {
                        return r.json();
                    }).then(function (data) {
                        if (data && data.success) {
                            window.location.reload();
                        } else {
                            alert(data && data.message ? data.message : 'Failed to delete');
                        }
                    }).catch(function () {
                        alert('Network error while deleting');
                    });
                }
            });
        });

        updateButtons();
    });
})();
