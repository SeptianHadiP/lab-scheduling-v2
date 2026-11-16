<?php

namespace App\Enums;

enum ReservationType: string
{
    case Schedule = 'schedule';
    case Booking = 'booking';

    public function label(): string
    {
        return match ($this) {
            self::Schedule => 'Jadwal Praktikum',
            self::Booking => 'Booking',
        };
    }
}