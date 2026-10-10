<?php

declare(strict_types=1);

namespace Schilo\Builder\Tests\Unit\Repository;

use Mockery;
use Schilo\Builder\Repository\ArticleReportRepository;
use Schilo\Builder\Tests\TestCase;

final class ArticleReportRepositoryTest extends TestCase
{
    /** @var \Mockery\MockInterface */
    private $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('ARRAY_A')) {
            define('ARRAY_A', 'ARRAY_A');
        }

        $this->wpdb         = Mockery::mock('wpdb');
        $this->wpdb->prefix = 'wp_';
    }

    public function testUpdateStatusOnlyTouchesTheStatusOfThatReport(): void
    {
        $this->wpdb->shouldReceive('update')->once()->with(
            'wp_schilo_signalements',
            array('status' => 'corrige'),
            array('post_id' => 42, 'report_type' => 'migration'),
            array('%s'),
            array('%d', '%s')
        )->andReturn(1);

        self::assertTrue((new ArticleReportRepository($this->wpdb))->updateStatus(42, 'migration', 'corrige'));
    }

    public function testUpdateStatusSucceedsWhenTheStatusWasAlreadyThatOne(): void
    {
        // $wpdb->update renvoie 0 ligne modifiée, ce qui n'est pas une erreur.
        $this->wpdb->shouldReceive('update')->once()->andReturn(0);

        self::assertTrue((new ArticleReportRepository($this->wpdb))->updateStatus(42, 'migration', 'corrige'));
    }

    public function testUpdateStatusReportsADatabaseError(): void
    {
        $this->wpdb->shouldReceive('update')->once()->andReturn(false);

        self::assertFalse((new ArticleReportRepository($this->wpdb))->updateStatus(42, 'migration', 'corrige'));
    }

    public function testFindForPostReturnsNoteAndStatusByType(): void
    {
        $this->wpdb->shouldReceive('prepare')->once()->with(
            'SELECT report_type, note, status FROM wp_schilo_signalements WHERE post_id = %d',
            42
        )->andReturn('SQL');
        $this->wpdb->shouldReceive('get_results')->once()->with('SQL', ARRAY_A)->andReturn(array(
            array('report_type' => 'versets', 'note' => 'Luc 1.5', 'status' => 'corrige'),
            array('report_type' => 'migration', 'note' => '', 'status' => 'ouvert'),
        ));

        self::assertSame(
            array(
                'versets'   => array('note' => 'Luc 1.5', 'status' => 'corrige'),
                'migration' => array('note' => '', 'status' => 'ouvert'),
            ),
            (new ArticleReportRepository($this->wpdb))->findForPost(42)
        );
    }

    public function testFindForPostReturnsNothingWhenTheQueryFails(): void
    {
        $this->wpdb->shouldReceive('prepare')->andReturn('SQL');
        $this->wpdb->shouldReceive('get_results')->andReturn(null);

        self::assertSame(array(), (new ArticleReportRepository($this->wpdb))->findForPost(42));
    }

    public function testFindAllExposesTheStatusOfEachReport(): void
    {
        $this->wpdb->shouldReceive('get_results')->once()->with(
            Mockery::on(static function (string $sql): bool {
                return strpos($sql, 'SELECT post_id, report_type, note, status, user_id, created_at') === 0;
            }),
            ARRAY_A
        )->andReturn(array(
            array('post_id' => '42', 'report_type' => 'migration', 'note' => '', 'status' => 'corrige', 'user_id' => '2', 'created_at' => '2026-10-10 12:00:00'),
        ));

        self::assertSame(
            array(array(
                'post_id'    => 42,
                'type'       => 'migration',
                'note'       => '',
                'status'     => 'corrige',
                'user_id'    => 2,
                'created_at' => '2026-10-10 12:00:00',
            )),
            (new ArticleReportRepository($this->wpdb))->findAll()
        );
    }
}
