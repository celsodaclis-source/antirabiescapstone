<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VaccineInventory;
use App\Services\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryApiController extends Controller
{
    public function index(): JsonResponse
    {
        $items = VaccineInventory::orderBy('expiration_date')->get();

        return response()->json([
            'data' => $items,
            'summary' => [
                'total_stock' => $items->sum('quantity'),
                'batch_count' => $items->count(),
                'low_stock_count' => $items->filter(fn ($i) => $i->isLowStock())->count(),
                'expiring_count' => $items->filter(fn ($i) => $i->isExpiringSoon(30) && !$i->isExpired())->count(),
                'expired_count' => $items->filter(fn ($i) => $i->isExpired())->count(),
            ],
        ]);
    }

    public function lowStock(): JsonResponse
    {
        $items = VaccineInventory::all()->filter(fn ($i) => $i->isLowStock())->values();
        return response()->json(['data' => $items, 'count' => $items->count()]);
    }

    public function expiring(): JsonResponse
    {
        $items = VaccineInventory::all()->filter(fn ($i) => $i->isExpiringSoon(30) && !$i->isExpired())->values();
        return response()->json(['data' => $items, 'count' => $items->count()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'batch_number' => ['required', 'string', 'max:50', 'unique:vaccine_inventory,batch_number'],
            'vaccine_name' => ['required', 'string', 'max:150'],
            'quantity' => ['required', 'integer', 'min:0'],
            'expiration_date' => ['required', 'date'],
            'received_date' => ['nullable', 'date', 'before_or_equal:today'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['low_stock_threshold'] = $data['low_stock_threshold'] ?? 10;

        $item = VaccineInventory::create($data);
        Audit::log('inventory.add', "API: Added stock batch {$item->batch_number} ({$item->vaccine_name}, qty {$item->quantity})", $item);

        return response()->json(['data' => $item, 'message' => "Batch {$item->batch_number} added."], 201);
    }

    public function update(Request $request, VaccineInventory $inventory): JsonResponse
    {
        $data = $request->validate([
            'batch_number' => ['required', 'string', 'max:50', 'unique:vaccine_inventory,batch_number,' . $inventory->id],
            'vaccine_name' => ['required', 'string', 'max:150'],
            'quantity' => ['required', 'integer', 'min:0'],
            'expiration_date' => ['required', 'date'],
            'received_date' => ['nullable', 'date', 'before_or_equal:today'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['low_stock_threshold'] = $data['low_stock_threshold'] ?? 10;
        $inventory->update($data);
        Audit::log('inventory.edit', "API: Updated stock batch {$inventory->batch_number} (qty {$inventory->quantity})", $inventory);

        return response()->json(['data' => $inventory, 'message' => "Batch {$inventory->batch_number} updated."]);
    }

    public function destroy(VaccineInventory $inventory): JsonResponse
    {
        Audit::log('inventory.delete', "API: Deleted stock batch {$inventory->batch_number}", $inventory);
        $inventory->delete();

        return response()->json(['message' => 'Batch removed.']);
    }
}

