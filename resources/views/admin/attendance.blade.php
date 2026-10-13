<x-layouts.app title="Asistencia del módulo">
    @if (! $module)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900">No hay un módulo activo.</div>
    @else
        <div class="mb-6">
            <p class="text-sm text-slate-500">Módulo activo</p>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $module->name }}</h1>
        </div>

        {{-- Filtros --}}
        <form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_auto]">
            <div>
                <label for="q" class="mb-1 block text-xs font-medium text-slate-600">Buscar por nombre o número de registro</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Ej. Ana o 2024001"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="session" class="mb-1 block text-xs font-medium text-slate-600">Fecha de clase</label>
                <select id="session" name="session" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas las fechas</option>
                    @foreach ($allSessions as $s)
                        <option value="{{ $s->id }}" @selected(($filters['session'] ?? null) == $s->id)>{{ $s->date->format('d/m/Y') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="student" class="mb-1 block text-xs font-medium text-slate-600">Estudiante</label>
                <select id="student" name="student" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($allStudents as $st)
                        <option value="{{ $st->id }}" @selected(($filters['student'] ?? null) == $st->id)>{{ $st->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrar</button>
                <a href="{{ route('admin.attendance.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Limpiar</a>
            </div>
        </form>

        {{-- Totales --}}
        <section class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-stat label="Estudiantes" :value="$rows->count()" />
            <x-stat label="Fechas" :value="$sessions->count()" />
            <x-stat label="Presentes" :value="$totals['present']" tone="emerald" />
            <x-stat label="Ausentes" :value="$totals['absent']" tone="red" />
        </section>

        <p class="mb-2 text-xs text-slate-500">Haz clic en un estado para cambiarlo. Cada cambio queda registrado en el historial.</p>

        {{-- Lista estudiantes x fechas --}}
        <section class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="sticky left-0 z-10 bg-slate-50 px-4 py-3">Registro</th>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Correo</th>
                        @foreach ($sessions as $s)
                            <th @class(['px-3 py-3 text-center whitespace-nowrap', 'text-indigo-700' => $s->isToday()])>
                                {{ $s->date->format('d/m') }}
                            </th>
                        @endforeach
                        <th class="px-3 py-3 text-center text-emerald-700">P</th>
                        <th class="px-3 py-3 text-center text-red-700">F</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-slate-50/60">
                            <td class="sticky left-0 bg-white px-4 py-2 font-mono text-xs text-slate-700">{{ $row['student']->registration_number }}</td>
                            <td class="px-4 py-2 font-medium whitespace-nowrap text-slate-900">{{ $row['student']->name }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $row['student']->email }}</td>
                            @foreach ($sessions as $s)
                                @php $cell = $row['cells'][$s->id]; @endphp
                                <td class="px-3 py-2 text-center">
                                    <details class="inline-block text-left">
                                        <summary class="cursor-pointer list-none" title="{{ $cell['attendance']?->marked_at ? 'Marcó a las '.$cell['attendance']->marked_at->format('H:i:s') : '' }}{{ $cell['attendance']?->updated_by ? ' · editado por administración' : '' }}">
                                            <x-status-badge :status="$cell['status']" />
                                            @if ($cell['status'] === \App\Models\Attendance::PRESENT && $cell['attendance']?->marked_at)
                                                <div class="mt-0.5 text-[10px] tabular-nums text-slate-400">{{ $cell['attendance']->marked_at->format('H:i') }}</div>
                                            @endif
                                            @if ($cell['attendance']?->updated_by)
                                                <div class="text-[10px] text-amber-600">editado</div>
                                            @endif
                                        </summary>
                                        <div class="mt-1 w-32 rounded-lg border border-slate-200 bg-white p-1.5 text-left shadow-sm">
                                            <p class="mb-1 text-[11px] text-slate-500">{{ $s->date->format('d/m/Y') }}</p>
                                            @foreach ([\App\Models\Attendance::PRESENT, \App\Models\Attendance::ABSENT] as $newStatus)
                                                <form method="POST" action="{{ route('admin.attendance.update', [$s, $row['student']]) }}"
                                                      data-confirm="¿Cambiar a «{{ \App\Models\Attendance::label($newStatus) }}» la asistencia de {{ $row['student']->name }} del {{ $s->date->format('d/m/Y') }}?">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="status" value="{{ $newStatus }}">
                                                    <button type="submit" @disabled($cell['status'] === $newStatus)
                                                            class="w-full rounded px-2 py-1 text-left text-xs hover:bg-slate-100 disabled:cursor-default disabled:opacity-40">
                                                        Marcar {{ \App\Models\Attendance::label($newStatus) }}
                                                    </button>
                                                </form>
                                            @endforeach
                                        </div>
                                    </details>
                                </td>
                            @endforeach
                            <td class="px-3 py-2 text-center font-semibold tabular-nums text-emerald-700">{{ $row['present'] }}</td>
                            <td class="px-3 py-2 text-center font-semibold tabular-nums text-red-700">{{ $row['absent'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 5 + $sessions->count() }}" class="px-4 py-8 text-center text-slate-500">No se encontraron estudiantes.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50 text-xs">
                        <tr>
                            <td class="sticky left-0 bg-slate-50 px-4 py-2 font-medium text-slate-600" colspan="3">Totales por fecha (P / F)</td>
                            @foreach ($sessions as $s)
                                <td class="px-3 py-2 text-center tabular-nums whitespace-nowrap">
                                    <span class="text-emerald-700">{{ $sessionTotals[$s->id]['present'] }}</span>
                                    /
                                    <span class="text-red-700">{{ $sessionTotals[$s->id]['absent'] }}</span>
                                </td>
                            @endforeach
                            <td class="px-3 py-2 text-center font-semibold text-emerald-700">{{ $totals['present'] }}</td>
                            <td class="px-3 py-2 text-center font-semibold text-red-700">{{ $totals['absent'] }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </section>
    @endif
</x-layouts.app>
