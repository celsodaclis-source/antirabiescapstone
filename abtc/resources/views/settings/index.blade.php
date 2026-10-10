<x-app-layout>
    <x-slot name="header">Users / Settings</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 text-black">

            <div class="flex gap-3 text-black">
                <a href="{{ route('settings.users') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">Manage Users</a>
                <a href="{{ route('settings.audit') }}" class="bg-gray-700 text-white px-4 py-2 rounded-md hover:bg-gray-800">Audit Trail</a>
                <a href="{{ route('settings.backup') }}" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">Backup & Recovery</a>
            </div>

            <div class="bg-white shadow rounded-lg p-6 text-black">
                <h3 class="font-semibold text-black mb-4 text-black">System Thresholds</h3>
                <form method="POST" action="{{ route('settings.config') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @csrf
                    <div>
                        <x-input-label for="inventory_low_stock" value="Low-Stock Threshold (units)"></x-input-label>
                        <x-text-input id="inventory_low_stock" type="number" name="inventory_low_stock" class="block mt-1 w-full" :value="old('inventory_low_stock', $thresholds['inventory_low_stock'])" required></x-text-input>
                    </div>
                    <div>
                        <x-input-label for="inventory_expiry_warning" value="Expiry Warning (days)"></x-input-label>
                        <x-text-input id="inventory_expiry_warning" type="number" name="inventory_expiry_warning" class="block mt-1 w-full" :value="old('inventory_expiry_warning', $thresholds['inventory_expiry_warning'])" required></x-text-input>
                    </div>
                    <div>
                        <x-input-label for="sms_reminder_lead" value="SMS Lead Time (hours)"></x-input-label>
                        <x-text-input id="sms_reminder_lead" type="number" name="sms_reminder_lead" class="block mt-1 w-full" :value="old('sms_reminder_lead', $thresholds['sms_reminder_lead'])" required></x-text-input>
                    </div>
                    <div class="sm:col-span-3 flex justify-end text-black">
                        <button class="bg-green-600 text-white px-5 py-2 rounded-md font-semibold hover:bg-green-700">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
