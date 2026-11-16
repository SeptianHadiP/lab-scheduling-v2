<?php

namespace App\Exceptions;

use RuntimeException;

class ReservationConflictException extends RuntimeException
{
    /**
     * @param  list<array{sequence_no:int,date:string,start_time:string,end_time:string}>  $conflicts
     */
    public function __construct(public readonly array $conflicts)
    {
        parent::__construct('Jadwal bentrok pada '.count($conflicts).' pertemuan.');
    }
}