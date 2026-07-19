(function () {
  const LOG_PREFIX = '[USX-SWITCHER]';

  function log(...args) { console.log(LOG_PREFIX, ...args); }
  function warn(...args) { console.warn(LOG_PREFIX, ...args); }
  function err(...args) { console.error(LOG_PREFIX, ...args); }

  function qs(root, sel) { return root.querySelector(sel); }
  function qsa(root, sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); }

  function getCfg() {
    // Compat: accepte les 2 noms possibles sans changer la logique côté PHP
    return window.USX_VersionSwitcherButtons || window.USX_VersionSwitcher || null;
  }

  function setBusy(wrapper, busy, msg) {
    log('setBusy()', { wrapperId: wrapper?.id || null, busy, msg });
    qsa(wrapper, '.usxv-btn').forEach(b => b.disabled = !!busy);
    const st = qs(wrapper, '[data-role="status"]');
    if (st) st.textContent = msg || '';
    wrapper.classList.toggle('is-loading', !!busy);
  }

  function setActive(wrapper, version) {
    qsa(wrapper, '.usxv-btn').forEach(btn => {
      const v = btn.getAttribute('data-version') || '';
      btn.classList.toggle('is-active', v === (version || ''));
    });
  }

  // ✅ NEW: postAjax paramétrable (action/nonce choisis par le caller)
  // ✅ postAjax compatible:
  // - postAjax(payload, action, nonce)
  // - postAjax({action, nonce, ...})
  async function postAjax(payload, action, nonce) {
    const cfg = getCfg();
    log('postAjax payload', payload, 'cfg=', cfg);

    const ajaxUrlBase = (cfg && cfg.ajaxUrl) ? cfg.ajaxUrl : null;

    // Compat: si action/nonce pas passés en args, on les lit dans payload ou cfg
    const resolvedAction = action || (payload && payload.action) || (cfg && cfg.action) || null;
    const resolvedNonce  = nonce  || (payload && payload.nonce)  || (cfg && cfg.nonce)  || null;

    if (!ajaxUrlBase || !resolvedAction || !resolvedNonce) {
      err('Config AJAX absente/incomplète', { ajaxUrlBase, action: resolvedAction, nonce: resolvedNonce, cfg });
      throw new Error('Config AJAX absente');
    }

    // On évite d’envoyer action/nonce en double dans le body
    const cleanPayload = Object.assign({}, payload || {});
    delete cleanPayload.action;
    delete cleanPayload.nonce;

    const body = new URLSearchParams(cleanPayload);
    body.set('action', resolvedAction);
    body.set('nonce', resolvedNonce);

    const ajaxUrl = new URL(ajaxUrlBase, window.location.origin).toString();

    const res = await fetch(ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString(),
      credentials: 'same-origin'
    });

    const text = await res.text();
    log('AJAX status=', res.status, 'raw=', text.slice(0, 600));

    let json;
    try {
      json = JSON.parse(text);
    } catch (e) {
      err('Réponse non JSON', text);
      throw new Error('Réponse AJAX non JSON');
    }
    return json;
  }

  // INLINE blocks
  function initOne(wrapper) {
    const toolbarEl = qs(wrapper, '[data-role="toolbar"], .usxv-toolbar');
    const shortcode = (wrapper.getAttribute('data-shortcode') || (toolbarEl ? toolbarEl.getAttribute('data-shortcode') : '') || '').trim();
    const contentEl = qs(wrapper, '[data-role="content"]');

    log('initOne', {
      wrapperId: wrapper?.id || null,
      shortcode,
      hasToolbar: !!toolbarEl,
      hasContent: !!contentEl
    });

    if (!shortcode || !contentEl) {
      warn('initOne aborted (missing shortcode/content)', { shortcode, contentEl: !!contentEl });
      return;
    }

    // default active if none
    const currentActive = qs(wrapper, '.usxv-btn.is-active');
    if (!currentActive) {
      const defaultBtn = qs(wrapper, '.usxv-btn[data-version=""]');
      if (defaultBtn) defaultBtn.classList.add('is-active');
    }

    wrapper.addEventListener('click', async function (e) {
      const btn = e.target.closest('.usxv-btn');
      if (!btn) return;

      // popup buttons managed by popup handler
      if (btn.closest('.usxv-toolbar-popup')) return;

      e.preventDefault();
      e.stopPropagation();

      const version = btn.getAttribute('data-version') || '';
      const localToolbar = btn.closest('[data-role="toolbar"], .usxv-toolbar') || toolbarEl;
      const shortcodeLive = (wrapper.getAttribute('data-shortcode') || (localToolbar ? localToolbar.getAttribute('data-shortcode') : '') || '').trim();

      log('INLINE click', {
        wrapperId: wrapper?.id || null,
        label: btn.textContent?.trim(),
        version,
        shortcodeLive
      });

      if (!shortcodeLive) {
        setBusy(wrapper, false, 'Shortcode manquant.');
        err('INLINE missing shortcodeLive');
        return;
      }

      setBusy(wrapper, true, 'Chargement…');

      try {
        const cfg = getCfg();
        const data = await postAjax(
          { shortcode: shortcodeLive, version: version },
          cfg.action,
          cfg.nonce
        );

        if (!data || !data.success || !data.data || typeof data.data.html !== 'string') {
          setBusy(wrapper, false, 'Réponse AJAX invalide.');
          err('INLINE invalid response', data);
          return;
        }

        contentEl.innerHTML = data.data.html;
        setActive(wrapper, version);
        setBusy(wrapper, false, '');
      } catch (e2) {
        err('INLINE ajax error', e2);
        setBusy(wrapper, false, 'Erreur: ' + (e2?.message || e2));
      }
    });

    log('INLINE listener attached', wrapper?.id || null);
  }

  // POPUP isolated (AJAX dédié, ne modifie QUE 3 zones)
    // POPUP isolated (AJAX dédié : ne met à jour que ref + verses + copyright)
  function initPopupDelegation() {
    document.addEventListener('click', async function (e) {
      const btn = e.target.closest('.usxv-toolbar-popup .usxv-btn');
      if (!btn) return;

      e.preventDefault();
      e.stopPropagation();

      const toolbar = btn.closest('.usxv-toolbar-popup');
      const shortcode = ((toolbar && toolbar.getAttribute('data-shortcode')) || '').trim();
      const version = btn.getAttribute('data-version') || '';

      log('POPUP click', {
        label: btn.textContent?.trim(),
        version,
        shortcode
      });

      if (!shortcode) {
        err('POPUP missing shortcode');
        return;
      }

      const popupSwitcher = btn.closest('[data-role="popup-switcher"]');
      const versesZone = popupSwitcher ? qs(popupSwitcher, '[data-role="popup-verses"]') : null;
      if (!versesZone) {
        err('POPUP verses zone not found');
        return;
      }

      // retrouver le conteneur de popup pour maj ref + copyright
      const popupRoot = btn.closest('.bible-popup') || document.querySelector('.bible-popup');
      const refEl = popupRoot ? qs(popupRoot, '.popup-ref') : null;
      const copyrightEl = popupRoot ? qs(popupRoot, '.copyright') : null;

      qsa(toolbar, '.usxv-btn').forEach(b => b.disabled = true);

      try {
        const cfg = getCfg();
        if (!cfg) throw new Error('Config absente');

        const action = cfg.popupAction || cfg.action;
        const nonce = cfg.popupNonce || cfg.nonce;

        const data = await postAjax({
          action: action,
          nonce: nonce,
          shortcode: shortcode,
          version: version
        });

        // Réponse attendue: { success:true, data:{ref, verses_html, copyright} }
        if (!data || !data.success || !data.data) {
          err('POPUP invalid response', data);
          return;
        }

        const ref = (data.data.ref || '').toString();
        const versesHtml = (data.data.verses_html || '').toString();
        const copyright = (data.data.copyright || '').toString();

        if (refEl && ref) refEl.textContent = ref;
        if (versesHtml) versesZone.innerHTML = versesHtml;
        //if (copyrightEl && copyright) copyrightEl.textContent = copyright;
        if (copyrightEl) {
          const cp = (typeof copyright === 'string') ? copyright.trim() : '';
          copyrightEl.textContent = cp; // vide => efface, non vide => affiche
        }

        qsa(toolbar, '.usxv-btn').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');

      } catch (e3) {
        err('POPUP ajax error', e3);
      } finally {
        qsa(toolbar, '.usxv-btn').forEach(b => b.disabled = false);
      }
    });

    log('POPUP delegated listener attached');
  }

  function initAll() {
    const wrappers = document.querySelectorAll('.usx-version-switcher');
    log('initAll wrappers found', wrappers.length);

    wrappers.forEach(initOne);
    initPopupDelegation();
  }

  if (document.readyState === 'loading') {
    log('waiting DOMContentLoaded');
    document.addEventListener('DOMContentLoaded', () => {
      log('DOMContentLoaded');
      initAll();
    });
  } else {
    log('document ready immediate');
    initAll();
  }
})();