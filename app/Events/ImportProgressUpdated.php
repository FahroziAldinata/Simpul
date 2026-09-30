<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{
     *     batch_id: string,
     *     step: string,
     *     processed: int,
     *     total: int,
     *     valid: int,
     *     peringatan: int,
     *     gagal: int,
     *     percentage: float,
     *     message?: string|null
     * }  $progress
     */
    public function __construct(
        public string $sekolahId,
        public string $batchId,
        public array $progress
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("sekolah.{$this->sekolahId}.impor.{$this->batchId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ImportProgressUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->progress;
    }
}
