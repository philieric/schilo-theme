<?php

declare(strict_types=1);

namespace Schilo\Builder\Service;

use Schilo\Builder\Repository\ArticleReportRepository;

/**
 * Signalement d'articles par les administrateurs (repérage des articles mal
 * migrés) : un article est soit signalé, soit non — pas de motif ni de
 * statut intermédiaire.
 */
class ArticleReportService
{
    private ArticleReportRepository $repository;

    public function __construct(ArticleReportRepository $repository)
    {
        $this->repository = $repository;
    }

    public function isReported(int $postId): bool
    {
        return $postId > 0 && $this->repository->exists($postId);
    }

    /**
     * Bascule le signalement d'un article.
     *
     * @return bool|null Nouvel état (true = signalé, false = signalement
     *                   retiré), ou null si $postId n'est pas un article.
     */
    public function toggle(int $postId, int $userId): ?bool
    {
        $post = $postId > 0 ? get_post($postId) : null;

        if (!$post || $post->post_type !== 'post') {
            return null;
        }

        if ($this->repository->exists($postId)) {
            $this->repository->delete($postId);

            return false;
        }

        $this->repository->save($postId, $userId, current_time('mysql'));

        return true;
    }

    public function remove(int $postId): void
    {
        if ($postId > 0) {
            $this->repository->delete($postId);
        }
    }

    /**
     * @return array<int, array{post_id:int, user_id:int, created_at:string}>
     */
    public function getAll(): array
    {
        return $this->repository->findAll();
    }
}
