<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['npm', 'name', 'is_active'])]
class Student extends Model
{
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(CourseClass::class, 'class_student')->withTimestamps();
    }
}