<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RatePlan;
use App\Models\RestaurantCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantTable;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\TaxRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function index(): View
    {
        return view('admin.setup', [
            'roomTypes' => RoomType::query()->orderBy('name')->get(),
            'rooms' => Room::query()->with('roomType')->orderBy('number')->get(),
            'roomBlocks' => RoomBlock::query()->with('room')->where('status', 'active')->orderBy('starts_on')->get(),
            'ratePlans' => RatePlan::query()->orderBy('name')->get(),
            'roomRates' => RoomRate::query()->orderByDesc('starts_on')->limit(50)->get(),
            'taxRules' => TaxRule::query()->orderBy('applies_to')->get(),
            'restaurantCategories' => RestaurantCategory::query()->orderBy('sort_order')->get(),
            'restaurantMenuItems' => RestaurantMenuItem::query()->orderBy('name')->get(),
            'restaurantTables' => RestaurantTable::query()->orderBy('code')->get(),
        ]);
    }

    public function updateRoomType(Request $request, RoomType $roomType): RedirectResponse
    {
        $data = $request->validate([
            'max_adults' => ['nullable', 'integer', 'min:1', 'max:20'],
            'max_children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'base_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $roomType->update([
            'max_adults' => $data['max_adults'] ?? null,
            'max_children' => $data['max_children'] ?? null,
            'base_rate' => $data['base_rate'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Room type updated.');
    }

    public function storeRoom(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_type_id' => ['required', 'exists:room_types,id'],
            'number' => ['required', 'string', 'max:30', 'unique:rooms,number'],
            'floor' => ['nullable', 'string', 'max:50'],
        ]);

        Room::query()->create($data + ['status' => 'active']);

        return back()->with('status', 'Room added.');
    }

    public function storeRoomBlock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        RoomBlock::query()->create($data + ['status' => 'active']);

        return back()->with('status', 'Room block added.');
    }

    public function closeRoomBlock(RoomBlock $roomBlock): RedirectResponse
    {
        $roomBlock->update(['status' => 'inactive']);

        return back()->with('status', 'Room block closed.');
    }

    public function storeRatePlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'includes_breakfast' => ['nullable', 'boolean'],
        ]);

        $code = $data['code'] ?: Str::slug($data['name']);
        if ($code === '' || RatePlan::query()->where('code', $code)->exists()) {
            return back()->withInput()->withErrors(['code' => 'Choose a unique rate plan code.']);
        }

        RatePlan::query()->create([
            'code' => $code,
            'name' => $data['name'],
            'includes_breakfast' => $request->boolean('includes_breakfast'),
            'is_active' => true,
        ]);

        return back()->with('status', 'Rate plan added.');
    }

    public function storeRoomRate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_type_id' => ['required', 'exists:room_types,id'],
            'rate_plan_id' => ['required', 'exists:rate_plans,id'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'nightly_rate' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'min_stay' => ['required', 'integer', 'min:1', 'max:365'],
            'max_stay' => ['nullable', 'integer', 'gte:min_stay', 'max:365'],
        ]);

        $overlap = RoomRate::query()
            ->where('room_type_id', $data['room_type_id'])
            ->where('rate_plan_id', $data['rate_plan_id'])
            ->whereDate('starts_on', '<=', $data['ends_on'])
            ->whereDate('ends_on', '>=', $data['starts_on'])
            ->exists();

        if ($overlap) {
            return back()->withInput()->withErrors(['starts_on' => 'A dated rate already overlaps this room type and rate plan.']);
        }

        RoomRate::query()->create($data);

        return back()->with('status', 'Dated room rate added.');
    }

    public function storeTaxRule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'applies_to' => ['required', 'in:hotel,restaurant,all'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ]);

        TaxRule::query()->create($data + ['is_active' => true]);

        return back()->with('status', 'Tax rule added.');
    }

    public function storeRestaurantCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        RestaurantCategory::query()->create($data + ['is_active' => true]);

        return back()->with('status', 'Restaurant category added.');
    }

    public function storeRestaurantMenuItem(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'restaurant_category_id' => ['required', 'exists:restaurant_categories,id'],
            'name' => ['required', 'string', 'max:160'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'is_vegetarian' => ['nullable', 'boolean'],
        ]);

        RestaurantMenuItem::query()->create([
            ...$data,
            'is_vegetarian' => $request->boolean('is_vegetarian'),
            'is_active' => true,
        ]);

        return back()->with('status', 'Menu item added.');
    }

    public function storeRestaurantTable(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:restaurant_tables,code'],
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        RestaurantTable::query()->create($data + ['is_active' => true]);

        return back()->with('status', 'Restaurant table added.');
    }
}
