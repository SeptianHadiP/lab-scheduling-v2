<?php

namespace App\Enums;

enum Role: string
{
    case Dosen = 'dosen';
    case AsistenLab = 'asisten_lab';
    case KetuaLab = 'ketua_lab';

    public function label(): string
    {
        return match ($this) {
            self::Dosen => 'Dosen',
            self::AsistenLab => 'Asisten Lab',
            self::KetuaLab => 'Ketua Lab',
        };
    }
}