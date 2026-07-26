<?php
/**
 * Liste canonique des préfixes d'articles Schilo.
 *
 * SOURCE DE VÉRITÉ UNIQUE, appelable de partout via Schilo_Prefixes::all(),
 * pour éviter les listes divergentes (un scan dynamique ici, une constante
 * figée là, un LEFT(titre,3) ailleurs…). La liste est volontairement figée :
 * un nouveau préfixe s'ajoute ICI, en un seul endroit, et se répercute partout
 * (filtres de la liste d'articles, page « Parcours & Thèmes », etc.).
 *
 * Les *comptes* d'articles par préfixe restent calculés selon le contexte
 * (tous les articles, ou seulement les indexés valides…) ; seule la LISTE des
 * préfixes est centralisée ici.
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Prefixes {

    /** Préfixes d'articles, dans l'ordre d'affichage voulu. */
    private const LIST = array(
        'PER', 'CTD', 'ANN', 'APO', 'BIB', 'DAN', 'DOC', 'FDS', 'LGH', 'PAR',
        'PDA', 'INF', 'MIR', 'PRB', 'ADA', 'CHR', 'PLA', 'REP', 'CHA', 'PRO',
        'VER',
    );

    /** Liste canonique des préfixes (ordre d'affichage). */
    public static function all(): array {
        return self::LIST;
    }

    /** Indique si un préfixe (en MAJUSCULES) appartient à la liste canonique. */
    public static function is_valid( string $prefix ): bool {
        return in_array( strtoupper( $prefix ), self::LIST, true );
    }

    /**
     * Ordonne/filtre un tableau [prefixe => valeur] selon la liste canonique :
     * ne garde que les préfixes canoniques présents (valeur non vide), dans
     * l'ordre canonique. Pratique pour les listes qui masquent les préfixes
     * sans article (ex. filtres de la liste d'articles).
     */
    public static function order_counts( array $counts ): array {
        $ordered = array();
        foreach ( self::LIST as $prefix ) {
            if ( ! empty( $counts[ $prefix ] ) ) {
                $ordered[ $prefix ] = $counts[ $prefix ];
            }
        }
        return $ordered;
    }

    /**
     * Associe à CHAQUE préfixe canonique son compte (0 par défaut), dans l'ordre
     * canonique — pour afficher la liste COMPLÈTE des préfixes même quand l'un
     * d'eux n'a pas (encore) d'article (ex. page « Parcours & Thèmes »).
     */
    public static function map_counts( array $counts ): array {
        $mapped = array();
        foreach ( self::LIST as $prefix ) {
            $mapped[ $prefix ] = isset( $counts[ $prefix ] ) ? (int) $counts[ $prefix ] : 0;
        }
        return $mapped;
    }
}
