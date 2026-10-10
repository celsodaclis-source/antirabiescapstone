<x-app-layout>
    <x-slot name="header">Manage Users</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4 text-black">

            <div class="flex justify-between items-center text-black">
                <a href="{{ route('settings.index') }}" class="bg-gray-200 text-black px-4 py-2 rounded-md text-sm font-semibold hover:bg-gray-300 transition-colors border border-gray-300">&larr; Back</a>
                <a href="{{ route('settings.users.create') }}" class="bg-green-600 text-white px-5 py-2 rounded-md font-semibold hover:bg-green-700">+ Add User</a>
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow text-black">
                <table class="w-full min-w-[900px] table-fixed text-sm">
                    <thead class="bg-gray-50 text-left text-black">
                        <tr>
                            <th class="w-[18%] px-4 py-3 font-semibold text-black">Name</th>
                            <th class="w-[24%] px-4 py-3 font-semibold text-black">Email</th>
                            <th class="w-[15%] px-4 py-3 font-semibold text-black">Role</th>
                            <th class="w-[15%] px-4 py-3 font-semibold text-black">Contact</th>
                            <th class="w-[28%] px-4 py-3 font-semibold text-black">Reset Password</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $u)
                            <tr class="align-top">
                                <td class="px-4 py-3 font-medium text-black">{{ $u->name }}</td>
                                <td class="px-4 py-3 text-black">{{ $u->email }}</td>
                                <td class="px-4 py-3 text-black"><x-status-badge :status="$u->role === 'Admin' ? 'administered' : 'scheduled'"></x-status-badge> {{ $u->role }}</td>
                                <td class="px-4 py-3 text-black">{{ $u->contact_number ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('settings.users.password.reset', $u) }}" class="flex items-center gap-2" onsubmit="return confirm('Reset the password for {{ $u->name }}?');">
                                        @csrf
                                        @method('PUT')
                                        <label class="sr-only" for="password-{{ $u->id }}">New password for {{ $u->name }}</label>
                                        <input id="password-{{ $u->id }}" type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="New password (8+ chars)" class="min-w-0 flex-1 rounded-md border-slate-300 px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-[#176b9c] focus:ring-[#176b9c]">
                                        <button type="submit" class="shrink-0 rounded-md bg-[#176b9c] px-3 py-2 text-xs font-semibold text-white hover:bg-[#11567f]">Reset</button>
                                    </form>
                                    <p class="mt-1 text-xs text-slate-500">Admin only. The new password must have at least 8 characters.</p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="text-black">{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
