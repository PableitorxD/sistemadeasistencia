<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Module::create(['name' => 'Módulo 2', 'is_active' => true]);
    }

    public function test_page_requires_login(): void
    {
        $this->get('/cuenta/contrasena')->assertRedirect('/login');
    }

    public function test_student_and_admin_can_change_their_password(): void
    {
        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get('/cuenta/contrasena')->assertOk()->assertSee('Cambiar contraseña');

            $this->actingAs($user)->put('/cuenta/contrasena', [
                'current_password' => 'password',
                'password' => 'NuevaClave2026',
                'password_confirmation' => 'NuevaClave2026',
            ])->assertRedirect('/cuenta/contrasena')->assertSessionHas('success');

            $this->assertTrue(Hash::check('NuevaClave2026', $user->fresh()->password));
            $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => ActivityLog::PASSWORD_CHANGED]);
        }
    }

    public function test_student_can_login_with_new_password_only(): void
    {
        $student = User::factory()->create(['registration_number' => '2026001']);

        $this->actingAs($student)->put('/cuenta/contrasena', [
            'current_password' => 'password',
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ]);
        $this->post('/logout');

        $this->post('/login', ['login' => '2026001', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->post('/login', ['login' => '2026001', 'password' => 'NuevaClave2026'])->assertRedirect(route('home'));
    }

    public function test_validation_rules(): void
    {
        $user = User::factory()->create();

        $cases = [
            'current_password' => ['current_password' => 'incorrecta', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026'],
            'password' => ['current_password' => 'password', 'password' => 'NuevaClave2026', 'password_confirmation' => 'OtraClave2026'],
        ];

        foreach ($cases as $field => $payload) {
            $this->actingAs($user)->put('/cuenta/contrasena', $payload)->assertSessionHasErrors($field);
        }

        // Muy corta, sin números, o igual a la actual
        foreach (['abc12', 'solamenteletras', 'password'] as $bad) {
            $this->actingAs($user)->put('/cuenta/contrasena', [
                'current_password' => 'password',
                'password' => $bad,
                'password_confirmation' => $bad,
            ])->assertSessionHasErrors('password');
        }

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
