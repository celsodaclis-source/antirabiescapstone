<x-app-layout>
    <x-slot name="header">{{ $station->name }}</x-slot>

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $station->name }}</h1>
                <p class="mt-1 text-sm text-slate-600">Patient registration review and assessment queue</p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Station Active</span>
        </div>

        @if ($errors->has('assessment') || $errors->has('station'))
            <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first('assessment') ?: $errors->first('station') }}</div>
        @endif

        <section class="mb-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="nurse-now-serving">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="nurse-now-serving" class="font-semibold text-slate-900">Now Serving</h2>
                    @if ($currentTicket)
                        <p class="mt-1 text-sm text-slate-600"><span class="font-bold text-blue-900">{{ $currentTicket->ticket_number }}</span> · {{ $currentTicket->patient->fullName() }}</p>
                        <p class="text-xs text-slate-500">Registration and bite incident review in progress</p>
                    @else
                        <p class="mt-1 text-sm text-slate-600">No patient is currently being assessed.</p>
                    @endif
                </div>
                @if ($currentTicket)
                    <form method="POST" action="{{ route('nurse.station.assessment.complete', $currentTicket) }}">@csrf
                        <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Complete Assessment</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('nurse.station.call-next') }}">@csrf
                        <button @disabled($waiting->isEmpty()) class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300">Call Next Patient</button>
                    </form>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header class="flex flex-col gap-1 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="font-semibold text-slate-900">Waiting for Nurse Assessment</h2><p class="mt-1 text-sm text-slate-600">Call patients in priority order, then review their registration and bite incident.</p></div>
                <span class="w-fit rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $waiting->total() }} Waiting</span>
            </header>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr><th class="px-5 py-3">Queue #</th><th class="px-5 py-3">Patient</th><th class="px-5 py-3">Visit</th><th class="px-5 py-3">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($waiting as $ticket)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-4 font-semibold text-slate-900">{{ $ticket->ticket_number }}</td>
                                <td class="px-5 py-4"><a class="font-medium text-blue-800 underline decoration-blue-200 underline-offset-2" href="{{ route('patients.show', $ticket->patient) }}">{{ $ticket->patient->fullName() }}</a><span class="mt-1 block text-xs text-slate-500">{{ ucfirst($ticket->service_stage) }}</span></td>
                                <td class="px-5 py-4 text-slate-700">{{ ucfirst($ticket->visit_type) }}</td>
                                <td class="px-5 py-4"><span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Waiting</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-slate-600">No patients are waiting for a nurse assessment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($waiting->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $waiting->links() }}</div>
            @endif
        </section>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">Completing assessment records that this nurse reviewed the registration and bite incident. It does not enter clinical findings or a diagnosis.</p>
            <form method="POST" action="{{ route('nurse.stations.release') }}">@csrf @method('DELETE')<button class="text-sm font-medium text-slate-600 underline decoration-slate-300 underline-offset-4">Leave Station</button></form>
        </div>
    </div>
</x-app-layout>
