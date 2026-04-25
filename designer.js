/**
 * Barcode Designer JS
 * Refactored version with Virtual Scrolling and Centralized API
 */

const pxPerMm = 3.78;
const PX_PER_MM = 3.78;

// Global state
let labelObjects = [];
let selectedIndices = [];
let zoomLevel = 1;
let _autoZoom = 1;
let _manualZoom = null;
let _formatSaveTimer = null;
let pendingImageReplacement = null;
let pendingImageRatio = null;
let dimSyncInProgress = false;
let isDirty = false;
let history = [];
let historyIndex = -1;
let _isHistoryAction = false;
let isDbMode = false;
let allFields = [];

// Virtual Scrolling State
let allRecords = [];
let filteredRecords = [];
const rowHeight = 35; // px
const visibleRows = 40;
let scrollTop = 0;

/**
 * Initialize the designer
 */
function initDesigner(config) {
    labelObjects = config.labelObjects.map(o => {
        if (typeof o.properties === 'string') {
            try { o.properties = JSON.parse(o.properties); } catch (e) { o.properties = {}; }
        }
        return o;
    });
    
    allRecords = config.records;
    filteredRecords = [...allRecords];
    isDbMode = !!config.isDbMode;
    allFields = config.fields || [];
    
    initEventListeners(config.projectId);
    updateDesignerZoom();
    renderObjects();
    
    // Initial Virtual Scroll Render
    renderVirtualTable();
    
    // Initial History State
    saveState();
    
    initKeyboardShortcuts();

    // Init Bootstrap Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

function initKeyboardShortcuts() {
    window.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key.toLowerCase() === 'z') { e.preventDefault(); undo(); }
        if (e.ctrlKey && e.key.toLowerCase() === 'y') { e.preventDefault(); redo(); }
        if (e.ctrlKey && e.key.toLowerCase() === 's') { e.preventDefault(); saveDesigner(); }
        if (e.key === 'Delete') {
            if (selectedIndices.length > 0) {
                [...selectedIndices].sort((a,b) => b-a).forEach(idx => deleteObject(idx));
            }
        }
    });
}

function initEventListeners(projectId) {
    const hash = window.location.hash;
    if (hash) {
        const tCol = document.querySelector(`button[data-bs-target="${hash}"]`);
        if (tCol) bootstrap.Tab.getOrCreateInstance(tCol).show();
    }
    
    document.querySelectorAll('button[data-bs-toggle="pill"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', (e) => {
            const t = e.target.getAttribute('data-bs-target');
            history.replaceState(null, null, t);
            if (t === '#pills-designer') renderObjects();
            if (t === '#pills-data') renderVirtualTable();
        });
    });

    // Format Form Auto-Save
    document.querySelectorAll('#formatForm input, #formatForm select').forEach(inp => {
        inp.addEventListener('input', () => { updateDesignerZoom(); scheduleFormatSave(); });
        inp.addEventListener('change', () => { updateDesignerZoom(); scheduleFormatSave(); });
    });

    // Scroll-Event for Virtual Scrolling
    const tableContainer = document.getElementById('table-scroll-container');
    if (tableContainer) {
        tableContainer.addEventListener('scroll', (e) => {
            scrollTop = e.target.scrollTop;
            renderVirtualTable();
        });
    }

    // Modal Sync
    document.getElementById('objBarcodeType').addEventListener('change', function() {
        if (this.value === 'qr') syncObjectModalDimensions('width');
    });
    document.getElementById('objImageLockRatio').addEventListener('change', function() {
        if (this.checked) syncObjectModalDimensions('width');
    });
    document.getElementById('objWidth').addEventListener('input', () => syncObjectModalDimensions('width'));
    document.getElementById('objHeight').addEventListener('input', () => syncObjectModalDimensions('height'));

    // Image Upload Logic
    initImageUploadListeners();
}

/**
 * VIRTUAL SCROLLING LOGIC
 */
function renderVirtualTable() {
    const body = document.getElementById('data-table-body');
    const sentinel = document.getElementById('table-sentinel');
    const container = document.getElementById('table-scroll-container');
    if (!body || !container) return;

    const totalHeight = filteredRecords.length * rowHeight;
    sentinel.style.height = totalHeight + 'px';

    const startIndex = Math.max(0, Math.floor(scrollTop / rowHeight));
    const endIndex = Math.min(filteredRecords.length, startIndex + visibleRows);

    // Tabelle ist sticky, daher keine Verschiebung nötig
    body.style.transform = 'none';

    const slice = filteredRecords.slice(startIndex, endIndex);
    
    let html = '';
    slice.forEach((rec, i) => {
        const actualRid = rec.id;
        html += `<tr>
            <td class="ps-3 text-center"><input type="checkbox" class="form-check-input record-select-checkbox" data-id="${actualRid}" onchange="updateSelection(${actualRid}, this.checked)" ${rec.selected ? 'checked' : ''}></td>
            <td class="text-secondary small" style="font-size: 0.7rem;">${isDbMode ? (startIndex + i + 1) : (actualRid + 1)}</td>
            ${isDbMode ? `<td class="text-nowrap" style="width: 70px;"><button class="btn btn-link btn-sm text-info p-0 me-2" onclick="editRecord(${startIndex + i})"><i class="bi bi-pencil"></i></button><button class="btn btn-link btn-sm text-danger p-0" onclick="deleteRecord(${startIndex + i})"><i class="bi bi-trash"></i></button></td>` : ''}`;
        
        config_fields.forEach(f => {
            html += `<td>${rec.values[f.id] || ''}</td>`;
        });
        html += `</tr>`;
    });
    body.innerHTML = html;
}

let filterTimeout;
function filterTable() {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        const filters = Array.from(document.querySelectorAll('.column-filter'))
            .filter(i => i.value.trim() !== '')
            .map(input => ({
                colId: input.getAttribute('data-field-id'),
                val: input.value.toLowerCase()
            }));

        if (filters.length === 0) {
            filteredRecords = [...allRecords];
        } else {
            filteredRecords = allRecords.filter(rec => {
                return filters.every(f => {
                    const cellVal = String(rec.values[f.colId] || '').toLowerCase();
                    return cellVal.includes(f.val);
                });
            });
        }
        
        scrollTop = 0;
        const container = document.getElementById('table-scroll-container');
        if (container) container.scrollTop = 0;
        renderVirtualTable();
    }, 300);
}

