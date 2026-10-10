<?php

declare(strict_types=1);

namespace Schilo\Builder\Service;

use Schilo\Builder\Repository\ArticleReportRepository;

/**
 * Signalement d'articles par les administrateurs. Deux types de problème,
 * indépendants l'un de l'autre (un même article peut porter les deux) :
 * versets (références/citations bibliques) et migration (contenu mal repris).
 * Chaque type est soit signalé, soit non — pas de motif libre.
 */
class ArticleReportService
{
    public const TYPE_VERSES    = 'versets';
    public const TYPE_MIGRATION = 'migration';

    /** Ordre d'affichage des boutons et des filtres. */
    public const TYPES = array(self::TYPE_VERSES, self::TYPE_MIGRATION);

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

    /**
     * @return string[] Types actuellement signalés sur cet article.
     */
    public function getReportedTypes(int $postId): array
    {
        if ($postId <= 0) {
            return array();
        }

        return array_values(array_filter(
            $this->repository->findTypesForPost($postId),
            array($this, 'isValidType')
        ));
    }

    /**
     * Bascule le signalement d'un type de problème sur un article.
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

    public function remove(int $postId, string $type): void
    {
        if ($postId > 0 && $this->isValidType($type)) {
            $this->repository->delete($postId, $type);
        }
    }

    /**
     * @return array<int, array{post_id:int, type:string, user_id:int, created_at:string}>
     */
    public function getAll(): array
    {
        return $this->repository->findAll();
    }
}
