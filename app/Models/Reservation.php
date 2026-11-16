<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Enums\ReservationType;
use App\Models\ReservationApproval;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'type', 'lab_id', 'booked_for_user_id', 'external_name', 'external_contact',
    'title', 'course_class_id', 'tools_software',
    'grading_enabled', 'certificate_enabled', 'certificate_title',
])]
class Reservation extends Model
{
    protected $attributes = [
        'status' => 'draft',
        'grading_enabled' => false,
        'certificate_enabled' => false,
        'version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'type' => ReservationType::class,
            'status' => ReservationStatus::class,
            'grading_enabled' => 'boolean',
            'certificate_enabled' => 'boolean',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bookedFor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_for_user_id');
    }

    public function gradeOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grade_owner_id');
    }

    public function courseClass(): BelongsTo
    {
        return $this->belongsTo(CourseClass::class);
    }

    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ReservationSession::class)->orderBy('sequence_no');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ReservationApproval::class)->orderBy('id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'reservation_participants')->withTimestamps();
    }

}