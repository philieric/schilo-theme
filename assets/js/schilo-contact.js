/**
 * Schilo.Contact
 * Namespace : Schilo
 * Classe    : Schilo.Contact
 *
 * Gère le guide dynamique du formulaire de contact :
 * - Conseil de rédaction selon le sujet sélectionné
 * - Questions préétablies cliquables → insèrent dans le textarea
 */

var Schilo = Schilo || {};

Schilo.Contact = (function () {

    'use strict';

    /* ── État interne ── */
    var _state = {
        guides      : {},
        select      : null,
        textarea    : null,
        guideBox    : null,
        conseilBox  : null,
        conseilText : null,
        questionsBox: null,
        questionsList: null
    };

    /* ────────────────────────────────────────────
       MÉTHODES PRIVÉES
    ──────────────────────────────────────────── */

    function _showGuide(sujet) {
        var data = _state.guides[sujet];

        if (!data) {
            _state.guideBox.style.display = 'none';
            return;
        }

        var hasConseil   = data.conseil   && data.conseil.length > 0;
        var hasQuestions = data.questions && data.questions.length > 0;

        if (!hasConseil && !hasQuestions) {
            _state.guideBox.style.display = 'none';
            return;
        }

        _state.guideBox.style.display = 'block';

        /* Conseil */
        if (hasConseil) {
            _state.conseilText.textContent   = data.conseil;
            _state.conseilBox.style.display  = 'block';
        } else {
            _state.conseilBox.style.display  = 'none';
        }

        /* Questions préétablies */
        if (hasQuestions) {
            _renderQuestions(data.questions);
            _state.questionsBox.style.display = 'block';
        } else {
            _state.questionsBox.style.display = 'none';
        }
    }

    function _renderQuestions(questions) {
        _state.questionsList.innerHTML = '';

        for (var i = 0; i < questions.length; i++) {
            _state.questionsList.appendChild(_createQuestionBtn(questions[i]));
        }
    }

    function _createQuestionBtn(question) {
        var btn = document.createElement('button');
        btn.type      = 'button';
        btn.className = 'schilo-contact-guide__q-btn';
        btn.textContent = question;

        btn.addEventListener('click', function () {
            _insertQuestion(question, btn);
        });

        return btn;
    }

    function _insertQuestion(question, btn) {
        var current = _state.textarea.value.trim();
        _state.textarea.value = current ? current + '\n\n' + question : question;
        _state.textarea.focus();
        _state.textarea.selectionStart =
            _state.textarea.selectionEnd = _state.textarea.value.length;

        /* Feedback visuel */
        btn.classList.add('used');
        setTimeout(function () { btn.classList.remove('used'); }, 1500);

        /* Scroll vers le textarea sur mobile */
        _state.textarea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function _updatePlaceholder(sujet) {
        if (!_state.textarea) return;
        _state.textarea.placeholder = sujet
            ? 'Votre message…'
            : "Votre message… Choisissez d'abord un sujet pour obtenir de l'aide à la rédaction.";
    }

    /* ────────────────────────────────────────────
       MÉTHODE PUBLIQUE : init()
    ──────────────────────────────────────────── */

    function init() {
        /* Récupérer les données PHP → JS */
        _state.guides = window.schiloContactGuides || {};

        /* Récupérer les éléments du DOM */
        _state.select        = document.getElementById('schilo_sujet');
        _state.textarea      = document.getElementById('schilo_message');
        _state.guideBox      = document.getElementById('schilo-contact-guide');
        _state.conseilBox    = document.getElementById('schilo-guide-conseil');
        _state.conseilText   = document.getElementById('schilo-guide-conseil-text');
        _state.questionsBox  = document.getElementById('schilo-guide-questions');
        _state.questionsList = document.getElementById('schilo-guide-questions-list');

        /* Vérification que tous les éléments existent */
        if (!_state.select || !_state.textarea || !_state.guideBox) return;

        /* Événement changement de sujet */
        _state.select.addEventListener('change', function () {
            _showGuide(this.value);
            _updatePlaceholder(this.value);
        });

        /* Repopulation après erreur PHP */
        if (_state.select.value) {
            _showGuide(_state.select.value);
            _updatePlaceholder(_state.select.value);
        }
    }

    /* ── API publique ── */
    return {
        init       : init,
        showGuide  : _showGuide,
        insertQuestion: _insertQuestion
    };

})();


/* ── Auto-init ── */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Schilo.Contact.init(); });
} else {
    Schilo.Contact.init();
}
