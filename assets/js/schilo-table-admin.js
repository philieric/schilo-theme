/**
 * schilo-table-admin.js
 * Éditeur de tableau interactif dans la méta box WordPress.
 */
(function () {
    'use strict';

    var app     = document.getElementById('sct-app');
    if (!app) return;

    var tbody   = document.getElementById('sct-tbody');
    var jsonFld = document.getElementById('sct-json');
    var hasHdr  = document.getElementById('sct-has-header');

    /* ── État interne ─────────────────────────────────────── */
    var rows  = [];
    var ncols = 2;

    try {
        rows  = JSON.parse(app.dataset.rows || '[]');
        ncols = parseInt(app.dataset.ncols, 10) || 2;
    } catch (e) {}

    if (!rows.length) {
        rows = [['', ''], ['', '']];
        ncols = 2;
    }

    /* ── Rendu du tbody ───────────────────────────────────── */
    function render() {
        tbody.innerHTML = '';

        rows.forEach(function (row, ri) {
            var tr  = document.createElement('tr');
            var isH = (ri === 0 && hasHdr.checked);

            /* Cellules */
            row.forEach(function (cell, ci) {
                var td  = document.createElement('td');
                var inp = document.createElement('input');
                inp.type  = 'text';
                inp.value = cell;
                inp.placeholder = isH ? 'En-tête ' + (ci + 1) : 'Cellule';
                inp.setAttribute('data-ri', ri);
                inp.setAttribute('data-ci', ci);
                inp.addEventListener('input', function () {
                    rows[ri][ci] = this.value;
                    sync();
                });
                td.appendChild(inp);
                if (isH) td.classList.add('sct-head-cell');
                tr.appendChild(td);
            });

            /* Bouton supprimer ligne */
            var tdDel = document.createElement('td');
            tdDel.className = 'sct-del-cell';
            var btnDel = document.createElement('button');
            btnDel.type      = 'button';
            btnDel.innerHTML = '&times;';
            btnDel.title     = 'Supprimer cette ligne';
            btnDel.addEventListener('click', function () {
                if (rows.length <= 1) return;
                rows.splice(ri, 1);
                render();
                sync();
            });
            tdDel.appendChild(btnDel);
            tr.appendChild(tdDel);

            tbody.appendChild(tr);
        });

        /* En-tête des colonnes (boutons suppr col) */
        renderColDelRow();
    }

    function renderColDelRow() {
        /* Ligne fantôme avec boutons "suppr colonne" sous le tableau */
        var existing = document.getElementById('sct-col-del-row');
        if (existing) existing.remove();

        var tr = document.createElement('tr');
        tr.id  = 'sct-col-del-row';

        for (var ci = 0; ci < ncols; ci++) {
            (function (colIdx) {
                var td  = document.createElement('td');
                td.className = 'sct-del-col-cell';
                var btn = document.createElement('button');
                btn.type      = 'button';
                btn.innerHTML = '&times; col';
                btn.title     = 'Supprimer la colonne ' + (colIdx + 1);
                btn.addEventListener('click', function () {
                    if (ncols <= 1) return;
                    rows = rows.map(function (row) {
                        return row.filter(function (_, i) { return i !== colIdx; });
                    });
                    ncols--;
                    render();
                    sync();
                });
                td.appendChild(btn);
                tr.appendChild(td);
            })(ci);
        }

        /* Cellule vide pour aligner avec la colonne suppr-ligne */
        var tdEmpty = document.createElement('td');
        tr.appendChild(tdEmpty);
        tbody.appendChild(tr);
    }

    /* ── Sync → champ caché ───────────────────────────────── */
    function sync() {
        try { jsonFld.value = JSON.stringify(rows); } catch(e) {}
    }

    /* ── Ajouter une ligne ────────────────────────────────── */
    document.getElementById('sct-add-row').addEventListener('click', function () {
        var newRow = [];
        for (var i = 0; i < ncols; i++) newRow.push('');
        rows.push(newRow);
        render();
        sync();
    });

    /* ── Ajouter une colonne ──────────────────────────────── */
    document.getElementById('sct-add-col').addEventListener('click', function () {
        rows = rows.map(function (row) {
            return row.concat(['']);
        });
        ncols++;
        render();
        sync();
    });

    /* ── Réagir au toggle en-tête ─────────────────────────── */
    hasHdr.addEventListener('change', function () { render(); });

    /* ── Préparer le JSON avant soumission ────────────────── */
    var form = app.closest('form');
    if (form) {
        form.addEventListener('submit', function () { sync(); });
    }

    /* ── Initialisation ───────────────────────────────────── */
    render();
    sync();
})();
