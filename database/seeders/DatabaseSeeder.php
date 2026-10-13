<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Datos de demostración. Credenciales de prueba:
     *  - Administrador: usuario "admin", contraseña "admin123"
     *  - Estudiantes: correo o registro (2026001 ... 2026010), contraseña "password"
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Administrador',
            'username' => 'admin',
            'email' => 'admin@asistencia.test',
            'password' => 'admin123',
        ]);

        $module = Module::create([
            'name' => 'Módulo 2 · Desarrollo Web',
            'code' => 'MOD-2',
            'is_active' => true,
        ]);

        $names = [
            'Ana Gutiérrez', 'Bruno Mamani', 'Carla Rojas', 'Diego Fernández', 'Elena Quispe',
            'Fernando Vargas', 'Gabriela Choque', 'Hugo Salazar', 'Isabel Méndez', 'Jorge Céspedes',
        ];

        $students = collect($names)->map(fn (string $name, int $i) => User::factory()->create([
            'name' => $name,
            'email' => 'estudiante'.($i + 1).'@asistencia.test',
            'registration_number' => (string) (2026001 + $i),
        ]));

        $module->students()->attach($students->pluck('id'));

        // Clases martes y jueves, desde 4 semanas atrás hasta 3 semanas adelante, más la fecha de hoy.
        $dates = collect();
        for ($d = today()->subWeeks(4); $d->lte(today()->addWeeks(3)); $d = $d->copy()->addDay()) {
            if ($d->isTuesday() || $d->isThursday() || $d->isToday()) {
                $dates->push($d->copy());
            }
        }

        $sessions = $dates->values()->map(fn (Carbon $date, int $i) => ClassSession::create([
            'module_id' => $module->id,
            'date' => $date,
            'topic' => 'Clase '.($i + 1),
        ]));

        // Asistencias de fechas pasadas (~80% presentes; el resto queda sin marca = Falta).
        foreach ($sessions->filter(fn (ClassSession $s) => $s->hasEnded()) as $session) {
            foreach ($students as $student) {
                if (fake()->boolean(80)) {
                    $markedAt = $session->date->copy()->setTime(8, 0)->addMinutes(fake()->numberBetween(0, 25))->addSeconds(fake()->numberBetween(0, 59));

                    $attendance = Attendance::create([
                        'class_session_id' => $session->id,
                        'user_id' => $student->id,
                        'status' => Attendance::PRESENT,
                        'marked_at' => $markedAt,
                    ]);

                    ActivityLog::forceCreate([
                        'user_id' => $student->id,
                        'module_id' => $module->id,
                        'action' => ActivityLog::ATTENDANCE_MARKED,
                        'description' => 'Marcó asistencia de la clase del '.$session->date->format('d/m/Y'),
                        'student_id' => $student->id,
                        'class_session_id' => $session->id,
                        'attendance_id' => $attendance->id,
                        'new_values' => ['status' => Attendance::PRESENT, 'marked_at' => $markedAt->toDateTimeString()],
                        'ip_address' => '127.0.0.1',
                        'created_at' => $markedAt,
                    ]);
                }
            }
        }
    }
}
