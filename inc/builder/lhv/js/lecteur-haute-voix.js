document.addEventListener('DOMContentLoaded', function () {
    let voices = [];
    const cleanupRules = (window.lhvConfig && window.lhvConfig.cleanupRules) ? window.lhvConfig.cleanupRules : [];

    function getBrowser() {
        const ua = navigator.userAgent;
        if (ua.includes('Edg')) return 'Edge';
        if (ua.includes('Chrome')) return 'Chrome';
        if (ua.includes('Firefox')) return 'Firefox';
        return 'Other';
    }

    function makeDraggable(popup) {
        let isDragging = false;
        let offsetX = 0;
        let offsetY = 0;

        const header = document.createElement('div');
        header.className = 'lhv-drag-handle';
        popup.insertBefore(header, popup.firstChild);

        header.addEventListener('mousedown', function (e) {
            isDragging = true;
            offsetX = e.clientX - popup.getBoundingClientRect().left;
            offsetY = e.clientY - popup.getBoundingClientRect().top;
            popup.style.position = 'absolute';
            popup.style.margin = 0;
        });

        document.addEventListener('mousemove', function (e) {
            if (isDragging) {
                popup.style.left = (e.clientX - offsetX) + 'px';
                popup.style.top = (e.clientY - offsetY) + 'px';
            }
        });

        document.addEventListener('mouseup', function () {
            isDragging = false;
        });
    }

    function cleanNodeByRules(rootNode) {
        cleanupRules.forEach(rule => {
            rootNode.querySelectorAll('.' + rule.parentClass).forEach(parent => {
                rule.childClassesToRemove.forEach(childClass => {
                    parent.querySelectorAll('.' + childClass).forEach(node => node.remove());
                });
            });
        });
    }

    function getCleanTextFromZone(zone) {
        const clone = zone.cloneNode(true);
        cleanNodeByRules(clone);
        return clone.innerText.trim();
    }

    function createPopup(text) {
        const savedWidth = localStorage.getItem('popupWidth');
        const savedHeight = localStorage.getItem('popupHeight');

        const overlay = document.createElement('div');
        overlay.className = 'lhv-overlay';

        const popup = document.createElement('div');
        popup.className = 'lhv-popup';
        popup.classList.add(window.matchMedia('(prefers-color-scheme: dark)').matches ? 'theme-dark' : 'theme-light');
        popup.style.width = savedWidth || '700px';
        popup.style.height = savedHeight || 'auto';

        makeDraggable(popup);

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'lhv-close-btn';
        closeBtn.textContent = '✖';

        const themeToggle = document.createElement('button');
        themeToggle.type = 'button';
        themeToggle.className = 'lhv-theme-toggle';

        function updateThemeLabel() {
            themeToggle.textContent = popup.classList.contains('theme-dark')
                ? '☀️ Thème clair'
                : '🌙 Thème sombre';
        }

        themeToggle.addEventListener('click', function () {
            popup.classList.toggle('theme-dark');
            popup.classList.toggle('theme-light');
            updateThemeLabel();
        });

        updateThemeLabel();

        const voiceSelect = document.createElement('select');
        voiceSelect.className = 'lhv-voice-select';

        const placeholderOption = document.createElement('option');
        placeholderOption.textContent = 'Choisissez une voix';
        placeholderOption.disabled = true;
        placeholderOption.selected = true;
        voiceSelect.appendChild(placeholderOption);

        voices.forEach((voice, index) => {
            const option = document.createElement('option');
            option.value = index;
            option.textContent = `${voice.name} (${voice.lang})`;
            voiceSelect.appendChild(option);
        });

        const browser = getBrowser();
        if (browser === 'Edge' && voices.length >= 13) {
            voiceSelect.selectedIndex = 14;
        } else if ((browser === 'Chrome' || browser === 'Firefox') && voices.length >= 2) {
            voiceSelect.selectedIndex = 3;
        }

        const progressWrap = document.createElement('div');
        progressWrap.className = 'lhv-progress-wrap';
        const progressBar = document.createElement('div');
        progressBar.className = 'lhv-progress-bar';
        progressWrap.appendChild(progressBar);

        const buttonsWrapper = document.createElement('div');
        buttonsWrapper.className = 'lhv-buttons-wrapper';

        const leftButtons = document.createElement('div');
        leftButtons.className = 'lhv-left-buttons';

        const readBtn = document.createElement('button');
        readBtn.type = 'button';
        readBtn.className = 'lhv-btn lhv-btn-read';
        readBtn.textContent = '🔊 Lire';

        const stopBtn = document.createElement('button');
        stopBtn.type = 'button';
        stopBtn.className = 'lhv-btn lhv-btn-stop';
        stopBtn.textContent = '⏹️ Arrêter';

        const copyBtn = document.createElement('button');
        copyBtn.type = 'button';
        copyBtn.className = 'lhv-btn lhv-btn-copy';
        copyBtn.textContent = '📋 Copier';

        copyBtn.addEventListener('click', function () {
            navigator.clipboard.writeText(text).then(() => {
                copyBtn.textContent = '✅ Copié';
                setTimeout(() => {
                    copyBtn.textContent = '📋 Copier';
                }, 1500);
            });
        });

        leftButtons.appendChild(readBtn);
        leftButtons.appendChild(stopBtn);
        buttonsWrapper.appendChild(leftButtons);
        buttonsWrapper.appendChild(copyBtn);

        const content = document.createElement('div');
        content.className = 'lhv-popup-content';

        const words = text.split(/(\s+)/);
        const wordSpans = [];

        words.forEach(word => {
            const span = document.createElement('span');
            span.textContent = word;
            span.className = 'lhv-popup-word';
            content.appendChild(span);
            wordSpans.push(span);
        });

        popup.appendChild(closeBtn);
        popup.appendChild(themeToggle);
        popup.appendChild(voiceSelect);
        popup.appendChild(progressWrap);
        popup.appendChild(buttonsWrapper);
        popup.appendChild(content);
        overlay.appendChild(popup);
        document.body.appendChild(overlay);

        let utterance = null;

        readBtn.addEventListener('click', function () {
            speechSynthesis.cancel();
            wordSpans.forEach(w => w.classList.remove('lhv-word-active'));

            utterance = new SpeechSynthesisUtterance(text);
            utterance.voice = voices[voiceSelect.value] || null;
            utterance.lang = utterance.voice?.lang || 'fr-FR';

            const totalChars = text.length;

            utterance.onboundary = function (event) {
                if (event.name === 'word') {
                    const idx = event.charIndex;

                    // Mise à jour barre de progression
                    progressBar.style.width = Math.min(100, (idx / totalChars) * 100) + '%';

                    let total = 0;
                    for (let i = 0; i < wordSpans.length; i++) {
                        const w = wordSpans[i];
                        const len = w.textContent.length;
                        if (idx >= total && idx < total + len) {
                            wordSpans.forEach(item => item.classList.remove('lhv-word-active'));
                            w.classList.add('lhv-word-active');
                            w.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            break;
                        }
                        total += len;
                    }
                }
            };

            utterance.onend = function () {
                wordSpans.forEach(w => w.classList.remove('lhv-word-active'));
                progressBar.style.width = '100%';
                setTimeout(function () { progressBar.style.width = '0%'; }, 800);
            };

            speechSynthesis.speak(utterance);
        });

        stopBtn.addEventListener('click', function () {
            speechSynthesis.cancel();
            wordSpans.forEach(w => w.classList.remove('lhv-word-active'));
            progressBar.style.width = '0%';
        });

        closeBtn.addEventListener('click', function () {
            speechSynthesis.cancel();
            document.body.removeChild(overlay);
        });
    }

    function addControlsToLectureBlock(lectureBlock) {
        if (lectureBlock.dataset.controlsInitialized === 'true') {
            return;
        }

        lectureBlock.dataset.controlsInitialized = 'true';

        let controls = lectureBlock.querySelector('.lhv-controls');
        if (!controls) {
            controls = document.createElement('div');
            controls.className = 'lhv-controls';
            lectureBlock.insertBefore(controls, lectureBlock.firstChild);
        }

        let zone = lectureBlock.querySelector('.lvh-txtlecture');
        if (!zone) {
            const children = Array.from(lectureBlock.children);
            const firstContentBlock = children.find(el => !el.classList.contains('lhv-controls'));

            if (firstContentBlock) {
                firstContentBlock.classList.add('lvh-txtlecture');
                zone = firstContentBlock;
            } else {
                console.warn('Aucune zone de texte trouvée dans .lhv-lecture');
                return;
            }
        }

        const lireBtn = document.createElement('button');
        lireBtn.type = 'button';
        lireBtn.className = 'lhv-btn lhv-btn-listen';
        lireBtn.textContent = '🔊 Écouter ce texte';
        controls.appendChild(lireBtn);

        lireBtn.addEventListener('click', function () {
            const text = getCleanTextFromZone(zone);
            if (!text) {
                alert('Aucun texte à lire.');
                return;
            }
            createPopup(text);
        });
    }

    function tryPopulateVoices() {
        voices = speechSynthesis.getVoices().filter(v => v.lang.startsWith('fr'));

        if (voices.length) {
            document.querySelectorAll('.lhv-lecture').forEach(addControlsToLectureBlock);
        } else {
            setTimeout(tryPopulateVoices, 100);
        }
    }

    if ('speechSynthesis' in window) {
        speechSynthesis.onvoiceschanged = tryPopulateVoices;
        tryPopulateVoices();
    }
});