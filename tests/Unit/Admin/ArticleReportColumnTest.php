<?php

declare(strict_types=1);

namespace Schilo\Builder\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Mockery;
use Schilo\Builder\Admin\ArticleReportColumn;
use Schilo\Builder\Service\ArticleReportService;
use Schilo\Builder\Tests\TestCase;

final class ArticleReportColumnTest extends TestCase
{
    /** @var ArticleReportService&\Mockery\MockInterface */
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();

        $this->service = Mockery::mock(ArticleReportService::class);
        $this->service->shouldReceive('getTypeLabels')->andReturn(
            array('versets' => 'Versets', 'migration' => 'Migration')
        );
        $this->service->shouldReceive('getStatusLabels')->andReturn(
            array('ouvert' => 'À corriger', 'corrige' => 'Corrigé — en attente de vérification')
        );
    }

    private function renderCell(ArticleReportColumn $column, int $postId): string
    {
        ob_start();
        $column->renderCell(ArticleReportColumn::COLUMN, $postId);

        return (string) ob_get_clean();
    }

    public function testAFixedReportShowsThatItAwaitsVerification(): void
    {
        $this->service->shouldReceive('getAllByPost')->once()->andReturn(array(
            42 => array('migration' => array('note' => 'tableau cassé', 'status' => 'corrige')),
        ));

        $html = $this->renderCell(new ArticleReportColumn($this->service), 42);

        self::assertStringContainsString('Migration', $html);
        self::assertStringContainsString('Corrigé — en attente de vérification', $html);
        self::assertStringContainsString('tableau cassé', $html);
        self::assertStringNotContainsString('À corriger', $html);
    }

    public function testAnOpenReportShowsThatItIsStillToFix(): void
    {
        $this->service->shouldReceive('getAllByPost')->once()->andReturn(array(
            42 => array('versets' => array('note' => '', 'status' => 'ouvert')),
        ));

        $html = $this->renderCell(new ArticleReportColumn($this->service), 42);

        self::assertStringContainsString('Versets', $html);
        self::assertStringContainsString('À corriger', $html);
        self::assertStringNotContainsString('en attente de vérification', $html);
    }

    public function testAnArticleWithoutReportShowsNoStatus(): void
    {
        $this->service->shouldReceive('getAllByPost')->once()->andReturn(array());

        $html = $this->renderCell(new ArticleReportColumn($this->service), 42);

        self::assertStringContainsString('Non signalé', $html);
        self::assertStringNotContainsString('À corriger', $html);
    }

    public function testReportsAreLoadedOnceForTheWholeList(): void
    {
        $this->service->shouldReceive('getAllByPost')->once()->andReturn(array());

        $column = new ArticleReportColumn($this->service);
        $this->renderCell($column, 42);
        $this->renderCell($column, 43);

        self::assertTrue(true);
    }

    public function testOtherColumnsAreLeftAlone(): void
    {
        $this->service->shouldNotReceive('getAllByPost');

        ob_start();
        (new ArticleReportColumn($this->service))->renderCell('title', 42);

        self::assertSame('', ob_get_clean());
    }
}
