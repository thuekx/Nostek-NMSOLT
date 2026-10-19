<?php

namespace Tests\Feature;

use Tests\TestCase;

class StandaloneEntryTest extends TestCase
{
    public function test_root_redirects_to_authenticated_dashboard(): void
    {
        $this->get('/')->assertRedirect(route('dashboard'));
    }
}
