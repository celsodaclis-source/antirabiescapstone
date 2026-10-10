<x-app-layout>
    <x-slot name="header">Vaccination Scheduling & Dose Monitoring</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4 text-black">

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <a href="{{ route('dashboard') }}" class="bg-gray-200 text-black px-4 py-2 rounded-md text-sm font-semibold hover:bg-gray-300 transition-colors border border-gray-300">&larr; Back</a>
                <div class="flex flex-wrap items-center gap-2 text-black flex-1">
                @foreach (['all'=>'All','upcoming'=>'Upcoming','overdue'=>'Overdue','administered'=>'Administered','missed'=>'Missed'] as $key => $label)
                    <a href="{{ route('schedules.index', ['filter' => $key]) }}" class="px-3 py-2.5 rounded-md text-sm font-medium {{ $filter === $key ? 'bg-indigo-600 text-white' : 'bg-white border text-black' }}">
                        {{ $label }}
                    </a>
                @endforeach
                    <a href="{{ route('schedules.overdue') }}" class="px-3 py-2.5 rounded-md text-sm font-medium whitespace-nowrap bg-red-600 text-white sm:ml-auto">Overdue Alert ({{ $overdueCount }})</a>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow text-black">
                <table class="w-full min-w-[850px] table-fixed text-sm">
                    <thead class="bg-gray-50 text-left text-black">
                        <tr>
                            <th class="w-[25%] px-4 py-3 font-semibold text-black">Patient</th>
                            <th class="w-[9%] px-4 py-3 text-center font-semibold text-black">Dose</th>
                            <th class="w-[20%] px-4 py-3 text-center font-semibold text-black">Scheduled</th>
                            <th class="w-[14%] px-4 py-3 text-center font-semibold text-black">Status</th>
                            <th class="w-[20%] px-4 py-3 text-center font-semibold text-black">Administered</th>
                            <th class="w-[12%] px-4 py-3 text-right font-semibold text-black">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($schedules as $s)
                            <tr class="{{ $s->isOverdue() ? 'bg-red-50' : '' }} hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3 font-medium text-black">
                                    <a href="{{ route('patients.show', $s->patient) }}" class="text-black hover:underline">{{ $s->patient->fullName() }}</a>
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums text-black">{{ $s->dose_number }}</td>
                                <td class="px-4 py-3 text-center whitespace-nowrap text-black">{{ $s->scheduled_date->format('M d, Y') }} @if($s->isOverdue())<span class="ml-1 text-xs font-semibold text-red-700">OVERDUE</span>@endif</td>
                                <td class="px-4 py-3 text-center whitespace-nowrap"><x-status-badge :status="$s->status"></x-status-badge></td>
                                <td class="px-4 py-3 text-center whitespace-nowrap text-black">{{ $s->administered_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1 whitespace-nowrap text-black">
                                        <a href="{{ route('schedules.edit', $s) }}" class="text-xs bg-indigo-600 text-white px-2 py-2 rounded">Edit</a>
                                        @if ($s->status !== 'administered')
                                            <a href="{{ route('schedules.edit', $s) }}#administer" class="text-xs bg-green-600 text-white px-2 py-2 rounded">Administer</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-black">No schedules found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="text-black">{{ $schedules->links() }}</div>
        </div>
    </div>
</x-app-layout>