function updateSelection(recordId, isSelected) {
    // Update local state
    const rec = allRecords.find(r => r.id === recordId);
    if (rec) rec.selected = isSelected;

    const fd = new FormData();
    fd.append('action', 'update_selection');
    fd.append('record_id', isDbMode && rec ? rec.db_id : recordId);
    fd.append('project_id', config_projectId);
    fd.append('selected', isSelected ? 1 : 0);
    if (isDbMode) fd.append('is_db_mode', '1');
    fetch('api.php', { method: 'POST', body: fd });
}

function toggleAllRecords(checked) {
    const ids = filteredRecords.map(r => isDbMode ? r.db_id : r.id);
    filteredRecords.forEach(r => r.selected = checked);
    renderVirtualTable();

    // Anzeige, dass gespeichert wird
    const btn = document.querySelector('button[onclick="openPreview()"]');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sync...';

    const fd = new FormData();
    fd.append('action', 'update_selection_batch');
    fd.append('project_id', config_projectId);
    fd.append('ids', JSON.stringify(ids));
    fd.append('selected', checked ? 1 : 0);
    if (isDbMode) fd.append('is_db_mode', '1');

    fetch('api.php', { method: 'POST', body: fd })
        .then(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
}

/**
 * DESIGNER LOGIC
 */
function updateDesignerZoom() {
    const fw = parseFloat(document.querySelector(`[name="width_mm_${config_projectId}"]`).value.replace(',', '.')) || 10;
    const fh = parseFloat(document.querySelector(`[name="height_mm_${config_projectId}"]`).value.replace(',', '.')) || 10;
    const canv = document.getElementById('designer-canvas');
    const rx = document.getElementById('ruler-x');
    const ry = document.getElementById('ruler-y');

    canv.style.width = (fw * pxPerMm) + 'px';
    canv.style.height = (fh * pxPerMm) + 'px';
    rx.style.width = (fw * pxPerMm) + 'px';
    ry.style.height = (fh * pxPerMm) + 'px';

    renderRulers(fw, fh);

    const targetAreaW = 850;
    const targetAreaH = 450;
    let scaleW = targetAreaW / (fw * pxPerMm);
    let scaleH = targetAreaH / (fh * pxPerMm);
    let newZoom = Math.min(scaleW, scaleH);
    if (newZoom > 5.0) newZoom = 5.0;
    if (newZoom < 0.2) newZoom = 0.2;

    zoomLevel = newZoom;
    _autoZoom = newZoom;
    _manualZoom = null;
    applyZoom(newZoom);
}

function applyZoom(z) {
    zoomLevel = z;
    document.getElementById('zoom-container').style.transform = `scale(${z})`;
    const lbl = document.getElementById('zoomLabel');
    if (lbl) lbl.textContent = Math.round(z * 100) + '%';
}

function manualZoom(delta) {
    const current = _manualZoom !== null ? _manualZoom : zoomLevel;
    let next = Math.round((current + delta) * 10) / 10;
    if (next < 0.1) next = 0.1;
    if (next > 5.0) next = 5.0;
    _manualZoom = next;
    applyZoom(next);
}

function resetZoom() {
    _manualZoom = null;
    applyZoom(_autoZoom || 1);
}

function renderRulers(fw, fh) {
    const rx = document.getElementById('ruler-x');
    const ry = document.getElementById('ruler-y');
    if (!rx || !ry) return;
    rx.innerHTML = '';
    ry.innerHTML = '';

    for(let i=0; i<=fw; i+=10) {
        rx.innerHTML += `<div style="position:absolute; left:${i*pxPerMm}px; bottom:2px; transform:translateX(2px);">${i}</div>
                         <div style="position:absolute; left:${i*pxPerMm}px; bottom:0; height:12px; border-left:1px solid #475569;"></div>`;
    }
    for(let i=5; i<=fw; i+=10) rx.innerHTML += `<div style="position:absolute; left:${i*pxPerMm}px; bottom:0; height:5px; border-left:1px solid #475569;"></div>`;

    for(let i=0; i<=fh; i+=10) {
        ry.innerHTML += `<div style="position:absolute; top:${i*pxPerMm}px; right:12px; transform:translateY(-50%);">${i}</div>
                         <div style="position:absolute; top:${i*pxPerMm}px; right:0; width:10px; border-top:1px solid #475569;"></div>`;
    }
    for(let i=5; i<=fh; i+=10) ry.innerHTML += `<div style="position:absolute; top:${i*pxPerMm}px; right:0; width:5px; border-top:1px solid #475569;"></div>`;
}

function updateEditButton() {
    const btn = document.getElementById('btnEditSelected');
    if (!btn) return;
    const hasOne = selectedIndices.length === 1;
    btn.disabled = !hasOne;
    btn.classList.toggle('btn-outline-primary', hasOne);
    btn.classList.toggle('btn-outline-secondary', !hasOne);
}

function snapValue(val) {
    const snap = document.getElementById('snapToGrid');
    if (!snap || !snap.checked) return val;
    const grid = parseFloat(document.getElementById('gridSize').value) || 1;
    return Math.round(val / grid) * grid;
}

function setDirty(dirty) {
    isDirty = dirty;
    const btn = document.getElementById('btnSaveDesigner');
    if (!btn) return;
    if (dirty) {
        if (!_isHistoryAction) saveState();
        btn.classList.add('btn-dirty');
        btn.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Design speichern *';
    } else {
        btn.classList.remove('btn-dirty');
        btn.innerHTML = '<i class="bi bi-cloud-check me-1"></i> Design speichern';
    }
}

function saveState() {
    if (_isHistoryAction) return;
    const state = JSON.stringify(labelObjects);
    // Don't save if same as last
    if (historyIndex >= 0 && history[historyIndex] === state) return;

    history = history.slice(0, historyIndex + 1);
    history.push(state);
    if (history.length > 50) history.shift();
    else historyIndex++;
    updateUndoRedoButtons();
}

function undo() {
    if (historyIndex <= 0) return;
    _isHistoryAction = true;
    historyIndex--;
    labelObjects = JSON.parse(history[historyIndex]);
    selectedIndices = [];
    _isHistoryAction = false;
    setDirty(true);
    renderObjects();
    updateUndoRedoButtons();
}

function redo() {
    if (historyIndex >= history.length - 1) return;
    _isHistoryAction = true;
    historyIndex++;
    labelObjects = JSON.parse(history[historyIndex]);
    selectedIndices = [];
    _isHistoryAction = false;
    setDirty(true);
    renderObjects();
    updateUndoRedoButtons();
}

function applyHistoryState() {
    _isHistoryAction = true;
    labelObjects = JSON.parse(history[historyIndex]);
    selectedIndices = [];
    renderObjects();
    updateUndoRedoButtons();
    _isHistoryAction = false;
}

function updateUndoRedoButtons() {
    const u = document.getElementById('btnUndo');
    const r = document.getElementById('btnRedo');
    if (u) u.disabled = (historyIndex <= 0);
    if (r) r.disabled = (historyIndex >= history.length - 1);
}

function renderObjects() {
    const canv = document.getElementById('designer-canvas');
    if (!canv) return;
    canv.innerHTML = '';
    updateEditButton();

    const guidesLayer = document.createElement('div');
    guidesLayer.id = 'guides-layer';
    guidesLayer.style.cssText = 'position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:1000;';
    canv.appendChild(guidesLayer);

    const fw = parseFloat(document.querySelector(`[name="width_mm_${config_projectId}"]`).value) || 10;
    const fh = parseFloat(document.querySelector(`[name="height_mm_${config_projectId}"]`).value) || 10;

    canv.addEventListener('click', (e) => {
        if (e.target === canv) {
            selectedIndices = [];
            document.querySelectorAll('.designer-object').forEach(el => el.classList.remove('selected'));
            updateEditButton();
        }
    }, true);

    if (document.getElementById('showCalibrationBorder').checked) {
        const border = document.createElement('div');
        border.style.position = 'absolute';
        border.style.boxSizing = 'border-box';
        border.style.pointerEvents = 'none';
        border.style.zIndex = '500';
        const bW = (fw - 1) * PX_PER_MM;
        const bH = (fh - 1) * PX_PER_MM;
        const bL = 0.5 * PX_PER_MM;
        const bT = 0.5 * PX_PER_MM;
        const bThick = 1 * PX_PER_MM;
        border.style.left = bL + 'px';
        border.style.top = bT + 'px';
        border.style.width = bW + 'px';
        border.style.height = bH + 'px';
        border.style.border = `${bThick}px solid rgba(239, 68, 68, 0.6)`;
        canv.appendChild(border);
    }

    labelObjects.forEach((obj, idx) => {
        const div = document.createElement('div');
        div.className = 'designer-object ' + (selectedIndices.includes(idx) ? 'selected' : '');
        div.title = "Doppelklick zum Bearbeiten";
        const rot = obj.rotation || 0;
        div.style.cssText = `left:${obj.x_mm*PX_PER_MM}px; top:${obj.y_mm*PX_PER_MM}px; width:${obj.width_mm*PX_PER_MM}px; height:${obj.height_mm*PX_PER_MM}px; transform: rotate(${rot}deg);`;
        
        const ctrl = document.createElement('div');
        ctrl.className = 'obj-controls no-print';
        const elHeightPx = obj.height_mm * PX_PER_MM;
        const ctrlScale = Math.min(1, Math.max(0.45, elHeightPx / 56));
        ctrl.style.transform = `scale(${ctrlScale.toFixed(2)})`;
        ctrl.innerHTML = `<div class="obj-btn" style="background:#6366f1" title="Ebene nach vorne" onclick="event.stopPropagation(); bringForward(${idx})">▲</div>
                          <div class="obj-btn" style="background:#6366f1" title="Ebene nach hinten" onclick="event.stopPropagation(); sendBackward(${idx})">▼</div>
                          <div class="obj-btn" style="background:#3b82f6" onclick="event.stopPropagation(); editObject(${idx})">✏️</div>
                          <div class="obj-btn" style="background:#ef4444" onclick="event.stopPropagation(); deleteObject(${idx})">🗑️</div>`;
        div.appendChild(ctrl);

        const inner = document.createElement('div');
        inner.style.pointerEvents = 'none'; inner.style.width='100%'; inner.style.height='100%'; inner.style.display='flex'; inner.style.alignItems='center'; inner.style.justifyContent='center';

        if(obj.type==='text') {
            inner.innerText = obj.properties.content||'Text';
            inner.style.fontSize = (obj.properties.font_size||10)+'pt';
            inner.style.fontFamily = obj.properties.font_family || "'Outfit', sans-serif";
            if(obj.properties.bold) inner.style.fontWeight = 'bold';
            if(obj.properties.italic) inner.style.fontStyle = 'italic';
            if(obj.properties.vertical) {
                inner.style.writingMode = 'vertical-rl';
                inner.style.textOrientation = 'upright';
                inner.style.letterSpacing = '-2px';
            }
            const ta = obj.properties.text_align || 'center';
            if (ta === 'left') inner.style.justifyContent = 'flex-start';
            if (ta === 'right') inner.style.justifyContent = 'flex-end';
        }
        else if(obj.type==='image') {
            if(obj.properties.image_data) {
                const img = document.createElement('img');
                img.src = obj.properties.image_data;
                img.style.width = '100%'; img.style.height = '100%'; img.style.objectFit = 'contain'; img.style.display = 'block';
                inner.appendChild(img);
            } else {
                inner.innerHTML = '<span style="font-size:9px; color:#94a3b8;"><i class="bi bi-image"></i> Kein Bild</span>';
            }
        }
        else {
            const c = document.createElement('canvas');
            const bType = obj.properties.barcode_type||'code128';
            const content = obj.properties.content||'123';
            let hasError = false;
            let errorMsg = "";
            if (bType === 'ean8' && !/^\d{8}$/.test(content) && content.indexOf('[~') === -1) {
                hasError = true; errorMsg = "EAN8 ERROR\n(8 Ziffern!)";
            } else if (bType === 'ean13' && !/^\d{12,13}$/.test(content) && content.indexOf('[~') === -1) {
                hasError = true; errorMsg = "EAN13 ERROR\n(12-13 Ziffern!)";
            }
            if (hasError) {
                const warn = document.createElement('div');
                warn.style.cssText = "position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(239, 68, 68, 0.6); display:flex; align-items:center; justify-content:center; text-align:center; color:white; font-size:8px; z-index:10; pointer-events:none;";
                warn.innerText = errorMsg;
                inner.appendChild(warn);
            }
            try {
                let bTypeInternal = bType;
                let isQR = bTypeInternal === 'qr';
                if(isQR) bTypeInternal = 'qrcode';
                const opts = { bcid: bTypeInternal, text: content, scale: 2, includetext: isQR ? false : (obj.properties.show_htr !== false) };
                if(!isQR) opts.height = 10;
                bwipjs.toCanvas(c, opts);
                c.style.width = '100%'; c.style.height = '100%'; c.style.objectFit = 'contain';
            } catch(e){}
            inner.appendChild(c);
        }
        div.appendChild(inner);

        // Resizers
        ['tl','tr','bl','br'].forEach(pos => {
            const resizer = document.createElement('div');
            resizer.className = 'resizer ' + pos;
            resizer.onmousedown = (e) => {
                e.stopPropagation();
                const sX=e.clientX, sY=e.clientY;
                const iW=obj.width_mm, iH=obj.height_mm, iX=obj.x_mm, iY=obj.y_mm;
                const isQR = obj.properties.barcode_type === 'qr';
                const move = (ev) => {
                    const dx = (ev.clientX - sX) / (PX_PER_MM * zoomLevel);
                    const dy = (ev.clientY - sY) / (PX_PER_MM * zoomLevel);
                    let nw = iW, nh = iH, nx = iX, ny = iY;
                    if(pos.includes('r')) nw = snapValue(iW + dx);
                    if(pos.includes('l')) { 
                        nw = snapValue(iW - dx); 
                        nx = snapValue(iX + dx); 
                        // Korrektur falls durch Snap nx/nw inkonsistent werden
                        if (pos.includes('l')) nx = iX + iW - nw;
                    }
                    if(pos.includes('b')) nh = snapValue(iH + dy);
                    if(pos.includes('t')) { 
                        nh = snapValue(iH - dy); 
                        ny = snapValue(iY + dy); 
                        if (pos.includes('t')) ny = iY + iH - nh;
                    }
                    if(nw < 3) { if(pos.includes('l')) nx = iX + (iW - 3); nw = 3; }
                    if(nh < 3) { if(pos.includes('t')) ny = iY + (iH - 3); nh = 3; }
                    if(isQR) {
                        const newSize = Math.max(nw, nh);
                        if(pos === 'br') { nw = newSize; nh = newSize; }
                        else if(pos === 'tl') { nx = iX + (iW - newSize); ny = iY + (iH - newSize); nw = newSize; nh = newSize; }
                        else if(pos === 'tr') { ny = iY + (iH - newSize); nw = newSize; nh = newSize; }
                        else if(pos === 'bl') { nx = iX + (iW - newSize); nw = newSize; nh = newSize; }
                    }
                    if(obj.type === 'image' && obj.properties.lock_ratio !== false) {
                        const r = obj.properties.ratio || 1;
                        nh = nw / r;
                        if(pos.includes('t')) ny = iY + iH - nh;
                        if(nh < 3) { nh = 3; nw = nh * r; if(pos.includes('l')) nx = iX + iW - nw; }
                    }
                    obj.width_mm = nw; obj.height_mm = nh; obj.x_mm = nx; obj.y_mm = ny;
                    div.style.width = (nw * PX_PER_MM) + 'px'; div.style.height = (nh * PX_PER_MM) + 'px';
                    div.style.left = (nx * PX_PER_MM) + 'px'; div.style.top = (ny * PX_PER_MM) + 'px';
                };
                const up = () => { 
                    document.removeEventListener('mousemove', move); 
                    document.removeEventListener('mouseup', up); 
                    document.getElementById('guides-layer').innerHTML = '';
                    setDirty(true);
                    renderObjects(); 
                };
                document.addEventListener('mousemove', move); document.addEventListener('mouseup', up);
            };
            div.appendChild(resizer);
        });

        div.onmousedown = (e) => {
            if(e.target.classList.contains('obj-btn')) return;
            if (e.ctrlKey) {
                if (selectedIndices.includes(idx)) selectedIndices = selectedIndices.filter(i => i !== idx);
                else selectedIndices.push(idx);
            } else {
                selectedIndices = [idx];
            }
            document.querySelectorAll('.designer-object').forEach((el, i) => {
                if (selectedIndices.includes(i)) el.classList.add('selected');
                else el.classList.remove('selected');
            });
            updateEditButton();
            const sX=e.clientX, sY=e.clientY;
            const initialPos = selectedIndices.map(i => ({idx: i, x: labelObjects[i].x_mm * PX_PER_MM, y: labelObjects[i].y_mm * PX_PER_MM}));
            const move = (ev) => {
                const dx = (ev.clientX - sX) / zoomLevel;
                const dy = (ev.clientY - sY) / zoomLevel;

                initialPos.forEach(p => {
                    const obj = labelObjects[p.idx];
                    obj.x_mm = snapValue((p.x + dx) / PX_PER_MM);
                    obj.y_mm = snapValue((p.y + dy) / PX_PER_MM);
                    const el = document.querySelectorAll('.designer-object')[p.idx];
                    if (el) {
                        el.style.left = obj.x_mm * PX_PER_MM + 'px';
                        el.style.top = obj.y_mm * PX_PER_MM + 'px';
                    }
                });
                checkSmartGuides(selectedIndices);
            };
            const up = () => { 
                document.removeEventListener('mousemove', move); 
                document.removeEventListener('mouseup', up); 
                document.getElementById('guides-layer').innerHTML = '';
                setDirty(true);
            };
            document.addEventListener('mousemove', move); document.addEventListener('mouseup', up);
        };
        div.ondblclick = () => editObject(idx);
        canv.appendChild(div);
    });
}

function addObject(t) {
    if (t === 'image') {
        document.getElementById('newImageFile').value = '';
        document.getElementById('newImageError').style.display = 'none';
        document.getElementById('newImagePreviewBox').style.display = 'none';
        document.getElementById('newImagePreview').src = '';
        document.getElementById('btnConfirmAddImage').disabled = true;
        new bootstrap.Modal(document.getElementById('imageUploadModal')).show();
        return;
    }
    const STEP = 5, W = 40, H = 15;
    const n = labelObjects.length;
    const fw = parseFloat(document.querySelector(`[name="width_mm_${config_projectId}"]`).value) || 10;
    const fh = parseFloat(document.querySelector(`[name="height_mm_${config_projectId}"]`).value) || 10;
    const maxX = Math.max(0, fw - W);
    const maxY = Math.max(0, fh - H);
    const offsetX = Math.min(n * STEP, maxX);
    const offsetY = Math.min(n * STEP, maxY);
    labelObjects.push({type:t, x_mm: offsetX, y_mm: offsetY, width_mm:W, height_mm:H, rotation: 0, properties:{content:t==='text'?'Text':'123', font_size:10, barcode_type:'code128', text_align: 'center'}});
    selectedIndices = [labelObjects.length - 1];
    setDirty(true);
    renderObjects();
}

function confirmAddImage() {
    const imgEl = document.getElementById('newImagePreview');
    const imgData = imgEl.src;
    if (!imgData || imgData === window.location.href) return;
    const STEP = 5, W = 20;
    const n = labelObjects.length;
    const ratio = (imgEl.naturalWidth > 0 && imgEl.naturalHeight > 0) ? imgEl.naturalWidth / imgEl.naturalHeight : 1;
    const H = parseFloat((W / ratio).toFixed(2));
    const fw = parseFloat(document.querySelector(`[name="width_mm_${config_projectId}"]`).value) || 10;
    const fh = parseFloat(document.querySelector(`[name="height_mm_${config_projectId}"]`).value) || 10;
    const maxX = Math.max(0, fw - W);
    const maxY = Math.max(0, fh - H);
    const offsetX = Math.min(n * STEP, maxX);
    const offsetY = Math.min(n * STEP, maxY);
    labelObjects.push({type:'image', x_mm: offsetX, y_mm: offsetY, width_mm: W, height_mm: H, rotation: 0, properties:{image_data: imgData, lock_ratio: true, ratio: ratio}});
    selectedIndices = [labelObjects.length - 1];
    setDirty(true);
    bootstrap.Modal.getInstance(document.getElementById('imageUploadModal')).hide();
    renderObjects();
}

function deleteObject(idx) { 
    labelObjects.splice(idx, 1); 
    selectedIndices = selectedIndices.filter(i => i !== idx).map(i => i > idx ? i - 1 : i); 
    setDirty(true);
    renderObjects(); 
}
function bringForward(idx) {
    if (idx >= labelObjects.length - 1) return;
    [labelObjects[idx], labelObjects[idx+1]] = [labelObjects[idx+1], labelObjects[idx]];
    selectedIndices = selectedIndices.map(i => i === idx ? idx+1 : i === idx+1 ? idx : i);
    setDirty(true);
    renderObjects();
}
function sendBackward(idx) {
    if (idx <= 0) return;
    [labelObjects[idx], labelObjects[idx-1]] = [labelObjects[idx-1], labelObjects[idx]];
    selectedIndices = selectedIndices.map(i => i === idx ? idx-1 : i === idx-1 ? idx : i);
    setDirty(true);
    renderObjects();
}

function editObject(idx) {
    if (!selectedIndices.includes(idx)) selectedIndices = [idx];
    const o = labelObjects[idx];
    document.getElementById('objContent').value = o.properties.content || '';
    document.getElementById('objContentGroup').style.display = o.type==='image'?'none':'block';
    document.getElementById('fontSizeGroup').style.display = o.type==='text'?'block':'none';
    document.getElementById('barcodeTypeGroup').style.display = o.type==='barcode'?'block':'none';
    document.getElementById('textOptionsGroup').style.display = o.type==='text'?'block':'none';
    document.getElementById('barcodeOptionsGroup').style.display = o.type==='barcode'?'block':'none';
    document.getElementById('imageOptionsGroup').style.display = o.type==='image'?'block':'none';
    
    if (o.type === 'image') {
        pendingImageReplacement = null; pendingImageRatio = null;
        if (!o.properties.ratio && o.width_mm > 0 && o.height_mm > 0) o.properties.ratio = o.width_mm / o.height_mm;
        document.getElementById('objImagePreview').src = o.properties.image_data || '';
        document.getElementById('objImageFile').value = '';
        document.getElementById('objImageError').style.display = 'none';
        document.getElementById('objImageLockRatio').checked = o.properties.lock_ratio !== false;
    }
    document.getElementById('objWidth').value = o.width_mm;
    document.getElementById('objHeight').value = o.height_mm;
    document.getElementById('objRotation').value = o.rotation || 0;

    if(o.type==='text') {
        document.getElementById('objFontSize').value = o.properties.font_size||10;
        document.getElementById('objFontFamily').value = o.properties.font_family || "'Outfit', sans-serif";
        document.getElementById('objBold').checked = !!o.properties.bold;
        document.getElementById('objItalic').checked = !!o.properties.italic;
        document.getElementById('objVertical').checked = !!o.properties.vertical;
        const ta = o.properties.text_align || 'center';
        const rb = document.querySelector(`input[name="objTextAlign"][value="${ta}"]`);
        if (rb) rb.checked = true;
    } else {
        document.getElementById('objBarcodeType').value = o.properties.barcode_type||'code128';
        document.getElementById('objShowHTR').checked = o.properties.show_htr !== false;
    }
    new bootstrap.Modal(document.getElementById('objectModal')).show();
}

function applyObjectProperties() {
    const idx = selectedIndices[0];
    if (idx === undefined) return;
    const o = labelObjects[idx];
    o.properties.content = document.getElementById('objContent').value;
    o.width_mm = parseFloat(document.getElementById('objWidth').value.replace(',', '.')) || o.width_mm;
    o.height_mm = parseFloat(document.getElementById('objHeight').value.replace(',', '.')) || o.height_mm;
    o.rotation = parseFloat(document.getElementById('objRotation').value) || 0;

    if(o.type==='text') {
        o.properties.font_size = document.getElementById('objFontSize').value;
        o.properties.font_family = document.getElementById('objFontFamily').value;
        o.properties.bold = document.getElementById('objBold').checked;
        o.properties.italic = document.getElementById('objItalic').checked;
        o.properties.vertical = document.getElementById('objVertical').checked;
        o.properties.text_align = document.querySelector('input[name="objTextAlign"]:checked').value;
    } else if(o.type==='barcode') {
        o.properties.barcode_type = document.getElementById('objBarcodeType').value;
        o.properties.show_htr = document.getElementById('objShowHTR').checked;
    } else if(o.type==='image') {
        if (pendingImageReplacement) {
            o.properties.image_data = pendingImageReplacement;
            pendingImageReplacement = null;
            if (pendingImageRatio) { o.properties.ratio = pendingImageRatio; pendingImageRatio = null; }
        }
        o.properties.lock_ratio = document.getElementById('objImageLockRatio').checked;
    }
    bootstrap.Modal.getInstance(document.getElementById('objectModal')).hide();
    setDirty(true);
    renderObjects();
}

function saveDesigner(silent = false) {
    const fd = new FormData(); 
    fd.append('action', 'save_objects');
    fd.append('project_id', config_projectId); 
    fd.append('objects', JSON.stringify(labelObjects));
    return fetch('api.php', {method:'POST', body:fd})
        .then(r=>r.json())
        .then(d=>{ 
            if(d.success) {
                setDirty(false);
                if(!silent) alert('Design erfolgreich gespeichert!'); 
            }
        });
}

function restoreDesign() {
    if (!confirm('Möchten Sie den letzten gespeicherten Stand aus der Datenbank wirklich wiederherstellen? Alle ungespeicherten Änderungen gehen verloren.')) return;
    fetch(`api.php?action=get_objects&project_id=${config_projectId}`)
        .then(r => r.json())
        .then(d => {
            if (!d.success) { alert('Fehler beim Wiederherstellen: ' + (d.message || 'Unbekannter Fehler')); return; }
            labelObjects = d.objects.map(o => { if (typeof o.properties === 'string') try { o.properties = JSON.parse(o.properties); } catch(e) { o.properties = {}; } return o; });
            selectedIndices = [];
            setDirty(false);
            renderObjects();
        })
        .catch(() => alert('Verbindungsfehler beim Wiederherstellen.'));
}

function saveDesignerAndPrint() {
    const cal = document.getElementById('showCalibrationBorder').checked ? 1 : 0;
    persistFormat()
        .then(() => saveDesigner(true))
        .then(() => { window.location.href = 'print_labels.php?id=' + config_projectId + '&cal=' + cal; });
}

function persistFormat() {
    clearTimeout(_formatSaveTimer);
    const fd = new FormData(document.getElementById('formatForm'));
    fd.append('action', 'update_format');
    return fetch('api.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => {
            if (!d || d.success !== true) throw new Error((d && d.message) ? d.message : 'Format konnte nicht gespeichert werden.');
            return d;
        });
}

