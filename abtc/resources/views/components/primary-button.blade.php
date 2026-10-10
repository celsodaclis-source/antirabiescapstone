<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-[#176b9c] border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-wider hover:bg-[#11567f] focus:bg-[#11567f] active:bg-[#0d476b] focus:outline-none focus:ring-2 focus:ring-[#176b9c] focus:ring-offset-2 transition-all duration-150 ease-in-out hover:scale-[1.02] active:scale-[0.98] shadow-sm hover:shadow-md']) }}>
    {{ $slot }}
</button>
