param(
    [string]$MySqlHost = "127.0.0.1",
    [int]$MySqlPort = 3306,
    [string]$MySqlDatabase = "hotel_vastu_booking_ci",
    [string]$MySqlUsername = "root",
    [string]$MySqlPassword = ""
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$BookingRoot = Split-Path -Parent $PSScriptRoot
Set-Location $BookingRoot

function Invoke-ExternalStep {
    param(
        [string]$Name,
        [scriptblock]$Command
    )

    Write-Host ""
    Write-Host "==> $Name" -ForegroundColor Cyan
    & $Command
    if ($LASTEXITCODE -ne 0) {
        throw "$Name failed with exit code $LASTEXITCODE."
    }
    Write-Host "PASS: $Name" -ForegroundColor Green
}

function Assert-PhpEnvironment {
    $version = & php -r "echo PHP_VERSION;"
    if ($LASTEXITCODE -ne 0) {
        throw "PHP is not available on PATH."
    }

    if ([version]$version -lt [version]"8.3.0") {
        throw "PHP 8.3+ is required. Found PHP $version."
    }

    $modules = (& php -m) -join "`n"
    foreach ($extension in @("mbstring", "PDO", "pdo_sqlite", "pdo_mysql")) {
        if ($modules -notmatch "(?im)^$([regex]::Escape($extension))$") {
            throw "Required PHP extension '$extension' is not enabled."
        }
    }

    Write-Host "PASS: PHP $version with required extensions" -ForegroundColor Green
}

function Assert-ScopeFreeze {
    if (-not (Test-Path "VERSION")) {
        throw "booking/VERSION is missing."
    }

    $version = (Get-Content "VERSION" -Raw).Trim()
    if ($version -ne "1.0") {
        throw "Expected booking/VERSION to be 1.0, found '$version'."
    }

    if (-not (Test-Path "composer.lock")) {
        throw "booking/composer.lock is missing."
    }

    if (-not (Test-Path "docs/MASTER_BLUEPRINT.md")) {
        throw "docs/MASTER_BLUEPRINT.md is missing."
    }

    $blueprint = Get-Content "docs/MASTER_BLUEPRINT.md" -Raw
    if (-not $blueprint.Contains("MASTER BLUEPRINT v1.0 — BOOKING SYSTEM SCOPE FREEZE")) {
        throw "v1.0 master blueprint title is missing."
    }

    $readme = Get-Content "README.md" -Raw
    if (-not $readme.Contains("Booking System v1.0 is scope-frozen")) {
        throw "README v1.0 scope-freeze declaration is missing."
    }

    Write-Host "PASS: v1.0 scope-freeze contract" -ForegroundColor Green
}

function Set-OrRemoveEnvironmentVariable {
    param(
        [string]$Name,
        [AllowNull()][string]$Value
    )

    if ($null -eq $Value) {
        Remove-Item "Env:$Name" -ErrorAction SilentlyContinue
    } else {
        Set-Item "Env:$Name" $Value
    }
}

$envPath = Join-Path $BookingRoot ".env"
$envExamplePath = Join-Path $BookingRoot ".env.example"
$envBackupPath = Join-Path $env:TEMP ("hotel-vastu-booking-env-" + [guid]::NewGuid().ToString("N") + ".bak")
$hadEnv = Test-Path $envPath

$databaseEnvNames = @(
    "DB_CONNECTION",
    "DB_HOST",
    "DB_PORT",
    "DB_DATABASE",
    "DB_USERNAME",
    "DB_PASSWORD"
)
$databaseEnvBackup = @{}
foreach ($name in $databaseEnvNames) {
    $databaseEnvBackup[$name] = [Environment]::GetEnvironmentVariable($name, "Process")
}

try {
    Write-Host "Hotel Vastu Booking v1.0 — Local CI parity" -ForegroundColor Yellow
    Write-Host "Working directory: $BookingRoot"

    Assert-PhpEnvironment

    if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
        throw "Composer is not available on PATH."
    }

    if (-not (Test-Path $envExamplePath)) {
        throw ".env.example is missing."
    }

    if ($hadEnv) {
        Copy-Item $envPath $envBackupPath -Force
        Write-Host "Local .env backed up temporarily." -ForegroundColor DarkGray
    }

    Copy-Item $envExamplePath $envPath -Force
    Write-Host "Using .env.example during local CI to mirror GitHub Actions." -ForegroundColor DarkGray

    Write-Host ""
    Write-Host "==> Verify v1.0 scope-freeze contract" -ForegroundColor Cyan
    Assert-ScopeFreeze

    Invoke-ExternalStep "Composer validate --strict" {
        composer validate --strict
    }

    Invoke-ExternalStep "Composer install" {
        composer install --no-interaction --prefer-dist --no-progress
    }

    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        throw "Docker is required for the Docker + Redis production stack smoke gate."
    }

    Invoke-ExternalStep "Docker engine availability" {
        docker version --format "{{.Server.Version}}"
    }

    $dockerProject = "hotel-vastu-booking-ci-$PID"
    $originalAppKey = [Environment]::GetEnvironmentVariable("APP_KEY", "Process")
    $env:APP_KEY = (& php artisan key:generate --show).Trim()

    try {
        Invoke-ExternalStep "Docker booking image build" {
            docker compose -p $dockerProject build app
        }

        Invoke-ExternalStep "Docker MySQL + Redis services" {
            docker compose -p $dockerProject up -d mysql redis
        }

        Invoke-ExternalStep "Docker production migrations" {
            docker compose -p $dockerProject run --rm app php artisan migrate --force
        }

        Invoke-ExternalStep "Docker MySQL + Redis runtime smoke" {
            docker compose -p $dockerProject run --rm app php artisan hotel:infra-check
        }
    }
    finally {
        & docker compose -p $dockerProject down -v --remove-orphans
        Set-OrRemoveEnvironmentVariable -Name "APP_KEY" -Value $originalAppKey
    }

    Invoke-ExternalStep "SQLite feature suite" {
        php artisan test --fail-on-warning
    }

    Invoke-ExternalStep "Route cache build" {
        php artisan route:cache
    }

    Invoke-ExternalStep "View cache build" {
        php artisan view:cache
    }

    Invoke-ExternalStep "Route cache clear" {
        php artisan route:clear
    }

    Invoke-ExternalStep "View cache clear" {
        php artisan view:clear
    }

    if ($MySqlDatabase -notmatch '(_ci|_test)$') {
        throw "Safety check: local CI database name must end in _ci or _test. Current: '$MySqlDatabase'."
    }

    $env:LOCAL_CI_DB_HOST = $MySqlHost
    $env:LOCAL_CI_DB_PORT = [string]$MySqlPort
    $env:LOCAL_CI_DB_DATABASE = $MySqlDatabase
    $env:LOCAL_CI_DB_USERNAME = $MySqlUsername
    $env:LOCAL_CI_DB_PASSWORD = $MySqlPassword

    Invoke-ExternalStep "Create/check dedicated MySQL CI database" {
        php scripts/create-ci-database.php
    }

    $env:DB_CONNECTION = "mysql"
    $env:DB_HOST = $MySqlHost
    $env:DB_PORT = [string]$MySqlPort
    $env:DB_DATABASE = $MySqlDatabase
    $env:DB_USERNAME = $MySqlUsername
    $env:DB_PASSWORD = $MySqlPassword

    Invoke-ExternalStep "MySQL production-contract suite" {
        php artisan test --fail-on-warning
    }

    Invoke-ExternalStep "MySQL row-lock contract" {
        php artisan test --fail-on-warning tests/MySql/MySqlLockingContractTest.php
    }

    Write-Host ""
    Write-Host "ALL LOCAL v1.0 CI GATES PASSED" -ForegroundColor Green
    Write-Host "SQLite + route/view cache + MySQL production contracts match the GitHub Booking CI sequence." -ForegroundColor Green
}
finally {
    foreach ($name in $databaseEnvNames) {
        Set-OrRemoveEnvironmentVariable -Name $name -Value $databaseEnvBackup[$name]
    }

    foreach ($name in @(
        "LOCAL_CI_DB_HOST",
        "LOCAL_CI_DB_PORT",
        "LOCAL_CI_DB_DATABASE",
        "LOCAL_CI_DB_USERNAME",
        "LOCAL_CI_DB_PASSWORD"
    )) {
        Remove-Item "Env:$name" -ErrorAction SilentlyContinue
    }

    if ($hadEnv) {
        if (Test-Path $envBackupPath) {
            Copy-Item $envBackupPath $envPath -Force
            Remove-Item $envBackupPath -Force
            Write-Host "Original local .env restored." -ForegroundColor DarkGray
        }
    } elseif (Test-Path $envPath) {
        Remove-Item $envPath -Force
        Write-Host "Temporary .env removed." -ForegroundColor DarkGray
    }
}
