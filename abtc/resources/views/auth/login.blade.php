<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')"></x-auth-session-status>

    <h2 class="text-center text-xl font-bold text-slate-800 mb-6">Sign in to your account</h2>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')"></x-input-label>
            <div class="relative mt-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-slate-400" viewbox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z"/><path d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z"/></svg>
                </div>
                <x-text-input id="email" class="block w-full pl-9" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="you@example.com"></x-text-input>
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2"></x-input-error>
        </div>

        <!-- Password -->
        <div class="mt-5">
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')"></x-input-label>
                @if (Route::has('password.request'))
                    <a class="text-xs font-medium text-[#176b9c] hover:text-[#11567f] transition-colors" href="{{ route('password.request') }}">
                        {{ __('Forgot?') }}
                    </a>
                @endif
            </div>
            <div class="relative mt-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-slate-400" viewbox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd"/></svg>
                </div>
                <x-text-input id="password" class="block w-full pl-9 pe-16" type="password" name="password" required autocomplete="current-password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"></x-text-input>
                <button type="button" id="password-toggle" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 transition-colors" aria-label="Show password" aria-pressed="false">
                    <svg id="eye-open" class="h-5 w-5" viewbox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                    <svg id="eye-closed" class="hidden h-5 w-5" viewbox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.1A10.6 10.6 0 0 1 12 5c6 0 9.5 7 9.5 7a16.9 16.9 0 0 1-3.1 3.8M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 7 9.5 7a9.8 9.8 0 0 0 3.1-.5"></path></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2"></x-input-error>
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between mt-6">
            <label for="remember_me" class="inline-flex items-center gap-2 cursor-pointer">
                <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-[#176b9c] shadow-sm focus:ring-[#176b9c] focus:ring-offset-0 cursor-pointer" name="remember">
                <span class="text-sm text-slate-600 select-none">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="mt-6">
            <x-primary-button class="w-full justify-center py-3 text-sm">
                {{ __('Log in') }}
            </x-primary-button>
        </div>

        <p class="mt-6 text-center text-sm text-slate-500">
            {{ __("Don't have an account?") }}
            <a class="font-semibold text-[#176b9c] hover:text-[#11567f] transition-colors" href="{{ route('register') }}">
                {{ __('Create one') }}
            </a>
        </p>
    </form>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const btn = document.getElementById('password-toggle');
            if (btn) {
                btn.addEventListener('click', function () {
                    const input = document.getElementById('password');
                    const showing = input.type === 'text';
                    input.type = showing ? 'password' : 'text';
                    document.getElementById('eye-open').classList.toggle('hidden', showing);
                    document.getElementById('eye-closed').classList.toggle('hidden', !showing);
                    this.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                    this.setAttribute('aria-pressed', String(!showing));
                });
            }
        });
    </script>
    @endpush
</x-guest-layout>
