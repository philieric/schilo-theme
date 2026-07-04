<?php
/**
 * Études mises en avant sur la page d'accueil.
 *
 * Rotation horaire déterministe : le même trio s'affiche pendant toute l'heure,
 * puis passe au suivant. Résultat identique pour tous les visiteurs simultanés.
 *
 * Pour ajouter ou modifier des études : éditer POOL ci-dessous.
 * Chaque entrée : ev (luc|mat|jean|marc), label, title, url.
 *
 * Intervalle de rotation : INTERVAL_SECONDS (défaut : 1 heure).
 * Taille du bloc affiché : PER_DISPLAY (défaut : 3).
 */
defined( 'ABSPATH' ) || exit;

class Schilo_Featured {

    const INTERVAL_SECONDS = HOUR_IN_SECONDS;
    const PER_DISPLAY      = 3;
    const CACHE_KEY        = 'schilo_featured';

    /**
     * Pool complet des études mises en avant.
     * Doit être un multiple de PER_DISPLAY pour des rotations propres.
     */
    private static function pool(): array {
        return [
            // — Trio 1 —
            [
                'ev'    => 'luc',
                'label' => 'Évangile de Luc · PER001',
                'title' => 'Pourquoi et comment Luc a-t-il écrit son Évangile ?',
                'url'   => get_search_link( 'Évangile de Luc' ),
            ],
            [
                'ev'    => 'mat',
                'label' => 'Matthieu · PER045',
                'title' => 'Le Sermon sur la Montagne',
                'url'   => get_search_link( 'Sermon sur la Montagne' ),
            ],
            [
                'ev'    => 'jean',
                'label' => 'Jean · PER089',
                'title' => "Les « Je suis » dans l'Évangile de Jean",
                'url'   => get_search_link( 'Je suis Évangile de Jean' ),
            ],
            // — Trio 2 —
            [
                'ev'    => 'marc',
                'label' => 'Marc · PER001',
                'title' => "Marc — l'Évangile de l'action",
                'url'   => get_search_link( 'Évangile de Marc' ),
            ],
            [
                'ev'    => 'luc',
                'label' => 'Luc · Paraboles',
                'title' => 'Les paraboles de Luc',
                'url'   => get_search_link( 'Paraboles de Luc' ),
            ],
            [
                'ev'    => 'mat',
                'label' => 'Matthieu · Généalogie',
                'title' => 'La généalogie de Jésus selon Matthieu',
                'url'   => get_search_link( 'Généalogie de Jésus' ),
            ],
            // — Trio 3 —
            [
                'ev'    => 'jean',
                'label' => 'Jean · Signes',
                'title' => "Les sept signes dans l'Évangile de Jean",
                'url'   => get_search_link( 'Signes de Jean' ),
            ],
            [
                'ev'    => 'mat',
                'label' => 'Matthieu · Prophéties',
                'title' => 'Les prophéties messianiques accomplies',
                'url'   => get_search_link( 'Prophéties messianiques' ),
            ],
            [
                'ev'    => 'marc',
                'label' => 'Marc · Passion',
                'title' => 'La Passion de Jésus selon Marc',
                'url'   => get_search_link( 'Passion de Jésus' ),
            ],
        ];
    }

    /**
     * Retourne le trio courant selon l'heure.
     * Mis en cache jusqu'à la prochaine rotation.
     *
     * @return array<int, array{ev: string, label: string, title: string, url: string}>
     */
    public static function get(): array {
        $cached = get_transient( self::CACHE_KEY );
        if ( false !== $cached ) {
            return $cached;
        }

        $pool       = self::pool();
        $total      = count( $pool );
        $n          = self::PER_DISPLAY;
        $trio_count = (int) floor( $total / $n );

        // Index du trio courant basé sur l'heure Unix
        $slot  = (int) floor( time() / self::INTERVAL_SECONDS );
        $trio  = $slot % $trio_count;
        $items = array_slice( $pool, $trio * $n, $n );

        // Expire à la prochaine rotation (pas à minuit)
        $next_rotation = ( $slot + 1 ) * self::INTERVAL_SECONDS;
        $ttl           = max( 60, $next_rotation - time() );

        set_transient( self::CACHE_KEY, $items, $ttl );

        return $items;
    }
}
