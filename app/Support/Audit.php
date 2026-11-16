<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Audit
{
    /**
     * Catat satu kejadian. Jangan pernah memasukkan password atau secret ke $metadata.
     */
    public static function record(
        string $action,
        ?Model $entity = null,
        array $metadata = [],
        string $result = 'success',
        ?string $reason = null,
        ?User $actor = null,
    ): void {
        $actor ??= Auth::user();

        AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'result' => $result,
            'reason' => $reason,
            'metadata' => $metadata ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}