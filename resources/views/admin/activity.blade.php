<x-layouts.app title="Historial de actividades">
    @if (! $module)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900">No hay un módulo activo.</div>
    @else
        <div class="mb-6">
            <p class="text-sm text-slate-500">Historial de actividades · {{ $module->name }}</p>
            <h1 class="text-2xl font-semibold text-slate-900">Registro de actividades</h1>
        </div>

        <form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_auto]">
            <div>
                <label for="student" class="mb-1 block text-xs font-medium text-slate-600">Estudiante</label>
                <select id="student" name="student" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($students as $st)
                        <option value="{{ $st->id }}" @selected(($filters['student'] ?? null) == $st->id)>{{ $st->registration_number }} · {{ $st->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="date" class="mb-1 block text-xs font-medium text-slate-600">Fecha</label>
                <input id="date" name="date" type="date" value="{{ $filters['date'] ?? '' }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="action" class="mb-1 block text-xs font-medium text-slate-600">Acción</label>
                <select id="action" name="action" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    @foreach (\App\Models\ActivityLog::LABELS as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['action'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrar</button>
                <a href="{{ route('admin.activity.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Limpiar</a>
            </div>
        </form>

        <section class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Hora</th>
                        <th class="px-4 py-3">Usuario</th>
                        <th class="px-4 py-3">Acción</th>
                        <th class="px-4 py-3">Detalle</th>
                        <th class="px-4 py-3">Registro afectado</th>
                        <th class="px-4 py-3">Anterior → Nuevo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        <tr class="align-top">
                            <td class="px-4 py-2 whitespace-nowrap tabular-nums">{{ $log->created_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 whitespace-nowrap tabular-nums">{{ $log->created_at->format('H:i:s') }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                @if ($log->user)
                                    <div class="font-medium text-slate-900">{{ $log->user->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $log->user->isAdmin() ? 'Administrador' : $log->user->registration_number }}</div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-emerald-100 text-emerald-800' => $log->action === \App\Models\ActivityLog::ATTENDANCE_MARKED,
                                    'bg-amber-100 text-amber-800' => $log->action === \App\Models\ActivityLog::ATTENDANCE_UPDATED,
                                    'bg-red-100 text-red-800' => $log->action === \App\Models\ActivityLog::LOGIN_FAILED,
                                    'bg-slate-100 text-slate-700' => in_array($log->action, [\App\Models\ActivityLog::LOGIN, \App\Models\ActivityLog::LOGOUT, \App\Models\ActivityLog::PASSWORD_CHANGED]),
                                ])>{{ $log->actionLabel() }}</span>
                            </td>
                            <td class="min-w-64 px-4 py-2 text-slate-600">{{ $log->description }}</td>
                            <td class="px-4 py-2 text-xs whitespace-nowrap text-slate-600">
                                @if ($log->student)
                                    <div>{{ $log->student->registration_number }} · {{ $log->student->name }}</div>
                                @endif
                                @if ($log->classSession)
                                    <div class="text-slate-500">Clase del {{ $log->classSession->date->format('d/m/Y') }}</div>
                                @endif
                                @if (! $log->student && ! $log->classSession) — @endif
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                @if ($log->old_values || $log->new_values)
                                    @if (isset($log->old_values['status']))
                                        <x-status-badge :status="$log->old_values['status']" /> →
                                    @endif
                                    @if (isset($log->new_values['status']))
                                        <x-status-badge :status="$log->new_values['status']" />
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No hay actividades registradas con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <div class="mt-4">{{ $logs->links() }}</div>
    @endif
</x-layouts.app>
