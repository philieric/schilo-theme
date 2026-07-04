jQuery(function($){
  $(document).on('click', '.mcg-loadmore', function(){
    const $btn  = $(this);
    const $wrap = $btn.closest('.mcg');

    const page    = (parseInt($wrap.attr('data-page') || '1', 10) + 1);
    const cat     = parseInt($wrap.data('cat') || 0, 10);
    const perPage = parseInt($wrap.data('per-page') || 9, 10);
    const orderby = $wrap.data('orderby') || 'date';

    $btn.prop('disabled', true).text('Chargement...');

    $.post(MCG.ajaxUrl, {
      action: 'mcg_fetch',
      nonce: MCG.nonce,
      page: page,
      cat: cat,
      per_page: perPage,
      orderby: orderby
    }).done(function(res){
      if(!res || !res.success) return;

      // Ajoute uniquement les nouveaux articles
      $wrap.find('.mcg-grid').append(res.data.html);

      // Mémorise la page actuelle
      $wrap.attr('data-page', page);

      // Cache le bouton si on est arrivé au bout
      const max = parseInt(res.data.max || 1, 10);
      if(page >= max) {
        $btn.remove();
      } else {
        $btn.prop('disabled', false).text('Voir plus');
      }
    }).fail(function(){
      $btn.prop('disabled', false).text('Voir plus');
    });
  });
});
