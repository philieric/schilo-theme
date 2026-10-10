<?php

declare(strict_types=1);

namespace Schilo\Builder\Repository;

/**
 * Accès à la table des signalements d'articles ({prefix}schilo_signalements) :
 * une ligne par couple article + type de problème (unique), avec l'auteur, la
 * date du signalement, une note facultative et un statut de suivi — voir
 * Schilo\Builder\Service\ArticleReportService pour les règles métier et la
 * liste des types.
 */
class ArticleReportRepository
{
    public const DB_VERSION        = '1.3.0';
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
     * Crée ou met à niveau la table si le schéma attendu n'est pas encore en
     * place. Appelé sur chaque requête ('init') : doit rester gratuit dans le
     * cas courant, d'où l'option de version autoloadée (aucune requête
     * supplémentaire).
     */
    public function maybeCreateTable(): void
    {
        $installed = get_option(self::DB_VERSION_OPTION);

        if ($installed === self::DB_VERSION) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1.0.0 : un seul signalement par article (post_id unique). dbDelta
        // n'enlève jamais un index existant ; sans ce retrait, signaler un 2e
        // type de problème sur le même article serait refusé par MySQL. Les
        // lignes existantes prennent le type par défaut 'migration' (le seul
        // usage prévu en 1.0.0).
        if ($installed === '1.0.0'
            && $this->wpdb->get_var("SHOW INDEX FROM {$this->table} WHERE Key_name = 'idx_post_id'") !== null
        ) {
            $this->wpdb->query("ALTER TABLE {$this->table} DROP INDEX idx_post_id");
        }

        $charset = $this->wpdb->get_charset_collate();

        // 1.2.0 : colonne note ; 1.3.0 : colonne status (ajoutées par dbDelta
        // aux tables existantes — les signalements déjà posés restent 'ouvert').
        dbDelta("CREATE TABLE {$this->table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            report_type VARCHAR(20) NOT NULL DEFAULT 'migration',
            note VARCHAR(500) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'ouvert',
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_post_type (post_id,report_type)
        ) {$charset};");

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    public function exists(int $postId, string $type): bool
    {
        $found = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT 1 FROM {$this->table} WHERE post_id = %d AND report_type = %s LIMIT 1",
                $postId,
                $type
            )
        );

        return $found !== null;
    }

    /**
     * @return array<string, array{note:string, status:string}> Signalements de cet article, par type.
     */
    public function findForPost(int $postId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT report_type, note, status FROM {$this->table} WHERE post_id = %d", $postId),
            ARRAY_A
        );

        $reports = array();
        foreach ((array) $rows as $row) {
            $reports[(string) $row['report_type']] = array(
                'note'   => (string) $row['note'],
                'status' => (string) $row['status'],
            );
        }

        return $reports;
    }

    /**
     * Idempotent : un signalement déjà présent garde son auteur, sa date, sa
     * note et son statut d'origine (le couple post_id + report_type est unique).
     */
    public function save(int $postId, string $type, int $userId, string $createdAt): bool
    {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->table} (post_id, report_type, user_id, created_at) VALUES (%d, %s, %d, %s)
                 ON DUPLICATE KEY UPDATE post_id = post_id",
                $postId,
                $type,
                $userId,
                $createdAt
            )
        );

        return $result !== false;
    }

    public function updateNote(int $postId, string $type, string $note): bool
    {
        $result = $this->wpdb->update(
            $this->table,
            array('note' => $note),
            array('post_id' => $postId, 'report_type' => $type),
            array('%s'),
            array('%d', '%s')
        );

        return $result !== false;
    }

    public function updateStatus(int $postId, string $type, string $status): bool
    {
        $result = $this->wpdb->update(
            $this->table,
            array('status' => $status),
            array('post_id' => $postId, 'report_type' => $type),
            array('%s'),
            array('%d', '%s')
        );

        return $result !== false;
    }

    public function delete(int $postId, string $type): bool
    {
        $result = $this->wpdb->delete(
            $this->table,
            array('post_id' => $postId, 'report_type' => $type),
            array('%d', '%s')
        );

        return $result !== false;
    }

    /**
     * @return array<int, array{post_id:int, type:string, note:string, status:string, user_id:int, created_at:string}> Du plus récent au plus ancien.
     */
    public function findAll(): array
    {
        $rows = $this->wpdb->get_results(
            "SELECT post_id, report_type, note, status, user_id, created_at FROM {$this->table} ORDER BY created_at DESC, id DESC",
            ARRAY_A
        );

        return array_map(static function (array $row): array {
            return array(
                'post_id'    => (int) $row['post_id'],
                'type'       => (string) $row['report_type'],
                'note'       => (string) $row['note'],
                'status'     => (string) $row['status'],
                'user_id'    => (int) $row['user_id'],
                'created_at' => (string) $row['created_at'],
            );
        }, (array) $rows);
    }
}
