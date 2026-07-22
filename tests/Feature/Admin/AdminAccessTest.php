<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_view_admin_dashboard(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
        ]);

        $this->actingAs($student)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_student_cannot_authenticate_through_admin_login(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'username' => 'student_user',
        ]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'username' => $student->username,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors([
            'username' => 'These credentials do not have moderator access.',
        ]);
    }

    public function test_admin_can_authenticate_through_admin_login(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'username' => 'admin_user',
        ]);

        $response = $this->post(route('admin.login.store'), [
            'username' => $admin->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }
}
