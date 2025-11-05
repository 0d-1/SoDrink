<?php

declare(strict_types=1);

namespace Tests\Domain;

use PHPUnit\Framework\TestCase;
use SoDrink\Domain\Torpille;
use SoDrink\Storage\JsonStore;

class TorpilleTest extends TestCase
{
    private string $photosFile;
    private string $stateFile;
    private Torpille $torpille;
    private string $uploadDir;
    /** @var list<string> */
    private array $initialUploads = [];

    protected function setUp(): void
    {
        $photos = tempnam(sys_get_temp_dir(), 'torpille_photos_');
        $state = tempnam(sys_get_temp_dir(), 'torpille_state_');
        if ($photos === false || $state === false) {
            self::fail('Unable to create temporary torpille files');
        }
        $this->photosFile = $photos;
        $this->stateFile = $state;

        file_put_contents($this->photosFile, json_encode([]));
        file_put_contents($this->stateFile, json_encode([]));

        $this->torpille = new Torpille($this->photosFile, $this->stateFile);

        $projectRoot = realpath(__DIR__ . '/..');
        if ($projectRoot === false) {
            self::fail('Unable to resolve project root');
        }
        $projectRoot = dirname($projectRoot);
        $this->uploadDir = $projectRoot . '/public/uploads/torpille';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0775, true);
        }
        $existing = glob($this->uploadDir . '/*');
        $this->initialUploads = $existing ? array_values($existing) : [];
    }

    protected function tearDown(): void
    {
        foreach ([$this->photosFile, $this->stateFile] as $file) {
            if (isset($file) && is_file($file)) {
                unlink($file);
            }
        }

        if (isset($this->uploadDir)) {
            $current = glob($this->uploadDir . '/*');
            $current = $current ? array_values($current) : [];
            foreach ($current as $file) {
                if (!in_array($file, $this->initialUploads, true) && is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function testSetInitialUpdatesState(): void
    {
        $this->torpille->setInitial(42);

        self::assertSame(42, $this->torpille->currentUserId());
        $state = $this->torpille->getState();
        self::assertSame(42, $state['current_user_id']);
        self::assertSame(0, $state['sequence']);
    }

    public function testPassWithPhotoAppendsRecordAndAdvancesState(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension is required to test photo processing.');
        }

        $this->torpille->setInitial(5);
        $tmpImage = $this->createTempImage();

        $record = $this->torpille->passWithPhoto(5, $tmpImage, 'soirée.jpg', 7);

        self::assertSame(5, $record['user_id']);
        self::assertSame(1, $record['seq']);
        self::assertStringStartsWith('/uploads/torpille/', $record['path']);
        self::assertArrayHasKey('filename', $record);
        self::assertFileExists($this->uploadDir . '/' . $record['filename']);

        $state = $this->torpille->getState();
        self::assertSame(7, $state['current_user_id']);
        self::assertSame(1, $state['sequence']);

        $latest = $this->torpille->latest();
        self::assertNotNull($latest);
        self::assertSame($record['id'], $latest['id']);

        @unlink($tmpImage);
    }

    public function testListPhotosReturnsDescendingPagination(): void
    {
        $store = new JsonStore($this->photosFile);
        $store->saveAll([
            ['id' => 1, 'seq' => 1, 'path' => '/uploads/torpille/a.jpg'],
            ['id' => 2, 'seq' => 2, 'path' => '/uploads/torpille/b.jpg'],
            ['id' => 3, 'seq' => 3, 'path' => '/uploads/torpille/c.jpg'],
        ]);

        $page = $this->torpille->listPhotos(2, 2);
        self::assertSame(2, $page['page']);
        self::assertSame(2, $page['pages']);
        self::assertSame(3, $page['total']);
        self::assertSame([1], array_column($page['items'], 'id'));

        $latest = $this->torpille->latest();
        self::assertNotNull($latest);
        self::assertSame(3, $latest['id']);
    }

    private function createTempImage(): string
    {
        $path = sys_get_temp_dir() . '/torpille_image_' . uniqid('', true) . '.jpg';
        $img = imagecreatetruecolor(20, 20);
        $bg = imagecolorallocate($img, 200, 50, 50);
        imagefilledrectangle($img, 0, 0, 19, 19, $bg);
        imagejpeg($img, $path, 90);
        imagedestroy($img);
        return $path;
    }
}
