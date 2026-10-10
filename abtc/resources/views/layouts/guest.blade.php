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

<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
    <div class="relative bg-slate-800 px-6 py-3 text-sm font-semibold tracking-wide text-white/90">
        <div class="absolute inset-0 bg-slate-950/45"></div>
        <div class="relative">Mandaluyong City Animal Bite Treatment Center</div>
    </div>
    <main class="relative flex min-h-[calc(100vh-44px)] items-center justify-center overflow-hidden bg-slate-100 px-5 py-12">
        <div class="absolute inset-x-0 bottom-0 h-40 bg-slate-900/10" style="clip-path:polygon(0 72%,10% 36%,19% 62%,27% 14%,36% 57%,45% 0,55% 46%,65% 18%,74% 62%,85% 12%,100% 52%,100% 100%,0 100%)"></div>
        <div class="absolute -top-24 -right-24 h-64 w-64 rounded-full bg-slate-500/10 blur-3xl"></div>
        <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl shadow-slate-950/15 ring-1 ring-slate-900/5">
            <div class="bg-slate-800 px-8 py-8 text-center">
                <a href="/" class="inline-flex items-center gap-3">
                    <img src="{{ asset('images/abtclogo-transparent.png') }}" alt="ABTC Logo" class="h-14 w-14 object-contain drop-shadow-sm">
                    <span class="text-left leading-none">
                        <span class="block text-lg font-medium text-white/80">City of</span>
                        <span class="block text-xl font-extrabold tracking-tight text-white">Mandaluyong</span>
                    </span>
                </a>
                <div class="mx-auto mt-5 h-px w-12 bg-gradient-to-r from-transparent via-white/60 to-transparent"></div>
                <p class="mt-5 text-sm font-medium tracking-wide text-white/80">Animal Bite Treatment Center</p>
            </div>
            <div class="px-7 py-8 sm:px-9">{{ $slot }}</div>
        </div>
    </main>
</body>
</html>
