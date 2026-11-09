<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Read-only by design: audit entries have no edit or delete route.
 */
class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'module' => ['nullable', Rule::in(AuditLog::MODULES)],
            'user' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return view('prodi.audit-log.index', [
            'logs' => AuditLog::query()
                ->when($filters['module'] ?? null, fn ($query, $module) => $query->where('module', $module))
                ->when($filters['user'] ?? null, fn ($query, $user) => $query->where('user_name', 'like', "%{$user}%"))
                ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
                ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'modules' => array_values(AuditLog::MODULES),
            'filters' => $filters,
        ]);
    }
}
