<?php

namespace App\Policies;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public const CANCELABLE = [
        ReservationStatus::Draft,
        ReservationStatus::RevisionRequested,
        ReservationStatus::PendingAssistantReview,
        ReservationStatus::PendingFinalApproval,
        ReservationStatus::Pending,
        ReservationStatus::Approved,
    ];

    /** Status yang masih boleh diubah dan dikirim (ulang) oleh pembuatnya. */
    public const EDITABLE = [
        ReservationStatus::Draft,
        ReservationStatus::RevisionRequested,
    ];

    public function view(User $user, Reservation $reservation): bool
    {
        if (! $user->is_active) {
            return false;
        }

        // Draft hanya milik pembuatnya.
        if ($reservation->status === ReservationStatus::Draft) {
            return $this->isCreator($user, $reservation);
        }

        if ($user->isKetuaLab()) {
            return true;
        }

        if ($user->isAsistenLab() && (int) $user->lab_id === (int) $reservation->lab_id) {
            return true;
        }

        $terkait = array_filter([
            $reservation->created_by,
            $reservation->booked_for_user_id,
            $reservation->grade_owner_id,
        ]);

        return in_array((int) $user->id, array_map('intval', $terkait), true);
    }

    public function update(User $user, Reservation $reservation): bool
    {
        return $user->is_active
            && $this->isCreator($user, $reservation)
            && in_array($reservation->status, self::EDITABLE, true);
    }

    public function send(User $user, Reservation $reservation): bool
    {
        return $this->update($user, $reservation);
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        if (! $user->is_active || ! in_array($reservation->status, self::CANCELABLE, true)) {
            return false;
        }

        if ($this->isCreator($user, $reservation)) {
            return true;
        }

        return $reservation->status !== ReservationStatus::Draft
            && $user->isKetuaLab()
            && (int) $user->lab_id === (int) $reservation->lab_id;
    }

    /**
     * Boleh memutuskan pengajuan pada tahap yang sedang berjalan.
     * Pembuat tidak dapat memutuskan pengajuannya sendiri.
     */
    public function decide(User $user, Reservation $reservation): bool
    {
        if (! $user->is_active || $this->isCreator($user, $reservation)) {
            return false;
        }

        $sameLab = (int) $user->lab_id === (int) $reservation->lab_id;

        return match ($reservation->status) {
            ReservationStatus::PendingAssistantReview => $user->isKetuaLab() || ($user->isAsistenLab() && $sameLab),
            ReservationStatus::PendingFinalApproval => $user->isKetuaLab(),
            ReservationStatus::Pending => $this->canDecideBooking($user, $reservation, $sameLab),
            default => false,
        };
    }

    /**
     * Keputusan di luar jalur normal (BR-APP-05): wajib disertai alasan dan ditandai override.
     */
    public static function requiresOverride(User $user, Reservation $reservation): bool
    {
        $sameLab = (int) $user->lab_id === (int) $reservation->lab_id;

        return match ($reservation->status) {
            ReservationStatus::PendingAssistantReview => ! ($user->isAsistenLab() && $sameLab),
            ReservationStatus::PendingFinalApproval => ! ($user->isKetuaLab() && $sameLab),
            ReservationStatus::Pending => $reservation->creator->isAsistenLab()
                ? ! ($user->isKetuaLab() && $sameLab)
                : ! (($user->isKetuaLab() || $user->isAsistenLab()) && $sameLab),
            default => false,
        };
    }

    /**
     * Booking Dosen: Asisten atau Ketua Lab. Booking Asisten Lab: hanya Ketua Lab (BR-BOOK-15 dan 16).
     */
    private function canDecideBooking(User $user, Reservation $reservation, bool $sameLab): bool
    {
        if ($user->isKetuaLab()) {
            return true;
        }

        return $user->isAsistenLab() && $sameLab && ! $reservation->creator->isAsistenLab();
    }

    private function isCreator(User $user, Reservation $reservation): bool
    {
        return (int) $reservation->created_by === (int) $user->id;
    }
}