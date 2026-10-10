<?php

declare(strict_types=1);

namespace Schilo\Builder\Service;

use Schilo\Builder\Repository\ArticleReportRepository;

/**
 * Signalement d'articles par les administrateurs. Deux types de problème,
 * indépendants l'un de l'autre (un même article peut porter les deux) :
 * versets (références/citations bibliques) et migration (contenu mal repris).
 * Chaque type est soit signalé, soit non, avec une note facultative qui
 * précise le problème et un statut de suivi : ouvert (à corriger), puis
 * corrigé — en attente de vérification. Le signalement n'est retiré qu'une
 * fois la correction vérifiée.
 */
class ArticleReportService
{
    public const TYPE_VERSES    = 'versets';
    public const TYPE_MIGRATION = 'migration';

    /** Ordre d'affichage des boutons et des filtres. */
    public const TYPES = array(self::TYPE_VERSES, self::TYPE_MIGRATION);

    public const STATUS_OPEN  = 'ouvert';
    public const STATUS_FIXED = 'corrige';

    /** Ordre d'affichage des filtres ; le premier est le statut d'un nouveau signalement. */
    public const STATUSES = array(self::STATUS_OPEN, self::STATUS_FIXED);

    /** Longueur maximale d'une note (taille de la colonne en base). */
    public const NOTE_MAX_LENGTH = 500;

    private ArticleReportRepository $repository;

    public function __construct(ArticleReportRepository $repository)
    {
        $this->repository = $repository;
    }

    public function isValidType(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    /**
     * @return array<string, string> type => libellé affichable.
     */
    public function getTypeLabels(): array
    {
        return array(
            self::TYPE_VERSES    => __('Versets', 'schilo'),
            self::TYPE_MIGRATION => __('Migration', 'schilo'),
        );
    }

    public function isValidStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    /**
     * @return array<string, string> statut => libellé affichable.
     */
    public function getStatusLabels(): array
    {
        return array(
            self::STATUS_OPEN  => __('À corriger', 'schilo'),
            self::STATUS_FIXED => __('Corrigé — en attente de vérification', 'schilo'),
        );
    }

    /**
     * Un statut inconnu (ligne écrite par une autre version) est traité
     * comme ouvert : mieux vaut revoir un article de trop que d'en perdre un.
     */
    private function normalizeStatus(string $status): string
    {
        return $this->isValidStatus($status) ? $status : self::STATUS_OPEN;
    }

    /**
     * @return array<string, array{note:string, status:string}> Signalements
     *         actifs de cet article, par type (note '' si aucune).
     */
    public function getReports(int $postId): array
    {
        if ($postId <= 0) {
            return array();
        }

        $reports = array();
        foreach ($this->repository->findForPost($postId) as $type => $report) {
            if ($this->isValidType((string) $type)) {
                $reports[$type] = array(
                    'note'   => $report['note'],
                    'status' => $this->normalizeStatus($report['status']),
                );
            }
        }

        return $reports;
    }

    /**
     * Bascule le signalement d'un type de problème sur un article. Retirer un
     * signalement supprime aussi sa note.
     *
     * @return bool|null Nouvel état (true = signalé, false = signalement
     *                   retiré), ou null si l'article ou le type est invalide.
     */
    public function toggle(int $postId, string $type, int $userId): ?bool
    {
        if (!$this->isValidType($type)) {
            return null;
        }

        $post = $postId > 0 ? get_post($postId) : null;

        if (!$post || $post->post_type !== 'post') {
            return null;
        }

        if ($this->repository->exists($postId, $type)) {
            $this->repository->delete($postId, $type);

            return false;
        }

        $this->repository->save($postId, $type, $userId, current_time('mysql'));

        return true;
    }

    /**
     * Enregistre (ou vide) la note d'un signalement existant.
     *
     * @return string|null Note réellement enregistrée (nettoyée, tronquée à
     *                     NOTE_MAX_LENGTH), ou null s'il n'y a pas de
     *                     signalement de ce type sur cet article.
     */
    public function saveNote(int $postId, string $type, string $note): ?string
    {
        if ($postId <= 0 || !$this->isValidType($type) || !$this->repository->exists($postId, $type)) {
            return null;
        }

        $note = mb_substr(trim($note), 0, self::NOTE_MAX_LENGTH, 'UTF-8');
        $this->repository->updateNote($postId, $type, $note);

        return $note;
    }

    /**
     * Tous les signalements actifs, regroupés par article — pour afficher
     * une liste d'articles sans faire une requête par ligne.
     *
     * @return array<int, array<string, array{note:string, status:string}>>
     *         post_id => [type => signalement], types dans l'ordre de TYPES.
     */
    public function getAllByPost(): array
    {
        $byPost = array();

        foreach ($this->repository->findAll() as $row) {
            if ($this->isValidType($row['type'])) {
                $byPost[$row['post_id']][$row['type']] = array(
                    'note'   => $row['note'],
                    'status' => $this->normalizeStatus($row['status']),
                );
            }
        }

        $order = array_flip(self::TYPES);
        foreach ($byPost as $postId => $reports) {
            uksort($reports, static function (string $a, string $b) use ($order): int {
                return $order[$a] <=> $order[$b];
            });
            $byPost[$postId] = $reports;
        }

        return $byPost;
    }

    /**
     * Change le statut de suivi d'un signalement existant, sans le retirer.
     *
     * @return bool false si le type ou le statut est invalide, ou s'il n'y a
     *              pas de signalement de ce type sur cet article.
     */
    public function setStatus(int $postId, string $type, string $status): bool
    {
        if ($postId <= 0
            || !$this->isValidType($type)
            || !$this->isValidStatus($status)
            || !$this->repository->exists($postId, $type)
        ) {
            return false;
        }

        return $this->repository->updateStatus($postId, $type, $status);
    }

    public function remove(int $postId, string $type): void
    {
        if ($postId > 0 && $this->isValidType($type)) {
            $this->repository->delete($postId, $type);
        }
    }

    /**
     * @return array<int, array{post_id:int, type:string, note:string, status:string, user_id:int, created_at:string}>
     */
    public function getAll(): array
    {
        return array_map(function (array $row): array {
            $row['status'] = $this->normalizeStatus($row['status']);

            return $row;
        }, $this->repository->findAll());
    }
}
