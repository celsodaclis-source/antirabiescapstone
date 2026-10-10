<x-app-layout>
    <x-slot name="header">Audit Trail</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4 text-black">

            <div class="flex items-center gap-2">
                <a href="{{ route('settings.index') }}" class="bg-gray-200 text-black px-4 py-2 rounded-md text-sm font-semibold hover:bg-gray-300 transition-colors border border-gray-300">&larr; Back</a>
                <form method="GET" action="{{ route('settings.audit') }}" class="flex gap-2 items-center">
                    <input type="text" name="action" value="{{ $action }}" placeholder="Filter by action (e.g. patient, sms, queue)..." class="block w-full max-w-md border-gray-300 rounded-md shadow-sm">
                    <button class="bg-indigo-600 text-white px-4 py-2 rounded-md">Filter</button>
                </form>
            </div>

            <div class="bg-white shadow rounded-lg overflow-hidden text-black">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-black">
                        <tr><th class="px-4 py-3 text-black">Time</th><th class="px-4 py-3 text-black">User</th><th class="px-4 py-3 text-black">Action</th><th class="px-4 py-3 text-black">Description</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($logs as $log)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap text-black">{{ $log->created_at?->format('M d, Y h:i A') }}</td>
                                <td class="px-4 py-2 text-black">{{ $log->user?->name ?? 'System' }}</td>
                                <td class="px-4 py-2 font-medium text-black">{{ $log->action }}</td>
                                <td class="px-4 py-2 text-black">{{ $log->description }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-black">No audit logs.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="text-black">{{ $logs->links() }}</div>
        </div>
    </div>
</x-app-layout>
