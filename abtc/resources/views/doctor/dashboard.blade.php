<x-app-layout>
    <x-slot name="header">Doctor Dashboard</x-slot>

    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Doctor Dashboard</h1>
                <p class="mt-1 text-sm text-slate-600">Select your station to begin patient consultations.</p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1 text-sm font-semibold {{ $assignedStation ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                <span class="h-2 w-2 rounded-full {{ $assignedStation ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                {{ $assignedStation ? 'On Duty' : 'Off Duty' }}
            </span>
        </div>

        @if ($errors->has('station'))
            <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first('station') }}</div>
        @endif

        <section aria-label="Doctor Stations">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-slate-900">Doctor Stations</h2>
                <p class="mt-0.5 text-sm text-slate-600">Select your assigned station</p>
            </div>

            @if ($assignedStation)
                <div class="mb-5 flex flex-col gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-emerald-900">You are assigned to <strong>{{ $assignedStation->name }}</strong>. Your patient queue is ready.</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('queue.index') }}" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Go to Patient Queue</a>
                        <form method="POST" action="{{ route('doctor.stations.release') }}">
                            @csrf @method('DELETE')
                            <button class="rounded-lg border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">Leave Station</button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                @foreach ($stations as $station)
                    @php($occupied = $station->user_id !== null)
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-800" aria-hidden="true">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v5a4 4 0 0 0 8 0V3M4 3h4M12 3h4M10 12v1a5 5 0 0 0 10 0v-1"/><circle cx="20" cy="10" r="2"/><path d="M17 18a3 3 0 1 0 6 0"/></svg>
                                </span>
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-900">{{ $station->name }}</h3>
                                    <p class="mt-0.5 text-sm text-slate-600">
                                        {{ $occupied ? ($station->user_id === Auth::id() ? 'Assigned to you' : 'Assigned to another doctor') : 'No doctor assigned' }}
                                    </p>
                                    @if ($occupied)
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $station->user_id === Auth::id() ? 'Current assignment: ' : 'Assigned doctor: ' }}{{ $station->doctor?->name }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $occupied ? 'bg-slate-100 text-slate-700' : 'bg-emerald-100 text-emerald-800' }}">{{ $occupied ? 'In Use' : 'Available' }}</span>
                        </div>

                        <div class="mt-6">
                            @if ($occupied)
                                <button type="button" disabled class="w-full cursor-not-allowed rounded-lg bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-500">In Use</button>
                            @else
                                <form method="POST" action="{{ route('doctor.stations.assign', $station) }}">
                                    @csrf
                                    <button type="submit" class="w-full rounded-lg bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">Open Station</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <p class="mt-6 text-center text-xs text-slate-500">Station availability reflects current database assignments. Assigned stations remain in use until the doctor leaves the station.</p>
    </div>
</x-app-layout>
