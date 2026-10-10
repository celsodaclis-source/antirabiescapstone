<x-app-layout>
    <x-slot name="header">Add Stock Batch</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-black">
            <form method="POST" action="{{ route('inventory.store') }}" class="bg-white shadow rounded-lg p-6 space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="batch_number" value="Batch Number"></x-input-label>
                        <x-text-input id="batch_number" name="batch_number" class="block mt-1 w-full" :value="old('batch_number')" required></x-text-input>
                        <x-input-error :messages="$errors->get('batch_number')" class="mt-2"></x-input-error>
                    </div>
                    <div>
                        <x-input-label for="vaccine_name" value="Vaccine Name"></x-input-label>
                        <x-text-input id="vaccine_name" name="vaccine_name" class="block mt-1 w-full" :value="old('vaccine_name')" required></x-text-input>
                        <x-input-error :messages="$errors->get('vaccine_name')" class="mt-2"></x-input-error>
                    </div>
                    <div>
                        <x-input-label for="quantity" value="Quantity"></x-input-label>
                        <x-text-input id="quantity" type="number" name="quantity" class="block mt-1 w-full" :value="old('quantity')" required></x-text-input>
                        <x-input-error :messages="$errors->get('quantity')" class="mt-2"></x-input-error>
                    </div>
                    <div>
                        <x-input-label for="low_stock_threshold" value="Low-Stock Threshold"></x-input-label>
                        <x-text-input id="low_stock_threshold" type="number" name="low_stock_threshold" class="block mt-1 w-full" :value="old('low_stock_threshold', 10)"></x-text-input>
                    </div>
                    <div>
                        <x-input-label for="received_date" value="Received Date"></x-input-label>
                        <x-text-input id="received_date" type="date" name="received_date" class="block mt-1 w-full" :value="old('received_date', now()->toDateString())"></x-text-input>
                    </div>
                    <div>
                        <x-input-label for="expiration_date" value="Expiration Date"></x-input-label>
                        <x-text-input id="expiration_date" type="date" name="expiration_date" class="block mt-1 w-full" :value="old('expiration_date')" required></x-text-input>
                        <x-input-error :messages="$errors->get('expiration_date')" class="mt-2"></x-input-error>
                    </div>
                </div>
                <div class="flex justify-end gap-3 text-black">
                    <a href="{{ route('inventory.index') }}" class="px-4 py-2 text-black hover:underline text-black">Cancel</a>
                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-md font-semibold hover:bg-green-700">Save Batch</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
