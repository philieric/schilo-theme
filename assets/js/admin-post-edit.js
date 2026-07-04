/* Schilo — Admin Post Edit — Layout custom v1.1.15 */
(function () {
    'use strict';

    /* ── État "modifié non sauvegardé" ── */
    var isDirty = false;

    function markDirty() {
        if (isDirty) return;
        isDirty = true;
        var dot = document.getElementById('se-dirty-dot');
        if (dot) dot.style.display = 'inline-block';
    }
    function markClean() {
        isDirty = false;
        var dot = document.getElementById('se-dirty-dot');
        if (dot) dot.style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.getElementById('poststuff')) return;
        if (document.getElementById('schilo-edit-wrap')) return; /* anti double-init */

        /* ══════════════════════════════════════════════════════════
           1. MASQUER le layout WP d'origine
        ══════════════════════════════════════════════════════════ */
        var hide = ['#post-body', '#postbox-container-1',
                    '#postbox-container-2', '#normal-sortables',
                    '#advanced-sortables', '#side-sortables'];
        hide.forEach(function (sel) {
            var el = document.querySelector(sel);
            if (el) el.style.display = 'none';
        });

        var postBodyContent = document.getElementById('post-body-content');
        if (postBodyContent) postBodyContent.style.display = 'none';

        /* ══════════════════════════════════════════════════════════
           2. CONSTRUIRE la structure custom
        ══════════════════════════════════════════════════════════ */
        var wrap = document.createElement('div');
        wrap.id = 'schilo-edit-wrap';

        /* ── Barre sticky ── */
        var titleInput = document.getElementById('title');
        var postTitle  = titleInput ? titleInput.value : '';
        var statusEl   = document.getElementById('post-status-display');
        var statusText = statusEl ? statusEl.textContent.trim() : '';
        var statusSelectEl = document.getElementById('post_status');
        var statusValue = statusSelectEl ? statusSelectEl.value : '';
        var viewLink   = document.querySelector('#wp-admin-bar-view a') ||
                         document.querySelector('#sample-permalink a');
        var viewHref   = viewLink ? viewLink.href : '';

        var topbar = document.createElement('div');
        topbar.id  = 'se-topbar';
        topbar.innerHTML =
            '<span class="se-title">' + escHtml(postTitle || 'Nouvel article') +
            '<span id="se-dirty-dot" title="Modifications non enregistrées"></span></span>' +
            '<div class="se-actions">' +
            '<span id="se-status-badge" data-status="' + escAttr(statusValue) + '"' + (statusText ? '' : ' style="display:none"') + ' class="se-status">' + escHtml(statusText) + '</span>' +
            (viewHref ? '<a href="' + escAttr(viewHref) + '" target="_blank" class="se-btn-view">' +
                '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg> Voir</a>' : '') +
            '<button type="button" id="se-save" class="se-btn-save">' +
            '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Enregistrer</button>' +
            '</div>';
        wrap.appendChild(topbar);

        /* Sync titre */
        if (titleInput) {
            titleInput.addEventListener('input', function () {
                var el = topbar.querySelector('.se-title');
                if (el) el.firstChild.textContent = this.value || 'Nouvel article';
                markDirty();
            });
        }

        /* Clic Enregistrer */
        function doSave() {
            var pub = document.getElementById('publish');
            if (pub) { markClean(); pub.click(); }
        }
        wrap.addEventListener('click', function (e) {
            if (e.target.id === 'se-save' || e.target.closest('#se-save')) doSave();
        });

        /* ── Champ titre + slug ── */
        var titleZone = document.createElement('div');
        titleZone.id = 'se-title-zone';
        var titlediv = document.getElementById('titlediv');
        if (titlediv) {
            titlediv.removeAttribute('style');
            if (titleInput) {
                titleInput.removeAttribute('style');
                titleInput.className = 'se-title-input';
            }
            titleZone.appendChild(titlediv);
        }
        wrap.appendChild(titleZone);

        /* ── Bande de méta (Étiquettes | Catégories | Publier) ── */
        var metaStrip = document.createElement('div');
        metaStrip.id = 'se-meta-strip';
        var metaOrder = ['tagsdiv-post_tag', 'categorydiv', 'submitdiv'];
        metaOrder.forEach(function (id) {
            var box = document.getElementById(id);
            if (box) {
                box.removeAttribute('style');
                metaStrip.appendChild(box);
            }
        });
        wrap.appendChild(metaStrip);

        /* Sync badge statut depuis le select WP "Statut" dans submitdiv.
           Delegation sur document : WP reconstruit parfois ce <select>
           (widget "Modifier" du statut), ce qui casserait un listener direct. */
        document.addEventListener('change', function (e) {
            if (!e.target || e.target.id !== 'post_status') return;
            var badge = document.getElementById('se-status-badge');
            if (!badge) return;
            var sel   = e.target;
            var label = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
            badge.textContent = label;
            badge.setAttribute('data-status', sel.value);
            badge.style.display = label ? '' : 'none';
        });

        /* ── Zone de contenu principal (Builder + TinyMCE) ── */
        var contentZone = document.createElement('div');
        contentZone.id = 'se-content-zone';

        var postdivrich = document.getElementById('postdivrich') ||
                          document.getElementById('wp-content-wrap');

        if (postBodyContent) {
            var builderInner = document.createElement('div');
            builderInner.id = 'se-builder';
            Array.from(postBodyContent.childNodes).forEach(function (node) {
                if (node !== postdivrich) builderInner.appendChild(node);
            });
            contentZone.appendChild(builderInner);
        }

        /* TinyMCE — masqué par défaut */
        if (postdivrich) {
            var editorWrap = document.createElement('div');
            editorWrap.id = 'se-editor';

            var editorToggle = document.createElement('button');
            editorToggle.type = 'button';
            editorToggle.id   = 'se-editor-toggle';
            editorToggle.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Afficher le contenu texte';

            var editorInner = document.createElement('div');
            editorInner.id = 'se-editor-inner';
            editorInner.style.display = 'none';
            postdivrich.removeAttribute('style');
            editorInner.appendChild(postdivrich);

            editorToggle.addEventListener('click', function () {
                var hidden = editorInner.style.display === 'none';
                editorInner.style.display = hidden ? '' : 'none';
                editorToggle.innerHTML = hidden
                    ? '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg> Masquer le contenu texte'
                    : '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Afficher le contenu texte';
            });

            editorWrap.appendChild(editorToggle);
            editorWrap.appendChild(editorInner);
            contentZone.appendChild(editorWrap);
        }

        wrap.appendChild(contentZone);

        /* IDs à reléguer dans la zone secondaire */
        var secondaryIds = ['trackbacksdiv', 'postcustom', 'commentsdiv', 'commentstatusdiv', 'ez-toc', 'revisionsdiv'];
        /* Préfixes de metaboxes tierces à ignorer complètement (WPBakery, Divi…) */
        var ignoreIdPrefixes = ['vc_', 'wpb_', 'et_'];

        /* ── Méta basses — grille 2 colonnes ── */
        var bottomGrid = document.createElement('div');
        bottomGrid.id = 'se-bottom-grid';

        /* Zone secondaire repliée */
        var secondaryZone = document.createElement('div');
        secondaryZone.id = 'se-secondary-zone';
        var secondaryToggle = document.createElement('button');
        secondaryToggle.type = 'button';
        secondaryToggle.id = 'se-secondary-toggle';
        secondaryToggle.innerHTML =
            '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:5px"><polyline points="6 9 12 15 18 9"/></svg>' +
            'Options avancées <span id="se-secondary-label">(révisions, rétroliens, champs personnalisés, commentaires, table des matières)</span>';
        var secondaryInner = document.createElement('div');
        secondaryInner.id = 'se-secondary-inner';
        secondaryInner.style.display = 'none';
        secondaryToggle.addEventListener('click', function () {
            var open = secondaryInner.style.display === 'none';
            secondaryInner.style.display = open ? '' : 'none';
            var arrow = secondaryToggle.querySelector('svg polyline');
            if (arrow) arrow.setAttribute('points', open ? '18 15 12 9 6 15' : '6 9 12 15 18 9');
        });
        secondaryZone.appendChild(secondaryToggle);
        secondaryZone.appendChild(secondaryInner);

        /* Trier les metaboxes */
        ['normal-sortables', 'advanced-sortables', 'side-sortables'].forEach(function (sortableId) {
            var sortable = document.getElementById(sortableId);
            if (!sortable) return;
            Array.from(sortable.querySelectorAll(':scope > .postbox')).forEach(function (box) {
                if (box.id === 'postimagediv') return;
                /* Ignorer les metaboxes de plugins tiers (WPBakery, Divi…) */
                for (var i = 0; i < ignoreIdPrefixes.length; i++) {
                    if (box.id.indexOf(ignoreIdPrefixes[i]) === 0) return;
                }
                box.removeAttribute('style');
                /* WP masque certaines boites par defaut via la classe hide-if-js
                   (regle core ".js .hide-if-js{display:none}") : notre propre
                   toggle "Options avancees" gere deja la visibilite, donc on l'enleve */
                box.classList.remove('hide-if-js');
                if (secondaryIds.indexOf(box.id) !== -1) {
                    secondaryInner.appendChild(box);
                } else {
                    bottomGrid.appendChild(box);
                }
            });
        });

        if (bottomGrid.children.length) wrap.appendChild(bottomGrid);
        if (secondaryInner.children.length) wrap.appendChild(secondaryZone);

        /* ══════════════════════════════════════════════════════════
           3. INSÉRER dans la page
        ══════════════════════════════════════════════════════════ */
        var poststuff = document.getElementById('poststuff');
        poststuff.insertBefore(wrap, poststuff.firstChild);

        /* ══════════════════════════════════════════════════════════
           4. DIRTY TRACKING — surveiller les modifications
        ══════════════════════════════════════════════════════════ */
        var postForm = document.getElementById('post');
        if (postForm) {
            postForm.addEventListener('input',  markDirty);
            postForm.addEventListener('change', markDirty);
        }
        /* Intercepter la sauvegarde native (bouton "Mettre à jour" dans submitdiv) */
        var publishBtn = document.getElementById('publish');
        if (publishBtn) {
            publishBtn.addEventListener('click', markClean);
        }

        /* ══════════════════════════════════════════════════════════
           5. CTRL+S / CMD+S → sauvegarder
        ══════════════════════════════════════════════════════════ */
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                doSave();
            }
        });

        /* ══════════════════════════════════════════════════════════
           6. BEFOREUNLOAD — avertir si modifications non sauvegardées
        ══════════════════════════════════════════════════════════ */
        window.addEventListener('beforeunload', function (e) {
            if (!isDirty) return;
            e.preventDefault();
            e.returnValue = '';
        });
    });

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;')
                          .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escAttr(str) { return escHtml(str); }
})();
