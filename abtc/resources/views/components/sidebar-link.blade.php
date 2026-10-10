@props(['href' => '#', 'active' => false])

@php
    $classes = $active
        ? 'flex items-center px-3 py-2 rounded-md bg-sky-100 text-black font-semibold'
        : 'flex items-center px-3 py-2 rounded-md text-black hover:bg-sky-50 hover:text-black';
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
