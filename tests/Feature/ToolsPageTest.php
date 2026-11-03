<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ToolsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_the_tools_page(): void
    {
        $response = $this->get('/tools');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tools/Index'));
    }

    public function test_shortcut_route_redirects_to_the_tools_page(): void
    {
        $this->get('/apps')->assertRedirect('/tools');
    }
}
