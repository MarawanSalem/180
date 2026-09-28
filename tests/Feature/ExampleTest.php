<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * This is an API-only application — the web root intentionally returns 404.
     */
    public function test_web_root_returns_404_for_api_only_app(): void
    {
        $response = $this->get('/');

        $response->assertStatus(404);
    }

    /**
     * The health check endpoint is always available.
     */
    public function test_health_check_returns_200(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }
}
