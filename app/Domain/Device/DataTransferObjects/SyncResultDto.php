<?php

declare(strict_types=1);

namespace App\Domain\Device\DataTransferObjects;

use DateTimeImmutable;

/**
 * Outcome of a sync operation.
 *
 * - `fetched`    : how many records came off the wire
 * - `ingested`   : how many were new and inserted
 * - `duplicates` : how many collided with the natural-key unique index
 * - `orphans`    : how many had a device_user_id with no employee mapping
 * - `newCursor`  : the largest deviceLogId we processed (advanced into devices.last_log_id)
 * - `errors`     : soft errors keyed by description; hard errors raise an exception instead
 */
final readonly class SyncResultDto
{
    /** @param array<string, mixed> $errors */
    public function __construct(
        public int $fetched,
        public int $ingested,
        public int $duplicates,
        public int $orphans,
        public ?int $newCursor,
        public array $errors,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $finishedAt,
    ) {}

    public function isClean(): bool
    {
        return $this->errors === [];
    }
}