function saveFormat() {
    persistFormat().then(() => location.reload()).catch(e => alert('Fehler beim Speichern des Formats: ' + (e.message || e)));
}

function openPreview() {
    const cal = document.getElementById('showCalibrationBorder').checked ? 1 : 0;
    persistFormat()
        .then(() => saveDesigner(true))
        .then(() => window.open(`generate_pdf.php?id=${config_projectId}&start=1&cal=` + cal, '_blank'))
        .catch(e => alert('Fehler vor der Vorschau: ' + (e.message || e)));
}

function applyTemplate(s) {
    if (!s.value) return;
    const tplId = parseInt(s.value);
    const fd = new FormData();
    fd.append('action', 'get_template');
    fd.append('id', tplId);
    fetch('api.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(t => {
            if (!t || !t.id) return;
            const pId = config_projectId;
            const f = document.getElementById('formatForm');
            f.querySelector(`[name="template_id_${pId}"]`).value = t.id;
            f.querySelector(`[name="width_mm_${pId}"]`).value = t.width_mm;
            f.querySelector(`[name="height_mm_${pId}"]`).value = t.height_mm;
            f.querySelector(`[name="cols_${pId}"]`).value = t.cols;
            f.querySelector(`[name="rows_${pId}"]`).value = t.rows;
            f.querySelector(`[name="col_gap_mm_${pId}"]`).value = t.col_gap_mm || 0;
            f.querySelector(`[name="row_gap_mm_${pId}"]`).value = t.row_gap_mm || 0;
            f.querySelector(`[name="margin_top_mm_${pId}"]`).value = t.margin_top_mm || 0;
            f.querySelector(`[name="margin_bottom_mm_${pId}"]`).value = t.margin_bottom_mm || 0;
            f.querySelector(`[name="margin_left_mm_${pId}"]`).value = t.margin_left_mm || 0;
            f.querySelector(`[name="margin_right_mm_${pId}"]`).value = t.margin_right_mm || 0;
            const mtSel = f.querySelector(`[name="media_type_${pId}"]`);
            if (mtSel) mtSel.value = t.media_type || 'sheet';
            const mtReadonly = document.getElementById('mediaTypeReadonly');
            if (mtReadonly) mtReadonly.value = ((t.media_type || 'sheet') === 'roll') ? 'Rolle (aus Vorlage)' : 'Bogen (aus Vorlage)';
            updateDesignerZoom();
            persistFormat().catch(e => alert('Fehler beim Übernehmen der Vorlage: ' + (e.message || e)));
        });
}

