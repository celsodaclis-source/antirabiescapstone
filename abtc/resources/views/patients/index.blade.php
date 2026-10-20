<x-app-layout>
    <x-slot name="header">Patient Registration & Records</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4 text-black">

            {{-- Stats Bar --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border-2 border-[#176b9c] shadow-sm px-5 py-4 flex items-center gap-4">
                    <div class="p-3 rounded-lg">
                        <svg class="w-6 h-6 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-black">{{ $stats['total'] }}</p>
                        <p class="text-xs text-black">Total Patients</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl border-2 border-[#ffad00] shadow-sm px-5 py-4 flex items-center gap-4">
                    <div class="p-3 rounded-lg">
                        <svg class="w-6 h-6 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-black">{{ $stats['today'] }}</p>
                        <p class="text-xs text-black">Registered Today</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl border-2 border-[#00a5e8] shadow-sm px-5 py-4 flex items-center gap-4">
                    <div class="p-3 rounded-lg">
                        <svg class="w-6 h-6 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-black">{{ $stats['priority'] }}</p>
                        <p class="text-xs text-black">Priority (PWD/Senior/Emergency)</p>
                    </div>
                </div>
            </div>

            {{-- Filters & Actions --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-2">
                    <a href="{{ route('dashboard') }}" class="bg-gray-200 text-black px-4 py-2 rounded-md text-sm font-semibold hover:bg-gray-300 transition-colors border border-gray-300">&larr; Back</a>
                    <form method="GET" action="{{ route('patients.index') }}" class="flex-1 flex flex-wrap items-center gap-2">
                    <input type="hidden" name="sort" value="{{ $sortField }}">
                    <input type="hidden" name="direction" value="{{ $sortDir }}">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search by name..." class="block w-full sm:w-48 rounded-md border-slate-300 shadow-sm focus:border-[#176b9c] focus:ring-[#176b9c] text-sm">
                    <select name="barangay" class="rounded-md border-slate-300 shadow-sm focus:border-[#176b9c] focus:ring-[#176b9c] text-sm">
                        <option value="">All Barangays</option>
                        @foreach ($barangays as $b)
                            <option value="{{ $b }}" {{ $filterBarangay === $b ? 'selected' : '' }}>{{ $b }}</option>
                        @endforeach
                    </select>
                    <select name="patient_type" class="rounded-md border-slate-300 shadow-sm focus:border-[#176b9c] focus:ring-[#176b9c] text-sm">
                        <option value="">All Types</option>
                        @foreach ($patientTypes as $type)
                            <option value="{{ $type }}" {{ $filterType === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-md bg-[#176b9c] px-4 py-2 text-white text-sm font-semibold hover:bg-[#125f98]">Filter</button>
                    @if ($search || $filterBarangay || $filterType)
                        <a href="{{ route('patients.index') }}" class="text-sm text-black hover:underline">Clear</a>
                    @endif
                    </form>
                </div>
                @if (Auth::user()->role === 'Admin')
                    <div class="flex gap-2">
                        <a href="{{ route('patients.export.csv') }}?{{ http_build_query(request()->only(['search', 'barangay', 'patient_type'])) }}" class="bg-emerald-600 text-white px-4 py-2 rounded-md font-semibold text-sm hover:bg-emerald-700 text-center whitespace-nowrap">
                            ⬇ Export CSV
                        </a>
                        <a href="{{ route('patients.create') }}" class="bg-[#176b9c] text-white px-5 py-2 rounded-md font-semibold text-sm hover:bg-[#11567f] text-center whitespace-nowrap">
                            + Register New Patient
                        </a>
                    </div>
                @endif
            </div>

            {{-- Desktop Table --}}
            <div class="hidden sm:block overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full min-w-[1000px] table-fixed text-sm">
                    <thead class="bg-sky-50 text-left">
                        <tr>
                            <th class="w-[18%] px-4 py-3 font-semibold text-black">
                                <a href="{{ route('patients.index', array_merge(request()->query(), ['sort' => 'last_name', 'direction' => ($sortField === 'last_name' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 hover:text-[#176b9c]">
                                    Name
                                    @if ($sortField === 'last_name')
                                        <svg class="w-3 h-3 {{ $sortDir === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                    @endif
                                </a>
                            </th>
                            <th class="w-[11%] px-4 py-3 font-semibold text-black">
                                <a href="{{ route('patients.index', array_merge(request()->query(), ['sort' => 'patient_type', 'direction' => ($sortField === 'patient_type' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 hover:text-[#176b9c]">
                                    Type
                                    @if ($sortField === 'patient_type')
                                        <svg class="w-3 h-3 {{ $sortDir === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                    @endif
                                </a>
                            </th>
                            <th class="w-[8%] px-4 py-3 font-semibold text-black">
                                <a href="{{ route('patients.index', array_merge(request()->query(), ['sort' => 'sex', 'direction' => ($sortField === 'sex' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 hover:text-[#176b9c]">
                                    Sex
                                    @if ($sortField === 'sex')
                                        <svg class="w-3 h-3 {{ $sortDir === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                    @endif
                                </a>
                            </th>
                            <th class="w-[14%] px-4 py-3 font-semibold text-black">
                                <a href="{{ route('patients.index', array_merge(request()->query(), ['sort' => 'barangay', 'direction' => ($sortField === 'barangay' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 hover:text-[#176b9c]">
                                    Barangay
                                    @if ($sortField === 'barangay')
                                        <svg class="w-3 h-3 {{ $sortDir === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                    @endif
                                </a>
                            </th>
                            <th class="w-[14%] px-4 py-3 font-semibold text-black">Contact</th>
                            <th class="w-[12%] px-4 py-3 font-semibold text-black">Last Ticket</th>
                            <th class="w-[13%] px-4 py-3 font-semibold text-black">
                                <a href="{{ route('patients.index', array_merge(request()->query(), ['sort' => 'created_at', 'direction' => ($sortField === 'created_at' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 hover:text-[#176b9c]">
                                    Registered
                                    @if ($sortField === 'created_at')
                                        <svg class="w-3 h-3 {{ $sortDir === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                    @else
                                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                    @endif
                                </a>
                            </th>
                            <th class="w-[10%] px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($patients as $p)
                            <tr class="hover:bg-sky-50/70 transition-colors">
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('patients.show', $p) }}" class="text-black hover:underline">{{ $p->fullName() }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($p->isPriority())
                                        <span class="inline-block bg-red-100 text-black text-xs font-semibold px-2 py-0.5 rounded-full">{{ $p->patientTypeLabel() }}</span>
                                    @else
                                        <span class="text-black/60 text-xs">Regular</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-black">{{ $p->sex }}</td>
                                <td class="px-4 py-3 text-black">{{ $p->barangay }}</td>
                                <td class="px-4 py-3 text-black">{{ $p->contact_number ?? '—' }}</td>
                                <td class="px-4 py-3 text-black">{{ $p->latestTicket?->ticket_number ?? '—' }}</td>
                                <td class="px-4 py-3 text-black">{{ $p->created_at->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-right flex items-center justify-end gap-2">
                                    <a href="{{ route('patients.show', $p) }}" class="font-semibold text-[#176b9c] hover:underline text-xs">View</a>
                                    <a href="{{ route('patients.edit', $p) }}" class="font-semibold text-amber-700 hover:underline text-xs">Edit</a>
                                    <form method="POST" action="{{ route('patients.destroy', $p) }}" onsubmit="return confirm('Are you sure you want to delete {{ $p->fullName() }}? This cannot be undone.');" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="font-semibold text-red-600 hover:underline text-xs">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-6 text-center text-black">No patients found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards --}}
            <div class="sm:hidden space-y-3">
                @forelse ($patients as $p)
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 hover:border-[#176b9c]/30 transition-colors">
                        <div class="flex justify-between items-start">
                            <a href="{{ route('patients.show', $p) }}" class="flex-1">
                                <p class="font-semibold text-black">{{ $p->fullName() }}</p>
                                <p class="text-xs text-black mt-0.5">{{ $p->sex }} &middot; {{ $p->barangay }}</p>
                            </a>
                            @if ($p->isPriority())
                                <span class="bg-red-100 text-black text-xs font-semibold px-2 py-0.5 rounded-full">{{ $p->patientTypeLabel() }}</span>
                            @endif
                        </div>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-black">
                            <span>Contact: {{ $p->contact_number ?? '—' }}</span>
                            <span>Ticket: {{ $p->latestTicket?->ticket_number ?? '—' }}</span>
                            <span>Registered: {{ $p->created_at->format('M d, Y') }}</span>
                        </div>
                        <div class="mt-2 flex gap-2">
                            <a href="{{ route('patients.show', $p) }}" class="text-xs font-semibold text-[#176b9c] hover:underline">View</a>
                            <a href="{{ route('patients.edit', $p) }}" class="text-xs font-semibold text-amber-700 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('patients.destroy', $p) }}" onsubmit="return confirm('Delete {{ $p->fullName() }}? This cannot be undone.');" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 text-center text-black">No patients found.</div>
                @endforelse
            </div>

            {{-- Pagination Info & Links --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-sm text-black">
                <p class="text-black">
                    Showing <span class="font-semibold">{{ $patients->firstItem() }}</span>
                    to <span class="font-semibold">{{ $patients->lastItem() }}</span>
                    of <span class="font-semibold">{{ $patients->total() }}</span> results
                </p>
                <div class="text-black">{{ $patients->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
