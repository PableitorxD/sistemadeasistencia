<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Module;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * Lista completa del módulo activo: estudiantes x fechas de clase,
     * con búsqueda, filtros por fecha / estudiante y totales.
     */
    public function index(Request $request): View
    {
        $module = Module::active();

        if (! $module) {
            return view('admin.attendance', ['module' => null]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'session' => ['nullable', 'integer'],
            'student' => ['nullable', 'integer'],
        ]);

        $allSessions = $module->classSessions()->get();
        $allStudents = $module->students()->orderBy('name')->get();

        $sessions = $allSessions->when(
            $filters['session'] ?? null,
            fn ($c, $id) => $c->where('id', (int) $id)->values(),
        );

        $students = $module->students()
            ->search($filters['q'] ?? null)
            ->when($filters['student'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->orderBy('name')
            ->get();

        $attendances = Attendance::whereIn('class_session_id', $sessions->pluck('id'))
            ->whereIn('user_id', $students->pluck('id'))
            ->get()
            ->keyBy(fn ($a) => $a->class_session_id.'-'.$a->user_id);

        $sessionTotals = $sessions->mapWithKeys(fn ($s) => [$s->id => ['present' => 0, 'absent' => 0]])->all();

        $rows = $students->map(function (User $student) use ($sessions, $attendances, &$sessionTotals) {
            $cells = [];
            $present = $absent = 0;

            foreach ($sessions as $session) {
                $attendance = $attendances->get($session->id.'-'.$student->id);
                $status = Attendance::statusFor($session, $attendance);

                if ($status === Attendance::PRESENT) {
                    $present++;
                    $sessionTotals[$session->id]['present']++;
                } elseif ($status === Attendance::ABSENT) {
                    $absent++;
                    $sessionTotals[$session->id]['absent']++;
                }

                $cells[$session->id] = ['status' => $status, 'attendance' => $attendance];
            }

            return compact('student', 'cells', 'present', 'absent');
        });

        return view('admin.attendance', [
            'module' => $module,
            'filters' => $filters,
            'allSessions' => $allSessions,
            'allStudents' => $allStudents,
            'sessions' => $sessions,
            'rows' => $rows,
            'sessionTotals' => $sessionTotals,
            'totals' => [
                'present' => $rows->sum('present'),
                'absent' => $rows->sum('absent'),
            ],
        ]);
    }

    /**
     * Cambia el estado Presente/Falta de un estudiante en cualquier fecha
     * programada (también pasadas) y deja constancia en el historial.
     */
    public function update(Request $request, ClassSession $classSession, User $student): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([Attendance::PRESENT, Attendance::ABSENT])],
        ]);

        $module = Module::active();

        abort_unless($module && $classSession->module_id === $module->id, 404, 'La fecha no pertenece al módulo activo.');
        abort_unless($module->students()->whereKey($student->id)->exists(), 404, 'El estudiante no está inscrito en el módulo activo.');

        $attendance = Attendance::where('class_session_id', $classSession->id)
            ->where('user_id', $student->id)
            ->first();

        $oldStatus = Attendance::statusFor($classSession, $attendance);

        if ($oldStatus === $data['status']) {
            return back()->with('info', 'El estado no cambió.');
        }

        DB::transaction(function () use ($attendance, $classSession, $student, $data, $oldStatus, $request) {
            $attendance ??= new Attendance([
                'class_session_id' => $classSession->id,
                'user_id' => $student->id,
            ]);

            $attendance->status = $data['status'];
            $attendance->updated_by = $request->user()->id;
            $attendance->save();

            ActivityLog::record(
                ActivityLog::ATTENDANCE_UPDATED,
                sprintf(
                    'Cambió la asistencia de %s del %s: %s → %s',
                    $student->name,
                    $classSession->date->format('d/m/Y'),
                    Attendance::label($oldStatus),
                    Attendance::label($data['status']),
                ),
                [
                    'student_id' => $student->id,
                    'class_session_id' => $classSession->id,
                    'attendance_id' => $attendance->id,
                    'old_values' => ['status' => $oldStatus],
                    'new_values' => ['status' => $data['status']],
                ],
            );
        });

        return back()->with('success', "Asistencia de {$student->name} del {$classSession->date->format('d/m/Y')} actualizada a «".Attendance::label($data['status']).'».');
    }
}
