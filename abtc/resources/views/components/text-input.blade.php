@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {{ $attributes->merge(['class' => 'block w-full rounded-lg border-slate-300 bg-white/80 px-4 py-2.5 text-sm text-slate-800 shadow-sm transition duration-150 ease-in-out placeholder:text-slate-400 hover:border-slate-400 focus:border-[#1976ba] focus:ring-2 focus:ring-[#1976ba]/20 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500']) }}>
