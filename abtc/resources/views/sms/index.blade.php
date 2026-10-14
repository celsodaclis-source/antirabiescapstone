<x-app-layout>
    <x-slot name="header">SMS Reminders</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 text-black">

            <div class="flex flex-wrap items-center gap-2 text-black">
                <a href="{{ route('dashboard') }}" class="bg-gray-200 text-black px-4 py-2 rounded-md hover:bg-gray-300 transition-colors">&larr; Back</a>
                <a href="{{ route('sms.template') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">Edit Message Template</a>
                <a href="{{ route('sms.settings') }}" class="bg-teal-600 text-white px-4 py-2 rounded-md hover:bg-teal-700">Gateway Settings</a>
                <form method="POST" action="{{ route('sms.send-overdue') }}">@csrf
                    <button class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">Send Overdue Reminders</button>
                </form>
                <form method="POST" action="{{ route('sms.test-connection') }}" class="inline">
                    @csrf
                    <button class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">Test Connection</button>
                </form>
                <span class="text-sm text-black ml-auto">Auto-send lead time: {{ $leadHours }}h before scheduled dose</span>
            </div>

            @if (session('gateway_status'))
                <div class="bg-{{ session('gateway_status.connected') ? 'green' : 'red' }}-100 border border-{{ session('gateway_status.connected') ? 'green' : 'red' }}-400 text-black px-4 py-3 rounded">
                    <strong>SMS Gateway:</strong> {{ session('gateway_status.message') }}
                    @if (session('gateway_status.device'))
                        <span class="ml-2">Device ID: <code>{{ session('gateway_status.device') }}</code></span>
                    @endif
                    @if (!session('gateway_status.connected'))
                        <a href="{{ route('sms.settings') }}" class="ml-2 underline font-semibold">Update Settings</a>
                    @endif
                </div>
            @endif

            @if (session('status'))
                <div class="bg-green-100 border border-green-400 text-black px-4 py-3 rounded">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 text-black">
                <!-- Upcoming doses -->
                <div class="bg-white shadow rounded-lg p-5 text-black">
                    <h3 class="font-semibold text-black mb-3 text-black">Upcoming Doses</h3>
                    @if ($upcoming->isEmpty())
                        <p class="text-sm text-black">No upcoming doses.</p>
                    @else
                        <table class="w-full text-sm">
                            <thead class="text-left text-black">
                                <tr><th class="text-black">Patient</th><th class="text-black">Dose</th><th class="text-black">Date</th><th></th></tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($upcoming as $s)
                                    <tr>
                                        <td class="py-2"><a href="{{ route('patients.show', $s->patient) }}" class="text-black hover:underline">{{ $s->patient->fullName() }}</a></td>
                                        <td class="text-black">{{ $s->dose_number }}</td>
                                        <td class="text-black">{{ $s->scheduled_date->format('M d, Y') }}</td>
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('sms.send', $s) }}">@csrf
                                                <button class="text-xs bg-green-600 text-white px-2 py-2 rounded">Send</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <!-- SMS log -->
                <div class="bg-white shadow rounded-lg p-5 text-black">
                    <h3 class="font-semibold text-black mb-3 text-black">SMS Log</h3>
                    <table class="w-full text-sm">
                        <thead class="text-left text-black">
                                <tr><th class="text-black">Recipient</th><th class="text-black">Status / Gateway response</th><th class="text-black">When</th></tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse ($logs as $log)
                                <tr>
                                    <td class="py-2 text-black">{{ $log->patient?->first_name ?? $log->recipient }}</td>
                                    <td>
                                        <x-status-badge :status="$log->status"></x-status-badge>
                                        @if (in_array($log->status, ['failed', 'pending'], true))
                                            @php $gatewayResponse = json_decode($log->response ?? '', true); @endphp
                                            <p class="mt-1 max-w-sm break-words text-xs text-slate-600">
                                                @if ($log->status === 'pending')
                                                    Accepted by gateway; check the phone app for send status.
                                                    @if (is_array($gatewayResponse) && !empty($gatewayResponse['id'])) Message ID: {{ $gatewayResponse['id'] }} @endif
                                                @else
                                                    {{ is_array($gatewayResponse) ? ($gatewayResponse['error'] ?? data_get($gatewayResponse, 'body.message') ?? data_get($gatewayResponse, 'body.error') ?? 'Gateway rejected or could not send the message.') : ($log->response ?: 'Gateway rejected or could not send the message.') }}
                                                @endif
                                            </p>
                                        @endif
                                    </td>
                                    <td class="text-black">{{ $log->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-2 text-black">No SMS sent yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="mt-3 text-black">{{ $logs->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
