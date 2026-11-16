<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sequence_no', 'date', 'start_time', 'end_time', 'module_path'])]
class ReservationSession extends Model
{
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(LabTimeBlock::class);
    }
}