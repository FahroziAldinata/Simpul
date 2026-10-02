<?php

namespace App\Services\Jadwal;

class ConflictResult
{
    /**
     * @param  array<int, ConflictItem>  $conflicts
     */
    public function __construct(
        public array $conflicts = [],
    ) {}

    public function hasConflict(): bool
    {
        return ! empty($this->conflicts);
    }

    public function firstMessage(): ?string
    {
        return $this->conflicts[0]->message ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function getMessages(): array
    {
        return array_map(fn (ConflictItem $c) => $c->message, $this->conflicts);
    }

    public function add(ConflictItem $item): self
    {
        $this->conflicts[] = $item;

        return $this;
    }
}
