<x-guest-layout>
    @php
        $minDate = now()->toDateString();
        $maxDate = now()->addDays(90)->toDateString();
        $locale = app()->getLocale();
        $isCg = $locale === 'cg';

        $t = fn (string $cg, string $en) => $isCg ? $cg : $en;
    @endphp

    <div class="space-y-6">
        <div class="space-y-1">
            <h1 class="text-lg font-semibold">{{ $t('Rezervacija (guest)', 'Reservation (guest)') }}</h1>
            <p class="text-sm text-gray-600">{{ $t('Korak-po-korak: datum → dolazak → odlazak → podaci', 'Step-by-step: date → arrival → departure → details') }}</p>
        </div>

        @if (session('message'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('message') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <form method="GET" action="{{ route('guest.reserve', [], false) }}" class="space-y-4" id="stepForm">
            <div>
                <x-input-label for="reservation_date" :value="$t('Datum', 'Date')" />
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
                <x-input-label for="drop_off_time_slot_id" :value="$t('Dolazak (Arrival)', 'Arrival time')" />
                <select
                    id="drop_off_time_slot_id"
                    name="drop_off_time_slot_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    @disabled(empty($selected_date))
                >
                    <option value="">{{ $t('Izaberi termin', 'Select a time slot') }}</option>
                    @foreach (($arrival_slots ?? []) as $s)
                        <option value="{{ $s['id'] }}" @selected((int)($arrival_id ?? 0) === (int)$s['id']) @disabled((bool)$s['disabled'])>
                            {{ $s['label'] }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('drop_off_time_slot_id')" />
            </div>

            <div>
                <x-input-label for="pick_up_time_slot_id" :value="$t('Odlazak (Departure)', 'Departure time')" />
                <select
                    id="pick_up_time_slot_id"
                    name="pick_up_time_slot_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    @disabled($departure_disabled ?? true)
                >
                    <option value="">{{ $t('Izaberi termin', 'Select a time slot') }}</option>
                    @foreach (($departure_slots ?? []) as $s)
                        <option value="{{ $s['id'] }}" @selected((int)($departure_id ?? 0) === (int)$s['id']) @disabled((bool)$s['disabled'])>
                            {{ $s['label'] }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">
                    {{ $t('Odlazak je onemogućen dok ne izabereš dolazak.', 'Departure is disabled until you choose arrival.') }}
                </p>
                <x-input-error class="mt-2" :messages="$errors->get('pick_up_time_slot_id')" />
            </div>

            <div>
                <x-input-label for="vehicle_type_id_step" :value="$t('Kategorija vozila', 'Vehicle category')" />
                <select
                    id="vehicle_type_id_step"
                    name="vehicle_type_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    @disabled(empty($selected_date) || empty($arrival_id) || empty($departure_id))
                >
                    <option value="">{{ $t('Izaberi', 'Select') }}</option>
                    @foreach (($vehicle_types ?? []) as $vt)
                        <option value="{{ $vt->id }}" @selected((int)request('vehicle_type_id') === (int)$vt->id)>
                            {{ ($vt->getTranslatedName(app()->getLocale()) ?: ('#'.$vt->id)) . (is_numeric((string) $vt->price) ? (' — '.number_format((float) $vt->price, 2, '.', '').' EUR') : '') }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('vehicle_type_id')" />
            </div>
        </form>

        <form method="POST" action="{{ route('checkout.store', [], false) }}" class="space-y-4">
            @csrf

            <input type="hidden" name="reservation_date" value="{{ $selected_date ?? '' }}">
            <input type="hidden" name="drop_off_time_slot_id" value="{{ $arrival_id ?? '' }}">
            <input type="hidden" name="pick_up_time_slot_id" value="{{ $departure_id ?? '' }}">
            <input type="hidden" name="vehicle_type_id" value="{{ request('vehicle_type_id') }}">

            <div class="rounded-md bg-gray-50 p-3 text-sm text-gray-800">
                @if (!empty($arrival_id) && !empty($departure_id))
                    @if ($is_free_reservation ?? false)
                        <strong>{{ $t('Ova rezervacija je besplatna', 'This reservation is free') }}</strong>
                    @else
                        <strong>{{ $t('Ukupno za plaćanje', 'Total to pay') }}:</strong>
                        {{ $paid_amount ?? '—' }} EUR
                    @endif
                @else
                    {{ $t('Izaberi dolazak i odlazak da vidiš da li je free ili paid.', 'Select arrival and departure to see if it is free or paid.') }}
                @endif
            </div>

            <div>
                <x-input-label for="user_name" :value="$t('Naziv firme (Company name)', 'Company name')" />
                <x-text-input id="user_name" name="user_name" type="text" class="mt-1 block w-full" :value="old('user_name', request('user_name'))" required />
                <x-input-error class="mt-2" :messages="$errors->get('user_name')" />
            </div>

            <div>
                <x-input-label for="country" :value="$t('Država (Country)', 'Country')" />
                <select id="country" name="country" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    <option value="">{{ $t('Izaberi', 'Select') }}</option>
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
                <x-input-label for="license_plate" :value="$t('Registracija (Registration plates)', 'Registration plates')" />
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
                <x-input-label for="email" :value="$t('Email', 'Email')" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', request('email'))" required />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />
            </div>

            <div class="space-y-2">
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="accept_terms" value="1" class="mt-1 rounded border-gray-300" {{ old('accept_terms') ? 'checked' : '' }} required>
                    <span>{{ $t('Slažem se sa uslovima korišćenja', 'I agree to the terms of service') }}</span>
                </label>
                <x-input-error class="mt-2" :messages="$errors->get('accept_terms')" />

                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="accept_privacy" value="1" class="mt-1 rounded border-gray-300" {{ old('accept_privacy') ? 'checked' : '' }} required>
                    <span>{{ $t('Slažem se sa politikom privatnosti', 'I agree to the privacy policy') }}</span>
                </label>
                <x-input-error class="mt-2" :messages="$errors->get('accept_privacy')" />
            </div>

            <button
                type="submit"
                id="reserveBtn"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                disabled
            >
                {{ $t('Rezerviši (Reserve)', 'Reserve') }}
            </button>
        </form>
    </div>

    <script>
        (function () {
            const form = document.getElementById('stepForm');
            if (!form) return;
            const date = document.getElementById('reservation_date');
            const arrival = document.getElementById('drop_off_time_slot_id');
            const departure = document.getElementById('pick_up_time_slot_id');
            const vehicleType = document.getElementById('vehicle_type_id_step');
            if (date) date.addEventListener('change', () => form.submit());
            if (arrival) arrival.addEventListener('change', () => form.submit());
            if (departure) departure.addEventListener('change', () => form.submit());
            if (vehicleType) vehicleType.addEventListener('change', () => form.submit());

            const reserveBtn = document.getElementById('reserveBtn');
            const postForm = reserveBtn ? reserveBtn.closest('form') : null;
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
                    if (el.type === 'checkbox') {
                        if (!el.checked) return false;
                        continue;
                    }
                    if (!el.value || String(el.value).trim() === '') return false;
                    if (el.getAttribute('pattern')) {
                        try {
                            const re = new RegExp('^' + el.getAttribute('pattern') + '$');
                            if (!re.test(el.value)) return false;
                        } catch (e) {}
                    }
                }
                // must have step selections
                const vt = postForm.querySelector('input[name="vehicle_type_id"]');
                const d = postForm.querySelector('input[name="reservation_date"]');
                const a = postForm.querySelector('input[name="drop_off_time_slot_id"]');
                const p = postForm.querySelector('input[name="pick_up_time_slot_id"]');
                return !!(vt && vt.value && d && d.value && a && a.value && p && p.value);
            }

            function refresh() {
                if (!reserveBtn) return;
                reserveBtn.disabled = !computePostValid();
            }

            if (postForm) {
                postForm.addEventListener('input', refresh);
                postForm.addEventListener('change', refresh);
                refresh();
            }
        })();
    </script>
</x-guest-layout>

