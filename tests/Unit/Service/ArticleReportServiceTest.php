<?php

declare(strict_types=1);

namespace Schilo\Builder\Tests\Unit\Service;

use Brain\Monkey\Functions;
use Mockery;
use Schilo\Builder\Repository\ArticleReportRepository;
use Schilo\Builder\Service\ArticleReportService;
use Schilo\Builder\Tests\TestCase;

final class ArticleReportServiceTest extends TestCase
{
    /** @var ArticleReportRepository&\Mockery\MockInterface */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ArticleReportRepository::class);

        Functions\when('current_time')->justReturn('2026-10-10 12:00:00');
    }

    private function givenPost(string $postType): void
    {
        Functions\when('get_post')->alias(static function () use ($postType): object {
            return (object) array('ID' => 42, 'post_type' => $postType);
        });
    }

    public function testToggleReportsAProblemThatWasNotReported(): void
    {
        $this->givenPost('post');
        $this->repository->shouldReceive('exists')->with(42, 'versets')->andReturn(false);
        $this->repository->shouldReceive('save')->once()->with(42, 'versets', 7, '2026-10-10 12:00:00')->andReturn(true);

        $service = new ArticleReportService($this->repository);

        self::assertTrue($service->toggle(42, ArticleReportService::TYPE_VERSES, 7));
    }

    public function testToggleRemovesOnlyTheReportOfThatType(): void
    {
        $this->givenPost('post');
        $this->repository->shouldReceive('exists')->with(42, 'migration')->andReturn(true);
        $this->repository->shouldReceive('delete')->once()->with(42, 'migration')->andReturn(true);
        $this->repository->shouldNotReceive('save');

        $service = new ArticleReportService($this->repository);

        self::assertFalse($service->toggle(42, ArticleReportService::TYPE_MIGRATION, 7));
    }

    public function testToggleRefusesAnUnknownType(): void
    {
        $this->givenPost('post');
        $this->repository->shouldNotReceive('exists');
        $this->repository->shouldNotReceive('save');

        $service = new ArticleReportService($this->repository);

        self::assertNull($service->toggle(42, 'autre', 7));
    }

    public function testToggleRefusesAnythingThatIsNotAnArticle(): void
    {
        $this->givenPost('page');
        $this->repository->shouldNotReceive('save');
        $this->repository->shouldNotReceive('delete');

        $service = new ArticleReportService($this->repository);

        self::assertNull($service->toggle(42, ArticleReportService::TYPE_MIGRATION, 7));
    }

    public function testToggleRefusesAnUnknownPost(): void
    {
        Functions\when('get_post')->justReturn(null);
        $this->repository->shouldNotReceive('save');

        $service = new ArticleReportService($this->repository);

        self::assertNull($service->toggle(999, ArticleReportService::TYPE_VERSES, 7));
        self::assertNull($service->toggle(0, ArticleReportService::TYPE_VERSES, 7));
    }

    public function testGetReportedTypesIgnoresTypesThatNoLongerExist(): void
    {
        $this->repository->shouldReceive('findTypesForPost')->with(42)->andReturn(array('migration', 'ancien-type'));

        $service = new ArticleReportService($this->repository);

        self::assertSame(array('migration'), $service->getReportedTypes(42));
    }

    public function testGetReportedTypesNeverQueriesForAnInvalidId(): void
    {
        $this->repository->shouldNotReceive('findTypesForPost');

        $service = new ArticleReportService($this->repository);

        self::assertSame(array(), $service->getReportedTypes(0));
    }

    public function testRemoveIgnoresAnUnknownType(): void
    {
        $this->repository->shouldNotReceive('delete');

        $service = new ArticleReportService($this->repository);
        $service->remove(42, 'autre');

        self::assertTrue(true);
    }
}
