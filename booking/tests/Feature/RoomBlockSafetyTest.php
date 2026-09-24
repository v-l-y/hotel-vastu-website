<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Stay;
use App\Models\StayRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomBlockSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): AdminUser
    {
        return AdminUser::query()->create([
            'name'=>'Admin',
            'email'=>'room-block-admin@example.com',
            'password_hash'=>password_hash('test-password-123', PASSWORD_DEFAULT),
            'role'=>'administrator',
            'is_active'=>true,
        ]);
    }

    public function test_room_block_cannot_reduce_inventory_below_existing_booking_demand(): void
    {
        $admin = $this->administrator();
        $type = RoomType::query()->create([
            'code'=>'block-sold-out',
            'name'=>'Block Sold Out',
            'is_active'=>true,
        ]);
        $room = Room::query()->create([
            'room_type_id'=>$type->id,
            'number'=>'B101',
            'status'=>'active',
            'housekeeping_status'=>'clean',
        ]);
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-BLOCK-1',
            'check_in_date'=>today()->addDay(),
            'check_out_date'=>today()->addDays(2),
            'status'=>'confirmed',
        ]);
        ReservationRoom::query()->create([
            'reservation_id'=>$reservation->id,
            'room_type_id'=>$type->id,
            'quantity'=>1,
        ]);

        $this->from('/admin/setup')
            ->withSession(['admin_user_id'=>$admin->id])
            ->post('/admin/setup/room-blocks', [
                'room_id'=>$room->id,
                'starts_on'=>today()->addDay()->toDateString(),
                'ends_on'=>today()->addDays(2)->toDateString(),
                'reason'=>'Maintenance',
            ])
            ->assertRedirect('/admin/setup')
            ->assertSessionHasErrors('room_block');

        $this->assertDatabaseMissing('room_blocks', [
            'room_id'=>$room->id,
            'status'=>'active',
        ]);
    }

    public function test_room_block_cannot_conflict_with_current_physical_room_assignment(): void
    {
        $admin = $this->administrator();
        $type = RoomType::query()->create([
            'code'=>'block-occupied',
            'name'=>'Block Occupied',
            'is_active'=>true,
        ]);
        $room = Room::query()->create([
            'room_type_id'=>$type->id,
            'number'=>'B201',
            'status'=>'active',
            'housekeeping_status'=>'clean',
        ]);
        Room::query()->create([
            'room_type_id'=>$type->id,
            'number'=>'B202',
            'status'=>'active',
            'housekeeping_status'=>'clean',
        ]);
        $reservation = Reservation::query()->create([
            'booking_number'=>'HV-BLOCK-2',
            'check_in_date'=>today(),
            'check_out_date'=>today()->addDay(),
            'status'=>'checked_in',
        ]);
        ReservationRoom::query()->create([
            'reservation_id'=>$reservation->id,
            'room_type_id'=>$type->id,
            'quantity'=>1,
        ]);
        $guest = Guest::query()->create([
            'first_name'=>'Current',
            'phone'=>'9000000033',
        ]);
        ReservationGuest::query()->create([
            'reservation_id'=>$reservation->id,
            'guest_id'=>$guest->id,
            'role'=>'primary',
        ]);
        $stay = Stay::query()->create([
            'reservation_id'=>$reservation->id,
            'status'=>'checked_in',
            'checked_in_at'=>now(),
        ]);
        StayRoom::query()->create([
            'stay_id'=>$stay->id,
            'room_id'=>$room->id,
            'assigned_at'=>now(),
        ]);

        $this->from('/admin/setup')
            ->withSession(['admin_user_id'=>$admin->id])
            ->post('/admin/setup/room-blocks', [
                'room_id'=>$room->id,
                'starts_on'=>today()->toDateString(),
                'ends_on'=>today()->addDay()->toDateString(),
                'reason'=>'Emergency maintenance',
            ])
            ->assertRedirect('/admin/setup')
            ->assertSessionHasErrors('room_block');

        $this->assertDatabaseMissing('room_blocks', [
            'room_id'=>$room->id,
            'status'=>'active',
        ]);
    }
}
