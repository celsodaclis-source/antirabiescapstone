<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/abtclogo-removebg-preview.png') }}">
    <title>Animal Bite Treatment Center</title>

    <script src="{{ asset('js/tailwindcss-cdn.js') }}"></script>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Noto Sans", sans-serif; }
    </style>
    @stack('scripts')
</head>

<body class="bg-slate-100 font-sans antialiased text-slate-900">
    @php
        $isAdmin = Auth::user()->role === 'Admin';
        $isStaff = Auth::user()->role === 'Staff';
        $isNurse = Auth::user()->role === 'Nurse';
        $homeRoute = $isStaff ? route('staff.dashboard') : route('dashboard');
    @endphp
    <div class="bg-[#142b49] text-slate-100">
        <div class="mx-auto flex max-w-screen-2xl items-center justify-between gap-4 px-4 py-2 text-xs sm:px-6 xl:px-8">
            <div class="flex min-w-0 items-center gap-3 sm:gap-5">
                <span class="inline-flex items-center gap-1.5 whitespace-nowrap"><x-icon name="calendar" class="h-3.5 w-3.5 text-blue-200" /><span id="portal-date">{{ now()->format('D, M j, Y') }}</span></span>
                <span class="hidden h-4 w-px bg-white/20 sm:block"></span>
                <span class="inline-flex items-center gap-1.5 whitespace-nowrap"><x-icon name="clock" class="h-3.5 w-3.5 text-blue-200" /><span id="portal-time">{{ now()->format('h:i:s A') }}</span></span>
                <span class="hidden h-4 w-px bg-white/20 md:block"></span>
                <span class="hidden items-center gap-1.5 truncate md:inline-flex"><x-icon name="pin" class="h-3.5 w-3.5 text-blue-200" />Mandaluyong City ABTC</span>
            </div>
            <a href="{{ $isStaff ? route('staff.dashboard') : route('profile.edit') }}" class="inline-flex shrink-0 items-center gap-2 font-medium text-white/90 hover:text-white"><x-icon name="user" class="h-4 w-4" /><span>{{ Auth::user()->name }} <span class="ml-1 text-white/60">{{ Auth::user()->role }}</span></span></a>
        </div>
    </div>

    <header class="border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-screen-2xl flex-wrap items-center gap-x-6 px-4 sm:px-6 xl:px-8">
            <a href="{{ $homeRoute }}" class="flex shrink-0 items-center gap-2.5 py-3 text-slate-900">
                <img src="{{ asset('images/abtclogo-removebg-preview.png') }}" alt="Mandaluyong City seal" class="h-11 w-11 object-contain">
                <span class="leading-tight"><span class="block text-xs font-medium text-slate-600">City of</span><span class="block text-lg font-extrabold">Mandaluyong</span></span>
            </a>
            <nav aria-label="Main navigation" class="order-3 -mx-4 flex basis-[calc(100%+2rem)] items-center overflow-x-auto border-t border-slate-100 px-3 lg:order-none lg:mx-0 lg:basis-auto lg:flex-1 lg:border-0 lg:px-0">
                <div class="flex min-w-max items-center gap-1">
                    @php
                        $navItems = $isStaff ? [
                            ['staff.dashboard', 'Dashboard', 'staff.dashboard', 'home'],
                            ['staff.patients.create', 'Patient Registration', 'staff.patients.create', 'users'],
                            ['staff.patients.search', 'Patient Search', 'staff.patients.search', 'users'],
                            ['staff.tickets.create', 'Create Ticket', 'staff.tickets.*', 'queue'],
                            ['queue.index', 'Queue', 'queue.*', 'queue'],
                        ] : ($isNurse ? [
                            ['dashboard', 'Nurse Dashboard', 'dashboard', 'home'], ['patients.index', 'Patients', 'patients.*', 'users'],
                            ['nurse.station.show', 'My Queue', 'nurse.station.*', 'queue'],
                            ['inventory.index', 'Inventory', 'inventory.*', 'inventory'], ['reports.index', 'Reports', 'reports.*', 'reports'],
                            ['schedules.index', 'Vaccination', 'schedules.*', 'vaccine'],
                        ] : (Auth::user()->role === 'Doctor' ? [
                            ['dashboard', 'Doctor Dashboard', 'dashboard', 'home'], ['patients.index', 'Patients', 'patients.*', 'users'],
                            ['doctor.station.show', 'My Queue', 'doctor.station.*', 'queue'],
                            ['queue.index', 'Patient Queue', 'queue.*', 'queue'], ['schedules.index', 'Vaccination', 'schedules.*', 'vaccine'],
                        ] : [
                            ['dashboard', 'Home', 'dashboard', 'home'], ['queue-monitoring.index', 'Queue Monitoring', 'queue-monitoring.*', 'queue'], ['patients.index', 'Patients', 'patients.*', 'users'],
                            ['inventory.index', 'Inventory', 'inventory.*', 'inventory'],
                            ['reports.index', 'Reports', 'reports.*', 'reports'], ['schedules.index', 'Vaccination', 'schedules.*', 'vaccine'],
                            ['sms.index', 'SMS', 'sms.*', 'message'],
                        ]));
                    @endphp
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

    @php $pageTitle = isset($header) ? trim(strip_tags($header)) : trim($__env->yieldContent('title', 'N-VISION')); @endphp
    <section class="bg-[#102c4e] text-white">
        <div class="mx-auto flex max-w-screen-2xl items-center justify-between gap-6 px-4 py-6 sm:px-6 xl:px-8">
            <div><p class="text-xs font-semibold uppercase tracking-wide text-blue-200">Animal Bite Treatment Center</p><h1 class="mt-1 text-2xl font-bold sm:text-3xl">{{ $pageTitle }}</h1></div>
            <p class="hidden shrink-0 text-sm text-slate-300 sm:block"><a href="{{ $homeRoute }}" class="hover:text-white">Home</a><span class="mx-2 text-slate-500">/</span>{{ $pageTitle }}</p>
        </div>
    </section>

    <div class="min-h-screen text-black">
        <main class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 xl:px-8">
            @yield('header')
            @if (session('status'))
                <div data-autodismiss="5000" role="status" class="mb-4 rounded bg-green-100 border border-green-400 px-4 py-3 text-black transition-opacity duration-300">
                    {{ session('status') }}
                </div>
            @endif
            @yield('content', $slot ?? '')
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

        document.querySelectorAll('[data-autodismiss]').forEach((message) => {
            const delay = Number(message.dataset.autodismiss) || 5000;
            window.setTimeout(() => {
                message.classList.add('opacity-0');
                window.setTimeout(() => message.remove(), 300);
            }, delay);
        });
    </script>
</body>
</html>
