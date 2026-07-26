document.addEventListener('DOMContentLoaded', function () {
    var container = document.querySelector('.sitemap-categories');
    if (!container) return;

    // ── Boutons « Tout ouvrir / Tout fermer » (thème) ────────────────
    container.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.spc-expand-btn') : null;
        if (!btn) return;

        var action    = btn.getAttribute('data-action');
        var catParent = btn.closest ? btn.closest('.cat-parent') : null;
        if (!catParent) return;

        catParent.querySelectorAll('.spc-node').forEach(function (n) {
            if (action === 'expand') {
                n.classList.add('open');
            } else {
                n.classList.remove('open');
            }
        });
    }, true);

    // ── Accessibilité de l'accordéon (amélioration progressive) ───────
    // Les en-têtes de nœuds du plugin sont des <div> cliquables : on les
    // rend utilisables au clavier et compréhensibles par les lecteurs
    // d'écran (rôle bouton, focusable, aria-expanded). La bascule .open
    // reste faite par le plugin (clic délégué sur .spc-node-header) — on
    // se contente de simuler ce clic au clavier, sans toucher sa logique.
    var headerId = 0;

    function syncExpanded(header) {
        var node = header.parentElement;
        if (!node) return;
        header.setAttribute('aria-expanded', node.classList.contains('open') ? 'true' : 'false');
    }

    function enhanceHeader(header) {
        if (header.dataset.schiloA11y) return;
        header.dataset.schiloA11y = '1';
        header.setAttribute('role', 'button');
        if (!header.hasAttribute('tabindex')) header.setAttribute('tabindex', '0');

        // Le chevron ▶ est purement décoratif : on le masque aux lecteurs
        // d'écran pour qu'il ne soit pas lu dans le nom du bouton.
        var chevron = header.querySelector('.spc-chevron');
        if (chevron) chevron.setAttribute('aria-hidden', 'true');

        // Relie l'en-tête au corps repliable (aria-controls) si présent.
        var body = header.parentElement
            ? header.parentElement.querySelector(':scope > .spc-node-body')
            : null;
        if (body) {
            if (!body.id) body.id = 'spc-node-body-' + (++headerId);
            header.setAttribute('aria-controls', body.id);
        }
        syncExpanded(header);

        header.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                // Rejoue le geste souris : le plugin écoute le clic délégué.
                header.click();
            }
        });
    }

    container.querySelectorAll('.spc-node-header').forEach(enhanceHeader);

    // Garde aria-expanded synchronisé quel que soit ce qui ouvre/ferme un
    // nœud (clic plugin, boutons tout ouvrir/fermer, recherche…).
    if ('MutationObserver' in window) {
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                if (m.type === 'attributes' && m.target.classList && m.target.classList.contains('spc-node')) {
                    var header = m.target.querySelector(':scope > .spc-node-header');
                    if (header) syncExpanded(header);
                }
            });
        });
        observer.observe(container, { attributes: true, attributeFilter: ['class'], subtree: true });
    }
});
