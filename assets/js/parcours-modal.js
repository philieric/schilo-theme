(function () {
    'use strict';

    var openModal = null;

    function open(modal) {
        if (!modal) return;
        close();
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('schilo-modal-open');
        openModal = modal;
    }

    function close() {
        if (!openModal) return;
        openModal.classList.remove('is-open');
        openModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('schilo-modal-open');
        openModal = null;
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-modal-trigger]');
        if (trigger) {
            var modal = document.getElementById(trigger.getAttribute('data-modal-trigger'));
            open(modal);
            return;
        }

        if (e.target.closest('[data-modal-close]')) {
            close();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
})();
