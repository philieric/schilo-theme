<?php

declare(strict_types=1);

namespace Schilo\Builder\Tests\Unit\Global;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use Schilo\Builder\Tests\TestCase;

/**
 * Schilo_Advanced_Search est une classe globale (non namespacée, style
 * Schilo_Search_Suggest) chargée par require_once dans functions.php plutot
 * que par l'autoload PSR-4 — on la require donc manuellement ici.
 *
 * Ne couvre que les fonctions pures/sans effet de bord (extraction du "token
 * livre" depuis une reference biblique libre, sanitisation des criteres) :
 * le reste de la classe est fortement couple a $wpdb / get_terms() et deja
 * verifie en direct (appels AJAX reels) lors de sa construction.
 */
final class AdvancedSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les deux fichiers commencent par defined('ABSPATH') || exit -- doit
        // etre pose AVANT le premier require_once, sinon le process PHPUnit
        // entier se termine (exit reel, pas une exception attrapable). De
        // meme, HOUR_IN_SECONDS est utilisee dans une constante de classe
        // (const TRANSIENT_TTL = 6 * HOUR_IN_SECONDS), evaluee au chargement
        // du fichier donc AVANT tout appel de methode.
        if (!defined('ABSPATH')) {
            define('ABSPATH', __DIR__);
        }
        if (!defined('HOUR_IN_SECONDS')) {
            define('HOUR_IN_SECONDS', 3600);
        }
        if (!class_exists('Schilo_Prefixes')) {
            require_once dirname(__DIR__, 3) . '/inc/classes/class-schilo-prefixes.php';
        }
        if (!class_exists('Schilo_Advanced_Search')) {
            require_once dirname(__DIR__, 3) . '/inc/classes/class-schilo-advanced-search.php';
        }

        Functions\when('sanitize_key')->alias(static function ($key): string {
            $key = strtolower((string) $key);

            return (string) preg_replace('/[^a-z0-9_\-]/', '', $key);
        });
    }

    #[DataProvider('referenceProvider')]
    public function testExtractBookTokenFindsTheLeadingBookName(string $reference, string $expectedToken): void
    {
        self::assertSame($expectedToken, \Schilo_Advanced_Search::extract_book_token($reference));
    }

    public static function referenceProvider(): array
    {
        return [
            'point comme separateur' => ['Luc 1.26-38', 'Luc'],
            'virgule comme separateur (donnees IA reelles)' => ['Luc 1,1-4', 'Luc'],
            'livre numerote' => ['1 Corinthiens 13.4', '1 Corinthiens'],
            'abreviation courte' => ['Jn 3,16', 'Jn'],
            'sans chiffre du tout' => ['Introduction', ''],
            'chaine vide' => ['', ''],
        ];
    }

    public function testSanitizeIntListDedupesAndDropsZeros(): void
    {
        $result = \Schilo_Advanced_Search::sanitize_int_list(['12', '7', '12', '0', 'abc', 7]);

        self::assertSame([12, 7], $result);
    }

    public function testSanitizeIntListReturnsEmptyForNonArray(): void
    {
        self::assertSame([], \Schilo_Advanced_Search::sanitize_int_list('not-an-array'));
    }

    public function testSanitizePrefixListKeepsOnlyKnownPrefixes(): void
    {
        $result = \Schilo_Advanced_Search::sanitize_prefix_list(['per', 'INCONNU', 'ctd']);

        self::assertSame(['PER', 'CTD'], $result);
    }

    public function testSanitizePrefixListAlwaysExcludesAnx(): void
    {
        $result = \Schilo_Advanced_Search::sanitize_prefix_list(['anx', 'per']);

        self::assertSame(['PER'], $result);
    }

    public function testSanitizeCodeListUppercasesAndStripsInvalidChars(): void
    {
        $result = \Schilo_Advanced_Search::sanitize_code_list(['luk', '1co', 'a-b!']);

        self::assertSame(['LUK', '1CO', 'AB'], $result);
    }
}
