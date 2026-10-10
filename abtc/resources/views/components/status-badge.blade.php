@props(['status'])

@php
    $map = [
        'waiting' => ['label' => 'Waiting', 'class' => 'bg-yellow-100 text-black'],
        'called' => ['label' => 'Called', 'class' => 'bg-sky-100 text-black'],
        'in_progress' => ['label' => 'In Progress', 'class' => 'bg-cyan-100 text-black'],
        'held' => ['label' => 'Held', 'class' => 'bg-gray-200 text-black'],
        'completed' => ['label' => 'Completed', 'class' => 'bg-emerald-100 text-black'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'bg-red-100 text-black'],
        'scheduled' => ['label' => 'Scheduled', 'class' => 'bg-yellow-100 text-black'],
        'administered' => ['label' => 'Administered', 'class' => 'bg-green-100 text-black'],
        'missed' => ['label' => 'Missed', 'class' => 'bg-red-100 text-black'],
        'delivered' => ['label' => 'Delivered', 'class' => 'bg-green-100 text-black'],
        'failed' => ['label' => 'Failed', 'class' => 'bg-red-100 text-black'],
        'pending' => ['label' => 'Pending', 'class' => 'bg-gray-200 text-black'],
    ];
    $info = $map[$status] ?? ['label' => ucfirst(str_replace('_', ' ', $status)), 'class' => 'bg-gray-100 text-black'];
@endphp

<span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full {{ $info['class'] }}">{{ $info['label'] }}</span>
