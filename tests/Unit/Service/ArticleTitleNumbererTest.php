<?php

declare(strict_types=1);

namespace Schilo\Builder\Tests\Unit\Service;

use Brain\Monkey\Functions;
use Schilo\Builder\Service\ArticleTitleNumberer;
use Schilo\Builder\Tests\Support\FakeWpdb;
use Schilo\Builder\Tests\TestCase;

/**
 * Verrouille en tests automatisés les scénarios vérifiés manuellement lors de
 * l'ajout de la numérotation configurable par préfixe : un article déjà
 * publié garde sa largeur de chiffres même si le réglage global change
 * ensuite, et un numéro réellement nouveau n'introduit jamais de zéro de
 * tête déroutant à côté d'une numérotation héritée plus courte.
 */
final class ArticleTitleNumbererTest extends TestCase
{
    /** @var array<int, string> */
    private array $titlesById = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->titlesById = [];

        Functions\when('sanitize_title')->alias(static function ($title): string {
            $slug = strtolower((string) $title);

            return trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
        });

        Functions\when('get_the_title')->alias(function ($postId): string {
            return $this->titlesById[(int) $postId] ?? '';
        });
    }

    /**
     * @param array<int, array{ID:int, post_title:string}> $legacyPosts
     */
    private function seedWpdb(array $legacyPosts): void
    {
        foreach ($legacyPosts as $post) {
            $this->titlesById[$post['ID']] = $post['post_title'];
        }

        $GLOBALS['wpdb'] = new FakeWpdb($legacyPosts);
    }

    public function testExistingArticleTitleIsFrozenWhenPrefixDigitsIncrease(): void
    {
        $this->seedWpdb([
            ['ID' => 1, 'post_title' => 'PER001 - Pourquoi et comment Luc a écrit son Évangile ?'],
        ]);

        Functions\when('get_option')->justReturn([
            'PER' => ['key' => 'PER', 'label' => 'Présentation', 'description' => '', 'active' => 1, 'sections' => [], 'digits' => 4],
        ]);
        Functions\when('sanitize_key')->returnArg();
        Functions\when('sanitize_text_field')->returnArg();

        $numberer = new ArticleTitleNumberer();

        $data = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_title' => 'PER001 - Pourquoi et comment Luc a écrit son Évangile ?',
        ];

        $result = $numberer->filterPostData($data, ['ID' => 1]);

        self::assertSame('PER001 - Pourquoi et comment Luc a écrit son Évangile ?', $result['post_title']);
    }

    public function testAutoAssignJumpsToHigherBlockWhenLegacyShorterWidthExists(): void
    {
        $this->seedWpdb([
            ['ID' => 1, 'post_title' => 'PER001 - Article un'],
            ['ID' => 2, 'post_title' => 'PER002 - Article deux'],
        ]);

        Functions\when('get_option')->justReturn([
            'PER' => ['key' => 'PER', 'label' => 'Présentation', 'description' => '', 'active' => 1, 'sections' => [], 'digits' => 4],
        ]);
        Functions\when('sanitize_key')->returnArg();
        Functions\when('sanitize_text_field')->returnArg();

        $numberer = new ArticleTitleNumberer();

        $data = ['post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'PER Titre auto'];

        $result = $numberer->filterPostData($data, ['ID' => 0]);

        self::assertSame('PER1000 - Titre auto', $result['post_title']);
    }

    public function testDeliberateRenumberAvoidsLeadingZero(): void
    {
        $this->seedWpdb([
            ['ID' => 1, 'post_title' => 'PER001 - Article un'],
            // Un AUTRE article a 3 chiffres : c'est lui qui etablit l'heritage
            // court, puisque hasLegacyShorterWidth() exclut le post courant
            // (ID 1, celui qu'on renumerote) de sa recherche.
            ['ID' => 2, 'post_title' => 'PER002 - Article deux'],
        ]);

        Functions\when('get_option')->justReturn([
            'PER' => ['key' => 'PER', 'label' => 'Présentation', 'description' => '', 'active' => 1, 'sections' => [], 'digits' => 4],
        ]);
        Functions\when('sanitize_key')->returnArg();
        Functions\when('sanitize_text_field')->returnArg();

        $numberer = new ArticleTitleNumberer();

        // Le meme post (ID 1) est renumerote deliberement vers 402.
        $data = ['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'PER402 Titre renumerote'];

        $result = $numberer->filterPostData($data, ['ID' => 1]);

        self::assertSame('PER1402 - Titre renumerote', $result['post_title']);
    }

    public function testPrefixWithoutLegacyShorterWidthKeepsNormalZeroPadding(): void
    {
        // Aucun article ANX existant du tout : pas d'heritage plus court.
        $this->seedWpdb([]);

        Functions\when('get_option')->justReturn([
            'ANX' => ['key' => 'ANX', 'label' => 'Annexes', 'description' => '', 'active' => 1, 'sections' => [], 'digits' => 4],
        ]);
        Functions\when('sanitize_key')->returnArg();
        Functions\when('sanitize_text_field')->returnArg();

        $numberer = new ArticleTitleNumberer();

        $data = ['post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'ANX Titre test'];

        $result = $numberer->filterPostData($data, ['ID' => 0]);

        self::assertSame('ANX0001 - Titre test', $result['post_title']);
    }
}
