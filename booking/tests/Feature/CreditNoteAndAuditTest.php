<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\FrontDeskService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditNoteAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_invoice_refund_creates_credit_note_without_mutating_invoice_snapshot(): void
    {
        $type = RoomType::query()->create(['code'=>'credit-room','name'=>'Classic Room','is_active'=>true]);
        $plan = RatePlan::query()->create(['code'=>'credit-plan','name'=>'Standard','is_active'=>true]);
        $room = Room::query()->create([
            'room_type_id'=>$type->id,'number'=>'C101','status'=>'active','housekeeping_status'=>'clean',
        ]);
        $guest = Guest::query()->create(['first_name'=>'Guest','phone'=>'9999999999']);
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-CREDIT-1','public_token'=>'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'check_in_date'=>today(),'check_out_date'=>today()->addDay(),
            'status'=>'confirmed','pricing_status'=>'priced','payment_status'=>'unpaid',
            'subtotal'=>1000,'tax'=>0,'total'=>1000,
        ]);
        ReservationRoom::query()->create([
            'reservation_id'=>$reservation->id,'room_type_id'=>$type->id,
            'rate_plan_id'=>$plan->id,'quantity'=>1,'nightly_rate'=>1000,
        ]);
        ReservationGuest::query()->create([
            'reservation_id'=>$reservation->id,'guest_id'=>$guest->id,'role'=>'primary',
        ]);

        $payment = app(PaymentService::class)->record([
            'idempotency_key'=>'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'reservation_id'=>$reservation->id,
            'method'=>'cash',
            'amount'=>1000,
        ]);

        $stay = app(FrontDeskService::class)->checkIn($reservation->fresh(), [$room->id]);
        $result = app(FrontDeskService::class)->checkOut($stay);
        $invoice = $result['invoice'];
        $this->assertSame('1000.00', $invoice->total);
        $this->assertSame('1000.00', $invoice->paid);

        $movedPayment = $payment->fresh();
        app(PaymentService::class)->refund($movedPayment, [
            'idempotency_key'=>'abababab-abab-4bab-8bab-abababababab',
            'amount'=>250,
            'reason'=>'Post checkout adjustment',
        ]);

        $this->assertSame('1000.00', $invoice->fresh()->total);
        $this->assertDatabaseHas('credit_notes', [
            'invoice_id'=>$invoice->id,
            'amount'=>250,
            'reason'=>'Post checkout adjustment',
        ]);
    }

    public function test_admin_mutation_is_written_to_audit_trail(): void
    {
        $admin = AdminUser::query()->create([
            'name'=>'Administrator',
            'email'=>'audit@example.com',
            'password_hash'=>password_hash('test-password-123', PASSWORD_DEFAULT),
            'role'=>'administrator',
            'is_active'=>true,
        ]);
        $type = RoomType::query()->create([
            'code'=>'audit-room','name'=>'Classic Room','is_active'=>true,
        ]);

        $this->withSession(['admin_user_id'=>$admin->id])
            ->post('/admin/setup/rooms', [
                'room_type_id'=>$type->id,
                'number'=>'AUD101',
                'floor'=>'1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id'=>$admin->id,
            'route_name'=>'admin.setup.rooms.store',
            'method'=>'POST',
            'status_code'=>302,
        ]);
    }
}
