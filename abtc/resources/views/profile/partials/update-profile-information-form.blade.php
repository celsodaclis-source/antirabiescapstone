<section>
    <header>
        <h2 class="text-lg font-medium text-black">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-black">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')"></x-input-label>
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name"></x-text-input>
            <x-input-error class="mt-2" :messages="$errors->get('name')"></x-input-error>
        </div>

        <div class="text-black">
            <x-input-label for="email" :value="__('Email')"></x-input-label>
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username"></x-text-input>
            <x-input-error class="mt-2" :messages="$errors->get('email')"></x-input-error>

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="text-black">
                    <p class="text-sm mt-2 text-black">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-black hover:text-black rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p data-autodismiss="5000" class="mt-2 font-medium text-sm text-black transition-opacity duration-300">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4 text-black">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

                    @if (session('status') === 'profile-updated')
                <p data-autodismiss="5000" class="text-sm text-black transition-opacity duration-300">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
