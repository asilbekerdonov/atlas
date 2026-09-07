
(function() {
    'use strict';
    
    // ============================================
    // ОСНОВНАЯ ЛОГИКА ПРОФИЛЯ
    // ============================================
    
    // Проверяем, что пользователь владелец профиля
    // isOwner передается из Twig через data-атрибут
    const profileContainer = document.querySelector('[data-profile-container]');
    const isOwner = profileContainer ? profileContainer.dataset.isOwner === 'true' : false;
    
    if (!isOwner) {
        return;
    }

    // --- Avatar: presign + direct upload, then autosave the URL ---
    const drop = document.getElementById('avatar-drop');
    if (drop) {
        drop.addEventListener('click', function() {
            if (isUploading) return;
            
            const i = document.createElement('input');
            i.type = 'file';
            i.accept = 'image/jpeg,image/png,image/webp';
            i.onchange = function() {
                if (i.files.length) {
                    // Передаем файл в редактор
                    openEditor(i.files[0]);
                }
            };
            i.click();
        });
        
        drop.addEventListener('dragover', function(e) {
            e.preventDefault();
            drop.classList.add('border-primary');
        });
        
        drop.addEventListener('dragleave', function() {
            drop.classList.remove('border-primary');
        });
        
        drop.addEventListener('drop', function(e) {
            e.preventDefault();
            drop.classList.remove('border-primary');
            if (e.dataTransfer.files.length && !isUploading) {
                openEditor(e.dataTransfer.files[0]);
            }
        });
    }

    // --- Info: attribute autocomplete + add row ---
    const search = document.getElementById('attribute-search');
    const suggestions = document.getElementById('attribute-suggestions');
    const addBtn = document.getElementById('attribute-add');
    let selectedAttribute = null;

    function fetchSuggestions(q) {
        if (!q || q.length < 1) {
            suggestions.classList.add('d-none');
            return;
        }
        
        fetch('/api/attributes/search?q=' + encodeURIComponent(q) + '&limit=8')
            .then(function(r) { return r.json(); })
            .then(function(attrs) {
                suggestions.innerHTML = '';
                if (!attrs.length) {
                    suggestions.classList.add('d-none');
                    return;
                }
                suggestions.classList.remove('d-none');
                attrs.forEach(function(a) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action d-flex justify-content-between';
                    btn.innerHTML = '<span>' + a.name + '</span><small class="text-muted">' + (a.category || '') + '</small>';
                    btn.addEventListener('click', function() {
                        selectedAttribute = a;
                        search.value = a.name;
                        suggestions.classList.add('d-none');
                    });
                    suggestions.appendChild(btn);
                });
            })
            .catch(function() {
                suggestions.classList.add('d-none');
            });
    }

    if (search) {
        search.addEventListener('input', function() {
            fetchSuggestions(search.value);
        });
        search.addEventListener('blur', function() {
            setTimeout(function() {
                suggestions.classList.add('d-none');
            }, 150);
        });
        search.addEventListener('focus', function() {
            if (search.value.length > 0) {
                fetchSuggestions(search.value);
            }
        });
    }

    function editorFor(a) {
        const wrap = document.createElement('div');
        wrap.className = 'col-md-8';
        if (a.dataType === 'ONE_OF_MANY') {
            const sel = document.createElement('select');
            sel.className = 'form-select';
            sel.dataset.attributeId = a.id;
            const none = document.createElement('option');
            none.value = '';
            none.textContent = '—';
            sel.appendChild(none);
            (a.options || []).forEach(function(o) {
                const opt = document.createElement('option');
                opt.value = o.id;
                opt.textContent = o.label;
                sel.appendChild(opt);
            });
            wrap.appendChild(sel);
        } else if (a.dataType === 'BOOLEAN') {
            const sel = document.createElement('select');
            sel.className = 'form-select';
            sel.dataset.attributeId = a.id;
            sel.innerHTML = '<option value="">—</option><option value="true">Да</option><option value="false">Нет</option>';
            wrap.appendChild(sel);
        } else if (a.dataType === 'DATE') {
            const input = document.createElement('input');
            input.type = 'date';
            input.className = 'form-control';
            input.dataset.attributeId = a.id;
            wrap.appendChild(input);
        } else if (a.dataType === 'PERIOD') {
            const div = document.createElement('div');
            div.className = 'd-flex gap-2';
            ['start', 'end'].forEach(function(part) {
                const input = document.createElement('input');
                input.type = 'date';
                input.className = 'form-control';
                input.dataset.attributeId = a.id;
                input.dataset.attrPart = part;
                div.appendChild(input);
            });
            wrap.appendChild(div);
        } else if (a.dataType === 'NUMERIC') {
            const input = document.createElement('input');
            input.type = 'number';
            input.step = '0.01';
            input.className = 'form-control';
            input.dataset.attributeId = a.id;
            wrap.appendChild(input);
        } else if (a.dataType === 'TEXT') {
            const ta = document.createElement('textarea');
            ta.rows = 2;
            ta.className = 'form-control';
            ta.dataset.attributeId = a.id;
            wrap.appendChild(ta);
        } else {
            const input = document.createElement('input');
            input.className = 'form-control';
            input.dataset.attributeId = a.id;
            wrap.appendChild(input);
        }
        return wrap;
    }

    if (addBtn) {
        addBtn.addEventListener('click', function() {
            if (!selectedAttribute) {
                fetchSuggestions(search?.value || '');
                return;
            }
            const a = selectedAttribute;
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 align-items-center';
            row.dataset.valueRow = '';
            const label = document.createElement('div');
            label.className = 'col-md-4';
            const strong = document.createElement('strong');
            strong.textContent = a.name;
            const type = document.createElement('span');
            type.className = 'text-muted small d-block';
            type.textContent = a.dataType;
            label.appendChild(strong);
            label.appendChild(type);
            row.appendChild(label);
            row.appendChild(editorFor(a));
            const removeCell = document.createElement('div');
            removeCell.className = 'col-auto';
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-sm btn-outline-danger';
            removeBtn.dataset.removeNew = '';
            removeBtn.innerHTML = '<i class="bi bi-dash-lg"></i>';
            removeBtn.title = 'Удалить';
            removeCell.appendChild(removeBtn);
            row.appendChild(removeCell);
            const container = document.getElementById('attribute-values-new');
            if (container) {
                container.appendChild(row);
            }
            selectedAttribute = null;
            if (search) search.value = '';
        });
    }

    // Удаление атрибутов
    document.addEventListener('click', function(e) {
        const removeNew = e.target.closest('[data-remove-new]');
        if (removeNew) {
            const newRow = removeNew.closest('[data-value-row]');
            if (newRow) {
                newRow.remove();
            }
            return;
        }

        const removeValue = e.target.closest('[data-remove-value]');
        if (!removeValue) {
            return;
        }

        const row = removeValue.closest('[data-value-row]');
        const valueId = removeValue.dataset.removeValue;
        const attributeName = removeValue.dataset.attributeName || '';

        if (!valueId) return;
        
        if (!confirm('Удалить значение атрибута "' + attributeName + '"?')) {
            return;
        }

        fetch('/api/profile/attribute-value/' + valueId, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json' }
        })
        .then(function(r) {
            return r.json().then(function(d) {
                return { ok: r.ok, status: r.status, data: d };
            });
        })
        .then(function(res) {
            if (!res.ok) {
                alert(res.data.message || 'Не удалось удалить значение');
                return;
            }
            if (row) {
                row.remove();
            }
            if (res.data.unpublishedCvs && res.data.unpublishedCvs.length > 0) {
                alert('CV "' + res.data.unpublishedCvs[0].positionTitle + '" будет снят с публикации после удаления атрибута "' + attributeName + '"');
            }
        })
        .catch(function() {
            alert('Ошибка сети при удалении значения');
        });
    });

    // --- Projects tab: inline edit + autosave ---
    const projectConflicts = {
        title: 'Конфликт версий',
        body: 'Данные проекта были изменены другим пользователем. Что вы хотите сделать?',
        reload: 'Загрузить версию сервера',
        keepMine: 'Сохранить мою версию',
        saved: 'Сохранено',
        saving: 'Сохранение...',
        confirmDelete: 'Вы уверены, что хотите удалить этот проект?'
    };

    function projectPayload(row) {
        return {
            name: row.querySelector('[data-field="name"]').value.trim(),
            startDate: row.querySelector('[data-field="startDate"]').value,
            endDate: row.querySelector('[data-field="endDate"]').value || null,
            descriptionMd: row.querySelector('[data-field="descriptionMd"]').value,
            tags: collectProjectTags(row),
            expectedVersion: parseInt(row.dataset.version || '1', 10)
        };
    }

    function collectProjectTags(row) {
        const input = row.querySelector('[data-field="tags"]');
        if (input && input.tagify) {
            return input.tagify.value.map(function(t) { return t.value; });
        }
        return (input ? input.value : '').split(',').map(function(s) { return s.trim(); }).filter(Boolean);
    }

    function setProjectState(row, text) {
        const el = row.querySelector('[data-state]');
        if (el) {
            el.textContent = text;
        }
    }

    function showProjectConflict(row, serverVersion) {
        // Просто показываем alert с вариантами
        const choice = confirm(
            projectConflicts.title + '\n\n' +
            projectConflicts.body + '\n\n' +
            'OK: ' + projectConflicts.reload + '\n' +
            'Cancel: ' + projectConflicts.keepMine
        );
        
        if (choice) {
            // Reload - перезагружаем страницу
            window.location.reload();
        } else {
            // Keep mine - обновляем версию и сохраняем
            row.dataset.version = String(serverVersion);
            saveProject(row);
        }
    }

    function saveProject(row) {
        if (row.dataset.saving === '1') {
            return;
        }
        const id = row.dataset.projectId;
        if (!id) {
            return;
        }
        row.dataset.saving = '1';
        setProjectState(row, projectConflicts.saving);

        fetch('/api/projects/' + id, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(projectPayload(row))
        })
        .then(function(r) {
            return r.json().then(function(d) {
                return { ok: r.ok, status: r.status, data: d };
            });
        })
        .then(function(res) {
            row.dataset.saving = '0';
            if (res.ok) {
                row.dataset.version = String(res.data.version);
                setProjectState(row, projectConflicts.saved);
            } else if (res.status === 409) {
                setProjectState(row, 'Конфликт');
                showProjectConflict(row, res.data.serverVersion);
            } else {
                setProjectState(row, 'Ошибка');
                alert(res.data.message || res.data.error || 'Не удалось сохранить проект');
            }
        })
        .catch(function() {
            row.dataset.saving = '0';
            setProjectState(row, 'Ошибка');
            alert('Ошибка сети при сохранении проекта');
        });
    }

    function initProjectRow(row) {
        // Tagify для тегов
        const tagsInput = row.querySelector('[data-field="tags"]');
        if (tagsInput && typeof Tagify !== 'undefined') {
            const tagify = new Tagify(tagsInput, {
                dropdown: {
                    enabled: 0,
                    classname: 'tagify__dropdown'
                },
                callbacks: {
                    input: function(e) {
                        tagify.settings.whitelist = [];
                        const query = e.detail.value || '';
                        if (query.length < 1) return;
                        
                        fetch('/api/tags?q=' + encodeURIComponent(query))
                            .then(function(r) { return r.json(); })
                            .then(function(tags) {
                                tagify.settings.whitelist = tags.map(function(t) { return t.name; });
                                tagify.dropdown.show.call(tagify, query);
                            })
                            .catch(function() { /* ignore */ });
                    }
                }
            });
            tagsInput.tagify = tagify;
        }

        // Debounced autosave
        let projectTimer = null;
        row.addEventListener('input', function() {
            clearTimeout(projectTimer);
            projectTimer = setTimeout(function() {
                saveProject(row);
            }, 600);
        });
        row.addEventListener('change', function() {
            clearTimeout(projectTimer);
            projectTimer = setTimeout(function() {
                saveProject(row);
            }, 600);
        });

        // Delete
        const deleteBtn = row.querySelector('.project-delete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                if (!confirm(projectConflicts.confirmDelete)) {
                    return;
                }
                fetch('/api/projects/' + row.dataset.projectId, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json' }
                })
                .then(function(r) {
                    if (r.ok || r.status === 204) {
                        row.remove();
                    } else {
                        return r.json().then(function(d) {
                            throw new Error(d.message || 'Delete failed');
                        });
                    }
                })
                .catch(function(e) {
                    alert(e.message || 'Не удалось удалить проект');
                });
            });
        }
    }

    document.querySelectorAll('#project-list [data-project-row]').forEach(initProjectRow);

    // Create project
    const projectCreateForm = document.getElementById('project-create');
    if (projectCreateForm) {
        // Инициализируем Tagify для формы создания
        const createTagsInput = document.getElementById('project-tags');
        if (createTagsInput && typeof Tagify !== 'undefined') {
            const tagify = new Tagify(createTagsInput, {
                dropdown: {
                    enabled: 0,
                    classname: 'tagify__dropdown'
                },
                callbacks: {
                    input: function(e) {
                        tagify.settings.whitelist = [];
                        const query = e.detail.value || '';
                        if (query.length < 1) return;
                        
                        fetch('/api/tags?q=' + encodeURIComponent(query))
                            .then(function(r) { return r.json(); })
                            .then(function(tags) {
                                tagify.settings.whitelist = tags.map(function(t) { return t.name; });
                                tagify.dropdown.show.call(tagify, query);
                            })
                            .catch(function() { /* ignore */ });
                    }
                }
            });
            createTagsInput.tagify = tagify;
        }

        projectCreateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const payload = {
                name: document.getElementById('project-name').value.trim(),
                startDate: document.getElementById('project-start').value,
                endDate: document.getElementById('project-end').value || null,
                descriptionMd: document.getElementById('project-desc').value,
                tags: []
            };
            
            const tagsInput = document.getElementById('project-tags');
            if (tagsInput && tagsInput.tagify) {
                payload.tags = tagsInput.tagify.value.map(function(t) { return t.value; });
            } else {
                payload.tags = (tagsInput ? tagsInput.value : '').split(',').map(function(s) { return s.trim(); }).filter(Boolean);
            }

            fetch('/api/projects', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(function(r) {
                return r.json().then(function(d) {
                    return { ok: r.ok, status: r.status, data: d };
                });
            })
            .then(function(res) {
                if (!res.ok) {
                    alert(res.data.message || res.data.error || 'Не удалось создать проект');
                    return;
                }
                
                const row = document.createElement('div');
                row.className = 'list-group-item';
                row.dataset.projectRow = '';
                row.dataset.projectId = String(res.data.id);
                row.dataset.version = String(res.data.version);
                row.innerHTML =
                    '<div class="row g-2">' +
                    '<div class="col-md-6"><input class="form-control form-control-sm project-field" data-field="name" value="' + escapeAttr(res.data.name) + '"></div>' +
                    '<div class="col-md-3"><input class="form-control form-control-sm project-field" type="date" data-field="startDate" value="' + escapeAttr(res.data.startDate) + '"></div>' +
                    '<div class="col-md-3"><input class="form-control form-control-sm project-field" type="date" data-field="endDate" value="' + escapeAttr(res.data.endDate || '') + '"></div>' +
                    '<div class="col-12"><textarea class="form-control form-control-sm project-field" rows="2" data-field="descriptionMd">' + escapeHtml(res.data.descriptionMd) + '</textarea></div>' +
                    '<div class="col-12"><input class="form-control form-control-sm project-field project-tags" data-field="tags" value="' + escapeAttr((res.data.tags || []).join(', ')) + '"></div>' +
                    '</div>' +
                    '<div class="d-flex justify-content-between align-items-center mt-1">' +
                    '<small class="text-muted project-state" data-state>' + projectConflicts.saved + '</small>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger project-delete"><i class="bi bi-trash"></i></button>' +
                    '</div>';
                
                const list = document.getElementById('project-list');
                const empty = list.querySelector('p.text-muted');
                if (empty) {
                    empty.remove();
                }
                list.prepend(row);
                initProjectRow(row);
                
                projectCreateForm.reset();
                if (createTagsInput && createTagsInput.tagify) {
                    createTagsInput.tagify.removeAllTags();
                }
                
                const collapseEl = document.getElementById('project-form');
                if (collapseEl) {
                    bootstrap.Collapse.getOrCreateInstance(collapseEl).hide();
                }
            })
            .catch(function() {
                alert('Ошибка сети при создании проекта');
            });
        });
    }

    function escapeHtml(value) {
        return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    
    function escapeAttr(value) {
        return escapeHtml(String(value)).replace(/"/g, '&quot;');
    }

    // ============================================
    // AVATAR EDITOR (native canvas)
    // ============================================
    
    // Состояние редактора
    let isUploading = false;
    let currentFile = null;
    let editorImg = null;   // оригинальное изображение (HTMLImageElement)
    // Трансформация изображения относительно центра канваса:
    // scale - множитель пикселей изображения, rot - градусы (кратно 90),
    // tx/ty - сдвиг центра изображения в CSS-пикселях канваса.
    let view = { scale: 1, rot: 0, tx: 0, ty: 0 };
    
    // DOM элементы для редактора
    const modal = document.getElementById('avatarEditorModal');
    const stageEl = document.querySelector('#avatarEditorModal .avatar-editor-stage');
    const avatarCanvas = document.getElementById('avatar-canvas');
    const zoomSlider = document.getElementById('zoom-slider');
    const rotateLeftBtn = document.getElementById('rotate-left-btn');
    const rotateRightBtn = document.getElementById('rotate-right-btn');
    const resetBtn = document.getElementById('reset-btn');
    const saveBtn = document.getElementById('save-avatar-btn');
    const progressContainer = document.getElementById('avatar-upload-progress');
    const progressBar = progressContainer ? progressContainer.querySelector('.progress-bar') : null;
    const hiddenAvatarField = document.querySelector('[data-autosave-field="avatarUrl"]');
    
    // ---- Геометрия ----
    // Стадия квадратная (aspect-ratio 1/1), канвас занимает её целиком.
    // Круглая "рамка" - статичная CSS-маска поверх канваса (не перерисовывается);
    // изображение рисуется на весь квадрат, а покрытие кадра гарантируется
    // cover-масштабом + клиппингом панорамы ниже.
    function canvasSide() {
        return avatarCanvas ? avatarCanvas.clientWidth : 0;
    }
    
    function setupCanvas() {
        const S = canvasSide();
        if (!S) return 0;
        const dpr = window.devicePixelRatio || 1;
        // Физический размер = CSS-размер x DPR, чтобы края были чёткими.
        avatarCanvas.width = Math.round(S * dpr);
        avatarCanvas.height = Math.round(S * dpr);
        return S;
    }
    
    // Cover-масштаб: изображение целиком накрывает квадрат SxS.
    function coverScale(S) {
        const Nw = editorImg.naturalWidth, Nh = editorImg.naturalHeight;
        return Math.max(S / Nw, S / Nh);
    }
    
    // Эффективные габариты изображения в осях канваса (поворот кратен 90°,
    // поэтому ширина и высота просто меняются местами).
    function effDims() {
        const Nw = editorImg.naturalWidth, Nh = editorImg.naturalHeight;
        const w = Nw * view.scale, h = Nh * view.scale;
        return (view.rot % 180 === 0) ? { w: w, h: h } : { w: h, h: w };
    }
    
    // Не даём утащить изображение так, чтобы внутри кадра появилась пустота.
    function clampPan() {
        const S = canvasSide();
        const d = effDims();
        const maxX = Math.max(0, (d.w - S) / 2);
        const maxY = Math.max(0, (d.h - S) / 2);
        view.tx = Math.min(maxX, Math.max(-maxX, view.tx));
        view.ty = Math.min(maxY, Math.max(-maxY, view.ty));
    }
    
    // Единственная перерисовка: один drawImage() на изменение трансформации.
    function render() {
        if (!editorImg || !avatarCanvas) return;
        const S = canvasSide();
        if (!S) return;
        const ctx = avatarCanvas.getContext('2d');
        const dpr = window.devicePixelRatio || 1;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, S, S);
        clampPan();
        const Nw = editorImg.naturalWidth, Nh = editorImg.naturalHeight;
        const rad = view.rot * Math.PI / 180;
        ctx.save();
        ctx.translate(S / 2 + view.tx, S / 2 + view.ty);
        ctx.rotate(rad);
        ctx.scale(view.scale, view.scale);
        ctx.drawImage(editorImg, -Nw / 2, -Nh / 2);
        ctx.restore();
    }
    
    // Zoom с сохранением точки изображения под курсором (px,py).
    function zoomAt(px, py, factor) {
        if (!editorImg) return;
        const S = canvasSide();
        const cover = coverScale(S);
        const next = Math.min(Math.max(cover, view.scale * factor), cover * 5);
        const f = next / view.scale;
        view.scale = next;
        view.tx = f * view.tx + (1 - f) * (px - S / 2);
        view.ty = f * view.ty + (1 - f) * (py - S / 2);
        render();
        if (zoomSlider) {
            zoomSlider.value = (view.scale / cover).toFixed(2);
        }
    }
    
    function resetView() {
        const S = canvasSide();
        view.rot = 0;
        view.tx = 0;
        view.ty = 0;
        view.scale = coverScale(S);
        if (zoomSlider) zoomSlider.value = 1;
        render();
    }
    
    function rotateView(degrees) {
        view.rot = (view.rot + degrees) % 360;
        render();
    }
    
    function initEditor() {
        if (!editorImg || !avatarCanvas) return;
        setupCanvas();
        resetView();
    }
    
    function openEditor(file) {
        // Проверка типа файла
        if (!file.type.startsWith('image/')) {
            alert('Пожалуйста, выберите файл изображения');
            return;
        }
        
        // Проверка размера (максимум 10MB)
        if (file.size > 10 * 1024 * 1024) {
            alert('Изображение слишком большое (максимум 10MB)');
            return;
        }
        
        currentFile = file;
        
        // Грузим файл через object URL: декодирование выполняет браузер,
        // а canvas получает уже готовое изображение (без ручного парсинга).
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = function() {
            URL.revokeObjectURL(url);
            editorImg = img;
            
            // Показываем модальное окно и инициализируем редактор строго после
            // события shown.bs.modal: CSS-анимация открытия (~300ms) должна
            // завершиться, иначе clientWidth канваса ещё не финальный.
            const bsModal = new bootstrap.Modal(modal);
            modal.addEventListener('shown.bs.modal', function onShown() {
                modal.removeEventListener('shown.bs.modal', onShown);
                initEditor();
            });
            bsModal.show();
        };
        img.onerror = function() {
            URL.revokeObjectURL(url);
            alert('Не удалось прочитать изображение. Попробуйте другой файл.');
        };
        img.src = url;
    }
    
    // ---- Взаимодействие: drag мышью/пальцем + pinch-zoom (Pointer Events) ----
    if (avatarCanvas && stageEl) {
        const pointers = new Map();
        let gesture = null; // { type: 'pan'|'pinch', ... }
        
        stageEl.addEventListener('pointerdown', function(e) {
            if (!editorImg) return;
            stageEl.setPointerCapture(e.pointerId);
            pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
            
            if (pointers.size === 1) {
                gesture = {
                    type: 'pan',
                    startX: e.clientX,
                    startY: e.clientY,
                    startTx: view.tx,
                    startTy: view.ty
                };
                stageEl.classList.add('dragging');
            } else if (pointers.size === 2) {
                // Второй палец опущен -> переходим в pinch-zoom.
                const pts = Array.from(pointers.values());
                gesture = {
                    type: 'pinch',
                    prevDist: Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y)
                };
            }
            e.preventDefault();
        });
        
        stageEl.addEventListener('pointermove', function(e) {
            if (!gesture || pointers.size === 0) return;
            pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
            const pts = Array.from(pointers.values());
            const rect = stageEl.getBoundingClientRect();
            const S = canvasSide();
            
            if (gesture.type === 'pan' && pts.length === 1) {
                view.tx = gesture.startTx + (e.clientX - gesture.startX);
                view.ty = gesture.startTy + (e.clientY - gesture.startY);
                render();
            } else if (gesture.type === 'pinch' && pts.length === 2) {
                const dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
                if (gesture.prevDist > 0 && dist > 0) {
                    const cx = (pts[0].x + pts[1].x) / 2 - rect.left;
                    const cy = (pts[0].y + pts[1].y) / 2 - rect.top;
                    zoomAt(cx, cy, dist / gesture.prevDist);
                }
                gesture.prevDist = dist;
            }
        });
        
        function endPointer(e) {
            pointers.delete(e.pointerId);
            if (pointers.size === 0) {
                gesture = null;
                stageEl.classList.remove('dragging');
            } else if (gesture && gesture.type === 'pinch' && pointers.size === 1) {
                // Остался один палец - продолжаем панораму с текущего положения.
                const rest = Array.from(pointers.values())[0];
                gesture = {
                    type: 'pan',
                    startX: rest.x,
                    startY: rest.y,
                    startTx: view.tx,
                    startTy: view.ty
                };
            }
        }
        stageEl.addEventListener('pointerup', endPointer);
        stageEl.addEventListener('pointercancel', endPointer);
        
        // Колесо мыши: zoom к точке под курсором.
        stageEl.addEventListener('wheel', function(e) {
            if (!editorImg) return;
            e.preventDefault();
            const rect = stageEl.getBoundingClientRect();
            const factor = Math.exp(-e.deltaY * 0.002);
            zoomAt(e.clientX - rect.left, e.clientY - rect.top, factor);
        }, { passive: false });
    }
    
    // Масштабирование через слайдер (1x = изображение накрывает весь кадр)
    if (zoomSlider) {
        zoomSlider.addEventListener('input', function() {
            if (!editorImg) return;
            const S = canvasSide();
            const cover = coverScale(S);
            const next = Math.max(cover, cover * parseFloat(this.value));
            const f = next / view.scale;
            view.scale = next;
            view.tx = f * view.tx;
            view.ty = f * view.ty;
            render();
        });
    }
    
    // Поворот влево
    if (rotateLeftBtn) {
        rotateLeftBtn.addEventListener('click', function() {
            if (!editorImg) return;
            rotateView(-90);
        });
    }
    
    // Поворот вправо
    if (rotateRightBtn) {
        rotateRightBtn.addEventListener('click', function() {
            if (!editorImg) return;
            rotateView(90);
        });
    }
    
    // Сброс
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            if (!editorImg) return;
            resetView();
        });
    }
    
    
    // Сохранение обрезанного изображения (400x400)
    if (saveBtn) {
        saveBtn.addEventListener('click', async function() {
            if (!editorImg || !currentFile || isUploading) return;
            
            try {
                isUploading = true;
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Сохранение...';
                
                // Экспортируем видимую область канваса в квадрат 400x400.
                // Трансформация повторяет render(): масштаб домножается на
                // k = 400/S, чтобы изображение заняло тот же кадр, что на экране.
                const S = canvasSide();
                const OUT = 400;
                const k = OUT / S;
                const outCanvas = document.createElement('canvas');
                outCanvas.width = OUT;
                outCanvas.height = OUT;
                const octx = outCanvas.getContext('2d');
                const Nw = editorImg.naturalWidth, Nh = editorImg.naturalHeight;
                const rad = view.rot * Math.PI / 180;
                octx.save();
                octx.translate(OUT / 2 + view.tx * k, OUT / 2 + view.ty * k);
                octx.rotate(rad);
                octx.scale(view.scale * k, view.scale * k);
                octx.imageSmoothingQuality = 'high';
                octx.drawImage(editorImg, -Nw / 2, -Nh / 2);
                octx.restore();
                
                // Конвертируем в Blob (JPEG)
                const blob = await new Promise(function(resolve) {
                    outCanvas.toBlob(resolve, 'image/jpeg', 0.92);
                });
                if (!blob) {
                    throw new Error('toBlob вернул null');
                }
                
                // Показываем прогресс
                progressContainer.classList.remove('d-none');
                updateProgress(10);
                
                // Загружаем на сервер через presign
                await uploadCroppedAvatar(blob, currentFile.name);
                
                // Закрываем модалку
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
                
                // Обновляем превью
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (drop) {
                        drop.innerHTML = '<img src="' + e.target.result + '" class="avatar-image" alt="avatar">';
                    }
                };
                reader.readAsDataURL(blob);
                
                updateProgress(100);
                setTimeout(function() {
                    progressContainer.classList.add('d-none');
                    if (progressBar) {
                        progressBar.style.width = '0%';
                        progressBar.textContent = '0%';
                    }
                }, 1000);
                
            } catch (error) {
                console.error('Error saving avatar:', error);
                alert('Не удалось загрузить аватар. Пожалуйста, попробуйте снова.');
            } finally {
                isUploading = false;
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="bi bi-check-lg"></i> Сохранить и продолжить';
            }
        });
    }
    
    
    async function uploadCroppedAvatar(blob, filename) {
        try {
            updateProgress(20);
            
            const presignResponse = await fetch('/api/media/presign', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    filename: filename,
                    mimeType: 'image/jpeg',
                    sizeBytes: blob.size
                })
            });
            
            if (!presignResponse.ok) {
                const error = await presignResponse.json();
                throw new Error(error.detail || error.message || 'Failed to get upload URL');
            }
            
            const presignData = await presignResponse.json();
            updateProgress(40);
            
            const formData = new FormData();
            Object.keys(presignData.fields).forEach(function(key) {
                formData.append(key, presignData.fields[key]);
            });
            formData.append('file', blob, filename);
            
            const uploadResponse = await fetch(presignData.url, {
                method: 'POST',
                body: formData
            });
            
            if (!uploadResponse.ok) {
                throw new Error('Upload failed');
            }
            
            updateProgress(70);
            
            let fileUrl = presignData.secure_url || presignData.url;
            
            if (!fileUrl || fileUrl === presignData.url) {
                const path = presignData.key || presignData.fields?.key || '';
                fileUrl = presignData.uploadUrl + '/' + path;
            }
            
            updateProgress(85);
            
            if (hiddenAvatarField) {
                hiddenAvatarField.value = fileUrl;
                hiddenAvatarField.dispatchEvent(new Event('change', { bubbles: true }));
                
                const form = hiddenAvatarField.closest('form[data-autosave]');
                if (form && form.__autosave && typeof form.__autosave.save === 'function') {
                    await form.__autosave.save();
                }
            }
            
            updateProgress(95);
            
            return fileUrl;
            
        } catch (error) {
            console.error('Upload error:', error);
            throw error;
        }
    }
    
    function updateProgress(percent) {
        if (!progressBar) return;
        const rounded = Math.round(percent);
        progressBar.style.width = rounded + '%';
        progressBar.textContent = rounded + '%';
    }
    
    // Очистка при закрытии модалки
    if (modal) {
        modal.addEventListener('hidden.bs.modal', function() {
            editorImg = null;
            currentFile = null;
            view = { scale: 1, rot: 0, tx: 0, ty: 0 };
            if (avatarCanvas) {
                avatarCanvas.getContext('2d').clearRect(0, 0, avatarCanvas.width, avatarCanvas.height);
            }
            if (zoomSlider) {
                zoomSlider.value = 1;
            }
        });
    }
    
    // Поддержка клавиатуры
    document.addEventListener('keydown', function(e) {
        if (!modal || !modal.classList.contains('show') || !editorImg) return;
        
        switch(e.key) {
            case 'r':
            case 'R':
                e.preventDefault();
                rotateView(90);
                break;
            case 'z':
            case 'Z':
                e.preventDefault();
                zoomAt(canvasSide() / 2, canvasSide() / 2, 1.1);
                break;
            case 'x':
            case 'X':
                e.preventDefault();
                zoomAt(canvasSide() / 2, canvasSide() / 2, 0.9);
                break;
            case 'Enter':
                e.preventDefault();
                if (saveBtn) saveBtn.click();
                break;
            case 'Escape':
                e.preventDefault();
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
                break;
        }
    });
    
    
    // Обработка ошибок сети
    window.addEventListener('online', function() {
        const badge = document.querySelector('.autosave-badge');
        if (badge) {
            badge.textContent = badge.dataset.saved || 'Сохранено';
            badge.classList.remove('text-bg-danger');
            badge.classList.add('text-bg-success');
        }
    });
    
    window.addEventListener('offline', function() {
        const badge = document.querySelector('.autosave-badge');
        if (badge) {
            badge.textContent = 'Офлайн - изменения будут сохранены при восстановлении соединения';
            badge.classList.remove('text-bg-success');
            badge.classList.add('text-bg-danger');
        }
    });

    console.log('Profile JS initialized successfully');
})();