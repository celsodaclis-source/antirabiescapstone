<section>
    <header>
        <h2 class="text-lg font-medium text-black">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-black">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Current Password')"></x-input-label>
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password"></x-text-input>
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2"></x-input-error>
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')"></x-input-label>
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password"></x-text-input>
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2"></x-input-error>
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')"></x-input-label>
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password"></x-text-input>
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2"></x-input-error>
        </div>

        <div class="flex items-center gap-4 text-black">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'password-updated')
                <p data-autodismiss="5000" class="text-sm text-black transition-opacity duration-300">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
