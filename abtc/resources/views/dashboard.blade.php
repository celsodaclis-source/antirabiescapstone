<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>
    <div class="py-6 text-black">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-black">Welcome, <span class="text-[#176b9c]">{{ Auth::user()->name }}</span> <span class="ml-2 inline-block bg-[#176b9c]/10 text-[#176b9c] text-xs font-semibold px-2.5 py-0.5 rounded-full">{{ $role }}</span></h2>
                        <p class="text-sm text-black/70 mt-1">{{ now()->format('l, F j, Y') }}</p>
                    </div>
                </div>
            </div>

            @if ($role === 'Admin')
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm border-l-4 border-[#176b9c]">
                    <div class="text-sm text-black font-medium">Patients Today</div>
                    <div class="text-3xl font-bold text-black">{{ $patientsToday }}</div>
                    <a href="{{ route('patients.index') }}" class="text-xs text-[#176b9c] hover:underline mt-2 inline-block font-semibold">View patients &rarr;</a>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm border-l-4 border-[#e5af22]">
                    <div class="text-sm text-black font-medium">Current Queue</div>
                    <div class="text-3xl font-bold text-black">{{ $queueCount }}</div>
                    <a href="{{ route('queue.index') }}" class="text-xs text-[#176b9c] hover:underline mt-2 inline-block font-semibold">Manage queue &rarr;</a>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm border-l-4 border-emerald-500">
                    <div class="text-sm text-black font-medium">Vaccines in Stock</div>
                    <div class="text-3xl font-bold text-black">{{ $totalStock }}</div>
                    <a href="{{ route('inventory.index') }}" class="text-xs text-[#176b9c] hover:underline mt-2 inline-block font-semibold">Manage inventory &rarr;</a>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm border-l-4 border-rose-500">
                    <div class="text-sm text-black font-medium">Overdue Follow-ups</div>
                    <div class="text-3xl font-bold text-black">{{ $pendingFollowups }}</div>
                    <a href="{{ route('schedules.overdue') }}" class="text-xs text-[#176b9c] hover:underline mt-2 inline-block font-semibold">View follow-ups &rarr;</a>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-black mb-3">Patient Volume &mdash; {{ now()->format('F Y') }}</h3>
                    <canvas id="volumeChart" height="100"></canvas>
                </div>
                <div class="space-y-4">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <h4 class="font-semibold text-black mb-2">Overdue Doses ({{ $overdue->count() }})</h4>
                        @forelse ($overdue->take(8) as $o)
                        <div class="flex justify-between py-1 border-b border-red-100 text-black text-sm"><span>{{ $o->patient->fullName() }}</span><span class="text-xs">Dose {{ $o->dose_number }} ({{ $o->scheduled_date->format('M d') }})</span></div>
                        @empty
                        <p class="text-sm text-black">No overdue follow-ups.</p>
                        @endforelse
                        @if ($overdue->isNotEmpty())
                        <a href="{{ route('schedules.overdue') }}" class="text-[#176b9c] underline text-sm mt-2 inline-block">View all &rarr;</a>
                        @endif
                    </div>
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                        <h4 class="font-semibold text-black mb-2">Low Stock ({{ $lowStock->count() }})</h4>
                        @forelse ($lowStock as $i)
                        <div class="flex justify-between py-1 border-b border-orange-100 text-black text-sm"><span>{{ $i->vaccine_name }} ({{ $i->batch_number }})</span><span class="font-semibold text-red-600">{{ $i->quantity }} left</span></div>
                        @empty
                        <p class="text-sm text-black">Stock levels adequate.</p>
                        @endforelse
                        @if ($lowStock->isNotEmpty())
                        <a href="{{ route('inventory.index') }}" class="text-[#176b9c] underline text-sm mt-2 inline-block">View inventory &rarr;</a>
                        @endif
                    </div>
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <h4 class="font-semibold text-black mb-2">Expiring Soon ({{ $expiringSoon->count() }})</h4>
                        @forelse ($expiringSoon as $i)
                        <div class="flex justify-between py-1 border-b border-amber-100 text-black text-sm"><span>{{ $i->batch_number }}</span><span class="font-semibold">{{ $i->expiration_date->format('M d, Y') }}</span></div>
                        @empty
                        <p class="text-sm text-black">Nothing expiring within 30 days.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-black mb-3">Active Queue</h3>
                    @forelse ($recentTickets as $t)
                    <div class="flex justify-between py-2 border-b border-slate-100 text-sm text-black"><span class="font-medium">{{ $t->ticket_number }}</span><span>{{ $t->patient->fullName() }}</span><span>{{ $t->counter_assigned ?? '&mdash;' }}</span><span><x-status-badge :status="$t->status" /></span></div>
                    @empty
                    <p class="text-sm text-black">Queue is empty.</p>
                    @endforelse
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-black mb-3">Recent SMS</h3>
                    @forelse ($smsRecent as $s)
                    <div class="flex justify-between py-2 border-b border-slate-100 text-sm text-black"><span>{{ $s->patient?->first_name ?? $s->recipient }}</span><span><x-status-badge :status="$s->status" /></span><span class="text-xs">{{ $s->created_at->diffForHumans() }}</span></div>
                    @empty
                    <p class="text-sm text-black">No SMS sent yet.</p>
                    @endforelse
                </div>
            </div>
            @endif

        </div>
    </div>
    @push('scripts')
    <script src="{{ asset('js/chart.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var ctx = document.getElementById('volumeChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: @json($labels),
                        datasets: [{
                            label: 'Patients Registered',
                            data: @json($values),
                            backgroundColor: '#176b9c',
                            borderRadius: 4,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { precision: 0, color: '#000000' }, title: { display: true, text: 'Patients', color: '#000000' } },
                            x: { ticks: { color: '#000000' }, title: { display: true, text: 'Day', color: '#000000' } }
                        }
                    }
                });
            }
        });
    </script>
    @endpush
</x-app-layout>
