<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'quantity', 'unit', 'note'])]
class LabFacility extends Model
{
    protected $attributes = ['quantity' => 1];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }
}
