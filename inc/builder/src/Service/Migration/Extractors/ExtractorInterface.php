<?php

namespace Schilo\Builder\Service\Migration\Extractors;

use Schilo\Builder\Service\Migration\MigrationSourceContent;

/**
 * Interface commune à tous les extracteurs de migration.
 *
 * Chaque extracteur sait reconnaître et extraire UN type d'élément précis
 * (titre Wikilogy, bloc "Consultation", textes bibliques, etc.) à partir
 * du contenu d'un article. Cette séparation permet de réutiliser le même
 * extracteur pour plusieurs préfixes (PER, ANN, CTD...) dès lors que la
 * structure est identique.
 */
interface ExtractorInterface
{
    /**
     * Identifiant technique unique de l'extracteur (ex: 'wikilogy_title').
     * Utilisé comme clé dans le modèle de migration enregistré.
     */
    public function getKey();

    /**
     * Libellé lisible affiché dans l'interface d'administration
     * (ex: "Titre Wikilogy").
     */
    public function getLabel();

    /**
     * Analyse le contenu de l'article et retourne la liste des éléments
     * trouvés. Un extracteur peut retourner zéro, un, ou plusieurs éléments
     * (ex: plusieurs liens "Consultation" dans un même article).
     *
     * Selon ce qu'il doit repérer, un extracteur utilise soit le HTML rendu
     * ($source->getRenderedHtml(), pour les éléments générés par des
     * shortcodes qui s'exécutent correctement comme Wikilogy), soit le
     * post_content brut ($source->getRawContent(), pour les shortcodes
     * WPBakery dont les attributs peuvent être corrompus par wptexturize
     * au moment du rendu — ex: guillemets droits transformés en guillemets
     * typographiques, qui cassent le parsing du shortcode).
     *
     * Chaque élément retourné est un tableau associatif avec au minimum :
     * - 'id'      : identifiant unique de cette occurrence (ex: 'wikilogy_title', 'blog_list_0')
     * - 'label'   : libellé affiché pour cette occurrence précise
     * - 'content' : contenu HTML/texte extrait
     * - 'meta'    : données complémentaires propres à l'extracteur (tableau)
     *
     * @param MigrationSourceContent $source
     * @return array<int, array>
     */
    public function extract(MigrationSourceContent $source);
}
