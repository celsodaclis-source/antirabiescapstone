<x-app-layout>
    <x-slot name="header">SMS Reminder Template</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-black">
            <form method="POST" action="{{ route('sms.template.update') }}" class="bg-white shadow rounded-lg p-6 space-y-4">
                @csrf
                <div class="text-black">
                    <x-input-label for="lead_hours" value="Send reminder (hours before scheduled dose)"></x-input-label>
                    <x-text-input id="lead_hours" type="number" name="lead_hours" class="block mt-1 w-full" :value="old('lead_hours', $leadHours ?? 24)" required></x-text-input>
                    <p class="text-xs text-black mt-1">Set to 0 to disable automatic reminders. Runs hourly via the scheduler.</p>
                </div>
                <div class="text-black">
                    <x-input-label for="template" value="Message Template"></x-input-label>
                    <textarea id="template" name="template" rows="5" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('template', $template) }}</textarea>
                    <p class="text-xs text-black mt-1">Available placeholders: <code>{patient_name}</code>, <code>{dose_number}</code>, <code>{date}</code>, <code>{time}</code></p>
                    <x-input-error :messages="$errors->get('template')" class="mt-2"></x-input-error>
                </div>
                <div class="flex justify-end gap-3 text-black">
                    <a href="{{ route('sms.index') }}" class="px-4 py-2 text-black hover:underline text-black">Cancel</a>
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md font-semibold hover:bg-indigo-700">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
