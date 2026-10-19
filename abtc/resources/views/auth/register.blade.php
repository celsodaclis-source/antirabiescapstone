<x-guest-layout>
    <h2 class="text-center text-xl font-bold text-slate-800 mb-6">Create a new account</h2>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')"></x-input-label>
            <div class="relative mt-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-slate-400" viewbox="0 0 20 20" fill="currentColor"><path d="M10 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-7 9a7 7 0 0 1 14 0H3Z"/></svg>
                </div>
                <x-text-input id="name" class="block w-full pl-9" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="Full name"></x-text-input>
            </div>
            <x-input-error :messages="$errors->get('name')" class="mt-2"></x-input-error>
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')"></x-input-label>
            <div class="relative mt-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-slate-400" viewbox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z"/><path d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z"/></svg>
                </div>
                <x-text-input id="email" class="block w-full pl-9" type="email" name="email" :value="old('email')" autocomplete="username" placeholder="you@example.com"></x-text-input>
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2"></x-input-error>
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')"></x-input-label>
            <div class="relative mt-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-slate-400" viewbox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd"/></svg>
                </div>
                <x-text-input id="password" class="block w-full pl-9 pe-16" type="password" name="password" required autocomplete="new-password"></x-text-input>
                <button type="button" id="password-toggle" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 transition-colors" aria-label="Show password" aria-pressed="false">
                    <svg id="eye-open" class="h-5 w-5" viewbox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                    <svg id="eye-closed" class="hidden h-5 w-5" viewbox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.1A10.6 10.6 0 0 1 12 5c6 0 9.5 7 9.5 7a16.9 16.9 0 0 1-3.1 3.8M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 7 9.5 7a9.8 9.8 0 0 0 3.1-.5"></path></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2"></x-input-error>
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')"></x-input-label>
            <div class="relative mt-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-slate-400" viewbox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd"/></svg>
                </div>
                <x-text-input id="password_confirmation" class="block w-full pl-9 pe-16" type="password" name="password_confirmation" required autocomplete="new-password"></x-text-input>
                <button type="button" id="confirm-password-toggle" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 transition-colors" aria-label="Show password" aria-pressed="false">
                    <svg id="confirm-eye-open" class="h-5 w-5" viewbox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                    <svg id="confirm-eye-closed" class="hidden h-5 w-5" viewbox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.1A10.6 10.6 0 0 1 12 5c6 0 9.5 7 9.5 7a16.9 16.9 0 0 1-3.1 3.8M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 7 9.5 7a9.8 9.8 0 0 0 3.1-.5"></path></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2"></x-input-error>
        </div>

        <!-- Contact Number -->
        <div class="mt-4">
            <x-input-label for="contact_number" :value="__('Contact Number')"></x-input-label>
            <div class="relative mt-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-slate-400" viewbox="0 0 20 20" fill="currentColor"><path d="M2 3.5A1.5 1.5 0 0 1 3.5 2h1.148a1.5 1.5 0 0 1 1.465 1.175l.716 3.223a1.5 1.5 0 0 1-1.052 1.767l-.933.267c-.41.117-.643.555-.48.95a11.542 11.542 0 0 0 6.254 6.254c.395.163.833-.07.95-.48l.267-.933a1.5 1.5 0 0 1 1.767-1.052l3.223.716A1.5 1.5 0 0 1 18 15.352V16.5a1.5 1.5 0 0 1-1.5 1.5H15c-1.149 0-2.263-.15-3.326-.43A13.022 13.022 0 0 1 2.43 8.326 13.019 13.019 0 0 1 2 5V3.5Z"/></svg>
                </div>
                <x-text-input id="contact_number" class="block w-full pl-9" type="text" name="contact_number" :value="old('contact_number')" placeholder="09XXXXXXXXX"></x-text-input>
            </div>
            <x-input-error :messages="$errors->get('contact_number')" class="mt-2"></x-input-error>
        </div>

        <p class="mt-4 text-sm text-slate-600">New accounts are created with Staff access. Contact an administrator if you need a different role.</p>

        <div class="flex items-center justify-between mt-6">
            <a class="text-sm font-medium text-[#176b9c] hover:text-[#11567f] transition-colors" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button>
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggles = ['password-toggle', 'confirm-password-toggle'];
            toggles.forEach((id) => {
                const btn = document.getElementById(id);
                if (!btn) return;
                btn.addEventListener('click', function () {
                    const prefix = id === 'password-toggle' ? '' : 'confirm-';
                    const input = document.getElementById(id === 'password-toggle' ? 'password' : 'password_confirmation');
                    const showing = input.type === 'text';
                    input.type = showing ? 'password' : 'text';
                    document.getElementById(prefix + 'eye-open').classList.toggle('hidden', showing);
                    document.getElementById(prefix + 'eye-closed').classList.toggle('hidden', !showing);
                    this.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                    this.setAttribute('aria-pressed', String(!showing));
                });
            });
        });
    </script>
    @endpush
</x-guest-layout>
