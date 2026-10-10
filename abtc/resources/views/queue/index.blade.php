<x-app-layout>
    <x-slot name="header">Ticket Queue Management</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 text-black">

            <!-- Counter status panel -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" id="counters-panel">
                @foreach ($counters as $id => $c)
                    <div id="counter-{{ $id }}" class="rounded-lg p-4 border {{ $c['status'] === 'idle' ? 'bg-gray-50 border-gray-200' : 'bg-sky-50 border-sky-300' }} text-black">
                        <div class="text-xs uppercase tracking-wide counter-state text-black">Counter {{ $id }}</div>
                        <div class="text-2xl font-bold mt-1 counter-ticket text-black">{{ $c['ticket'] ?? 'IDLE' }}</div>
                        <div class="text-sm counter-status text-black">{{ $c['status'] === 'idle' ? 'Available' : ($c['patient'] ?? '') }}</div>
                        @if ($c['status'] !== 'idle')
                            <div class="text-xs counter-staff text-black">{{ $c['staff'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 text-black" id="queue-blocks">
                <!-- Now serving -->
                <div class="bg-white shadow rounded-lg p-5 text-black">
                    <h3 class="font-semibold text-black mb-3 text-black">Now Serving</h3>
                    @if ($nowServing->isEmpty())
                        <p class="text-sm text-black">No ticket currently being served.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($nowServing as $t)
                                <div class="flex items-center justify-between bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-black">
                                    <div class="text-black">
                                        <div class="text-lg font-bold text-black">{{ $t->ticket_number }}</div>
                                        <div class="text-sm">{{ $t->patient->fullName() }}</div>
                                        <div class="text-xs text-black">Counter {{ $t->counter_assigned }} &middot; {{ $t->assignedStaff?->name }}</div>
                                    </div>
                                    <div class="text-right">
                                        <x-status-badge :status="$t->status"></x-status-badge>
                                        <div class="mt-2 flex gap-1 text-black">
                                            <form method="POST" action="{{ route('queue.serve', $t) }}">@csrf
                                                <button class="text-xs bg-cyan-600 text-white px-2 py-1 rounded">Serve</button>
                                            </form>
                                            <form method="POST" action="{{ route('queue.complete', $t) }}">@csrf
                                                <button class="text-xs bg-green-600 text-white px-2 py-1 rounded">Done</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Waiting -->
                <div class="bg-white shadow rounded-lg p-5 text-black">
                    <h3 class="font-semibold text-black mb-3 text-black">Waiting ({{ $waiting->count() }})</h3>
                    @if ($waiting->isEmpty())
                        <p class="text-sm text-black">No one waiting.</p>
                    @else
                        <ol class="space-y-2">
                            @foreach ($waiting as $t)
                                <li class="flex items-center justify-between {{ $t->is_priority ? 'bg-red-50 border-red-300' : 'bg-yellow-50 border-yellow-200' }} border rounded-lg p-3 text-black">
                                    <div class="text-black">
                                        <span class="font-semibold text-black">{{ $t->ticket_number }}</span>
                                        <span class="text-sm text-black ml-2">{{ $t->patient->fullName() }}</span>
                                        @if ($t->is_priority)
                                            <span class="text-xs bg-red-500 text-white px-1.5 py-0.5 rounded-full ml-1">{{ $t->patient->patientTypeLabel() }}</span>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('queue.call', $t) }}" class="flex gap-1 items-center">
                                        @csrf
                                        <select name="counter" class="text-xs border-gray-300 rounded">
                                            @foreach ([1,2,3,4] as $c) <option value="{{ $c }}">C{{ $c }}</option> @endforeach
                                        </select>
                                        <button class="text-xs bg-indigo-600 text-white px-2 py-1 rounded">Call</button>
                                    </form>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    <form method="POST" action="{{ route('queue.call-next') }}" class="mt-4 flex items-center gap-2">
                        @csrf
                        <select name="counter" class="text-sm border-gray-300 rounded">
                            @foreach ([1,2,3,4] as $c) <option value="{{ $c }}">Counter {{ $c }}</option> @endforeach
                        </select>
                        <button class="bg-green-600 text-white px-4 py-2 rounded-md font-semibold hover:bg-green-700">Call Next</button>
                    </form>
                </div>

                <!-- Held + Served -->
                <div class="space-y-6 text-black">
                    <div class="bg-white shadow rounded-lg p-5 text-black">
                        <h3 class="font-semibold text-black mb-3 text-black">On Hold ({{ $held->count() }})</h3>
                        @if ($held->isEmpty())
                            <p class="text-sm text-black">None on hold.</p>
                        @else
                            <ul class="space-y-2">
                                @foreach ($held as $t)
                                    <li class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-lg p-2 text-black">
                                        <span class="text-sm text-black"><span class="font-semibold text-black">{{ $t->ticket_number }}</span> &mdash; {{ $t->patient->fullName() }}</span>
                                        <form method="POST" action="{{ route('queue.call', $t) }}">@csrf
                                            <button class="text-xs bg-indigo-600 text-white px-2 py-1 rounded">Recall</button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="bg-white shadow rounded-lg p-5 text-black">
                        <h3 class="font-semibold text-black mb-3 text-black">Served Today ({{ $servedToday->count() }})</h3>
                        <ul class="text-sm max-h-48 overflow-auto divide-y">
                            @forelse ($servedToday as $t)
                                <li class="py-1 flex justify-between text-black">
                                    <span class="text-black">{{ $t->ticket_number }} &mdash; {{ $t->patient->fullName() }}</span>
                                    <span class="text-black">{{ $t->completed_at?->format('h:i A') }}</span>
                                </li>
                            @empty
                                <li class="text-black">None completed yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            <p class="text-xs text-black text-center">Queue auto-refreshes every 8 seconds.</p>
        </div>
    </div>

    @push('scripts')
    <script>
        function escapeHtml(s) {
            return (s ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }

        async function refreshQueue() {
            try {
                const res = await fetch('{{ route('queue.data') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();

                // Update counter cards
                for (let i = 1; i <= 4; i++) {
                    const card = document.getElementById('counter-' + i);
                    if (!card) continue;
                    const c = data.counters[i] || { status: 'idle' };
                    const isIdle = c.status === 'idle';
                    card.className = 'rounded-lg p-4 border ' + (isIdle ? 'bg-gray-50 border-gray-200' : 'bg-sky-50 border-sky-300');
                    card.querySelector('.counter-ticket').className = 'text-2xl font-bold mt-1 counter-ticket text-black';
                    const stateEl = card.querySelector('.counter-state');
                    const ticketEl = card.querySelector('.counter-ticket');
                    const statusEl = card.querySelector('.counter-status');
                    stateEl.textContent = 'Counter ' + i;
                    statusEl.textContent = isIdle ? 'Available' : (c.patient ?? '');
                    stateEl.className = 'text-xs uppercase tracking-wide counter-state text-black';
                    statusEl.className = 'text-sm counter-status text-black';
                    const staffEl = card.querySelector('.counter-staff');
                    if (staffEl) staffEl.textContent = c.staff ?? '';
                }
            } catch (e) { /* keep showing last known state */ }
        }

        setInterval(refreshQueue, 8000);
    </script>
    @endpush
</x-app-layout>
