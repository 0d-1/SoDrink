<?php

declare(strict_types=1);

namespace Tests\Domain;

use PHPUnit\Framework\TestCase;
use SoDrink\Domain\Gallery;

class GalleryTest extends TestCase
{
    private string $file;
    private Gallery $gallery;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'gallery_');
        if ($this->file === false) {
            self::fail('Unable to create temporary gallery file');
        }
        file_put_contents($this->file, json_encode([]));
        $this->gallery = new Gallery($this->file);
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testAddInitialisesLikesAndComments(): void
    {
        $photo = $this->gallery->add([
            'path' => '/tmp/photo.jpg',
            'title' => 'Soirée',
        ]);

        self::assertSame('/tmp/photo.jpg', $photo['path']);
        self::assertSame('Soirée', $photo['title']);
        self::assertSame([], $photo['likes']);
        self::assertSame([], $photo['comments']);
        self::assertArrayHasKey('created_at', $photo);
        self::assertArrayHasKey('id', $photo);
    }

    public function testToggleLikeAddsAndRemovesUser(): void
    {
        $photo = $this->gallery->add([
            'path' => '/tmp/photo2.jpg',
        ]);

        [$okAdd, $isNowLiked] = $this->gallery->toggleLike($photo['id'], 5);
        self::assertTrue($okAdd);
        self::assertTrue($isNowLiked);
        self::assertSame([5], $this->gallery->getById($photo['id'])['likes']);

        [$okRemove, $isNowLiked] = $this->gallery->toggleLike($photo['id'], 5);
        self::assertTrue($okRemove);
        self::assertFalse($isNowLiked);
        self::assertSame([], $this->gallery->getById($photo['id'])['likes']);
    }

    public function testAddAndDeleteComment(): void
    {
        $photo = $this->gallery->add([
            'path' => '/tmp/photo3.jpg',
        ]);

        $first = $this->gallery->addComment($photo['id'], [
            'user_id' => 7,
            'text' => 'Super ambiance !',
        ]);
        self::assertNotNull($first);
        self::assertSame(1, $first['id']);
        self::assertSame(7, $first['user_id']);

        $second = $this->gallery->addComment($photo['id'], [
            'user_id' => 8,
            'text' => 'On remet ça ?',
        ]);
        self::assertSame(2, $second['id']);

        self::assertTrue($this->gallery->deleteComment($photo['id'], 1));
        $remaining = $this->gallery->getById($photo['id']);
        self::assertCount(1, $remaining['comments']);
        self::assertSame(2, $remaining['comments'][0]['id']);
    }
}
