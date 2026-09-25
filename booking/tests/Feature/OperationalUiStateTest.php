<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Folio;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\RestaurantOrder;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomType;
use App\Models\Stay;
use App\Models\StayRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalUiStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_restaurant_new_order_uses_modal_entry_ui(): void
    {
        $restaurant = $this->admin('restaurant', 'restaurant-modal@example.com');

        $this->withSession($this->sessionFor($restaurant))
            ->get('/admin/restaurant')
            ->assertOk()
            ->assertSee('data-open-order-modal', false)
            ->assertSee('id="restaurant-order-modal"', false)
            ->assertSee('id="restaurant-order-form"', false)
            ->assertSee('order-modal-footer', false)
            ->assertSee('Customer & billing details', false)
            ->assertSee('Create order & KOT', false);

        $kitchen = $this->admin('kitchen', 'kitchen-no-order-modal@example.com');

        $this->withSession($this->sessionFor($kitchen))
            ->get('/admin/restaurant')
            ->assertOk()
            ->assertDontSee('data-open-order-modal', false)
            ->assertDontSee('id="restaurant-order-modal"', false);
    }

    public function test_restaurant_table_cards_use_table_and_chair_visuals(): void
    {
        $restaurant = $this->admin('restaurant', 'restaurant-table-icons@example.com');

        $this->withSession($this->sessionFor($restaurant))
            ->get('/admin/restaurant')
            ->assertOk()
            ->assertSee('restaurant-tables-grid', false)
            ->assertSee('data-table-card', false)
            ->assertSee('Seats 2')
            ->assertSee('Seats 8');
    }

    public function test_restaurant_status_ui_only_offers_valid_next_transitions(): void
    {
        $restaurant = $this->admin('restaurant', 'restaurant-state@example.com');

        RestaurantOrder::query()->create([
            'order_number' => 'RO-STATE-ACCEPTED',
            'order_type' => 'takeaway',
            'status' => 'accepted',
            'payment_status' => 'unpaid',
            'subtotal' => 100,
            'tax' => 0,
            'total' => 100,
        ]);

        $response = $this->withSession($this->sessionFor($restaurant))
            ->get('/admin/restaurant');

        $response->assertOk()
            ->assertSee('<option value="preparing">preparing</option>', false)
            ->assertSee('<option value="cancelled">cancelled</option>', false)
            ->assertDontSee('<option value="ready">ready</option>', false)
            ->assertDontSee('<option value="served">served</option>', false);
    }

    public function test_kitchen_ready_order_has_no_invalid_status_action(): void
    {
        $kitchen = $this->admin('kitchen', 'kitchen-ready@example.com');

        RestaurantOrder::query()->create([
            'order_number' => 'RO-KITCHEN-READY',
            'order_type' => 'takeaway',
            'status' => 'ready',
            'payment_status' => 'unpaid',
            'subtotal' => 100,
            'tax' => 0,
            'total' => 100,
        ]);

        $this->withSession($this->sessionFor($kitchen))
            ->get('/admin/restaurant')
            ->assertOk()
            ->assertSee('RO-KITCHEN-READY')
            ->assertDontSee('name="status"', false);
    }

    public function test_future_reservation_hides_checkin_and_no_show_actions(): void
    {
        $frontDesk = $this->admin('front_desk', 'frontdesk-future@example.com');
        [$reservation] = $this->reservationFixture('FUTURE', today()->addDay(), today()->addDays(2));

        $this->withSession($this->sessionFor($frontDesk))
            ->get('/admin/front-desk?tab=reservations')
            ->assertOk()
            ->assertSee('Check-in becomes available during the reserved stay window')
            ->assertDontSee('action="'.route('admin.front-desk.check-in', $reservation).'"', false)
            ->assertDontSee('action="'.route('admin.front-desk.no-show', $reservation).'"', false);
    }

    public function test_checkin_room_candidates_match_backend_room_invariants(): void
    {
        $frontDesk = $this->admin('front_desk', 'frontdesk-checkin-options@example.com');
        [$reservation, $type, $otherType] = $this->reservationFixture('CHECKIN', today(), today()->addDay());

        $eligible = $this->room($type, 'ELIGIBLE', 'clean');
        $wrongType = $this->room($otherType, 'WRONG-TYPE', 'clean');
        $dirty = $this->room($type, 'DIRTY', 'dirty');
        $occupied = $this->room($type, 'OCCUPIED', 'clean');
        $blocked = $this->room($type, 'BLOCKED', 'clean');

        $otherReservation = Reservation::query()->create([
            'booking_number' => 'HV-OCCUPIED',
            'check_in_date' => today(),
            'check_out_date' => today()->addDay(),
            'status' => 'checked_in',
            'pricing_status' => 'priced',
            'payment_status' => 'unpaid',
            'subtotal' => 1000,
            'tax' => 0,
            'total' => 1000,
        ]);
        $stay = Stay::query()->create([
            'reservation_id' => $otherReservation->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);
        StayRoom::query()->create([
            'stay_id' => $stay->id,
            'room_id' => $occupied->id,
            'assigned_at' => now(),
        ]);
        Folio::query()->create([
            'stay_id' => $stay->id,
            'reservation_id' => $otherReservation->id,
            'status' => 'open',
            'charges_total' => 0,
            'payments_total' => 0,
            'refunds_total' => 0,
            'balance' => 0,
        ]);

        RoomBlock::query()->create([
            'room_id' => $blocked->id,
            'starts_on' => today(),
            'ends_on' => today()->addDay(),
            'reason' => 'Maintenance',
            'status' => 'active',
        ]);

        $response = $this->withSession($this->sessionFor($frontDesk))
            ->get('/admin/front-desk?tab=arrivals');

        $response->assertOk();
        $response->assertViewHas('checkInReadyByReservation', fn ($map) => ($map[$reservation->id] ?? false) === true);
        $response->assertViewHas('checkInRoomsByReservation', function ($map) use (
            $reservation,
            $eligible,
            $wrongType,
            $dirty,
            $occupied,
            $blocked
        ) {
            $ids = $map[$reservation->id]->pluck('id')->all();

            return in_array($eligible->id, $ids, true)
                && ! in_array($wrongType->id, $ids, true)
                && ! in_array($dirty->id, $ids, true)
                && ! in_array($occupied->id, $ids, true)
                && ! in_array($blocked->id, $ids, true);
        });
    }

    public function test_transfer_candidates_are_same_type_ready_unoccupied_and_unblocked(): void
    {
        $frontDesk = $this->admin('front_desk', 'frontdesk-transfer-options@example.com');
        [$reservation, $type, $otherType] = $this->reservationFixture('TRANSFER', today(), today()->addDays(2));
        $source = $this->room($type, 'SOURCE', 'clean');
        $eligible = $this->room($type, 'TARGET-OK', 'inspected');
        $wrongType = $this->room($otherType, 'TARGET-WRONG', 'clean');
        $occupied = $this->room($type, 'TARGET-OCCUPIED', 'clean');
        $blocked = $this->room($type, 'TARGET-BLOCKED', 'clean');

        $reservation->update(['status' => 'checked_in']);
        $stay = Stay::query()->create([
            'reservation_id' => $reservation->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);
        $sourceAssignment = StayRoom::query()->create([
            'stay_id' => $stay->id,
            'room_id' => $source->id,
            'assigned_at' => now(),
        ]);
        Folio::query()->create([
            'stay_id' => $stay->id,
            'reservation_id' => $reservation->id,
            'status' => 'open',
            'charges_total' => 0,
            'payments_total' => 0,
            'refunds_total' => 0,
            'balance' => 0,
        ]);

        $occupiedReservation = Reservation::query()->create([
            'booking_number' => 'HV-TRANSFER-OCCUPIED',
            'check_in_date' => today(),
            'check_out_date' => today()->addDays(2),
            'status' => 'checked_in',
            'pricing_status' => 'priced',
            'payment_status' => 'unpaid',
            'subtotal' => 1000,
            'tax' => 0,
            'total' => 1000,
        ]);
        $occupiedStay = Stay::query()->create([
            'reservation_id' => $occupiedReservation->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);
        StayRoom::query()->create([
            'stay_id' => $occupiedStay->id,
            'room_id' => $occupied->id,
            'assigned_at' => now(),
        ]);
        Folio::query()->create([
            'stay_id' => $occupiedStay->id,
            'reservation_id' => $occupiedReservation->id,
            'status' => 'open',
            'charges_total' => 0,
            'payments_total' => 0,
            'refunds_total' => 0,
            'balance' => 0,
        ]);

        RoomBlock::query()->create([
            'room_id' => $blocked->id,
            'starts_on' => today(),
            'ends_on' => today()->addDay(),
            'reason' => 'Maintenance',
            'status' => 'active',
        ]);

        $response = $this->withSession($this->sessionFor($frontDesk))
            ->get('/admin/front-desk');

        $response->assertOk();
        $response->assertViewHas('transferRoomsByAssignment', function ($map) use (
            $sourceAssignment,
            $eligible,
            $wrongType,
            $occupied,
            $blocked,
            $source
        ) {
            $ids = $map[$sourceAssignment->id]->pluck('id')->all();

            return in_array($eligible->id, $ids, true)
                && ! in_array($wrongType->id, $ids, true)
                && ! in_array($occupied->id, $ids, true)
                && ! in_array($blocked->id, $ids, true)
                && ! in_array($source->id, $ids, true);
        });
    }

    private function admin(string $role, string $email): AdminUser
    {
        return AdminUser::query()->create([
            'name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $email,
            'password_hash' => password_hash('test-password-123', PASSWORD_DEFAULT),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function sessionFor(AdminUser $admin): array
    {
        return [
            'admin_user_id' => $admin->id,
            'admin_session_version' => $admin->session_version,
        ];
    }

    private function reservationFixture(string $suffix, $checkIn, $checkOut): array
    {
        $type = RoomType::query()->create([
            'code' => 'type-'.strtolower($suffix),
            'name' => 'Primary '.$suffix,
            'base_rate' => 1000,
            'is_active' => true,
        ]);
        $otherType = RoomType::query()->create([
            'code' => 'other-'.strtolower($suffix),
            'name' => 'Other '.$suffix,
            'base_rate' => 1000,
            'is_active' => true,
        ]);
        $plan = RatePlan::query()->create([
            'code' => 'plan-'.strtolower($suffix),
            'name' => 'Plan '.$suffix,
            'is_active' => true,
        ]);
        $reservation = Reservation::query()->create([
            'booking_number' => 'HV-'.$suffix,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'status' => 'confirmed',
            'pricing_status' => 'priced',
            'payment_status' => 'unpaid',
            'subtotal' => 1000,
            'tax' => 0,
            'total' => 1000,
        ]);
        ReservationRoom::query()->create([
            'reservation_id' => $reservation->id,
            'room_type_id' => $type->id,
            'rate_plan_id' => $plan->id,
            'quantity' => 1,
            'nightly_rate' => 1000,
        ]);

        return [$reservation, $type, $otherType];
    }

    private function room(RoomType $type, string $number, string $housekeeping): Room
    {
        return Room::query()->create([
            'room_type_id' => $type->id,
            'number' => $number,
            'status' => 'active',
            'housekeeping_status' => $housekeeping,
        ]);
    }
}
