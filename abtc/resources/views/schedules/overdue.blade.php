<x-app-layout>
    <x-slot name="header">Overdue Follow-ups</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4 text-black" style="width: 100%; max-width: 80rem; margin-left: auto; margin-right: auto;">

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <a href="{{ route('schedules.index') }}" class="bg-gray-200 text-black px-4 py-2 rounded-md text-sm font-semibold hover:bg-gray-300 transition-colors border border-gray-300">&larr; Back</a>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-black flex-1">
                    The following patients are <strong class="text-black">overdue</strong> for a follow-up anti-rabies dose.
                    Send reminders from the <a href="{{ route('sms.index') }}" class="underline text-black">SMS Reminders</a> module.
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow text-black" style="width: 100%;">
                <table class="w-full min-w-[850px] table-fixed text-sm" style="width: 100%; min-width: 850px; table-layout: fixed;">
                    <thead class="bg-gray-50 text-left text-black">
                        <tr>
                            <th class="w-[25%] px-4 py-3 font-semibold text-black">Patient</th>
                            <th class="w-[9%] px-4 py-3 text-center font-semibold text-black">Dose</th>
                            <th class="w-[18%] px-4 py-3 text-center font-semibold text-black">Due Date</th>
                            <th class="w-[14%] px-4 py-3 text-center font-semibold text-black">Days Late</th>
                            <th class="w-[20%] px-4 py-3 text-center font-semibold text-black">Contact</th>
                            <th class="w-[14%] px-4 py-3 text-right font-semibold text-black">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($overdue as $s)
                            <tr class="bg-red-50 hover:bg-red-100 transition-colors">
                                <td class="px-4 py-3 font-medium text-black">
                                    <a href="{{ route('patients.show', $s->patient) }}" class="text-black hover:underline">{{ $s->patient->fullName() }}</a>
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums text-black">{{ $s->dose_number }}</td>
                                <td class="px-4 py-3 text-center whitespace-nowrap text-black">{{ $s->scheduled_date->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-center whitespace-nowrap text-black">{{ $s->scheduled_date->diffInDays(now()) }} days</td>
                                <td class="px-4 py-3 text-center whitespace-nowrap text-black">{{ $s->patient->contact_number ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-right text-black">
                                    <a href="{{ route('schedules.edit', $s) }}" class="inline-block rounded bg-indigo-600 px-3 py-2 text-xs font-semibold text-white">Reschedule</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-black">No overdue follow-ups. &#10003;</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="text-black">{{ $overdue->links() }}</div>
        </div>
    </div>
</x-app-layout>
