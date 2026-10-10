<x-app-layout>
    <x-slot name="header">Edit Appointment — {{ $schedule->patient->fullName() }}</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 text-black">

            <div class="bg-white shadow rounded-lg p-5 text-black">
                <h3 class="font-semibold text-black mb-3 text-black">Dose {{ $schedule->dose_number }} — Reschedule</h3>
                <form method="POST" action="{{ route('schedules.update', $schedule) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="scheduled_date" value="Scheduled Date"></x-input-label>
                            <x-text-input id="scheduled_date" type="date" name="scheduled_date" class="block mt-1 w-full" :value="old('scheduled_date', $schedule->scheduled_date->toDateString())" required></x-text-input>
                            <x-input-error :messages="$errors->get('scheduled_date')" class="mt-2"></x-input-error>
                        </div>
                        <div>
                            <x-input-label for="status" value="Status"></x-input-label>
                            <select id="status" name="status" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                @foreach (['scheduled','administered','missed'] as $st)
                                    <option value="{{ $st }}" {{ old('status', $schedule->status) === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 text-black">
                        <a href="{{ route('schedules.index') }}" class="px-4 py-2 text-black hover:underline text-black">Cancel</a>
                        <button type="submit" class="bg-indigo-600 text-white px-5 py-2 rounded-md font-semibold hover:bg-indigo-700">Save</button>
                    </div>
                </form>
            </div>

            @if ($schedule->status !== 'administered')
                <div id="administer" class="bg-white shadow rounded-lg p-5 border-l-4 border-green-500 text-black">
                    <h3 class="font-semibold text-black mb-3 text-black">Administer This Dose (auto-deducts stock)</h3>
                    @if ($batches->isEmpty())
                        <p class="text-sm text-black">No vaccine batches in stock. Add stock in Inventory first.</p>
                    @else
                        <form method="POST" action="{{ route('schedules.administer', $schedule) }}" onsubmit="return confirm('Confirm dose administration? Stock will be deducted from the selected batch.');">
                            @csrf
                            <label class="block text-sm font-medium text-black">Select Vaccine Batch</label>
                            <select name="inventory_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                @foreach ($batches as $b)
                                    <option value="{{ $b->id }}">{{ $b->batch_number }} — {{ $b->vaccine_name }} ({{ $b->quantity }} left, exp {{ $b->expiration_date->format('M Y') }})</option>
                                @endforeach
                            </select>
                            <div class="flex justify-end mt-3 text-black">
                                <button type="submit" class="bg-green-600 text-white px-5 py-2 rounded-md font-semibold hover:bg-green-700">Mark Administered</button>
                            </div>
                        </form>
                    @endif
                </div>
            @else
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-black">
                    This dose was administered on {{ $schedule->administered_at?->format('M d, Y h:i A') }}
                    by {{ $schedule->administeredBy?->name ?? 'staff' }} (batch {{ $schedule->inventory?->batch_number }}).
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

