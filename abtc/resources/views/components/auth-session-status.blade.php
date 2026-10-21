@props(['status'])

@if ($status)
    <div data-autodismiss="5000" role="status" {{ $attributes->merge(['class' => 'flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 transition-opacity duration-300']) }}>
        <svg class="h-4 w-4 shrink-0 text-emerald-500" viewbox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
        {{ $status }}
    </div>
@endif
