<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['lab_id', 'reservation_session_id', 'block_start'])]
class LabTimeBlock extends Model
{
    public $timestamps = false;
}