<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Draft = 'draft';
    case PendingAssistantReview = 'pending_assistant_review'; // jadwal, tahap 1
    case PendingFinalApproval = 'pending_final_approval';     // jadwal, tahap final
    case Pending = 'pending';                                 // booking
    case Approved = 'approved';
    case RevisionRequested = 'revision_requested';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Superseded = 'superseded';

    /**
     * Status yang menahan slot (BR-TS-03). Pengajuan pending tetap memblokir.
     */
    public function blocksSlots(): bool
    {
        return in_array($this, [
            self::PendingAssistantReview,
            self::PendingFinalApproval,
            self::Pending,
            self::Approved,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingAssistantReview => 'Menunggu verifikasi Asisten Lab',
            self::PendingFinalApproval => 'Menunggu persetujuan Ketua Lab',
            self::Pending => 'Menunggu persetujuan',
            self::Approved => 'Disetujui',
            self::RevisionRequested => 'Perlu revisi',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
            self::Completed => 'Selesai',
            self::Superseded => 'Digantikan revisi',
        };
    }
}