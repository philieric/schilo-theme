/* Schilo — Détection des références bibliques en texte libre (éditeur article)
 *
 * Avant chaque enregistrement, scanne TOUS les éditeurs TinyMCE actifs sur la
 * page — le contenu classique (#content) ET chaque éditeur de section Schilo
 * Builder (wp_editor() par section, voir views/admin/sections/*.php et
 * builder-admin.js) — hors shortcodes [bib]/[bvc]/[brc]/[bnv] déjà présents,
 * à la recherche de motifs "Livre Chapitre.Verset" (dictionnaire des 66
 * livres injecté par PHP via schiloBibBooks — voir schilo_get_bible_book_titles()
 * dans inc/helpers.php). Si des références non balisées sont trouvées, propose
 * leur insertion via une liste à cocher avant de poursuivre l'enregistrement
 * (validation humaine, aucune insertion automatique silencieuse).
 */
(function () {
    'use strict';

    if ( ! window.schiloBibBooks || ! window.schiloBibBooks.length ) return;

    var TAGS = [ 'bib', 'bvc', 'brc', 'bnv' ];
    var TAG_LABELS = {
        bib: 'Bible (référence cliquable)',
        bvc: 'Carte verset(s)',
        brc: 'Bloc citation',
        bnv: 'Navigation (livre seul)'
    };

    function escRe( s ) {
        return s.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
    }

    /* Tri du plus long au plus court déjà fait côté PHP, mais on le refait ici
       par sécurité (l'ordre conditionne quel livre "gagne" dans l'alternation). */
    var booksSorted = window.schiloBibBooks.slice().sort( function ( a, b ) {
        return b.length - a.length;
    } );
    var bookAlt = booksSorted.map( escRe ).join( '|' );
    var REF_RE = new RegExp(
        '\\b(' + bookAlt + ')\\s+(\\d{1,3})\\s*[:.]\\s*(\\d{1,3})(?:\\s*-\\s*(\\d{1,3}))?\\b',
        'giu'
    );
    var SHORTCODE_RE = /\[b(?:ib|vc|nv|rc)?\b[^\]]*\][\s\S]*?\[\/b(?:ib|vc|nv|rc)?\]/gi;

    /* Remplace chaque shortcode biblique déjà présent par des caractères de
       même longueur, pour que les décalages (offset) restent alignés avec le
       texte original tout en excluant ces zones de la détection. */
    function maskShortcodes( html ) {
        return html.replace( SHORTCODE_RE, function ( m ) {
            return new Array( m.length + 1 ).join( ' ' );
        } );
    }

    function detect( html ) {
        var masked = maskShortcodes( html );
        var out = [];
        var m;
        REF_RE.lastIndex = 0;
        while ( ( m = REF_RE.exec( masked ) ) !== null ) {
            out.push( {
                text: m[ 0 ],
                offset: m.index,
                length: m[ 0 ].length
            } );
            if ( m.index === REF_RE.lastIndex ) REF_RE.lastIndex++;
        }
        return out;
    }

    /* Applique les sélections cochées (du dernier offset au premier, pour ne
       pas invalider les offsets des remplacements suivants). */
    function applySelections( html, selections ) {
        var sorted = selections.slice().sort( function ( a, b ) {
            return b.offset - a.offset;
        } );
        sorted.forEach( function ( sel ) {
            var before = html.slice( 0, sel.offset );
            var text   = html.slice( sel.offset, sel.offset + sel.length );
            var after  = html.slice( sel.offset + sel.length );
            html = before + '[' + sel.tag + ']' + text + '[/' + sel.tag + ']' + after;
        } );
        return html;
    }

    /* Liste des ids d'editeurs presents sur la page : l'editeur classique
       #content ET chaque editeur de section Schilo Builder (id dynamique,
       un par bloc "Contenu" de section — voir wp_editor() dans
       views/admin/sections/*.php et wp.editor.initialize() dans
       builder-admin.js). tinymce.editors recense toutes les instances,
       visibles ou basculees en mode "Texte". */
    function getAllEditorIds() {
        var ids = [];
        if ( window.tinymce && tinymce.editors && tinymce.editors.length ) {
            tinymce.editors.forEach( function ( ed ) { ids.push( ed.id ); } );
        } else if ( document.getElementById( 'content' ) ) {
            ids.push( 'content' );
        }
        return ids;
    }

    function editorGetContent( id ) {
        if ( window.tinymce ) {
            var ed = tinymce.get( id );
            if ( ed && ! ed.isHidden() ) return ed.getContent();
        }
        var ta = document.getElementById( id );
        return ta ? ta.value : '';
    }

    function editorSetContent( id, html ) {
        if ( window.tinymce ) {
            var ed = tinymce.get( id );
            if ( ed && ! ed.isHidden() ) {
                ed.setContent( html );
                ed.save();
                return;
            }
        }
        var ta = document.getElementById( id );
        if ( ta ) ta.value = html;
    }

    /* Libelle lisible pour identifier la section dans la liste de la modale.
       Best-effort : #content a un libelle fixe, les editeurs de section
       tentent de retrouver le titre saisi (.schilo-title-input) dans
       l'entree correspondante de la liste des sections. */
    function labelForEditor( id ) {
        if ( id === 'content' ) return 'Contenu (édition classique)';

        var wrap = document.getElementById( 'wp-' + id + '-wrap' );
        var container = wrap ? wrap.closest( '.schilo-section-item, .schilo-section-form, [data-section-index]' ) : null;
        if ( container ) {
            var titleInput = container.querySelector( '.schilo-title-input' );
            var number = container.querySelector( '.schilo-section-number' );
            var parts = [];
            if ( number && number.textContent.trim() ) parts.push( 'Section ' + number.textContent.trim() );
            if ( titleInput && titleInput.value ) parts.push( titleInput.value );
            if ( parts.length ) return parts.join( ' — ' );
        }
        return 'Section';
    }

    function escHtml( s ) {
        return String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
    }

    function buildModal( matches, onDone ) {
        var overlay = document.createElement( 'div' );
        overlay.id = 'schilo-bibref-overlay';

        var rows = matches.map( function ( m, i ) {
            var options = TAGS.map( function ( t ) {
                return '<option value="' + t + '"' + ( t === 'bib' ? ' selected' : '' ) + '>' + TAG_LABELS[ t ] + '</option>';
            } ).join( '' );
            return '<tr>' +
                '<td><input type="checkbox" class="schilo-bibref-check" data-i="' + i + '" checked></td>' +
                '<td class="schilo-bibref-text">' + escHtml( m.text ) + '</td>' +
                '<td class="schilo-bibref-section">' + escHtml( m.editorLabel ) + '</td>' +
                '<td><select class="schilo-bibref-tag" data-i="' + i + '">' + options + '</select></td>' +
                '</tr>';
        } ).join( '' );

        overlay.innerHTML =
            '<div class="schilo-bibref-modal">' +
                '<h2>Références bibliques détectées</h2>' +
                '<p>' + matches.length + ' référence(s) trouvée(s) dans le texte, hors balises déjà présentes. ' +
                'Cochez celles à entourer d’un shortcode avant l’enregistrement.</p>' +
                '<div class="schilo-bibref-toolbar">' +
                    '<button type="button" id="schilo-bibref-all" class="button">Tout cocher</button>' +
                    '<button type="button" id="schilo-bibref-none" class="button">Tout décocher</button>' +
                '</div>' +
                '<div class="schilo-bibref-table-wrap">' +
                '<table class="schilo-bibref-table"><thead><tr><th></th><th>Référence</th><th>Section</th><th>Balise</th></tr></thead>' +
                '<tbody>' + rows + '</tbody></table>' +
                '</div>' +
                '<div class="schilo-bibref-actions">' +
                    '<button type="button" id="schilo-bibref-skip" class="button">Enregistrer sans modifier</button>' +
                    '<button type="button" id="schilo-bibref-apply" class="button button-primary">Appliquer et enregistrer</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild( overlay );

        overlay.querySelector( '#schilo-bibref-all' ).addEventListener( 'click', function () {
            overlay.querySelectorAll( '.schilo-bibref-check' ).forEach( function ( c ) { c.checked = true; } );
        } );
        overlay.querySelector( '#schilo-bibref-none' ).addEventListener( 'click', function () {
            overlay.querySelectorAll( '.schilo-bibref-check' ).forEach( function ( c ) { c.checked = false; } );
        } );
        overlay.querySelector( '#schilo-bibref-skip' ).addEventListener( 'click', function () {
            document.body.removeChild( overlay );
            onDone( [] );
        } );
        overlay.querySelector( '#schilo-bibref-apply' ).addEventListener( 'click', function () {
            var selections = [];
            overlay.querySelectorAll( '.schilo-bibref-check' ).forEach( function ( c ) {
                if ( ! c.checked ) return;
                var i = parseInt( c.getAttribute( 'data-i' ), 10 );
                var tagSel = overlay.querySelector( '.schilo-bibref-tag[data-i="' + i + '"]' );
                selections.push( {
                    editorId: matches[ i ].editorId,
                    offset: matches[ i ].offset,
                    length: matches[ i ].length,
                    tag: tagSel ? tagSel.value : 'bib'
                } );
            } );
            document.body.removeChild( overlay );
            onDone( selections );
        } );
    }

    window.SchiloBibRefDetect = {
        runBeforeSave: function ( proceed ) {
            var ids = getAllEditorIds();
            var allMatches = [];
            ids.forEach( function ( id ) {
                var html = editorGetContent( id );
                detect( html ).forEach( function ( m ) {
                    m.editorId = id;
                    m.editorLabel = labelForEditor( id );
                    allMatches.push( m );
                } );
            } );

            if ( ! allMatches.length ) {
                proceed();
                return;
            }

            buildModal( allMatches, function ( selections ) {
                var byEditor = {};
                selections.forEach( function ( sel ) {
                    ( byEditor[ sel.editorId ] = byEditor[ sel.editorId ] || [] ).push( sel );
                } );
                Object.keys( byEditor ).forEach( function ( id ) {
                    var html = editorGetContent( id );
                    editorSetContent( id, applySelections( html, byEditor[ id ] ) );
                } );
                proceed();
            } );
        }
    };

    /* Point d'entrée unique : intercepte le clic sur le bouton natif "Publier
       / Mettre à jour" (#publish), qu'il soit déclenché par l'utilisateur ou
       programmatiquement par le bouton "Enregistrer" custom de la barre
       sticky (voir admin-post-edit.js, qui appelle pub.click()). Capture
       phase : s'exécute avant le submit natif du formulaire. */
    document.addEventListener( 'DOMContentLoaded', function () {
        var pub = document.getElementById( 'publish' );
        if ( ! pub ) return;

        var bypass = false;
        pub.addEventListener( 'click', function ( e ) {
            if ( bypass ) { bypass = false; return; }
            e.preventDefault();
            e.stopImmediatePropagation();
            window.SchiloBibRefDetect.runBeforeSave( function () {
                bypass = true;
                /* setTimeout : un re-clic synchrone et imbrique dans le
                   gestionnaire de clic du meme bouton "submit" ne declenche
                   pas fiablement la soumission native (le navigateur la
                   considere deja "en cours" dans la meme pile d'appels).
                   Sortir de la pile synchrone resout le probleme. */
                setTimeout( function () { pub.click(); }, 0 );
            } );
        }, true );
    } );
})();
