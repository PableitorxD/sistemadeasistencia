<x-layouts.app title="Mi asistencia">
    @if (! $enrolled)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900">
            @if (! $module)
                No hay un módulo activo en este momento.
            @else
                No estás inscrito en el módulo activo <strong>{{ $module->name }}</strong>. Consulta con el administrador.
            @endif
        </div>
    @else
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">Módulo activo</p>
                <h1 class="text-2xl font-semibold text-slate-900">{{ $module->name }}</h1>
            </div>
            <p class="text-sm text-slate-500">Hoy: {{ ucfirst(today()->translatedFormat('l d \d\e F \d\e Y')) }}</p>
        </div>

        {{-- Marca del día --}}
        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-slate-900">Asistencia de hoy</h2>
                    <p class="text-sm text-slate-500">
                        @if ($todayAttendance)
                            @if ($todayAttendance->status === \App\Models\Attendance::PRESENT && $todayAttendance->marked_at)
                                Registraste tu asistencia a las <strong>{{ $todayAttendance->marked_at->format('H:i:s') }}</strong>.
                            @else
                                Tu asistencia de hoy ya tiene un registro: <strong>{{ \App\Models\Attendance::label($todayAttendance->status) }}</strong>.
                            @endif
                        @elseif ($todaySession)
                            Hay clase hoy{{ $todaySession->topic ? ': '.$todaySession->topic : '' }}. Marca tu asistencia.
                        @else
                            Hoy no hay clase programada. Solo puedes marcar asistencia en una fecha de clase.
                        @endif
                    </p>
                </div>

                <form method="POST" action="{{ route('student.attendance.mark') }}">
                    @csrf
                    @php $canMark = $todaySession && ! $todayAttendance; @endphp
                    <button type="submit" @disabled(! $canMark)
                        class="rounded-xl px-6 py-3 font-semibold text-white shadow-sm transition enabled:bg-emerald-600 enabled:hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                        {{ $todayAttendance ? 'Asistencia registrada' : 'Marcar asistencia' }}
                    </button>
                </form>
            </div>
        </section>

        {{-- Resumen --}}
        <section class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-stat label="Clases del módulo" :value="$summary['total']" />
            <x-stat label="Asistencias" :value="$summary['present']" tone="emerald" />
            <x-stat label="Faltas" :value="$summary['absent']" tone="red" />
            @php $held = $summary['present'] + $summary['absent']; @endphp
            <x-stat label="% de asistencia" :value="$held ? round($summary['present'] * 100 / $held).'%' : '—'" tone="indigo" />
        </section>

        {{-- Fechas del módulo --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Tema</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Hora de marca</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $i => $row)
                        <tr @class(['bg-indigo-50/50' => $row['session']->isToday()])>
                            <td class="px-4 py-3 text-slate-400 tabular-nums">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                {{ ucfirst($row['session']->date->translatedFormat('D d/m/Y')) }}
                                @if ($row['session']->isToday()) <span class="ml-1 text-xs font-semibold text-indigo-600">HOY</span> @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $row['session']->topic ?? '—' }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$row['status']" /></td>
                            <td class="px-4 py-3 tabular-nums text-slate-600">
                                @if ($row['status'] === \App\Models\Attendance::PRESENT)
                                    {{ $row['attendance']?->marked_at?->format('H:i:s') ?? 'Registrada por administración' }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
</x-layouts.app>
