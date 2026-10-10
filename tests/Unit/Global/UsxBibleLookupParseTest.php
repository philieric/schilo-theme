<?php

declare(strict_types=1);

namespace Schilo\Builder\Tests\Unit\Global;

use PHPUnit\Framework\Attributes\DataProvider;
use Schilo\Builder\Tests\TestCase;

/**
 * Schilo_Usx_Bible_Lookup est une classe globale chargée par require_once
 * (voir AdvancedSearchTest pour le même montage). Seul parse_reference() est
 * couvert ici : c'est une fonction pure, le reste de la classe interroge les
 * tables wp_usx_* via $wpdb.
 */
final class UsxBibleLookupParseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Le fichier commence par defined('ABSPATH') || exit.
        if (!defined('ABSPATH')) {
            define('ABSPATH', __DIR__);
        }
        if (!class_exists('Schilo_Usx_Bible_Lookup')) {
            require_once dirname(__DIR__, 3) . '/inc/classes/usx/class-schilo-usx-bible-lookup.php';
        }
    }

    /**
     * @return array<string, array{0:string, 1:string, 2:int, 3:?int, 4:array<int, array{0:int, 1:int}>}>
     */
    public static function validReferences(): array
    {
        return array(
            'verset simple'                 => array('Luc 1.5', 'Luc', 1, null, array(array(5, 5))),
            'intervalle'                    => array('Luc 1.1-4', 'Luc', 1, null, array(array(1, 4))),
            'séparateur deux-points'        => array('Jean 3:16', 'Jean', 3, null, array(array(16, 16))),
            'livre numéroté'                => array('1 Rois 2.3', '1 Rois', 2, null, array(array(3, 3))),
            'balises HTML'                  => array('<strong><em>Luc 17.26-30</em></strong>', 'Luc', 17, null, array(array(26, 30))),
            'tiret demi-cadratin'           => array("Marc 13.1\u{2013}4", 'Marc', 13, null, array(array(1, 4))),
            'trait d\'union insécable'      => array("Luc 2.21\u{2011}24", 'Luc', 2, null, array(array(21, 24))),
            'tiret cadratin'                => array("Matthieu 24.29\u{2014}31", 'Matthieu', 24, null, array(array(29, 31))),
            'liste de versets'              => array('Joël 1.7,12', 'Joël', 1, null, array(array(7, 7), array(12, 12))),
            'liste avec espaces'            => array('Luc 24.27, 44', 'Luc', 24, null, array(array(27, 27), array(44, 44))),
            'plusieurs intervalles'         => array('Marc 11.12-14,20-25', 'Marc', 11, null, array(array(12, 14), array(20, 25))),
            'chapitre entier'               => array('Daniel 1', 'Daniel', 1, null, array()),
            'intervalle de chapitres'       => array("Matthieu 21\u{2013}23", 'Matthieu', 21, 23, array()),
            'chapitres, livre numéroté'     => array('1 Jean 2-3', '1 Jean', 2, 3, array()),
            'intervalle de chapitres nul'   => array('Marc 5-5', 'Marc', 5, null, array()),
        );
    }

    /**
     * @param array<int, array{0:int, 1:int}> $segments
     */
    #[DataProvider('validReferences')]
    public function testParsesReference(string $input, string $book, int $chapter, ?int $chapterEnd, array $segments): void
    {
        $parsed = \Schilo_Usx_Bible_Lookup::parse_reference($input);

        self::assertIsArray($parsed);
        self::assertSame($book, $parsed['bookToken']);
        self::assertSame($chapter, $parsed['chapter']);
        self::assertSame($chapterEnd, $parsed['chapterEnd']);
        self::assertSame($segments, $parsed['segments']);

        if ($segments === array()) {
            self::assertNull($parsed['verseStart']);
            self::assertNull($parsed['verseEnd']);
        } else {
            self::assertSame($segments[0][0], $parsed['verseStart']);
            self::assertSame($segments[count($segments) - 1][1], $parsed['verseEnd']);
        }
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function invalidReferences(): array
    {
        return array(
            'vide'                    => array(''),
            'livre seul'              => array('Jean'),
            'balise fermante cassée'  => array('Jean 8.12[]).'),
            'virgule finale'          => array('Luc 1.5,'),
            'texte libre'             => array('voir le chapitre suivant'),
        );
    }

    #[DataProvider('invalidReferences')]
    public function testRejectsInvalidReference(string $input): void
    {
        self::assertNull(\Schilo_Usx_Bible_Lookup::parse_reference($input));
    }

    public function testInvertedVerseIntervalIsClampedToItsStart(): void
    {
        $parsed = \Schilo_Usx_Bible_Lookup::parse_reference('Luc 1.9-4');

        self::assertSame(array(array(9, 9)), $parsed['segments']);
    }
}
