<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IdCheckPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_the_id_check_page(): void
    {
        $response = $this->get('/id-check');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('IdCheck/Index')
                ->where('defaults.name', '')
                ->where('defaults.handle', '')
                ->where('courses.0', 'BS Information Technology')
                ->where('campuses.0', 'Alangilan')
                ->where('caption', fn (string $caption) => str_contains($caption, '#BSUFreedomWall'))
            );
    }

    public function test_student_defaults_are_prefilled_on_the_id_check_page(): void
    {
        $user = User::factory()->create([
            'name' => 'Test Student',
            'username' => 'teststudent',
            'role' => 'student',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/id-check');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('IdCheck/Index')
                ->where('defaults.name', 'Test Student')
                ->where('defaults.handle', '@teststudent')
            );
    }

    public function test_shortcut_route_redirects_to_the_id_check_page(): void
    {
        $this->get('/id')->assertRedirect('/id-check');
    }
}
