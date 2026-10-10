<x-app-layout>
    <x-slot name="header">Add User</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-black">
            <form method="POST" action="{{ route('settings.users.store') }}" class="rounded-lg bg-[#176b9c] p-6 space-y-4 shadow">
                @csrf
                <div>
                    <x-input-label for="name" value="Full Name" class="text-white"></x-input-label>
                    <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name')" required></x-text-input>
                    <x-input-error :messages="$errors->get('name')" class="mt-2 text-rose-100 border-rose-200"></x-input-error>
                </div>
                <div>
                    <x-input-label for="email" value="Email" class="text-white"></x-input-label>
                    <x-text-input id="email" type="email" name="email" class="block mt-1 w-full" :value="old('email')" required></x-text-input>
                    <x-input-error :messages="$errors->get('email')" class="mt-2 text-rose-100 border-rose-200"></x-input-error>
                </div>
                <div>
                    <x-input-label for="password" value="Password (min 8 chars)" class="text-white"></x-input-label>
                    <x-text-input id="password" type="password" name="password" class="block mt-1 w-full" required></x-text-input>
                    <x-input-error :messages="$errors->get('password')" class="mt-2 text-rose-100 border-rose-200"></x-input-error>
                </div>
                <div>
                    <x-input-label for="role" value="Role" class="text-white"></x-input-label>
                    <select id="role" name="role" class="block mt-1 w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm focus:border-[#176b9c] focus:outline-none focus:ring-2 focus:ring-white/50">
                        @foreach ($roles as $r)
                            <option value="{{ $r }}">{{ $r }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('role')" class="mt-2 text-rose-100 border-rose-200"></x-input-error>
                </div>
                <div>
                    <x-input-label for="contact_number" value="Contact Number" class="text-white"></x-input-label>
                    <x-text-input id="contact_number" name="contact_number" class="block mt-1 w-full" :value="old('contact_number')"></x-text-input>
                </div>
                <div class="flex justify-end gap-3">
                    <a href="{{ route('settings.users') }}" class="px-4 py-2 text-white hover:underline">Cancel</a>
                    <button class="rounded-md bg-white px-6 py-2 font-semibold text-[#176b9c] hover:bg-slate-100">Create User</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