function scheduleFormatSave() {
    clearTimeout(_formatSaveTimer);
    _formatSaveTimer = setTimeout(() => { persistFormat().catch(() => {}); }, 600);
}

function setObjRotation(deg) { document.getElementById('objRotation').value = deg; }

function syncObjectModalDimensions(changedField) {
    if (dimSyncInProgress) return;
    const idx = selectedIndices[0];
    if (idx === undefined || !labelObjects[idx]) return;
    const obj = labelObjects[idx];
    const widthEl = document.getElementById('objWidth');
    const heightEl = document.getElementById('objHeight');
    const widthVal = parseFloat((widthEl.value || '').replace(',', '.'));
    const heightVal = parseFloat((heightEl.value || '').replace(',', '.'));
    if (!Number.isFinite(widthVal) || !Number.isFinite(heightVal) || widthVal <= 0 || heightVal <= 0) return;
    dimSyncInProgress = true;
    try {
        if (obj.type === 'barcode' && document.getElementById('objBarcodeType').value === 'qr') {
            if (changedField === 'width') heightEl.value = widthEl.value;
            else widthEl.value = heightEl.value;
        }
        if (obj.type === 'image' && document.getElementById('objImageLockRatio').checked) {
            const ratio = obj.properties.ratio || (obj.width_mm > 0 && obj.height_mm > 0 ? obj.width_mm / obj.height_mm : 1);
            if (ratio > 0) {
                if (changedField === 'width') heightEl.value = (widthVal / ratio).toFixed(2);
                else widthEl.value = (heightVal * ratio).toFixed(2);
            }
        }
    } finally { dimSyncInProgress = false; }
}

