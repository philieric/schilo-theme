/**
 * DEBUG GTranslate — à activer temporairement
 * Ouvre la console du navigateur pour voir les résultats
 */
(function() {
  console.group('=== DEBUG GTRANSLATE ===');
  
  // 1. doGTranslate disponible ?
  console.log('doGTranslate:', typeof window.doGTranslate);
  
  // 2. Liens .glink présents ?
  var glinks = document.querySelectorAll('a.glink');
  console.log('Liens .glink trouvés:', glinks.length);
  if (glinks.length > 0) {
    console.log('Exemple .glink[0]:', glinks[0].outerHTML.substring(0, 200));
    console.log('data-gt-lang:', glinks[0].getAttribute('data-gt-lang'));
  }
  
  // 3. Select goog-te-combo présent ?
  var sel = document.querySelector('select.goog-te-combo');
  console.log('select.goog-te-combo:', sel ? 'OUI' : 'NON');
  
  // 4. Widget GTranslate wrapper ?
  var wrap = document.querySelector('.gt_switcher_wrapper, .gtranslate_wrapper');
  console.log('Widget wrapper:', wrap ? wrap.className : 'NON TROUVÉ');
  
  // 5. Cookie actuel
  var cookie = document.cookie.match(/googtrans=([^;]+)/);
  console.log('Cookie googtrans:', cookie ? cookie[1] : 'absent');
  
  // 6. Toutes les fonctions GT exposées sur window
  var gtFns = Object.keys(window).filter(function(k) {
    return k.toLowerCase().includes('translate') || k.toLowerCase().includes('gtranslate') || k.toLowerCase().includes('dogtr');
  });
  console.log('Fonctions GT sur window:', gtFns);
  
  // 7. Scripts GT chargés
  var scripts = Array.from(document.scripts).filter(function(s) {
    return s.src && (s.src.includes('gtranslate') || s.src.includes('translate.google'));
  });
  console.log('Scripts GT chargés:', scripts.map(function(s){ return s.src; }));

  console.groupEnd();
  
  // 8. Test direct après 2s
  setTimeout(function() {
    console.group('=== TEST APRES 2s ===');
    console.log('doGTranslate:', typeof window.doGTranslate);
    var glinks2 = document.querySelectorAll('a.glink[data-gt-lang]');
    console.log('Liens .glink avec data-gt-lang:', glinks2.length);
    console.groupEnd();
  }, 2000);
  
})();
