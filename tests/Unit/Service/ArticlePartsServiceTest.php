<?php

declare(strict_types=1);

namespace Schilo\Builder\Tests\Unit\Service;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use Schilo\Builder\Service\ArticlePartsService;
use Schilo\Builder\Tests\TestCase;

final class ArticlePartsServiceTest extends TestCase
{
    /**
     * @return array<string, array{0:string, 1:bool}>
     */
    public static function labels(): array
    {
        return array(
            'chiffre romain + point'      => array('I. Le choix des 12 par le Messie Jésus', true),
            'romain long'                 => array('XIII. Tableau récapitulatif', true),
            'chiffre arabe + point'       => array('1. Un jeune exilé au cœur de Babylone', true),
            'chiffre + tiret'             => array('2 - Les deux systèmes de mesure du temps', true),
            'chiffre + tiret demi-cadr.'  => array("3 \u{2013} La précision temporelle", true),
            'chiffre + parenthèse'        => array('4) La difficulté posée par Jean', true),
            'sans numéro'                 => array('Introduction', false),
            'mot commençant par V'        => array('Vie et ministère de Jésus', false),
            'mot commençant par I'        => array('Israël au temps de Jésus', false),
            'année en tête'               => array('1948 marque un tournant', false),
            'numéro seul'                 => array('I.', false),
            'vide'                        => array('', false),
        );
    }

    #[DataProvider('labels')]
    public function testDetectsSelfNumberedLabel(string $label, bool $expected): void
    {
        self::assertSame($expected, (new ArticlePartsService())->isSelfNumbered($label));
    }

    public function testOwnNumberingWhenEveryPartIsNumbered(): void
    {
        $parts = array(
            array('label' => 'I. Première', 'anchor' => 'a'),
            array('label' => 'II. Deuxième', 'anchor' => 'b'),
        );

        self::assertTrue((new ArticlePartsService())->usesOwnNumbering($parts));
    }

    public function testOwnNumberingToleratesOneUnnumberedPart(): void
    {
        $parts = array(
            array('label' => 'I. Première', 'anchor' => 'a'),
            array('label' => 'II. Deuxième', 'anchor' => 'b'),
            array('label' => 'Annexe', 'anchor' => 'c'),
        );

        self::assertTrue((new ArticlePartsService())->usesOwnNumbering($parts));
    }

    public function testKeepsAutomaticCounterWhenPartsAreNotNumbered(): void
    {
        $parts = array(
            array('label' => 'Le contexte', 'anchor' => 'a'),
            array('label' => 'Les faits', 'anchor' => 'b'),
            array('label' => 'M. Dupont et son étude', 'anchor' => 'c'),
        );

        self::assertFalse((new ArticlePartsService())->usesOwnNumbering($parts));
    }

    public function testNoOwnNumberingWithoutParts(): void
    {
        self::assertFalse((new ArticlePartsService())->usesOwnNumbering(array()));
    }

    public function testGetPartsKeepsOnlyTitledPartSections(): void
    {
        Functions\when('sanitize_title')->alias(static function (string $title): string {
            return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
        });

        $parts = (new ArticlePartsService())->getParts(array(
            array('type' => 'paragraphe', 'title' => 'Introduction'),
            array('type' => 'titre-partie', 'title' => 'I. Première'),
            array('type' => 'paragraphe', 'title' => ''),
            array('type' => 'titre-partie', 'title' => '  '),
            'pas un tableau',
            array('type' => 'titre-partie', 'title' => 'II. Deuxième'),
        ));

        self::assertSame(array('I. Première', 'II. Deuxième'), array_column($parts, 'label'));
        self::assertSame('i-premi-re', $parts[0]['anchor']);
    }
}
