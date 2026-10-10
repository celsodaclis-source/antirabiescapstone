<?php

namespace App\Http\Controllers;

use App\Models\VaccineInventory;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        $items = VaccineInventory::orderBy('expiration_date')->get();

        $lowStock = $items->filter(fn ($i) => $i->isLowStock());
        $expiringSoon = $items->filter(fn ($i) => $i->isExpiringSoon(30) && ! $i->isExpired());
        $expired = $items->filter(fn ($i) => $i->isExpired());

        $totalStock = $items->sum('quantity');

        return view('inventory.index', compact('items', 'lowStock', 'expiringSoon', 'expired', 'totalStock'));
    }

    public function create(): View
    {
        return view('inventory.create');
    }

    public function store(Request $request): RedirectResponse
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
        Audit::log('inventory.add', "Added stock batch {$item->batch_number} ({$item->vaccine_name}, qty {$item->quantity})", $item);

        return redirect()->route('inventory.index')->with('status', "Batch {$item->batch_number} added.");
    }

    public function edit(VaccineInventory $inventory): View
    {
        return view('inventory.edit', compact('inventory'));
    }

    public function update(Request $request, VaccineInventory $inventory): RedirectResponse
    {
        $data = $request->validate([
            'batch_number' => ['required', 'string', 'max:50', 'unique:vaccine_inventory,batch_number,'.$inventory->id],
            'vaccine_name' => ['required', 'string', 'max:150'],
            'quantity' => ['required', 'integer', 'min:0'],
            'expiration_date' => ['required', 'date'],
            'received_date' => ['nullable', 'date', 'before_or_equal:today'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['low_stock_threshold'] = $data['low_stock_threshold'] ?? 10;

        $inventory->update($data);
        Audit::log('inventory.edit', "Updated stock batch {$inventory->batch_number} (qty {$inventory->quantity})", $inventory);

        return redirect()->route('inventory.index')->with('status', "Batch {$inventory->batch_number} updated.");
    }

    public function destroy(VaccineInventory $inventory): RedirectResponse
    {
        Audit::log('inventory.delete', "Deleted stock batch {$inventory->batch_number}", $inventory);
        $inventory->delete();

        return redirect()->route('inventory.index')->with('status', 'Batch removed.');
    }
}