function alignObjects(type) {
    const isCenter = type === 'center_h' || type === 'center_v';
    if (!isCenter && selectedIndices.length < 2) return;
    if (isCenter && selectedIndices.length < 1) return;
    const targets = selectedIndices.map(idx => labelObjects[idx]);
    if (type === 'left') {
        const minX = Math.min(...targets.map(o => o.x_mm));
        targets.forEach(o => o.x_mm = minX);
    } else if (type === 'right') {
        const maxRight = Math.max(...targets.map(o => o.x_mm + o.width_mm));
        targets.forEach(o => o.x_mm = maxRight - o.width_mm);
    } else if (type === 'center_h') {
        const fw = parseFloat(document.querySelector(`[name="width_mm_${config_projectId}"]`).value.replace(',', '.')) || 10;
        targets.forEach(o => o.x_mm = snapValue((fw - o.width_mm) / 2));
    } else if (type === 'center_v') {
        const fh = parseFloat(document.querySelector(`[name="height_mm_${config_projectId}"]`).value.replace(',', '.')) || 10;
        targets.forEach(o => o.y_mm = snapValue((fh - o.height_mm) / 2));
    } else if (type === 'width') {
        const maxWidth = Math.max(...targets.map(o => o.width_mm));
        targets.forEach(o => o.width_mm = maxWidth);
    } else if (type === 'spacing_dist') {
        targets.sort((a, b) => a.y_mm - b.y_mm);
        const first = targets[0], last = targets[targets.length - 1];
        const totalHeightOfMiddle = targets.slice(0, -1).reduce((s, o) => s + o.height_mm, 0);
        const gap = (last.y_mm - first.y_mm - totalHeightOfMiddle) / (targets.length - 1);
        let currentY = first.y_mm;
        for (let i = 1; i < targets.length - 1; i++) {
            currentY += targets[i-1].height_mm + gap;
            targets[i].y_mm = currentY;
        }
    }
    setDirty(true);
    renderObjects();
}

