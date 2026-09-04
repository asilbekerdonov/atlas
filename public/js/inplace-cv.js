// In-place editing of CV attribute values (master profile writes).
(function () {
    'use strict';

    function inputFor(field) {
        const type = field.dataset.type;
        if (type === 'one_of_many') {
            const select = document.createElement('select');
            select.className = 'form-select form-select-sm';
            try {
                const options = JSON.parse(field.dataset.options || '[]');
                options.forEach(function (option) {
                    const el = document.createElement('option');
                    el.value = option.id;
                    el.textContent = option.label;
                    if (String(option.id) === String(field.dataset.current)) {
                        el.selected = true;
                    }
                    select.appendChild(el);
                });
            } catch (e) {
                /* ignore malformed options */
            }
            return select;
        }
        if (type === 'date') {
            const input = document.createElement('input');
            input.type = 'date';
            input.value = field.dataset.current || '';
            input.className = 'form-control form-control-sm';
            return input;
        }
        if (type === 'boolean') {
            const select = document.createElement('select');
            select.className = 'form-select form-select-sm';
            select.innerHTML = '<option value="">—</option><option value="true">Yes</option><option value="false">No</option>';
            select.value = field.dataset.current === 'true' ? 'true' : (field.dataset.current === 'false' ? 'false' : '');
            return select;
        }
        const input = document.createElement('input');
        input.className = 'form-control form-control-sm';
        input.value = field.dataset.current || '';
        input.placeholder = field.dataset.current === '' ? 'empty' : '';
        if (type === 'numeric') {
            input.type = 'number';
            input.step = '0.01';
        }
        return input;
    }

    function normalizeValue(field, element) {
        if (field.dataset.type === 'one_of_many' || field.dataset.type === 'boolean') {
            return element.value === '' ? null : element.value;
        }
        if (element.type === 'date') {
            return element.value === '' ? null : element.value;
        }
        return element.value === '' ? null : element.value;
    }

    function renderEmpty(field, isEmpty) {
        field.classList.toggle('cv-empty', isEmpty);
        field.dataset.empty = isEmpty ? '1' : '';
        field.textContent = isEmpty
            ? (field.dataset.emptyLabel || '—')
            : (field.dataset.display || field.dataset.current);
        field.title = field.dataset.saveHint || '';
    }

    function save(field, element) {
        const cvId = field.dataset.cvId;
        const payload = {
            attributeId: parseInt(field.dataset.attributeId, 10),
            value: normalizeValue(field, element),
            version: field.dataset.version ? parseInt(field.dataset.version, 10) : null
        };

        fetch('/api/cv/' + cvId + '/attribute', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, status: response.status, data: data };
            });
        }).then(function (result) {
            if (result.ok) {
                field.dataset.current = result.data.rawValue !== null && result.data.rawValue !== undefined
                    ? String(result.data.rawValue) : (element.value === '' ? '' : element.value);
                field.dataset.display = result.data.displayValue || '';
                field.dataset.version = String(result.data.version);
                renderEmpty(field, result.data.isEmpty);
                field.dispatchEvent(new CustomEvent('cv:attribute-saved'));
                if (window.CvPublish && typeof window.CvPublish.refresh === 'function') {
                    window.CvPublish.refresh();
                }
            } else if (result.status === 409) {
                field.classList.add('cv-empty');
                window.location.reload();
            } else {
                renderEmpty(field, true);
            }
        });
    }

    document.querySelectorAll('.inplace-edit').forEach(function (field) {
        field.addEventListener('click', function () {
            const element = inputFor(field);
            field.replaceChildren(element);
            element.focus();
            element.select && element.select();

            function commit() {
                if (element.parentNode) {
                    save(field, element);
                    // Re-render the static label while the request is in flight.
                    element.replaceWith(document.createTextNode(element.value === '' ? '…' : element.value));
                }
            }

            element.addEventListener('change', commit);
            element.addEventListener('blur', commit);
            element.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    commit();
                } else if (e.key === 'Escape') {
                    renderEmpty(field, field.dataset.current === '');
                }
            });
        });
    });
})();
