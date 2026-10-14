<x-app-layout>
    <x-slot name="header">SMS Gateway Settings</x-slot>

    @php
        $gatewayStatus = session('gateway_status');
        $connected = is_array($gatewayStatus) ? ($gatewayStatus['connected'] ?? null) : null;
    @endphp

    <div class="space-y-5">
        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('sms.index') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                <x-icon name="back" class="h-4 w-4" /> Back to SMS
            </a>
        </div>

        <nav aria-label="SMS settings navigation" class="flex overflow-x-auto rounded-md border border-slate-200 bg-white px-2">
            <a href="{{ route('sms.settings') }}" aria-current="page" class="inline-flex items-center gap-2 whitespace-nowrap border-b-2 border-blue-600 px-4 py-3 text-sm font-semibold text-blue-700"><x-icon name="settings" class="h-4 w-4" />Configuration</a>
            <a href="{{ route('sms.template') }}" class="inline-flex items-center gap-2 whitespace-nowrap border-b-2 border-transparent px-4 py-3 text-sm font-medium text-slate-600 hover:border-slate-300 hover:text-slate-900"><x-icon name="template" class="h-4 w-4" />Message Templates</a>
            <a href="{{ route('sms.index') }}" class="inline-flex items-center gap-2 whitespace-nowrap border-b-2 border-transparent px-4 py-3 text-sm font-medium text-slate-600 hover:border-slate-300 hover:text-slate-900"><x-icon name="logs" class="h-4 w-4" />Sent Logs</a>
            <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-2 whitespace-nowrap border-b-2 border-transparent px-4 py-3 text-sm font-medium text-slate-600 hover:border-slate-300 hover:text-slate-900"><x-icon name="reports" class="h-4 w-4" />Usage &amp; Reports</a>
        </nav>

        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <p class="font-semibold">Settings could not be saved.</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1.75fr)_minmax(300px,0.8fr)]">
            <section class="rounded-md border border-slate-200 bg-white shadow-sm">
                <form id="gateway-settings-form" method="POST" action="{{ route('sms.settings.update') }}">
                    @csrf
                    <input type="hidden" name="enabled" value="0">
                <div class="flex flex-wrap items-start justify-between gap-5 border-b border-slate-200 px-5 py-5 sm:px-6">
                    <div class="max-w-xl">
                        <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-blue-700"><x-icon name="radio" class="h-4 w-4" />Connection details</p>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">SMS Gateway Configuration</h2>
                        <p class="mt-1.5 text-sm leading-5 text-slate-600">Connect this Railway-hosted system to the SMS Gateway app on your Android phone through its Cloud Server or a publicly reachable server.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-semibold text-slate-800">Enable gateway</span>
                        <input type="checkbox" name="enabled" value="1" aria-label="Enable SMS Gateway" @checked($settings['enabled']) class="h-5 w-9 cursor-pointer appearance-none rounded-full bg-slate-300 transition-colors before:block before:h-4 before:w-4 before:translate-x-0.5 before:translate-y-0.5 before:rounded-full before:bg-white before:shadow before:transition-transform checked:bg-blue-600 checked:before:translate-x-4 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    </div>
                </div>

                <div class="space-y-5 px-5 py-5 sm:px-6 sm:py-6">
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="host" class="block text-sm font-semibold text-slate-800">Gateway address <span class="text-red-600">*</span></label>
                            <div class="mt-1.5 flex rounded-md shadow-sm">
                                <span class="inline-flex items-center gap-1.5 rounded-l-md border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><x-icon name="link" class="h-4 w-4" />URL</span>
                                <input id="host" name="host" type="text" value="{{ old('host', $settings['host']) }}" placeholder="https://api.sms-gate.app" required class="min-w-0 flex-1 rounded-r-md border-slate-300 text-sm shadow-none focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500">For SMS Gateway Cloud Server, use <code class="rounded bg-slate-100 px-1">https://api.sms-gate.app</code>. A private Local address such as <code class="rounded bg-slate-100 px-1">192.168.x.x</code> is not reachable from Railway. Include <code>https://</code> for cloud endpoints.</p>
                            @error('host') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="username" class="block text-sm font-semibold text-slate-800">Username</label>
                            <input id="username" name="username" type="text" value="{{ old('username', $settings['username']) }}" required class="mt-1.5 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-semibold text-slate-800">Password</label>
                            <input id="password" name="password" type="password" value="" autocomplete="new-password" placeholder="{{ filled($settings['password']) ? 'Saved - leave blank to keep' : 'Enter gateway password' }}" class="mt-1.5 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1.5 text-xs text-slate-500">{{ filled($settings['password']) ? 'A password is saved. Leave blank to keep it.' : 'Enter the password displayed in the gateway app.' }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label for="device_id" class="block text-sm font-semibold text-slate-800">Device ID <span class="font-normal text-slate-500">(optional)</span></label>
                            <input id="device_id" name="device_id" type="text" value="{{ old('device_id', $settings['device_id']) }}" class="mt-1.5 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1.5 text-xs text-slate-500">Used to identify the phone when the gateway reports its device details.</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-5">
                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"><x-icon name="save" class="h-4 w-4" />Save Settings</button>
                        <p class="text-xs text-slate-500">Save a changed address before testing it.</p>
                    </div>
                </div>
                </form>

                <div class="flex flex-wrap gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                    <form method="POST" action="{{ route('sms.test-connection') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"><x-icon name="wifi" class="h-4 w-4" />Test Connection</button>
                    </form>
                </div>
            </section>

            <aside class="space-y-5">
                <section class="rounded-md border border-slate-200 bg-white shadow-sm" aria-labelledby="gateway-status-title">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-4">
                        <h2 id="gateway-status-title" class="inline-flex items-center gap-2 text-base font-bold text-slate-900"><x-icon name="shield" class="h-5 w-5 text-blue-700" />Gateway Status</h2>
                        @if ($connected === true)
                            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Connected</span>
                        @elseif ($connected === false)
                            <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800"><span class="h-2 w-2 rounded-full bg-red-500"></span>Unreachable</span>
                        @else
                            <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600"><span class="h-2 w-2 rounded-full bg-slate-400"></span>Not checked</span>
                        @endif
                    </div>
                    <div class="space-y-4 p-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-md bg-slate-50 p-3">
                                <p class="text-xs text-slate-500">Last checked</p>
                                <p class="mt-1 text-sm font-semibold text-slate-800">{{ is_array($gatewayStatus) ? now()->format('M j, Y g:i A') : 'No test yet' }}</p>
                            </div>
                            <div class="rounded-md bg-slate-50 p-3">
                                <p class="text-xs text-slate-500">Device ID</p>
                                <p class="mt-1 truncate text-sm font-semibold text-slate-800">{{ $settings['device_id'] ?: 'Not set' }}</p>
                            </div>
                        </div>
                        @if (is_array($gatewayStatus) && filled($gatewayStatus['message'] ?? null))
                            <p class="rounded-md {{ $connected ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800' }} px-3 py-2.5 text-sm" role="status">{{ $gatewayStatus['message'] }}</p>
                        @else
                            <p class="text-sm leading-5 text-slate-600">Run a connection test to check whether this computer can reach the phone.</p>
                        @endif
                        <div class="border-t border-slate-100 pt-3">
                            <p class="text-xs font-medium text-slate-500">Configured address</p>
                            <p class="mt-1 break-all text-sm font-semibold text-slate-800">{{ $settings['host'] ?: 'No address saved' }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-md border border-slate-200 bg-white shadow-sm" aria-labelledby="setup-guide-title">
                    <div class="border-b border-slate-200 px-4 py-4">
                        <h2 id="setup-guide-title" class="inline-flex items-center gap-2 text-base font-bold text-slate-900"><x-icon name="bulb" class="h-5 w-5 text-amber-600" />Quick Setup Guide</h2>
                    </div>
                    <ol class="space-y-4 p-4">
                        @foreach ([
                            'Enable Cloud Server in the SMS Gateway app and sign in.',
                            'Enter https://api.sms-gate.app as the Gateway address.',
                            'Enter the Cloud Server account username and password.',
                            'Save settings and use Test Connection; confirm the phone is online.',
                            'Send a test SMS to a number you can check.',
                        ] as $step)
                            <li class="flex items-start gap-3 text-sm leading-5 text-slate-700">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-700 text-xs font-bold text-white">{{ $loop->iteration }}</span>
                                <span>{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                    <p class="border-t border-slate-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-900">Railway cannot reach a phone’s private Wi-Fi Local address. Use the app’s Cloud Server or host a gateway at an address publicly reachable from Railway.</p>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
