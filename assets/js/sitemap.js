document.addEventListener('DOMContentLoaded', function () {
    var container = document.querySelector('.sitemap-categories');
    if (!container) return;

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
});
