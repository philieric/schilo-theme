<?php

declare(strict_types=1);

namespace Schilo\Builder\Tests\Unit\Service;

use Brain\Monkey\Functions;
use Schilo\Builder\Service\TemplateService;
use Schilo\Builder\Tests\TestCase;

final class TemplateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('sanitize_key')->alias(static function ($key): string {
            $key = strtolower((string) $key);

            return (string) preg_replace('/[^a-z0-9_\-]/', '', $key);
        });

        Functions\when('sanitize_text_field')->returnArg();
    }

    public function testDigitsDefaultsToThreeWhenAbsent(): void
    {
        Functions\when('get_option')->justReturn([
            'PER' => [
                'key' => 'PER',
                'label' => 'Présentation',
                'description' => '',
                'active' => 1,
                'sections' => ['intro'],
                // pas de 'digits' du tout.
            ],
        ]);

        $templates = (new TemplateService())->getAllTemplates();

        self::assertSame(3, $templates['PER']['digits']);
    }

    public function testDigitsWithinRangeIsPreserved(): void
    {
        Functions\when('get_option')->justReturn([
            'ANX' => [
                'key' => 'ANX',
                'label' => 'Annexes',
                'description' => '',
                'active' => 1,
                'sections' => ['paragraphe'],
                'digits' => 4,
            ],
        ]);

        $templates = (new TemplateService())->getAllTemplates();

        self::assertSame(4, $templates['ANX']['digits']);
    }

    public function testDigitsIsClampedToSix(): void
    {
        Functions\when('get_option')->justReturn([
            'ANX' => [
                'key' => 'ANX',
                'label' => 'Annexes',
                'description' => '',
                'active' => 1,
                'sections' => ['paragraphe'],
                'digits' => 42,
            ],
        ]);

        $templates = (new TemplateService())->getAllTemplates();

        self::assertSame(6, $templates['ANX']['digits']);
    }

    public function testDigitsOfZeroOrLessFallsBackToThree(): void
    {
        Functions\when('get_option')->justReturn([
            'ANX' => [
                'key' => 'ANX',
                'label' => 'Annexes',
                'description' => '',
                'active' => 1,
                'sections' => ['paragraphe'],
                'digits' => 0,
            ],
        ]);

        $templates = (new TemplateService())->getAllTemplates();

        self::assertSame(3, $templates['ANX']['digits']);
    }

    public function testGetDigitsForPrefixReadsTheConfiguredTemplate(): void
    {
        Functions\when('get_option')->justReturn([
            'PER' => [
                'key' => 'PER',
                'label' => 'Présentation',
                'description' => '',
                'active' => 1,
                'sections' => ['intro'],
                'digits' => 4,
            ],
        ]);

        self::assertSame(4, (new TemplateService())->getDigitsForPrefix('PER'));
    }

    public function testGetDigitsForPrefixFallsBackToThreeForUnknownPrefix(): void
    {
        Functions\when('get_option')->justReturn([]);

        // Aucun template sauvegardé => getAllTemplates() retombe sur les
        // templates par défaut, qui incluent 'DEFAULT' à 3 chiffres.
        self::assertSame(3, (new TemplateService())->getDigitsForPrefix('INCONNU'));
    }

    public function testSaveTemplatesClampsDigitsBeforePersisting(): void
    {
        $captured = null;

        Functions\when('update_option')->alias(function ($name, $value) use (&$captured): bool {
            $captured = $value;

            return true;
        });

        (new TemplateService())->saveTemplates([
            [
                'key' => 'ANX',
                'label' => 'Annexes',
                'description' => '',
                'active' => 1,
                'sections' => ['paragraphe'],
                'digits' => 99,
            ],
        ]);

        self::assertSame(6, $captured['ANX']['digits']);
    }
}
