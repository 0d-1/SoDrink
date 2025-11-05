<?php

declare(strict_types=1);

namespace Tests\Domain;

use PHPUnit\Framework\TestCase;
use SoDrink\Domain\Events;

class EventsTest extends TestCase
{
    private string $file;
    private Events $events;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'events_');
        if ($this->file === false) {
            self::fail('Unable to create temporary events file');
        }
        file_put_contents($this->file, json_encode([]));
        $this->events = new Events($this->file);
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testCreateInitialisesDefaults(): void
    {
        $created = $this->events->create([
            'date' => '2030-05-18',
            'theme' => 'BBQ',
        ]);

        self::assertSame('2030-05-18', $created['date']);
        self::assertSame('', $created['lieu']);
        self::assertSame('BBQ', $created['theme']);
        self::assertSame('', $created['description']);
        self::assertSame([], $created['participants']);
        self::assertArrayHasKey('created_at', $created);
        self::assertArrayHasKey('id', $created);

        $stored = $this->events->getById($created['id']);
        self::assertNotNull($stored);
        self::assertSame($created, $stored);
    }

    public function testNextUpcomingReturnsClosestFutureEvent(): void
    {
        $past = $this->events->create([
            'date' => (new \DateTimeImmutable('-2 days'))->format('Y-m-d'),
            'theme' => 'Ancien',
        ]);
        $future1 = $this->events->create([
            'date' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d'),
            'theme' => 'Prochain',
        ]);
        $future2 = $this->events->create([
            'date' => (new \DateTimeImmutable('+5 days'))->format('Y-m-d'),
            'theme' => 'Plus tard',
        ]);

        $next = $this->events->nextUpcoming();
        self::assertNotNull($next);
        self::assertSame($future1['id'], $next['id']);
        self::assertSame('Prochain', $next['theme']);

        $upcoming = $this->events->listUpcoming();
        $ids = array_column($upcoming, 'id');
        self::assertSame([$future1['id'], $future2['id']], $ids);
    }

    public function testParticipantsCanBeAddedAndRemovedOnce(): void
    {
        $event = $this->events->create([
            'date' => '2031-01-01',
            'theme' => 'Nouvel an',
        ]);

        self::assertTrue($this->events->addParticipant($event['id'], 10));
        self::assertTrue($this->events->addParticipant($event['id'], 11));
        self::assertTrue($this->events->addParticipant($event['id'], 10));

        $updated = $this->events->getById($event['id']);
        self::assertSame([10, 11], $updated['participants']);

        self::assertTrue($this->events->removeParticipant($event['id'], 10));
        $afterRemoval = $this->events->getById($event['id']);
        self::assertSame([11], $afterRemoval['participants']);
    }
}
