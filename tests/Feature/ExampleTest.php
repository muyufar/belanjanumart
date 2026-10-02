<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guests_must_login_before_opening_the_catalog(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/masuk');
    }
}
