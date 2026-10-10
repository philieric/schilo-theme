<?php

declare(strict_types=1);

namespace Schilo\Builder\Repository;

/**
 * Accès à la table des signalements d'articles ({prefix}schilo_signalements) :
 * une ligne par article signalé (post_id unique), avec l'auteur et la date du
 * signalement. Sert à repérer les articles mal migrés — voir
 * Schilo\Builder\Service\ArticleReportService pour les règles métier.
 */
class ArticleReportRepository
{
    public const DB_VERSION        = '1.0.0';
    public const DB_VERSION_OPTION = 'schilo_signalements_db_version';

    /** @var \wpdb */
    private $wpdb;

    private string $table;

    /**
     * @param \wpdb $wpdb
     */
    public function __construct($wpdb)
    {
        $this->wpdb  = $wpdb;
        $this->table = $wpdb->prefix . 'schilo_signalements';
    }

    /**
     * Crée la table si le schéma attendu n'est pas encore en place. Appelé
     * sur chaque requête ('init') : doit rester gratuit dans le cas courant,
     * d'où l'option de version autoloadée (aucune requête supplémentaire).
     */
    public function maybeCreateTable(): void
    {
        if (get_option(self::DB_VERSION_OPTION) === self::DB_VERSION) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $this->wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$this->table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_post_id (post_id)
        ) {$charset};");

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    public function exists(int $postId): bool
    {
        $found = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT 1 FROM {$this->table} WHERE post_id = %d LIMIT 1", $postId)
        );

        return $found !== null;
    }

    /**
     * Idempotent : un article déjà signalé garde son auteur et sa date
     * d'origine (post_id est unique).
     */
    public function save(int $postId, int $userId, string $createdAt): bool
    {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->table} (post_id, user_id, created_at) VALUES (%d, %d, %s)
                 ON DUPLICATE KEY UPDATE post_id = post_id",
                $postId,
                $userId,
                $createdAt
            )
        );

        return $result !== false;
    }

    public function delete(int $postId): bool
    {
        return $this->wpdb->delete($this->table, array('post_id' => $postId), array('%d')) !== false;
    }

    /**
     * @return array<int, array{post_id:int, user_id:int, created_at:string}> Du plus récent au plus ancien.
     */
    public function findAll(): array
    {
        $rows = $this->wpdb->get_results(
            "SELECT post_id, user_id, created_at FROM {$this->table} ORDER BY created_at DESC, id DESC",
            ARRAY_A
        );

        return array_map(static function (array $row): array {
            return array(
                'post_id'    => (int) $row['post_id'],
                'user_id'    => (int) $row['user_id'],
                'created_at' => (string) $row['created_at'],
            );
        }, (array) $rows);
    }
}
