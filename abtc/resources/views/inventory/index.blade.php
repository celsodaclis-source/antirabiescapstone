<x-app-layout>
    <x-slot name="header">Vaccine Inventory Monitoring</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4 text-black">

            <div class="flex flex-col gap-3 text-black sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('dashboard') }}" class="bg-gray-200 text-black px-4 py-2 rounded-md text-sm font-semibold hover:bg-gray-300 transition-colors border border-gray-300">&larr; Back</a>
                    <div class="flex flex-wrap gap-2">
                    @if (!$lowStock->isEmpty())
                        <span class="bg-orange-100 text-black px-3 py-2 rounded-full text-sm font-semibold">&#9888; Low Stock: {{ $lowStock->count() }}</span>
                    @endif
                    @if (!$expiringSoon->isEmpty())
                        <span class="bg-amber-100 text-black px-3 py-2 rounded-full text-sm font-semibold">&#9888; Expiring Soon: {{ $expiringSoon->count() }}</span>
                    @endif
                    @if (!$expired->isEmpty())
                        <span class="bg-red-100 text-black px-3 py-2 rounded-full text-sm font-semibold">&#9888; Expired: {{ $expired->count() }}</span>
                    @endif
                    </div>
                </div>
                @role('Admin', 'Nurse')
                    <a href="{{ route('inventory.create') }}" class="bg-green-600 text-white px-5 py-2 rounded-md font-semibold text-center whitespace-nowrap hover:bg-green-700">+ Add Stock Batch</a>
                @endrole
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow text-black">
                <table class="w-full min-w-[850px] table-fixed text-sm">
                    <thead class="bg-gray-50 text-left text-black">
                        <tr>
                            <th class="w-[17%] px-4 py-3 font-semibold text-black">Batch</th>
                            <th class="w-[22%] px-4 py-3 font-semibold text-black">Vaccine</th>
                            <th class="w-[8%] px-4 py-3 text-center font-semibold text-black">Qty</th>
                            <th class="w-[14%] px-4 py-3 text-center font-semibold text-black">Received</th>
                            <th class="w-[14%] px-4 py-3 text-center font-semibold text-black">Expiry</th>
                            <th class="w-[14%] px-4 py-3 text-center font-semibold text-black">Status</th>
                            <th class="w-[11%] px-4 py-3 text-right font-semibold text-black">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($items as $i)
                            @php
                                $alert = $i->isExpired() ? 'bg-red-50' : ($i->isLowStock() ? 'bg-orange-50' : ($i->isExpiringSoon(30) ? 'bg-amber-50' : ''));
                            @endphp
                            <tr class="{{ $alert }} hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3 font-medium whitespace-nowrap text-black">{{ $i->batch_number }}</td>
                                <td class="px-4 py-3 text-black">{{ $i->vaccine_name }}</td>
                                <td class="px-4 py-3 text-center tabular-nums text-black">{{ $i->quantity }}</td>
                                <td class="px-4 py-3 text-center whitespace-nowrap text-black">{{ $i->received_date?->format('M d, Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-center whitespace-nowrap text-black">{{ $i->expiration_date->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if ($i->isExpired()) <x-status-badge status="missed"></x-status-badge> Expired
                                    @elseif ($i->isLowStock()) <x-status-badge status="failed"></x-status-badge> Low stock
                                    @elseif ($i->isExpiringSoon(30)) <x-status-badge status="pending"></x-status-badge> Expiring
                                    @else <x-status-badge status="administered"></x-status-badge> OK @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @role('Admin', 'Nurse')
                                        <a href="{{ route('inventory.edit', $i) }}" class="text-black hover:underline mr-2 text-black">Edit</a>
                                        <form method="POST" action="{{ route('inventory.destroy', $i) }}" class="inline" onsubmit="return confirm('Delete this batch?');">@csrf @method('DELETE')
                                            <button class="text-black hover:underline text-black">Delete</button>
                                        </form>
                                    @endrole
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
