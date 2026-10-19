<x-app-layout>
    <x-slot name="header">Register New Patient</x-slot>

    @php
        $isStaffUser = Auth::user()->role === 'Staff';
        $registrationAction = $isStaffUser ? route('staff.patients.store') : route('patients.store');
        $cancelRoute = $isStaffUser ? route('staff.patients.search') : route('patients.index');
    @endphp

    <div class="py-6 text-black">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-black">
            <form method="POST" action="{{ $registrationAction }}" class="bg-white shadow rounded-lg p-6 space-y-6">
                @csrf

                {{-- Patient Information Section --}}
                <section class="rounded-lg bg-[#176b9c] p-5">
                    <div class="mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <h3 class="font-semibold text-white">Patient Information</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="first_name" value="First Name" class="text-white" />
                        <x-text-input id="first_name" name="first_name" class="block mt-1 w-full" :value="old('first_name')" required />
                        <x-input-error :messages="$errors->get('first_name')" class="mt-2 text-rose-100 border-rose-200" />
                    </div>
                    <div>
                        <x-input-label for="middle_name" value="Middle Name" class="text-white" />
                        <x-text-input id="middle_name" name="middle_name" class="block mt-1 w-full" :value="old('middle_name')" />
                    </div>
                    <div>
                        <x-input-label for="last_name" value="Last Name" class="text-white" />
                        <x-text-input id="last_name" name="last_name" class="block mt-1 w-full" :value="old('last_name')" required />
                        <x-input-error :messages="$errors->get('last_name')" class="mt-2 text-rose-100 border-rose-200" />
                    </div>
                    <div>
                        <x-input-label for="suffix" value="Suffix" class="text-white" />
                        <select id="suffix" name="suffix" class="block mt-1 w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-[#1976ba] focus:outline-none focus:ring-2 focus:ring-[#1976ba]/20">
                            @foreach (\App\Models\Patient::suffixes() as $s)
                                <option value="{{ $s }}" {{ old('suffix') === $s ? 'selected' : '' }}>{{ $s ?: 'None' }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('suffix')" class="mt-2 text-rose-100 border-rose-200" />
                    </div>
                    <div>
                        <x-input-label for="dob" value="Date of Birth" class="text-white" />
                        <x-text-input id="dob" type="date" name="dob" class="block mt-1 w-full" :value="old('dob')" required onchange="calcAge(this)" />
                        <p id="age-display" class="mt-1 text-xs text-white"></p>
                        <x-input-error :messages="$errors->get('dob')" class="mt-2 text-rose-100 border-rose-200" />
                    </div>
                    <div>
                        <x-input-label for="sex" value="Sex" class="text-white" />
                        <select id="sex" name="sex" class="block mt-1 w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-[#1976ba] focus:outline-none focus:ring-2 focus:ring-[#1976ba]/20">
                            <option value="">&mdash;</option>
                            <option value="Male" {{ old('sex') === 'Male' ? 'selected' : '' }}>Male</option>
                            <option value="Female" {{ old('sex') === 'Female' ? 'selected' : '' }}>Female</option>
                            <option value="Other" {{ old('sex') === 'Other' ? 'selected' : '' }}>Other</option>
                        </select>
                        <x-input-error :messages="$errors->get('sex')" class="mt-2 text-rose-100 border-rose-200" />
                    </div>
                    <div>
                        <x-input-label for="contact_number" value="Contact Number (optional)" class="text-white" />
                        <x-text-input id="contact_number" type="tel" name="contact_number" class="block mt-1 w-full" :value="old('contact_number')" placeholder="09171234567" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" aria-describedby="contact-number-help" title="Enter exactly 11 digits, for example 09171234567" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 11)" />
                        <p id="contact-number-help" class="mt-1 text-xs text-white">If provided, enter 11 digits only (example: 09171234567).</p>
                        <x-input-error :messages="$errors->get('contact_number')" class="mt-2 text-rose-100 border-rose-200" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="address" value="Home Address" class="text-white" />
                        <x-text-input id="address" name="address" class="block mt-1 w-full" :value="old('address')" required />
                        <x-input-error :messages="$errors->get('address')" class="mt-2 text-rose-100 border-rose-200" />
                    </div>
                    <div>
                        <x-input-label for="barangay" value="Barangay" class="text-white" />
                        <select id="barangay" name="barangay" class="block mt-1 w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-[#1976ba] focus:outline-none focus:ring-2 focus:ring-[#1976ba]/20" required>
                            <option value="">&mdash;</option>
                            @foreach (\App\Models\Patient::barangays() as $b)
                                <option value="{{ $b }}" {{ old('barangay') === $b ? 'selected' : '' }}>{{ $b }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('barangay')" class="mt-2 text-rose-100 border-rose-200" />
                    </div>
                    <div>
                        <x-input-label for="patient_type" value="Patient Type" class="text-white" />
                        <select id="patient_type" name="patient_type" class="block mt-1 w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-[#1976ba] focus:outline-none focus:ring-2 focus:ring-[#1976ba]/20">
                            <option value="regular" {{ old('patient_type', 'regular') === 'regular' ? 'selected' : '' }}>Regular</option>
                            <option value="pwd" {{ old('patient_type') === 'pwd' ? 'selected' : '' }}>PWD</option>
                            <option value="senior" {{ old('patient_type') === 'senior' ? 'selected' : '' }}>Senior</option>
                            <option value="pregnant" {{ old('patient_type') === 'pregnant' ? 'selected' : '' }}>Pregnant</option>
                            <option value="emergency" {{ old('patient_type') === 'emergency' ? 'selected' : '' }}>Emergency</option>
                        </select>
                        <p class="mt-1 text-xs text-white">Priority types get queue priority and special handling</p>
                        <x-input-error :messages="$errors->get('patient_type')" class="mt-2 text-rose-100 border-rose-200" />
                    </div>
                    </div>
                </section>

                {{-- Bite Incident Section --}}
                <section class="rounded-lg bg-[#176b9c] p-5">
                    <div class="mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <h3 class="font-semibold text-white">Animal Bite Incident</h3>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="date_of_bite" value="Date of Bite" class="text-white" />
                        <x-text-input id="date_of_bite" type="date" name="date_of_bite" class="block mt-1 w-full" :value="old('date_of_bite', now()->toDateString())" required />
                        <x-input-error :messages="$errors->get('date_of_bite')" class="mt-2 text-rose-100 border-rose-200" />
                    </div>
                    <div>
                        <x-input-label for="animal_type" value="Animal Type" class="text-white" />
                        <x-text-input id="animal_type" name="animal_type" class="block mt-1 w-full" :value="old('animal_type')" placeholder="Dog, Cat, etc." required />
                        <x-input-error :messages="$errors->get('animal_type')" class="mt-2 text-rose-100 border-rose-200" />
                    </div>
                    <div>
                        <x-input-label for="exposure_category" value="Exposure Category" class="text-white" />
                        <select id="exposure_category" name="exposure_category" class="block mt-1 w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-[#1976ba] focus:outline-none focus:ring-2 focus:ring-[#1976ba]/20">
                            <option value="">&mdash;</option>
                            <option value="I" {{ old('exposure_category') === 'I' ? 'selected' : '' }}>I</option>
                            <option value="II" {{ old('exposure_category') === 'II' ? 'selected' : '' }}>II</option>
                            <option value="III" {{ old('exposure_category') === 'III' ? 'selected' : '' }}>III</option>
                        </select>
                        <x-input-error :messages="$errors->get('exposure_category')" class="mt-2 text-rose-100 border-rose-200" />
                    </div>
                    <div>
                        <x-input-label for="bite_site" value="Site of Bite" class="text-white" />
                        <x-text-input id="bite_site" name="bite_site" class="block mt-1 w-full" :value="old('bite_site')" placeholder="Right arm, leg, etc." />
                    </div>
                    <div>
                        <x-input-label for="animal_status" value="Animal Status" class="text-white" />
                        <select id="animal_status" name="animal_status" class="block mt-1 w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-[#1976ba] focus:outline-none focus:ring-2 focus:ring-[#1976ba]/20">
                            <option value="">&mdash;</option>
                            <option value="Healthy" {{ old('animal_status') === 'Healthy' ? 'selected' : '' }}>Healthy</option>
                            <option value="Sick" {{ old('animal_status') === 'Sick' ? 'selected' : '' }}>Sick</option>
                            <option value="Dead" {{ old('animal_status') === 'Dead' ? 'selected' : '' }}>Dead</option>
                            <option value="Unknown" {{ old('animal_status') === 'Unknown' ? 'selected' : '' }}>Unknown</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="animal_vaccinated" value="1" {{ old('animal_vaccinated') ? 'checked' : '' }} class="rounded border-slate-300 text-[#176b9c] focus:ring-[#176b9c]">
                            <span class="text-sm text-white">Animal was vaccinated</span>
                        </label>
                    </div>
                </div>
                </section>

                {{-- Clinical vaccination plan is selected by staff at registration. --}}
                <section class="rounded-lg border border-sky-200 bg-sky-50 p-5">
                    <div class="mb-2">
                        <h3 class="font-semibold text-slate-900">Vaccination Plan</h3>
                        <p class="mt-1 text-sm text-slate-700">Review the animal and exposure details, then record the vaccine and dose schedule ordered by the healthcare provider. Animal type alone does not determine the regimen.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="vaccine_type" value="Vaccine Type" />
                            <select id="vaccine_type" name="vaccine_type" class="block mt-1 w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm" required>
                                <option value="">Select stocked vaccine</option>
                                @foreach ($vaccineTypes as $type)
                                    <option value="{{ $type }}" {{ old('vaccine_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                            @if (empty($vaccineTypes))
                                <p class="mt-1 text-sm text-rose-700">No eligible vaccine is in stock. Update Inventory before registering the vaccination plan.</p>
                            @endif
                            <x-input-error :messages="$errors->get('vaccine_type')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="planned_dose_count" value="Number of Doses in Provider-Ordered Schedule" />
                            <select id="planned_dose_count" name="planned_dose_count" class="block mt-1 w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm" required>
                                <option value="">Select the ordered schedule</option>
                                <option value="3" {{ old('planned_dose_count') === '3' ? 'selected' : '' }}>3 doses — Day 0, Day 3, Day 7</option>
                                <option value="4" {{ old('planned_dose_count', '4') === '4' ? 'selected' : '' }}>4 doses — Day 0, Day 3, Day 7, Day 28</option>
                            </select>
                            <x-input-error :messages="$errors->get('planned_dose_count')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-amber-200 bg-amber-50 p-5">
                    <label class="inline-flex items-start gap-3">
                        <input type="checkbox" name="requires_medical_review" value="1" {{ old('requires_medical_review') ? 'checked' : '' }} class="mt-1 rounded border-amber-400 text-amber-700 focus:ring-amber-600">
                        <span><span class="block font-semibold text-slate-900">Route for medical review</span><span class="mt-1 block text-sm text-slate-700">Select when the clinical assessment requires review before routine vaccination. Emergency patient type is routed for review automatically.</span></span>
                    </label>
                </section>

                {{-- Ticket generation is required before registration can be submitted. --}}
                <div class="border-t pt-4" data-regular-ticket="{{ $nextRegularTicketNumber }}" data-priority-ticket="{{ $nextPriorityTicketNumber }}">
                    <input type="hidden" id="register_queue" name="register_queue" value="{{ old('register_queue') ? '1' : '' }}">
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" id="generate-ticket" class="bg-emerald-700 text-white px-5 py-2 rounded-md font-semibold hover:bg-emerald-800 transition-colors">
                            Generate Ticket
                        </button>
                        <p id="ticket-generation-status" class="text-sm text-black" role="status" aria-live="polite">
                            Generate a queue ticket before registering this patient.
                        </p>
                    </div>
                    <x-input-error :messages="$errors->get('register_queue')" class="mt-2" />
                </div>

                {{-- Actions --}}
                <div class="flex justify-end gap-3">
                    <a href="{{ $cancelRoute }}" class="px-4 py-2 text-black hover:underline">Cancel</a>
                    <button type="submit" id="register-patient" @disabled(! old('register_queue')) class="bg-[#176b9c] text-white px-6 py-2 rounded-md font-semibold hover:bg-[#11567f] transition-colors disabled:cursor-not-allowed disabled:opacity-50">Register Patient</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function calcAge(input) {
            const display = document.getElementById('age-display');
            if (!input.value) { display.textContent = ''; return; }
            const birth = new Date(input.value);
            const today = new Date();
            let age = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            display.textContent = `Age will be: ${age} years`;
        }

        function capitalizeFirst(input) {
            let val = input.value;
            if (val.length > 0) {
                input.value = val.charAt(0).toUpperCase() + val.slice(1);
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            const dob = document.getElementById('dob');
            if (dob && dob.value) calcAge(dob);

            const form = document.querySelector('form[action="{{ $registrationAction }}"]');
            const patientType = document.getElementById('patient_type');
            const ticketFlag = document.getElementById('register_queue');
            const generateTicketButton = document.getElementById('generate-ticket');
            const ticketStatus = document.getElementById('ticket-generation-status');
            const registerPatientButton = document.getElementById('register-patient');
            const ticketNumbers = generateTicketButton.closest('[data-regular-ticket]');

            const resetTicketGeneration = () => {
                ticketFlag.value = '';
                registerPatientButton.disabled = true;
                ticketStatus.textContent = 'Generate a queue ticket before registering this patient.';
            };

            if (ticketFlag.value === '1') {
                registerPatientButton.disabled = false;
                ticketStatus.textContent = 'Ticket generation confirmed. Register the patient to create the ticket.';
            }

            generateTicketButton.addEventListener('click', () => {
                const isPriority = patientType.value !== 'regular';
                const ticketNumber = isPriority
                    ? ticketNumbers.dataset.priorityTicket
                    : ticketNumbers.dataset.regularTicket;

                ticketFlag.value = '1';
                registerPatientButton.disabled = false;
                ticketStatus.textContent = `Ticket ${ticketNumber} is ready. Register the patient to save the record and ticket.`;
            });

            patientType.addEventListener('change', resetTicketGeneration);
            form.addEventListener('submit', event => {
                if (ticketFlag.value !== '1') {
                    event.preventDefault();
                    ticketStatus.textContent = 'Click Generate Ticket before registering this patient.';
                    generateTicketButton.focus();
                }
            });

            document.getElementById('barangay').addEventListener('change', function() {
                const address = document.getElementById('address');
                if (this.value) {
                    address.value = this.value + ', Mandaluyong City';
                }
            });

            ['first_name', 'middle_name', 'last_name'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('input', function() { capitalizeFirst(this); });
            });
        });
    </script>
    @endpush
</x-app-layout>
