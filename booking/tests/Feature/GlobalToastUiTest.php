<?php

namespace Tests\Feature;

use Tests\TestCase;

class GlobalToastUiTest extends TestCase
{
    public function test_shared_toaster_supports_flash_validation_and_frontend_messages(): void
    {
        $toast = file_get_contents(resource_path('views/partials/toast.blade.php'));

        $this->assertIsString($toast);
        $this->assertStringContainsString("'status' => 'success'", $toast);
        $this->assertStringContainsString("'error' => 'error'", $toast);
        $this->assertStringContainsString('$errors->all()', $toast);
        $this->assertStringContainsString('window.HotelToast', $toast);
        $this->assertStringContainsString('success: (message', $toast);
        $this->assertStringContainsString('error: (message', $toast);
        $this->assertStringContainsString('textContent = safeMessage', $toast);
    }

    public function test_admin_and_public_booking_shells_mount_the_global_toaster(): void
    {
        $paths = [
            resource_path('views/admin/layout.blade.php'),
            resource_path('views/admin/auth-layout.blade.php'),
            resource_path('views/booking/alternatives.blade.php'),
            resource_path('views/booking/confirmation.blade.php'),
            resource_path('views/booking/feedback.blade.php'),
            resource_path('views/booking/guest.blade.php'),
            resource_path('views/booking/invoice.blade.php'),
            resource_path('views/booking/payment.blade.php'),
            resource_path('views/booking/search.blade.php'),
            resource_path('views/booking/verify.blade.php'),
        ];

        foreach ($paths as $path) {
            $view = file_get_contents($path);
            $this->assertIsString($view);
            $this->assertStringContainsString("@include('partials.toast')", $view, $path);
        }
    }

    public function test_duplicate_validation_banners_are_removed_and_ajax_uses_toasts(): void
    {
        foreach ([
            resource_path('views/admin/layout.blade.php'),
            resource_path('views/admin/login.blade.php'),
            resource_path('views/admin/two-factor-challenge.blade.php'),
            resource_path('views/booking/search.blade.php'),
            resource_path('views/booking/feedback.blade.php'),
            resource_path('views/booking/guest.blade.php'),
            resource_path('views/booking/alternatives.blade.php'),
            resource_path('views/booking/verify.blade.php'),
        ] as $path) {
            $view = file_get_contents($path);
            $this->assertIsString($view);
            $this->assertStringNotContainsString('$errors->all()', $view, $path);
        }

        $guest = file_get_contents(resource_path('views/booking/guest.blade.php'));
        $this->assertStringContainsString('window.HotelToast?.success', $guest);
        $this->assertStringContainsString('window.HotelToast?.error', $guest);
    }
}
