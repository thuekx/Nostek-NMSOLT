<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_uses_the_standalone_entry_redirect(): void
    {
        $this->get('/')->assertRedirect(route('dashboard'));
    }
}

