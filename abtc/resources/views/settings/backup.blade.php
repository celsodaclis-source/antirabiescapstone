<x-app-layout>
    <x-slot name="header">Backup & Recovery</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 text-black">

            <div class="flex items-center gap-2">
                <a href="{{ route('settings.index') }}" class="bg-gray-200 text-black px-4 py-2 rounded-md text-sm font-semibold hover:bg-gray-300 transition-colors border border-gray-300">&larr; Back</a>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow rounded-lg p-4 text-center text-black">
                    <p class="text-sm text-gray-500">Total Backups</p>
                    <p class="text-2xl font-bold">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white shadow rounded-lg p-4 text-center text-black">
                    <p class="text-sm text-gray-500">Total Size</p>
                    <p class="text-2xl font-bold">{{ number_format($stats['total_size'] / 1048576, 2) }} MB</p>
                </div>
                <div class="bg-white shadow rounded-lg p-4 text-center text-black">
                    <p class="text-sm text-gray-500">Last Backup</p>
                    <p class="text-2xl font-bold">{{ $stats['last_backup']?->created_at?->format('M d, Y') ?? 'None' }}</p>
                </div>
            </div>

            {{-- Create Backup --}}
            <div class="bg-white shadow rounded-lg p-6 text-black">
                <h3 class="font-semibold text-lg mb-4">Create New Backup</h3>
                <p class="text-sm text-gray-600 mb-4">Creates a snapshot of the current database. This can be used to restore the system in case of data loss or corruption.</p>
                <form method="POST" action="{{ route('settings.backup.store') }}" class="flex gap-3 items-end">
                    @csrf
                    <div class="flex-1">
                        <label class="text-sm font-medium text-gray-700">Notes (optional)</label>
                        <input type="text" name="notes" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm" placeholder="e.g. Before major update...">
                    </div>
                    <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-md font-semibold hover:bg-blue-700 transition-colors" onclick="return confirm('Create a new database backup?')">
                        Create Backup
                    </button>
                </form>
            </div>

            {{-- Backup List --}}
            <div class="bg-white shadow rounded-lg overflow-hidden text-black">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="font-semibold text-lg">Backup History</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-black">
                        <tr>
                            <th class="px-4 py-3 text-black">Date</th>
                            <th class="px-4 py-3 text-black">Filename</th>
                            <th class="px-4 py-3 text-black">Size</th>
                            <th class="px-4 py-3 text-black">Created By</th>
                            <th class="px-4 py-3 text-black">Notes</th>
                            <th class="px-4 py-3 text-black">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($backups as $backup)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap text-black">{{ $backup->created_at?->format('M d, Y h:i A') }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-black">{{ $backup->filename }}</td>
                                <td class="px-4 py-2 text-black">{{ $backup->formattedSize() }}</td>
                                <td class="px-4 py-2 text-black">{{ $backup->user?->name ?? 'System' }}</td>
                                <td class="px-4 py-2 text-black">{{ $backup->notes ?? '-' }}</td>
                                <td class="px-4 py-2 text-black">
                                    <div class="flex gap-2">
                                        <a href="{{ route('settings.backup.download', $backup) }}" class="text-blue-600 hover:underline text-xs font-semibold">Download</a>
                                        <form method="POST" action="{{ route('settings.backup.destroy', $backup) }}" class="inline">
                                            @csrf @method('DELETE')
                                            <button class="text-red-600 hover:underline text-xs font-semibold" onclick="return confirm('Delete this backup? This cannot be undone.')">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No backups yet. Create your first backup above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $backups->links() }}</div>

            {{-- Recovery Information --}}
            <div class="bg-white shadow rounded-lg p-6 text-black">
                <h3 class="font-semibold text-lg mb-4">Recovery Information</h3>
                <div class="space-y-3 text-sm text-gray-700">
                    <p><strong>What is backed up:</strong> All patient records, bite incidents, vaccination schedules, queue tickets, inventory, SMS logs, audit logs, user accounts, and file maintenance records.</p>
                    <p><strong>How to restore:</strong> Stop the application, replace the <code class="bg-gray-100 px-1 rounded">database/database.sqlite</code> file with the downloaded backup, then restart the application.</p>
                    <p><strong>Backup frequency:</strong> It is recommended to create backups daily or before any major system changes.</p>
                    <p><strong>Storage:</strong> Backups are stored in the <code class="bg-gray-100 px-1 rounded">storage/backups/</code> directory. Download important backups to an external location for safekeeping.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
