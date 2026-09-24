<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalCiParityContractTest extends TestCase
{
    public function test_windows_local_ci_runner_contains_every_booking_github_ci_gate(): void
    {
        $workflow = file_get_contents(base_path('../.github/workflows/booking-ci.yml'));
        $runner = file_get_contents(base_path('scripts/local-ci.ps1'));

        $this->assertNotFalse($workflow);
        $this->assertNotFalse($runner);

        foreach ([
            'composer validate --strict',
            'composer install --no-interaction --prefer-dist --no-progress',
            'php artisan test --fail-on-warning --exclude-group=mysql',
            'php artisan route:cache',
            'php artisan view:cache',
            'php artisan route:clear',
            'php artisan view:clear',
            'php artisan test --fail-on-warning',
            'php artisan test --fail-on-warning tests/MySql/MySqlLockingContractTest.php',
        ] as $command) {
            $this->assertStringContainsString($command, $workflow);
            $this->assertStringContainsString($command, $runner);
        }

        foreach ([
            'VERSION',
            'composer.lock',
            'docs/MASTER_BLUEPRINT.md',
            'MASTER BLUEPRINT v1.0 — BOOKING SYSTEM SCOPE FREEZE',
            'Booking System v1.0 is scope-frozen',
        ] as $scopeContract) {
            $this->assertStringContainsString($scopeContract, $workflow);
            $this->assertStringContainsString($scopeContract, $runner);
        }

        foreach ([
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
        ] as $databaseVariable) {
            $this->assertStringContainsString($databaseVariable, $workflow);
            $this->assertStringContainsString($databaseVariable, $runner);
        }

        $this->assertStringContainsString('hotel_vastu_booking_ci', $runner);
        $this->assertStringContainsString('scripts/create-ci-database.php', $runner);
    }
}