function adjustSpacing(dir) {
    if (selectedIndices.length < 2) return;
    const targets = selectedIndices.map(idx => labelObjects[idx]);
    targets.sort((a, b) => a.y_mm - b.y_mm);
    const step = 2 * dir;
    for (let i = 1; i < targets.length; i++) targets[i].y_mm += i * step;
    setDirty(true);
    renderObjects();
}

function initImageUploadListeners() {
    document.getElementById('newImageFile').addEventListener('change', function() {
        const file = this.files[0];
        const errorEl = document.getElementById('newImageError');
        const previewBox = document.getElementById('newImagePreviewBox'), preview = document.getElementById('newImagePreview');
        const btn = document.getElementById('btnConfirmAddImage');
        errorEl.style.display = 'none'; previewBox.style.display = 'none'; btn.disabled = true;
        if (!file) return;
        if (!['image/jpeg', 'image/png'].includes(file.type)) { errorEl.textContent = 'Fehler: Nur JPG und PNG sind erlaubt.'; errorEl.style.display = 'block'; this.value = ''; return; }
        if (file.size > 204800) { errorEl.textContent = 'Datei zu groß (' + Math.round(file.size / 1024) + ' KB). Maximum: 200 KB.'; errorEl.style.display = 'block'; this.value = ''; return; }
        const reader = new FileReader();
        reader.onload = (e) => { preview.src = e.target.result; previewBox.style.display = 'block'; btn.disabled = false; };
        reader.readAsDataURL(file);
    });

    document.getElementById('objImageFile').addEventListener('change', function() {
        const file = this.files[0], errorEl = document.getElementById('objImageError');
        pendingImageReplacement = null; errorEl.style.display = 'none';
        if (!file) return;
        if (!['image/jpeg', 'image/png'].includes(file.type)) { errorEl.textContent = 'Fehler: Nur JPG und PNG sind erlaubt.'; errorEl.style.display = 'block'; this.value = ''; return; }
        if (file.size > 204800) { errorEl.textContent = 'Datei zu groß (' + Math.round(file.size / 1024) + ' KB). Maximum: 200 KB.'; errorEl.style.display = 'block'; this.value = ''; return; }
        const reader = new FileReader();
        reader.onload = (e) => {
            pendingImageReplacement = e.target.result;
            document.getElementById('objImagePreview').src = e.target.result;
            const tmp = new Image();
            tmp.onload = () => { if (tmp.naturalWidth > 0) pendingImageRatio = tmp.naturalWidth / tmp.naturalHeight; };
            tmp.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

function insertPlaceholder(n) { const t = document.getElementById('objContent'); const s = t.selectionStart; t.value = t.value.substring(0, s) + `[~${n}~]` + t.value.substring(t.selectionEnd); t.focus(); }

function openRecordModal(idx = null) {
    const title = document.getElementById('recordModalTitle');
    const form = document.getElementById('recordForm');
    const dbIdInput = document.getElementById('editRecordDbId');
    const idxInput = document.getElementById('editRecordIdx');
    
    form.reset();
    if (idx !== null) {
        const rec = filteredRecords[idx];
        title.innerHTML = '<i class="bi bi-pencil-square me-2 text-primary"></i>DATENSATZ BEARBEITEN';
        dbIdInput.value = rec.db_id;
        idxInput.value = idx;
        form.querySelectorAll('.record-input').forEach(input => {
            const fieldId = input.getAttribute('data-field-id');
            input.value = rec.values[fieldId] || '';
        });
    } else {
        title.innerHTML = '<i class="bi bi-plus-circle me-2 text-primary"></i>NEUER DATENSATZ';
        dbIdInput.value = '';
        idxInput.value = '';
    }
    new bootstrap.Modal(document.getElementById('recordModal')).show();
}

function saveRecord() {
    const dbId = document.getElementById('editRecordDbId').value;
    const idx = document.getElementById('editRecordIdx').value;
    const data = {};
    document.querySelectorAll('.record-input').forEach(input => {
        data[input.getAttribute('data-field-id')] = input.value;
    });

    const fd = new FormData();
    fd.append('action', dbId ? 'update_record' : 'add_record');
    if (dbId) fd.append('id', dbId);
    else fd.append('project_id', config_projectId);
    fd.append('data_json', JSON.stringify(data));

    fetch('api.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('recordModal')).hide();
                location.reload(); // Einfachste Lösung: Seite neu laden, um die PHP-Records zu aktualisieren
            } else {
                alert('Fehler beim Speichern: ' + res.message);
            }
        });
}

function editRecord(idx) {
    openRecordModal(idx);
}

function deleteRecord(idx) {
    if (!confirm('Diesen Datensatz wirklich löschen?')) return;
    const rec = filteredRecords[idx];
    const fd = new FormData();
    fd.append('action', 'delete_record');
    fd.append('id', rec.db_id);

    fetch('api.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) location.reload();
            else alert('Fehler beim Löschen: ' + res.message);
        });
}

function checkSmartGuides(activeIndices) {
    const layer = document.getElementById('guides-layer');
    if (!layer) return;
    layer.innerHTML = '';
    
    if (activeIndices.length !== 1) return; // Vorerst nur für einzelne Objekte
    const idx = activeIndices[0];
    const obj = labelObjects[idx];
    const THRESHOLD = 1; // mm
    
    const others = labelObjects.filter((o, i) => i !== idx);
    const myEdges = {
        x: [obj.x_mm, obj.x_mm + obj.width_mm / 2, obj.x_mm + obj.width_mm],
        y: [obj.y_mm, obj.y_mm + obj.height_mm / 2, obj.y_mm + obj.height_mm]
    };

    others.forEach(other => {
        const otherEdges = {
            x: [other.x_mm, other.x_mm + other.width_mm / 2, other.x_mm + other.width_mm],
            y: [other.y_mm, other.y_mm + other.height_mm / 2, other.y_mm + other.height_mm]
        };

        myEdges.x.forEach(mx => {
            otherEdges.x.forEach(ox => {
                if (Math.abs(mx - ox) < THRESHOLD) {
                    showGuide('v', ox);
                }
            });
        });

        myEdges.y.forEach(my => {
            otherEdges.y.forEach(oy => {
                if (Math.abs(my - oy) < THRESHOLD) {
                    showGuide('h', oy);
                }
            });
        });
    });
}

function showGuide(type, posMm) {
    const layer = document.getElementById('guides-layer');
    const line = document.createElement('div');
    const posPx = posMm * PX_PER_MM;
    line.style.position = 'absolute';
    line.style.borderColor = '#6366f1';
    line.style.borderStyle = 'dashed';
    line.style.borderWidth = '0';
    if (type === 'v') {
        line.style.left = posPx + 'px';
        line.style.top = '-500px';
        line.style.bottom = '-500px';
        line.style.borderLeftWidth = '1px';
    } else {
        line.style.top = posPx + 'px';
        line.style.left = '-500px';
        line.style.right = '-500px';
        line.style.borderTopWidth = '1px';
    }
    layer.appendChild(line);
}

function openFieldsModal() {
    const list = document.getElementById('fieldsList');
    list.innerHTML = '';
    allFields.forEach(f => addFieldRow(f.name));
    if (allFields.length === 0) addFieldRow();
    new bootstrap.Modal(document.getElementById('fieldsModal')).show();
}

function addFieldRow(val = '') {
    const list = document.getElementById('fieldsList');
    const div = document.createElement('div');
    div.className = 'input-group input-group-sm mb-2';
    div.innerHTML = `
        <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-grip-vertical"></i></span>
        <input type="text" class="form-control bg-dark text-light border-secondary field-name-input" value="${val}" placeholder="Spaltenname (z.B. Artikelnummer)">
        <button class="btn btn-outline-danger" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>
    `;
    list.appendChild(div);
}

function saveFields() {
    const names = Array.from(document.querySelectorAll('.field-name-input'))
        .map(i => i.value.trim())
        .filter(n => n !== '');
    
    if (names.length === 0) {
        alert('Bitte geben Sie mindestens einen Spaltennamen an.');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'save_fields');
    fd.append('project_id', config_projectId);
    fd.append('fields', JSON.stringify(names));

    fetch('api.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) location.reload();
            else alert('Fehler beim Speichern der Spalten: ' + res.message);
        });
}
