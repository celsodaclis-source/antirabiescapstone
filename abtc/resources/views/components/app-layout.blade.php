<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/abtclogo-removebg-preview.png') }}">
    <title>Animal Bite Treatment Center</title>

    <script src="{{ asset('js/tailwindcss-cdn.js') }}"></script>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite('resources/js/app.js')
    @endif
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Noto Sans", sans-serif; }
    </style>
    @stack('scripts')
</head>

    <body class="bg-slate-100 font-sans antialiased text-slate-900">
    @php $isAdmin = Auth::user()->role === 'Admin'; @endphp
    <div class="bg-[#142b49] text-slate-100">
        <div class="mx-auto flex max-w-screen-2xl items-center justify-between gap-4 px-4 py-2 text-xs sm:px-6 xl:px-8">
            <div class="flex min-w-0 items-center gap-3 sm:gap-5">
                <span class="inline-flex items-center gap-1.5 whitespace-nowrap"><x-icon name="calendar" class="h-3.5 w-3.5 text-blue-200" /><span id="portal-date">{{ now()->format('D, M j, Y') }}</span></span>
                <span class="hidden h-4 w-px bg-white/20 sm:block"></span>
                <span class="inline-flex items-center gap-1.5 whitespace-nowrap"><x-icon name="clock" class="h-3.5 w-3.5 text-blue-200" /><span id="portal-time">{{ now()->format('h:i:s A') }}</span></span>
                <span class="hidden h-4 w-px bg-white/20 md:block"></span>
                <span class="hidden items-center gap-1.5 truncate md:inline-flex"><x-icon name="pin" class="h-3.5 w-3.5 text-blue-200" />Mandaluyong City ABTC</span>
            </div>
            <a href="{{ route('profile.edit') }}" class="inline-flex shrink-0 items-center gap-2 font-medium text-white/90 hover:text-white"><x-icon name="user" class="h-4 w-4" /><span>{{ Auth::user()->name }} <span class="ml-1 text-white/60">{{ Auth::user()->role }}</span></span></a>
        </div>
    </div>

    <header class="border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-screen-2xl flex-wrap items-center gap-x-6 px-4 sm:px-6 xl:px-8">
            <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-2.5 py-3 text-slate-900">
                <img src="{{ asset('images/abtclogo-removebg-preview.png') }}" alt="Mandaluyong City seal" class="h-11 w-11 object-contain">
                <span class="leading-tight">
                    <span class="block text-xs font-medium text-slate-600">City of</span>
                    <span class="block text-lg font-extrabold">Mandaluyong</span>
                </span>
            </a>

            <nav aria-label="Main navigation" class="order-3 -mx-4 flex basis-[calc(100%+2rem)] items-center overflow-x-auto border-t border-slate-100 px-3 lg:order-none lg:mx-0 lg:basis-auto lg:flex-1 lg:border-0 lg:px-0">
                <div class="flex min-w-max items-center gap-1">
                    @php $navItems = [
                        ['dashboard', 'Home', 'dashboard', 'home'], ['patients.index', 'Patients', 'patients.*', 'users'],
                        ['queue.index', 'Queue', 'queue.*', 'queue'], ['inventory.index', 'Inventory', 'inventory.*', 'inventory'],
                        ['reports.index', 'Reports', 'reports.*', 'reports'], ['schedules.index', 'Vaccination', 'schedules.*', 'vaccine'],
                        ['sms.index', 'SMS', 'sms.*', 'message'], ['files.index', 'Files', 'files.*', 'file'],
                    ]; @endphp
                    @foreach ($navItems as [$routeName, $label, $activeRoute, $icon])
                        <a href="{{ route($routeName) }}" class="inline-flex items-center gap-1.5 whitespace-nowrap border-b-2 px-2.5 py-4 text-xs font-semibold transition-colors sm:px-3 sm:text-sm {{ request()->routeIs($activeRoute) ? 'border-blue-600 bg-blue-50/70 text-blue-700' : 'border-transparent text-slate-700 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-950' }}"><x-icon :name="$icon" class="h-4 w-4" />{{ $label }}</a>
                    @endforeach
                    @if ($isAdmin)
                        <a href="{{ route('settings.index') }}" class="inline-flex items-center gap-1.5 whitespace-nowrap border-b-2 px-2.5 py-4 text-xs font-semibold transition-colors sm:px-3 sm:text-sm {{ request()->routeIs('settings.*') ? 'border-blue-600 bg-blue-50/70 text-blue-700' : 'border-transparent text-slate-700 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-950' }}"><x-icon name="settings" class="h-4 w-4" />Settings</a>
                    @endif
                </div>
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="ml-auto shrink-0 py-3 lg:ml-0">
                @csrf
                <button class="inline-flex items-center gap-1.5 whitespace-nowrap text-sm font-semibold text-slate-600 hover:text-blue-700"><x-icon name="logout" class="h-4 w-4" />Log out</button>
            </form>
        </div>
    </header>

    <section class="relative overflow-hidden bg-[#102c4e] text-white">
        <div class="pointer-events-none absolute inset-0 opacity-30" aria-hidden="true" style="background-image: repeating-linear-gradient(105deg, transparent 0, transparent 44px, rgba(125,177,226,.08) 45px, transparent 46px);"></div>
        <div class="relative mx-auto flex max-w-screen-2xl items-center gap-4 px-4 py-4 sm:gap-5 sm:px-6 sm:py-5 xl:px-8">
            @if (request()->routeIs('sms.*'))
                <div class="hidden h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#234a70] text-white sm:flex"><x-icon name="message" class="h-6 w-6" /></div>
            @endif
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-blue-200">{{ request()->routeIs('sms.*') ? 'SMS Gateway' : 'Animal Bite Treatment Center' }}</p>
                <h1 class="mt-0.5 text-xl font-bold leading-tight sm:text-2xl">{{ isset($header) ? trim(strip_tags($header)) : 'N-VISION' }}</h1>
                @if (request()->routeIs('sms.settings'))
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-200 sm:text-sm">Configure and manage the SMS Gateway integration for appointment reminders, notifications, and alerts.</p>
                @endif
            </div>
            @if (request()->routeIs('sms.*'))
                <div class="relative hidden h-[76px] w-48 shrink-0 items-center justify-center md:flex lg:w-56" aria-hidden="true">
                    <div class="absolute left-10 top-1 h-[68px] w-14 rounded-t-lg border border-blue-300/70 bg-[#183d64] p-1 shadow-lg"><div class="h-full rounded-t bg-[#e8f2fc] pt-3"><span class="mx-auto block h-1 w-5 rounded-full bg-blue-300"></span><span class="mx-1 mt-2 block h-1 rounded bg-blue-200"></span><span class="mx-1 mt-1 block h-1 rounded bg-blue-100"></span></div></div>
                    <div class="absolute right-1 top-3 rounded-lg bg-blue-600 px-3 py-2 shadow-lg"><span class="mb-1 block h-1.5 w-10 rounded bg-white"></span><span class="mb-1 block h-1.5 w-8 rounded bg-blue-200"></span><span class="block h-1.5 w-11 rounded bg-blue-200"></span></div>
                    <div class="absolute bottom-0 left-8 rounded-md bg-blue-100 px-2.5 py-2 shadow-lg"><span class="mb-1 block h-1.5 w-7 rounded bg-blue-400"></span><span class="block h-1.5 w-10 rounded bg-blue-300"></span></div>
                    <div class="absolute left-1 top-5 rotate-[-20deg] text-blue-400"><x-icon name="send" class="h-6 w-6" /></div>
                    <div class="absolute left-0 top-8 h-8 w-8 rounded-full border border-dashed border-blue-400/80"></div>
                </div>
                <p class="hidden shrink-0 items-center gap-2 text-[11px] text-slate-300 lg:flex"><a href="{{ route('dashboard') }}" class="hover:text-white">Home</a><span>/</span><a href="{{ route('sms.index') }}" class="hover:text-white">SMS</a><span>/</span><span class="font-semibold text-white">{{ isset($header) ? trim(strip_tags($header)) : 'SMS' }}</span></p>
            @else
                <p class="hidden shrink-0 text-sm text-slate-300 sm:block"><a href="{{ route('dashboard') }}" class="hover:text-white">Home</a><span class="mx-2 text-slate-500">/</span><span>{{ isset($header) ? trim(strip_tags($header)) : 'N-VISION' }}</span></p>
            @endif
        </div>
    </section>

    <div class="min-h-screen text-slate-900">
        <main class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 xl:px-8">
            @if (session('status'))
                <div class="mb-4">
                    <div class="bg-green-100 border border-green-400 text-black px-4 py-3 rounded">
                        {{ session('status') }}
                    </div>
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>

    <footer class="border-t border-slate-200 bg-white py-6 text-center text-xs font-medium text-slate-600">
        Mandaluyong City Animal Bite Treatment Center &middot; N-VISION Management System
    </footer>

    <script>
        const portalDate = document.getElementById('portal-date');
        const portalTime = document.getElementById('portal-time');
        setInterval(() => {
            const now = new Date();
            portalDate.textContent = now.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
            portalTime.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }, 1000);
    </script>
</body>
</html>
