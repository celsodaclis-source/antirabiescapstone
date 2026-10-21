@php
    $serving = $station['status'] === 'Serving';
    $assignedName = $role === 'nurse' ? $station['assigned_nurse'] : $station['assigned_doctor'];
@endphp
<article class="rounded-xl border border-slate-200 bg-white p-3 text-center shadow-sm sm:p-4">
    <h4 class="text-sm font-semibold text-slate-800">{{ $station['name'] }}</h4>
    <p class="mt-1 min-h-6 font-mono text-lg font-bold text-blue-900">{{ $station['ticket'] ?? '—' }}</p>
    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $serving ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $station['status'] }}</span>
    <p class="mt-2 min-h-4 truncate text-[11px] text-slate-500" title="{{ $assignedName ? 'Assigned: '.$assignedName : 'No staff assigned' }}">{{ $assignedName ? 'Assigned: '.$assignedName : 'No staff assigned' }}</p>
</article>
