@php
    $locale = $locale ?? app()->getLocale();
    $ui = fn (string $key, ?string $fallback = null) => \App\Support\UiText::t('free_request', $key, $fallback);
    $p = fn (string $key, ?string $fallback = null) => \App\Support\UiText::t('panel', $key, $fallback);

    $minDate = now()->toDateString();
    $maxDate = now()->addDays(90)->toDateString();

    $descFallbackCg = "Naknadu za ekonomsko iskorišćavanje kulturnih dobara ne plaćaju obveznici naknade pod uslovom da organizuju prevoz putnika koja su:\n- lica sa teškim čulnim i tjelesnim smetnjama;\n- učesnici školskih ekskurzija, odnosno učenici i studenti čiji boravak organizuju škole i fakulteti u okviru redovnih programa, održavanja obrazovnih, sportskih i kulturnih manifestacija sa teritorije Crne Gore;\n- strani državljani koji su po međunarodnim konvencijama i sporazumima oslobođeni plaćanja taksi i koji organizovano, preko zvaničnih humanitarnih organizacija, dolaze radi pružanja humanitarne pomoći.\nLica ne plaćaju naknadu ako podnesu dokaz o ispunjavanju uslova iz stava 1 ovog člana (potvrda obrazovne institucije, ljekara, međunarodna konvencija, sporazum i dr.).";
    $descFallbackEn = "Economic fees for the use of cultural assets are not paid by fee payers provided that they organise passenger transport of:\n- persons with severe sensory and physical disabilities;\n- participants of school excursions, i.e. pupils and students whose stay is organised by schools and faculties within regular programmes and educational, sports and cultural events from Montenegro;\n- foreign citizens who are exempt from paying fees under international conventions and agreements and who arrive in an organised manner through official humanitarian organisations to provide humanitarian aid.\nThe fee is not paid if proof of meeting the conditions is submitted (certificate from an educational institution, doctor, international convention, agreement, etc.).";

    $title = $ui('fzbr_title', 'Formular za besplatnu rezervaciju');
    $desc = $ui('fzbr_description', $locale === 'cg' ? $descFallbackCg : $descFallbackEn);
    $descLines = preg_split("/\\r?\\n/", trim((string) $desc)) ?: [];
    $descLines = array_values(array_filter(array_map('trim', $descLines), fn ($l) => $l !== ''));

    $uploadLabel = $ui('documents_label', 'Osnov za zahtjev za besplatnu rezervaciju');
    $uploadHint = $ui('documents_hint', 'Slika ili pdf ugovora ili drugog dokumenta kojim se reguliše Vaše angažovanje od strane lica/institucije/organizacije koja polaže pravo na besplatnu rezervaciju.');
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">FZBR</h2>
    </x-slot>

    <div class="py-6" x-data="fzbrForm()">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 space-y-1">
                    @foreach ($errors->all() as $err)
                        <div>{{ $err }}</div>
                    @endforeach
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-6 space-y-3">
                <div class="space-y-1">
                    <div class="text-lg font-semibold text-gray-900">{{ $title }}</div>
                </div>

                <div class="text-sm text-gray-700 space-y-2">
                    @foreach ($descLines as $line)
                        @if (str_starts_with($line, '-'))
                            @break
                        @endif
                    @endforeach
                    @php
                        $intro = [];
                        $bullets = [];
                        foreach ($descLines as $l) {
                            if (str_starts_with($l, '-')) {
                                $bullets[] = ltrim($l, "- \t");
                            } else {
                                $intro[] = $l;
                            }
                        }
                    @endphp
                    @foreach ($intro as $pLine)
                        <p>{{ $pLine }}</p>
                    @endforeach
                    @if (!empty($bullets))
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($bullets as $b)
                                <li>{{ $b }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <form method="GET" action="{{ route('panel.fzbr.create', [], false) }}" class="space-y-4" id="fzbrStepForm">
                <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-1">
                            <x-input-label for="reservation_date" :value="\App\Support\UiText::t('reservation', 'date')" />
                            <input
                                id="reservation_date"
                                name="reservation_date"
                                type="date"
                                min="{{ $minDate }}"
                                max="{{ $maxDate }}"
                                value="{{ $selected_date ?? '' }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div class="sm:col-span-1">
                            <x-input-label for="drop_off_time_slot_id" :value="\App\Support\UiText::t('reservation', 'arrival_time')" />
                            <select
                                id="drop_off_time_slot_id"
                                name="drop_off_time_slot_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                @disabled(empty($selected_date))
                            >
                                <option value="">{{ \App\Support\UiText::t('reservation', 'select_time_slot') }}</option>
                                @foreach (($arrival_slots ?? []) as $s)
                                    <option value="{{ $s['id'] }}" @selected((int)($arrival_id ?? 0) === (int)$s['id']) @disabled((bool)$s['disabled'])>
                                        {{ $s['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-1">
                            <x-input-label for="pick_up_time_slot_id" :value="\App\Support\UiText::t('reservation', 'departure_time')" />
                            <select
                                id="pick_up_time_slot_id"
                                name="pick_up_time_slot_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                @disabled($departure_disabled ?? true)
                            >
                                <option value="">{{ \App\Support\UiText::t('reservation', 'select_time_slot') }}</option>
                                @foreach (($departure_slots ?? []) as $s)
                                    <option value="{{ $s['id'] }}" @selected((int)($departure_id ?? 0) === (int)$s['id']) @disabled((bool)$s['disabled'])>
                                        {{ $s['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">{{ \App\Support\UiText::t('reservation', 'departure_disabled_hint') }}</p>
                        </div>
                    </div>
                </div>
            </form>

            <form method="POST" action="{{ route('panel.fzbr.store', [], false) }}" enctype="multipart/form-data" class="space-y-4 bg-white shadow sm:rounded-lg p-6">
                @csrf

                <input type="hidden" name="reservation_date" id="post_reservation_date" value="{{ $selected_date ?? '' }}">
                <input type="hidden" name="drop_off_time_slot_id" id="post_drop_off_time_slot_id" value="{{ $arrival_id ?? '' }}">
                <input type="hidden" name="pick_up_time_slot_id" id="post_pick_up_time_slot_id" value="{{ $departure_id ?? '' }}">

                <div class="space-y-2">
                    <div class="text-sm font-semibold text-gray-900">{{ $ui('vehicles_title', 'Vozila') }}</div>

                    <template x-for="(row, idx) in rows" :key="idx">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-start">
                            <div class="md:col-span-10 min-w-0">
                                <x-input-label :value="$p('nav_vehicles', 'Vehicles')" />
                                <select
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    :name="`vehicles[${idx}]`"
                                    x-model="row.vehicle_id"
                                    required
                                >
                                    <option value="">{{ $ui('select_vehicle', 'Izaberite vozilo') }}</option>
                                    <template x-for="opt in optionsFor(idx)" :key="opt.id">
                                        <option :value="opt.id" x-text="opt.label"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="md:col-span-2 flex gap-2 md:justify-end pt-6 min-w-0">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-md bg-gray-800 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                    x-show="isAddVisible(idx)"
                                    :disabled="!canAdd(idx)"
                                    @click="addRow()"
                                >
                                    {{ $ui('add_vehicle', 'Dodaj vozilo') }}
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-800 hover:bg-gray-50"
                                    x-show="!isAddVisible(idx)"
                                    @click="confirmRemove(idx)"
                                >
                                    {{ $ui('remove_vehicle', 'Ukloni vozilo') }}
                                </button>
                            </div>
                        </div>
                    </template>

                    <div x-show="showConfirm" class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="absolute inset-0 bg-black/50" @click="cancelRemove()"></div>
                        <div class="relative w-[92%] max-w-md rounded-lg bg-white shadow-lg p-4 space-y-3" @click.stop>
                            <div class="font-semibold">{{ $ui('remove_vehicle_confirm', 'Da li si siguran da želiš da ukloniš ovo vozilo?') }}</div>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-800 hover:bg-gray-50" @click="cancelRemove()">
                                    {{ $ui('remove_vehicle_no', 'Ne') }}
                                </button>
                                <button type="button" class="rounded-md bg-red-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-700" @click="applyRemove()">
                                    {{ $ui('remove_vehicle_yes', 'Da') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500">{{ $ui('vehicles_limit', 'Minimum 1, maksimum 9 vozila.') }}</p>
                </div>

                <div class="space-y-2">
                    <x-input-label for="documents" :value="$uploadLabel" />
                    <input
                        id="documents"
                        name="documents[]"
                        type="file"
                        multiple
                        accept="image/*,application/pdf"
                        class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-gray-800 file:px-3 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-widest file:text-white hover:file:bg-gray-700"
                        required
                    />
                    <p class="text-xs text-gray-600">{{ $uploadHint }}</p>
                    <p class="text-xs text-gray-500">{{ $ui('documents_limit', 'Ukupna veličina svih fajlova: do 10 MB. Dozvoljene slike i PDF.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('documents')" />
                </div>

                <div class="space-y-2">
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" name="accept_privacy" value="1" class="mt-1 rounded border-gray-300" {{ old('accept_privacy') ? 'checked' : '' }} required>
                        <span>{{ \App\Support\UiText::t('reservation', 'accept_privacy') }}</span>
                    </label>
                    <x-input-error class="mt-2" :messages="$errors->get('accept_privacy')" />
                </div>

                <button
                    type="submit"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="!canSubmit"
                >
                    {{ $ui('submit', 'Podnesi zahtjev') }}
                </button>
            </form>
        </div>
    </div>

    <script>
        function fzbrForm() {
            const all = @json(($userVehicles ?? collect())->map(function ($v) use ($locale) {
                $vtName = $v->vehicleType?->getTranslatedName($locale) ?: ('#' . $v->vehicle_type_id);
                $vtDesc = String($v->vehicleType?->getTranslatedDescription($locale) || '').trim();
                const label = $v->license_plate + ' — ' + (vtDesc ? (vtName + ' (' + vtDesc + ')') : vtName);
                return ['id' => (int)$v->id, 'label' => $label];
            })->values());

            return {
                rows: [{ vehicle_id: '' }],
                showConfirm: false,
                pendingRemoveIdx: null,

                get selectedIds() {
                    return this.rows.map(r => String(r.vehicle_id || '')).filter(v => v !== '');
                },

                optionsFor(idx) {
                    const current = String(this.rows[idx]?.vehicle_id || '');
                    const selected = new Set(this.selectedIds.filter(v => v !== current));
                    return all.filter(o => !selected.has(String(o.id)) || String(o.id) === current);
                },

                isAddVisible(idx) {
                    return idx === this.rows.length - 1;
                },

                canAdd(idx) {
                    if (this.rows.length >= 9) return false;
                    const v = String(this.rows[idx]?.vehicle_id || '');
                    return v !== '';
                },

                addRow() {
                    if (this.rows.length >= 9) return;
                    const last = this.rows[this.rows.length - 1];
                    if (!last || !String(last.vehicle_id || '')) return;
                    this.rows.push({ vehicle_id: '' });
                },

                confirmRemove(idx) {
                    this.pendingRemoveIdx = idx;
                    this.showConfirm = true;
                },

                cancelRemove() {
                    this.showConfirm = false;
                    this.pendingRemoveIdx = null;
                },

                applyRemove() {
                    const idx = this.pendingRemoveIdx;
                    if (idx === null) return;
                    if (this.rows.length <= 1) {
                        this.rows[0].vehicle_id = '';
                    } else {
                        this.rows.splice(idx, 1);
                        if (this.rows.length < 1) this.rows = [{ vehicle_id: '' }];
                    }
                    this.showConfirm = false;
                    this.pendingRemoveIdx = null;
                },

                get canSubmit() {
                    const date = document.getElementById('post_reservation_date')?.value || '';
                    const drop = document.getElementById('post_drop_off_time_slot_id')?.value || '';
                    const pick = document.getElementById('post_pick_up_time_slot_id')?.value || '';
                    if (!date || !drop || !pick) return false;
                    if (this.selectedIds.length < 1) return false;
                    const docs = document.getElementById('documents');
                    if (!docs || !docs.files || docs.files.length < 1) return false;
                    const privacy = document.querySelector('input[name="accept_privacy"]');
                    if (!privacy || !privacy.checked) return false;
                    return true;
                },
            }
        }

        (function () {
            const stepForm = document.getElementById('fzbrStepForm');
            if (!stepForm) return;
            const date = document.getElementById('reservation_date');
            const arrival = document.getElementById('drop_off_time_slot_id');
            const departure = document.getElementById('pick_up_time_slot_id');
            const postDate = document.getElementById('post_reservation_date');
            const postArrival = document.getElementById('post_drop_off_time_slot_id');
            const postDeparture = document.getElementById('post_pick_up_time_slot_id');

            function sync() {
                if (postDate && date) postDate.value = date.value || '';
                if (postArrival && arrival) postArrival.value = arrival.value || '';
                if (postDeparture && departure) postDeparture.value = departure.value || '';
            }

            if (date) date.addEventListener('change', () => { sync(); stepForm.submit(); });
            if (arrival) arrival.addEventListener('change', () => { sync(); stepForm.submit(); });
            if (departure) departure.addEventListener('change', () => { sync(); stepForm.submit(); });
            sync();
        })();
    </script>
</x-app-layout>

