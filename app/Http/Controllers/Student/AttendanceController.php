<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Module;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * Fechas del módulo activo con el estado del estudiante y su resumen.
     */
    public function index(Request $request): View
    {
        $student = $request->user();
        $module = Module::active();

        $enrolled = $module && $module->students()->whereKey($student->id)->exists();

        if (! $enrolled) {
            return view('student.attendance', ['module' => $module, 'enrolled' => false]);
        }

        $sessions = $module->classSessions()->get();
        $attendances = $student->attendances()
            ->whereIn('class_session_id', $sessions->pluck('id'))
            ->get()
            ->keyBy('class_session_id');

        $rows = $sessions->map(fn ($session) => [
            'session' => $session,
            'attendance' => $attendances->get($session->id),
            'status' => Attendance::statusFor($session, $attendances->get($session->id)),
        ]);

        $todaySession = $sessions->first(fn ($session) => $session->isToday());

        return view('student.attendance', [
            'module' => $module,
            'enrolled' => true,
            'rows' => $rows,
            'todaySession' => $todaySession,
            'todayAttendance' => $todaySession ? $attendances->get($todaySession->id) : null,
            'summary' => [
                'total' => $sessions->count(),
                'present' => $rows->where('status', Attendance::PRESENT)->count(),
                'absent' => $rows->where('status', Attendance::ABSENT)->count(),
                'remaining' => $rows->whereIn('status', [Attendance::PENDING, Attendance::SCHEDULED])->count(),
            ],
        ]);
    }

    /**
     * Marca la asistencia de hoy. La fecha de clase se determina en el
     * servidor; el estudiante no puede elegir otra fecha.
     */
    public function mark(Request $request): RedirectResponse
    {
        $student = $request->user();
        $module = Module::active();

        if (! $module || ! $module->students()->whereKey($student->id)->exists()) {
            return back()->with('error', 'No estás inscrito en el módulo activo.');
        }

        $session = $module->classSessions()->whereDate('date', today())->first();

        if (! $session) {
            return back()->with('error', 'Hoy no hay una clase programada.');
        }

        try {
            $attendance = DB::transaction(function () use ($session, $student) {
                $attendance = Attendance::create([
                    'class_session_id' => $session->id,
                    'user_id' => $student->id,
                    'status' => Attendance::PRESENT,
                    'marked_at' => now(),
                ]);

                ActivityLog::record(
                    ActivityLog::ATTENDANCE_MARKED,
                    'Marcó asistencia de la clase del '.$session->date->format('d/m/Y'),
                    [
                        'student_id' => $student->id,
                        'class_session_id' => $session->id,
                        'attendance_id' => $attendance->id,
                        'new_values' => ['status' => Attendance::PRESENT, 'marked_at' => $attendance->marked_at->toDateTimeString()],
                    ],
                );

                return $attendance;
            });
        } catch (UniqueConstraintViolationException) {
            return back()->with('error', 'Ya existe un registro de asistencia para la clase de hoy.');
        }

        return back()->with('success', 'Asistencia registrada a las '.$attendance->marked_at->format('H:i:s').'.');
    }
}
