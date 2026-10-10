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

    public function testGetReportsIgnoresTypesThatNoLongerExist(): void
    {
        $this->repository->shouldReceive('findNotesForPost')->with(42)->andReturn(
            array('migration' => 'tableau cassé', 'ancien-type' => 'x')
        );

        $service = new ArticleReportService($this->repository);

        self::assertSame(array('migration' => 'tableau cassé'), $service->getReports(42));
    }

    public function testGetReportsNeverQueriesForAnInvalidId(): void
    {
        $this->repository->shouldNotReceive('findNotesForPost');

        $service = new ArticleReportService($this->repository);

        self::assertSame(array(), $service->getReports(0));
    }

    public function testGetAllByPostGroupsReportsAndKeepsTheTypeOrder(): void
    {
        $row = static function (int $postId, string $type, string $note): array {
            return array('post_id' => $postId, 'type' => $type, 'note' => $note, 'user_id' => 7, 'created_at' => '2026-10-10 12:00:00');
        };

        $this->repository->shouldReceive('findAll')->once()->andReturn(array(
            $row(42, 'migration', 'tableau cassé'),
            $row(42, 'versets', ''),
            $row(43, 'ancien-type', 'ignoré'),
            $row(44, 'versets', 'Luc 1.5'),
        ));

        $service = new ArticleReportService($this->repository);

        self::assertSame(
            array(
                42 => array('versets' => '', 'migration' => 'tableau cassé'),
                44 => array('versets' => 'Luc 1.5'),
            ),
            $service->getAllByPost()
        );
    }

    public function testSaveNoteTrimsAndStoresTheNoteOfAnExistingReport(): void
    {
        $this->repository->shouldReceive('exists')->with(42, 'versets')->andReturn(true);
        $this->repository->shouldReceive('updateNote')->once()->with(42, 'versets', 'Luc 1.5 manquant')->andReturn(true);

        $service = new ArticleReportService($this->repository);

        self::assertSame('Luc 1.5 manquant', $service->saveNote(42, 'versets', "  Luc 1.5 manquant \n"));
    }

    public function testSaveNoteAcceptsAnEmptyNoteToClearIt(): void
    {
        $this->repository->shouldReceive('exists')->with(42, 'versets')->andReturn(true);
        $this->repository->shouldReceive('updateNote')->once()->with(42, 'versets', '')->andReturn(true);

        $service = new ArticleReportService($this->repository);

        self::assertSame('', $service->saveNote(42, 'versets', '   '));
    }

    public function testSaveNoteTruncatesToTheColumnLength(): void
    {
        $this->repository->shouldReceive('exists')->andReturn(true);
        $this->repository->shouldReceive('updateNote')->once()->andReturn(true);

        $service = new ArticleReportService($this->repository);
        $saved   = $service->saveNote(42, 'migration', str_repeat('é', 600));

        self::assertSame(ArticleReportService::NOTE_MAX_LENGTH, mb_strlen((string) $saved, 'UTF-8'));
    }

    public function testSaveNoteRefusesWhenThereIsNoReportOfThatType(): void
    {
        $this->repository->shouldReceive('exists')->with(42, 'versets')->andReturn(false);
        $this->repository->shouldNotReceive('updateNote');

        $service = new ArticleReportService($this->repository);

        self::assertNull($service->saveNote(42, 'versets', 'note orpheline'));
        self::assertNull($service->saveNote(42, 'autre', 'type inconnu'));
    }

    public function testRemoveIgnoresAnUnknownType(): void
    {
        $this->repository->shouldNotReceive('delete');

        $service = new ArticleReportService($this->repository);
        $service->remove(42, 'autre');

        self::assertTrue(true);
    }
}
