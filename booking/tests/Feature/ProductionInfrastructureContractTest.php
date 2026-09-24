<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionInfrastructureContractTest extends TestCase
{
    public function test_docker_and_redis_production_contract_is_present(): void
    {
        $this->assertFileExists(base_path('Dockerfile'));
        $this->assertFileExists(base_path('docker-compose.yml'));

        $dockerfile = file_get_contents(base_path('Dockerfile'));
        $compose = file_get_contents(base_path('docker-compose.yml'));
        $env = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('pecl install redis', $dockerfile);
        $this->assertStringContainsString('php:8.3-apache', $dockerfile);
        $this->assertStringContainsString('redis:7.4-alpine', $compose);
        $this->assertStringContainsString('SESSION_DRIVER=redis', $env);
        $this->assertStringContainsString('CACHE_STORE=redis', $env);
        $this->assertStringContainsString('CACHE_LIMITER=redis', $env);

        $this->assertArrayHasKey('redis', config('cache.stores'));
        $this->assertArrayHasKey('default', config('database.redis'));
        $this->assertArrayHasKey('cache', config('database.redis'));
    }
}
