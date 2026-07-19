(function () {
  function qs(s, r){return (r||document).querySelector(s);}
  function qsa(s, r){return Array.from((r||document).querySelectorAll(s));}

  async function postAjax(data){
    const body=new URLSearchParams(data);
    const res=await fetch(USX_VersionSwitcher.ajaxUrl,{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body:body.toString(),
      credentials:'same-origin'
    });
    return res.json();
  }

  async function updateAll(version){
    const bar=qs('#usx-global-version-switcher');
    const wrappers=qsa('.usx-version-switcher');
    if(!bar||!wrappers.length) return;

    const status = qs('[data-role="status"]', bar);
    if(status) status.textContent='Chargement...';

    await Promise.all(wrappers.map(async w=>{
      const b64=w.dataset.shortcode;
      const content=qs('[data-role="content"]',w);
      if(!b64 || !content) return;

      const res=await postAjax({
        action:USX_VersionSwitcher.action,
        nonce:USX_VersionSwitcher.nonce,
        shortcode:b64,
        version:version||''
      });

      if(res && res.success && res.data && typeof res.data.html === 'string'){
        content.innerHTML=res.data.html;
      }
    }));

    if(status) status.textContent='';
    qsa('.usxv-btn',bar).forEach(b=>{
      b.classList.toggle('is-active',(b.dataset.version||'')===(version||''));
    });
  }

  document.addEventListener('click', e => {
    // ✅ Ne réagit qu'aux boutons du switcher GLOBAL (sinon ça modifie tous les versets)
    const globalSwitcher = document.getElementById('usx-global-version-switcher');
    if (!globalSwitcher) return;
    const btn = e.target.closest('#usx-global-version-switcher .usxv-btn');
    if (!btn) return;
    updateAll(btn.dataset.version || '');
  });
})();