<x-admin-panel-layout :page-title="$pageTitle ?? 'Besplatne rezervacije'" :nav-active="$navActive ?? 'free-reservations'">
    @php
        $minDate = now()->toDateString();
        $maxDate = now()->addDays(90)->toDateString();
        $locale = 'cg';
        $ui = fn (string $key) => \App\Support\UiText::t('reservation', $key);
    @endphp

    <div class="space-y-6 max-w-3xl">
        <div class="space-y-1">
            <h1 class="text-lg font-semibold text-gray-900">Besplatna rezervacija</h1>
            <p class="text-sm text-gray-600">Kreiranje besplatne rezervacije za gosta (bez plaćanja). Sva polja i pravila termina kao na javnoj formi; jezik interfejsa i potvrde: crnogorski.</p>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <form method="GET" action="{{ route('panel_admin.free-reservations', [], false) }}" class="space-y-4" id="adminFreeStepForm">
            <div>
                <x-input-label for="reservation_date" :value="$ui('date')" />
                <input
                    id="reservation_date"
                    name="reservation_date"
                    type="date"
                    min="{{ $minDate }}"
                    max="{{ $maxDate }}"
                    value="{{ $selected_date ?? '' }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <x-input-error class="mt-2" :messages="$errors->get('reservation_date')" />
            </div>

            <div>
                <x-input-label for="drop_off_time_slot_id" :value="$ui('arrival_time')" />
                <select
                    id="drop_off_time_slot_id"
                    name="drop_off_time_slot_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    @disabled(empty($selected_date))
                >
                    <option value="">{{ $ui('select_time_slot') }}</option>
                    @foreach (($arrival_slots ?? []) as $s)
                        <option value="{{ $s['id'] }}" @selected((int)($arrival_id ?? 0) === (int)$s['id']) @disabled((bool)$s['disabled'])>
                            {{ $s['label'] }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('drop_off_time_slot_id')" />
            </div>

            <div>
                <x-input-label for="pick_up_time_slot_id" :value="$ui('departure_time')" />
                <select
                    id="pick_up_time_slot_id"
                    name="pick_up_time_slot_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    @disabled($departure_disabled ?? true)
                >
                    <option value="">{{ $ui('select_time_slot') }}</option>
                    @foreach (($departure_slots ?? []) as $s)
                        <option value="{{ $s['id'] }}" @selected((int)($departure_id ?? 0) === (int)$s['id']) @disabled((bool)$s['disabled'])>
                            {{ $s['label'] }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">{{ $ui('departure_disabled_hint') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('pick_up_time_slot_id')" />
            </div>

            <div>
                <x-input-label for="vehicle_type_id_step" :value="$ui('vehicle_category')" />
                <select
                    id="vehicle_type_id_step"
                    name="vehicle_type_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    @disabled(empty($selected_date) || empty($arrival_id) || empty($departure_id))
                >
                    <option value="">{{ $ui('select_vehicle_category') }}</option>
                    @foreach (($vehicle_types ?? []) as $vt)
                        <option value="{{ $vt->id }}" @selected((int) old('vehicle_type_id', request('vehicle_type_id')) === (int) $vt->id)>
                            {{ $vt->formatLabel($locale, 'EUR') }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('vehicle_type_id')" />
            </div>
        </form>

        <form method="POST" action="{{ route('panel_admin.free-reservations.store', [], false) }}" class="space-y-4 bg-white shadow rounded-lg p-5 border border-gray-200">
            @csrf

            <input type="hidden" name="reservation_date" value="{{ $selected_date ?? '' }}">
            <input type="hidden" name="drop_off_time_slot_id" value="{{ $arrival_id ?? '' }}">
            <input type="hidden" name="pick_up_time_slot_id" value="{{ $departure_id ?? '' }}">
            <input type="hidden" name="vehicle_type_id" value="{{ old('vehicle_type_id', request('vehicle_type_id')) }}">

            <div>
                <x-input-label for="name" :value="$ui('name')" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', request('name'))" required />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="country" :value="$ui('country')" />
                <select id="country" name="country" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    <option value="">{{ $ui('select_country') }}</option>
                    @foreach (($countries ?? []) as $code => $labels)
                        @php
                            $label = is_array($labels) ? ($labels[$locale] ?? ($labels['en'] ?? $code)) : (string) $labels;
                        @endphp
                        <option value="{{ $code }}" @selected(old('country', request('country')) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('country')" />
            </div>

            <div>
                <x-input-label for="license_plate" :value="$ui('registration_plates')" />
                <x-text-input
                    id="license_plate"
                    name="license_plate"
                    type="text"
                    class="mt-1 block w-full"
                    :value="old('license_plate', request('license_plate'))"
                    required
                    autocomplete="off"
                    inputmode="latin"
                    pattern="[A-Z0-9]+"
                />
                <x-input-error class="mt-2" :messages="$errors->get('license_plate')" />
            </div>

            <div>
                <x-input-label for="email" :value="$ui('email')" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', request('email'))" required />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />
            </div>

            <x-primary-button type="submit" id="adminFreeSubmitBtn" disabled>
                Napravi besplatnu rezervaciju
            </x-primary-button>
        </form>
    </div>

    <script>
        (function () {
            const form = document.getElementById('adminFreeStepForm');
            if (!form) return;
            const date = document.getElementById('reservation_date');
            const arrival = document.getElementById('drop_off_time_slot_id');
            const departure = document.getElementById('pick_up_time_slot_id');
            const vehicleType = document.getElementById('vehicle_type_id_step');
            if (date) date.addEventListener('change', () => form.submit());
            if (arrival) arrival.addEventListener('change', () => form.submit());
            if (departure) departure.addEventListener('change', () => form.submit());
            if (vehicleType) vehicleType.addEventListener('change', () => form.submit());

            const submitBtn = document.getElementById('adminFreeSubmitBtn');
            const postForm = submitBtn ? submitBtn.closest('form') : null;
            const lp = document.getElementById('license_plate');
            if (lp) {
                lp.addEventListener('input', () => {
                    const v = (lp.value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
                    if (lp.value !== v) lp.value = v;
                });
            }

            function computePostValid() {
                if (!postForm) return false;
                const required = Array.from(postForm.querySelectorAll('[required]'));
                for (const el of required) {
                    if (!el.value || String(el.value).trim() === '') return false;
                    if (el.getAttribute('pattern')) {
                        try {
                            const re = new RegExp('^' + el.getAttribute('pattern') + '$');
                            if (!re.test(el.value)) return false;
                        } catch (e) {}
                    }
                }
                const vt = postForm.querySelector('input[name="vehicle_type_id"]');
                const d = postForm.querySelector('input[name="reservation_date"]');
                const a = postForm.querySelector('input[name="drop_off_time_slot_id"]');
                const p = postForm.querySelector('input[name="pick_up_time_slot_id"]');
                return !!(vt && vt.value && d && d.value && a && a.value && p && p.value);
            }

            function refresh() {
                if (!submitBtn) return;
                submitBtn.disabled = !computePostValid();
            }

            if (postForm) {
                postForm.addEventListener('input', refresh);
                postForm.addEventListener('change', refresh);
                refresh();
            }
        })();
    </script>
</x-admin-panel-layout>
