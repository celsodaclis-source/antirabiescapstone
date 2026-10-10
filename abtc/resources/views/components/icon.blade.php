@props(['name', 'class' => 'h-5 w-5'])

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('calendar')
            <rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            @break
        @case('pin')
            <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>
            @break
        @case('home')
            <path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1z"/>
            @break
        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
            @break
        @case('queue')
            <path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/><path d="M3 3h18v18H3z"/>
            @break
        @case('inventory')
            <path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 8v9l9 5 9-5V8M12 13v9"/>
            @break
        @case('reports')
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>
            @break
        @case('vaccine')
            <path d="m14 4 6 6M16 2l6 6M12 6l6 6M5 13l6 6M3 21l4-4M4 12 12 4M8 16l8-8"/><path d="m2 22 3-3"/>
            @break
        @case('message')
            <path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/>
            @break
        @case('file')
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>
            @break
        @case('settings')
            <path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.6a8 8 0 0 1-1.5.9l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.5-.9l-1.7.6-1.4-2.4 1.4-1.1a7 7 0 0 1 0-1.8l-1.4-1.1 1.4-2.4 1.7.6a8 8 0 0 1 1.5-.9l.3-1.8h2.8l.3 1.8a8 8 0 0 1 1.5.9l1.7-.6 1.4 2.4-1.4 1.1a7 7 0 0 1 0 1.8Z"/>
            @break
        @case('user')
            <circle cx="12" cy="8" r="4"/><path d="M4 21v-1a8 8 0 0 1 16 0v1"/>
            @break
        @case('logout')
            <path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>
            @break
        @case('back')
            <path d="m12 19-7-7 7-7M5 12h14"/>
            @break
        @case('template')
            <path d="M8 4h12v16H8zM4 8v12a2 2 0 0 0 2 2h12"/><path d="M11 9h6M11 13h6M11 17h4"/>
            @break
        @case('logs')
            <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
            @break
        @case('link')
            <path d="M10 13a5 5 0 0 0 7.1 0l3-3a5 5 0 0 0-7.1-7.1l-1.7 1.7M14 11a5 5 0 0 0-7.1 0l-3 3a5 5 0 0 0 7.1 7.1l1.7-1.7"/>
            @break
        @case('eye')
            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>
            @break
        @case('eye-off')
            <path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15 15 0 0 1-3 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1.2 0 2.3-.3 3.3-.7"/>
            @break
        @case('save')
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/>
            @break
        @case('wifi')
            <path d="M5 12.5a11 11 0 0 1 14 0M2 9a16 16 0 0 1 20 0M8.5 16a5 5 0 0 1 7 0M12 20h.01"/>
            @break
        @case('radio')
            <path d="M5 8.5a10 10 0 0 1 14 0M8 11.5a6 6 0 0 1 8 0M11 14.5a2 2 0 0 1 2 0M12 18h.01"/><path d="M12 18v3"/>
            @break
        @case('zap')
            <path d="m13 2-3 8H5l6 12 3-8h5L13 2Z"/>
            @break
        @case('shield')
            <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/>
            @break
        @case('layers')
            <path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/>
            @break
        @case('bulb')
            <path d="M9 18h6M10 22h4M8.5 14.5A7 7 0 1 1 15.5 14.5c-.9.8-1.5 1.6-1.5 3.5h-4c0-1.9-.6-2.7-1.5-3.5Z"/>
            @break
        @case('send')
            <path d="m22 2-7 20-4-9-9-4 20-7ZM22 2 11 13"/>
            @break
        @case('close')
            <path d="m18 6-12 12M6 6l12 12"/>
            @break
    @endswitch
</svg>
