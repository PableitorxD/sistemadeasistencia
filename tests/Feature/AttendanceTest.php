<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Module $module;

    private User $student;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(9, 15));

        $this->admin = User::factory()->admin()->create(['username' => 'admin', 'password' => 'admin123']);
        $this->student = User::factory()->create([
            'email' => 'ana@asistencia.test',
            'registration_number' => '2026001',
        ]);

        $this->module = Module::create(['name' => 'Módulo 2', 'is_active' => true]);
        $this->module->students()->attach($this->student);
    }

    private function classOn(string $date): ClassSession
    {
        return ClassSession::create(['module_id' => $this->module->id, 'date' => $date]);
    }

    // --- Login ------------------------------------------------------------

    public function test_student_can_login_with_email_or_registration_number(): void
    {
        $this->post('/login', ['login' => 'ana@asistencia.test', 'password' => 'password'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($this->student);

        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', ['login' => '2026001', 'password' => 'password'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($this->student);

        $this->assertDatabaseHas('activity_logs', ['user_id' => $this->student->id, 'action' => ActivityLog::LOGIN]);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $this->student->id, 'action' => ActivityLog::LOGOUT]);
    }

    public function test_admin_cannot_use_student_login_and_vice_versa(): void
    {
        $this->post('/login', ['login' => $this->admin->email, 'password' => 'admin123'])->assertSessionHasErrors('login');
        $this->post('/admin/login', ['username' => '2026001', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->post('/admin/login', ['username' => 'admin', 'password' => 'admin123'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_wrong_portal_shows_hint_only_with_correct_password(): void
    {
        $this->post('/login', ['login' => 'admin', 'password' => 'admin123'])
            ->assertSessionHasErrors(['login' => 'Esta es una cuenta de administrador. Ingresa desde «Ingreso de administrador» (/admin/login).']);

        $this->post('/login', ['login' => 'admin', 'password' => 'incorrecta'])
            ->assertSessionHasErrors(['login' => 'Las credenciales no son válidas.']);

        $this->assertGuest();
    }

    public function test_roles_are_enforced(): void
    {
        $this->actingAs($this->student)->get('/admin/asistencia')->assertForbidden();
        $this->actingAs($this->student)->get('/admin/actividad')->assertForbidden();
        $this->actingAs($this->admin)->post('/mi-asistencia/marcar')->assertForbidden();
    }

    // --- Marca del estudiante --------------------------------------------

    public function test_student_marks_attendance_on_class_day_with_real_time(): void
    {
        $today = $this->classOn('2026-10-13');

        $this->actingAs($this->student)->post('/mi-asistencia/marcar')->assertSessionHas('success');

        $attendance = Attendance::first();
        $this->assertSame($today->id, $attendance->class_session_id);
        $this->assertSame(Attendance::PRESENT, $attendance->status);
        $this->assertSame('2026-10-13 09:15:00', $attendance->marked_at->toDateTimeString());

        $this->assertDatabaseHas('activity_logs', [
            'action' => ActivityLog::ATTENDANCE_MARKED,
            'user_id' => $this->student->id,
            'class_session_id' => $today->id,
        ]);
    }

    public function test_only_one_mark_per_student_and_date(): void
    {
        $this->classOn('2026-10-13');

        $this->actingAs($this->student)->post('/mi-asistencia/marcar');
        $this->actingAs($this->student)->post('/mi-asistencia/marcar')->assertSessionHas('error');

        $this->assertSame(1, Attendance::count());
    }

    public function test_student_cannot_mark_when_no_class_today(): void
    {
        $this->classOn('2026-10-12');
        $this->classOn('2026-10-15');

        $this->actingAs($this->student)->post('/mi-asistencia/marcar')->assertSessionHas('error');
        $this->assertSame(0, Attendance::count());

        $this->actingAs($this->student)->get('/mi-asistencia')
            ->assertOk()
            ->assertSee('Hoy no hay clase programada');
    }

    public function test_unmarked_past_date_shows_as_absence_with_summary(): void
    {
        $past = $this->classOn('2026-10-08');
        $marked = $this->classOn('2026-10-10');
        $this->classOn('2026-10-20');

        Attendance::create([
            'class_session_id' => $marked->id,
            'user_id' => $this->student->id,
            'status' => Attendance::PRESENT,
            'marked_at' => '2026-10-10 08:05:30',
        ]);

        $this->assertSame(Attendance::ABSENT, Attendance::statusFor($past, null));

        $response = $this->actingAs($this->student)->get('/mi-asistencia')->assertOk();
        $response->assertSee('08:05:30');
        $response->assertViewHas('summary', fn ($s) => $s['present'] === 1 && $s['absent'] === 1 && $s['remaining'] === 1);
    }

    // --- Administrador -----------------------------------------------------

    public function test_admin_can_correct_past_attendance_and_change_is_logged(): void
    {
        $past = $this->classOn('2026-10-08');

        $this->actingAs($this->admin)
            ->put("/admin/asistencia/{$past->id}/{$this->student->id}", ['status' => Attendance::PRESENT])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendances', [
            'class_session_id' => $past->id,
            'user_id' => $this->student->id,
            'status' => Attendance::PRESENT,
            'updated_by' => $this->admin->id,
        ]);

        $log = ActivityLog::where('action', ActivityLog::ATTENDANCE_UPDATED)->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame($this->student->id, $log->student_id);
        $this->assertSame(['status' => Attendance::ABSENT], $log->old_values);
        $this->assertSame(['status' => Attendance::PRESENT], $log->new_values);

        // Y de vuelta a Falta
        $this->actingAs($this->admin)
            ->put("/admin/asistencia/{$past->id}/{$this->student->id}", ['status' => Attendance::ABSENT]);
        $this->assertSame(Attendance::ABSENT, Attendance::first()->status);
        $this->assertSame(2, ActivityLog::where('action', ActivityLog::ATTENDANCE_UPDATED)->count());
    }

    public function test_admin_list_search_filters_and_totals(): void
    {
        $other = User::factory()->create(['name' => 'Bruno Mamani', 'registration_number' => '2026002']);
        $this->module->students()->attach($other);
        $this->student->update(['name' => 'Ana Gutiérrez']);

        $past = $this->classOn('2026-10-08');
        Attendance::create(['class_session_id' => $past->id, 'user_id' => $other->id, 'status' => Attendance::PRESENT, 'marked_at' => now()]);

        $this->actingAs($this->admin)->get('/admin/asistencia')
            ->assertOk()
            ->assertSee('Ana Gutiérrez')->assertSee('Bruno Mamani')
            ->assertViewHas('totals', ['present' => 1, 'absent' => 1]);

        $names = fn ($rows) => $rows->pluck('student.name')->all();

        $this->actingAs($this->admin)->get('/admin/asistencia?q=2026002')
            ->assertViewHas('rows', fn ($rows) => $names($rows) === ['Bruno Mamani']);

        $this->actingAs($this->admin)->get('/admin/asistencia?q=ana')
            ->assertViewHas('rows', fn ($rows) => $names($rows) === ['Ana Gutiérrez']);

        $this->actingAs($this->admin)->get("/admin/asistencia?session={$past->id}&student={$this->student->id}")
            ->assertViewHas('rows', fn ($rows) => $names($rows) === ['Ana Gutiérrez'])
            ->assertViewHas('totals', ['present' => 0, 'absent' => 1]);
    }

    public function test_activity_panel_filters_by_student(): void
    {
        $this->classOn('2026-10-13');
        $this->actingAs($this->student)->post('/mi-asistencia/marcar');

        $this->actingAs($this->admin)->get('/admin/actividad?student='.$this->student->id)
            ->assertOk()
            ->assertSee('Marca de asistencia')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);

        $this->actingAs($this->admin)->get('/admin/actividad?date=2026-10-01')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 0);
    }
}
