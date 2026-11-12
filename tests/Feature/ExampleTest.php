<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response()
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('property="og:title" content="Cổng góp ý Đoàn TNTT GX Mỹ Vân"', false)
            ->assertSee('property="og:description"', false)
            ->assertSee('property="og:image" content="'.rtrim(config('app.url'), '/').'/images/tntt-my-van.png"', false);
    }
}
