<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Moja vozila') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if(session('message'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('message') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Dodaj vozilo') }}</h3>
                    <form method="post" action="{{ route('profile.vehicles.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        @csrf
                        <div>
                            <x-input-label for="license_plate" :value="__('Tablica')" />
                            <x-text-input id="license_plate" name="license_plate" class="mt-1 block w-full" :value="old('license_plate')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('license_plate')" />
                        </div>
                        <div>
                            <x-input-label for="vehicle_type_id" :value="__('Tip vozila')" />
                            <select id="vehicle_type_id" name="vehicle_type_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="">{{ __('Izaberi') }}</option>
                                @foreach($vehicleTypes as $type)
                                    <option value="{{ $type->id }}" @selected((string) old('vehicle_type_id') === (string) $type->id)>
                                        {{ $type->getTranslatedName(app()->getLocale()) }} ({{ $type->price }})
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('vehicle_type_id')" />
                        </div>
                        <div>
                            <x-primary-button>{{ __('Dodaj') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Lista vozila') }}</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Tablica') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Tip') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Akcije') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($vehicles as $vehicle)
                                    <tr>
                                        <td class="px-4 py-3">{{ $vehicle->license_plate }}</td>
                                        <td class="px-4 py-3">{{ $vehicle->vehicleType?->getTranslatedName(app()->getLocale()) }}</td>
                                        <td class="px-4 py-3">
                                            <form method="post" action="{{ route('profile.vehicles.update', $vehicle->id) }}" class="grid grid-cols-1 md:grid-cols-4 gap-2 items-end">
                                                @csrf
                                                @method('patch')
                                                <input name="license_plate" value="{{ $vehicle->license_plate }}" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                <select name="vehicle_type_id" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                    @foreach($vehicleTypes as $type)
                                                        <option value="{{ $type->id }}" @selected($vehicle->vehicle_type_id === $type->id)>{{ $type->getTranslatedName(app()->getLocale()) }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="inline-flex items-center px-3 py-2 bg-gray-800 text-white rounded-md text-xs font-semibold uppercase tracking-widest">{{ __('Sačuvaj') }}</button>
                                            </form>
                                            <form method="post" action="{{ route('profile.vehicles.destroy', $vehicle->id) }}" class="mt-2">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="inline-flex items-center px-3 py-2 bg-red-600 text-white rounded-md text-xs font-semibold uppercase tracking-widest">{{ __('Obriši') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-6 text-center text-gray-500">{{ __('Nema dodatih vozila.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($vehicles->hasPages())
                        <div class="mt-4">{{ $vehicles->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
