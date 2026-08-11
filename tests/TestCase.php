<?php

declare(strict_types=1);

namespace Schilo\Builder\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Classe de base pour les tests unitaires du thème : bascule les fonctions
 * WordPress (get_option, sanitize_key...) en mocks Brain\Monkey le temps du
 * test, sans nécessiter une vraie installation WordPress.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
