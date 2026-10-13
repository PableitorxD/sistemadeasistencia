<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Module;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    /**
     * Historial de acciones del módulo activo, filtrable por estudiante,
     * fecha y tipo de acción.
     */
    public function index(Request $request): View
    {
        $module = Module::active();

        if (! $module) {
            return view('admin.activity', ['module' => null]);
        }

        $filters = $request->validate([
            'student' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
            'action' => ['nullable', 'string', 'in:'.implode(',', array_keys(ActivityLog::LABELS))],
        ]);

        $logs = ActivityLog::query()
            ->with(['user', 'student', 'classSession'])
            ->where('module_id', $module->id)
            ->when($filters['student'] ?? null, fn (Builder $q, $id) => $q->where(
                fn (Builder $q) => $q->where('student_id', $id)->orWhere('user_id', $id),
            ))
            ->when($filters['date'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', $date))
            ->when($filters['action'] ?? null, fn (Builder $q, $action) => $q->where('action', $action))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.activity', [
            'module' => $module,
            'filters' => $filters,
            'logs' => $logs,
            'students' => $module->students()->orderBy('name')->get(),
        ]);
    }
}
