<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['reservation_id', 'round', 'stage', 'decision', 'decided_by', 'note', 'is_override'])]
class ReservationApproval extends Model
{
    public const UPDATED_AT = null;

    protected $attributes = ['round' => 1, 'is_override' => false];

    protected function casts(): array
    {
        return ['is_override' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Riwayat persetujuan bersifat append-only.'));
        static::deleting(fn () => throw new LogicException('Riwayat persetujuan bersifat append-only.'));
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function stageLabel(): string
    {
        return match ($this->stage) {
            'assistant' => 'Verifikasi Asisten Lab',
            'final' => 'Persetujuan Ketua Lab',
            'booking' => 'Persetujuan Booking',
            default => $this->stage,
        };
    }

    public function decisionLabel(): string
    {
        return match ($this->decision) {
            'approved' => 'Disetujui',
            'revision_requested' => 'Diminta revisi',
            'rejected' => 'Ditolak',
            'skipped' => 'Dilewati',
            'auto' => 'Otomatis',
            default => $this->decision,
        };
    }
}