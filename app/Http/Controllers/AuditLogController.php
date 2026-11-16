<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;


class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::query()
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', Date::parse($v)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Date::parse($v)->endOfDay()))
            ->latest('id')
            ->simplePaginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $l) => [
                'id' => $l->id,
                'at' => $l->created_at->format('d/m/Y H:i:s'),
                'actor' => $l->actor_name ?? 'Sistem / tamu',
                'action' => $l->action,
                'entity' => $l->entity_type ? "{$l->entity_type} #{$l->entity_id}" : null,
                'result' => $l->result,
                'reason' => $l->reason,
                'metadata' => $l->metadata,
                'ip' => $l->ip_address,
            ]);

        return Inertia::render('audit-logs/index', [
            'logs' => $logs,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'filters' => [
                'action' => $filters['action'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
        ]);
    }
}
