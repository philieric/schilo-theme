<?php

declare(strict_types=1);

namespace Schilo\Builder\Tests\Unit\Service;

use Brain\Monkey\Functions;
use Schilo\Builder\Service\AnnexeService;
use Schilo\Builder\Tests\TestCase;

/**
 * Verrouille en tests automatisés les règles de l'index inverse
 * "proprietaire principal" d'une annexe (une meme annexe peut avoir
 * plusieurs proprietaires, un seul est marque principal a la fois).
 */
final class AnnexeServiceTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $meta = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->meta = [];

        Functions\when('get_post_meta')->alias(function ($postId, $key, $single = false) {
            $postId = (int) $postId;
            if (!array_key_exists($key, $this->meta[$postId] ?? [])) {
                return $single ? '' : [];
            }

            return $this->meta[$postId][$key];
        });

        Functions\when('update_post_meta')->alias(function ($postId, $key, $value) {
            $this->meta[(int) $postId][$key] = $value;

            return true;
        });

        Functions\when('delete_post_meta')->alias(function ($postId, $key) {
            unset($this->meta[(int) $postId][$key]);

            return true;
        });
    }

    public function testLinkingAnAnnexeRegistersTheOwnerInItsBacklinks(): void
    {
        $service = new AnnexeService();

        $service->saveSecondaryLinks(1, true, [10, 11]);

        self::assertSame([1], $service->getOwnerIds(10));
        self::assertSame([1], $service->getOwnerIds(11));
    }

    public function testTheSameAnnexeCanHaveSeveralOwners(): void
    {
        $service = new AnnexeService();

        $service->saveSecondaryLinks(1, true, [10]);
        $service->saveSecondaryLinks(2, true, [10]);

        self::assertSame([1, 2], $service->getOwnerIds(10));
    }

    public function testUnlinkingRemovesTheOwnerFromTheAnnexeBacklinks(): void
    {
        $service = new AnnexeService();

        $service->saveSecondaryLinks(1, true, [10, 11]);
        $service->saveSecondaryLinks(1, true, [10]); // retire 11

        self::assertSame([1], $service->getOwnerIds(10));
        self::assertSame([], $service->getOwnerIds(11));
    }

    public function testDisablingClearsAllBacklinksForThatOwner(): void
    {
        $service = new AnnexeService();

        $service->saveSecondaryLinks(1, true, [10, 11]);
        $service->saveSecondaryLinks(1, false, []);

        self::assertSame([], $service->getOwnerIds(10));
        self::assertSame([], $service->getOwnerIds(11));
    }

    public function testSetPrimaryOwnerAcceptsAKnownOwner(): void
    {
        $service = new AnnexeService();

        $service->saveSecondaryLinks(1, true, [10]);
        $service->saveSecondaryLinks(2, true, [10]);

        $service->setPrimaryOwner(10, 2);

        self::assertSame(2, $service->getPrimaryOwnerId(10));
    }

    public function testSetPrimaryOwnerRejectsAnUnrelatedOwner(): void
    {
        $service = new AnnexeService();

        $service->saveSecondaryLinks(1, true, [10]);

        $service->setPrimaryOwner(10, 999); // 999 n'a jamais lie l'annexe 10

        self::assertSame(0, $service->getPrimaryOwnerId(10));
    }

    public function testChoosingANewPrimaryDoesNotRequireTouchingOtherOwners(): void
    {
        // Contrairement a ArticleVersionService::setPrimary(), tous les
        // candidats sont regroupes sur l'annexe elle-meme : aucune boucle de
        // decochage sur d'autres posts n'est necessaire.
        $service = new AnnexeService();

        $service->saveSecondaryLinks(1, true, [10]);
        $service->saveSecondaryLinks(2, true, [10]);

        $service->setPrimaryOwner(10, 1);
        self::assertSame(1, $service->getPrimaryOwnerId(10));

        $service->setPrimaryOwner(10, 2);
        self::assertSame(2, $service->getPrimaryOwnerId(10));
    }

    public function testRemovingTheCurrentPrimaryOwnerClearsThePrimaryFlag(): void
    {
        $service = new AnnexeService();

        $service->saveSecondaryLinks(1, true, [10]);
        $service->setPrimaryOwner(10, 1);
        self::assertSame(1, $service->getPrimaryOwnerId(10));

        $service->saveSecondaryLinks(1, true, []); // retire le lien -> desactive

        self::assertSame(0, $service->getPrimaryOwnerId(10));
    }
}
