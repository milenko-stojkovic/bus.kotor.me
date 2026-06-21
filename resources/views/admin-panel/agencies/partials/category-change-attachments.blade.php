@php
    /** @var \App\Models\User $user */
    /** @var \App\Models\VehicleCategoryChangeRequest $req */
    $previewItems = $req->previewAttachments();
@endphp

@if ($previewItems->isEmpty())
    <span class="text-gray-500">—</span>
@else
    <ul class="space-y-1">
        @foreach ($previewItems as $index => $item)
            @php
                $isLegacy = ! empty($item->legacy);
                $hasLocal = $isLegacy
                    ? ($req->document_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($req->document_path))
                    : ($item instanceof \App\Models\VehicleCategoryChangeRequestAttachment && $item->hasLocalFile());
                $statusLabel = $isLegacy
                    ? 'Lokalni dokument dostupan'
                    : ($item instanceof \App\Models\VehicleCategoryChangeRequestAttachment ? $item->adminArchiveStatusLabel() : 'Lokalni dokument dostupan');
            @endphp
            <li>
                <span class="text-gray-700">{{ $item->original_name }}</span>
                <span class="text-gray-500 text-sm"> — {{ $statusLabel }}</span>
                @if ($hasLocal)
                    @if ($isLegacy)
                        <a class="text-red-700 underline font-medium ml-1"
                           href="{{ route('panel_admin.agencies.vehicle_category_change_requests.document', ['user' => $user->id, 'request' => $req->id], false) }}"
                           target="_blank" rel="noopener">
                            Preview
                        </a>
                    @else
                        <a class="text-red-700 underline font-medium ml-1"
                           href="{{ route('panel_admin.agencies.vehicle_category_change_requests.attachments.preview', ['user' => $user->id, 'request' => $req->id, 'attachment' => $item->id], false) }}"
                           target="_blank" rel="noopener">
                            Preview
                        </a>
                    @endif
                @elseif (! $isLegacy && $item instanceof \App\Models\VehicleCategoryChangeRequestAttachment && $item->archived_at && $item->archive_path)
                    <span class="text-gray-500 text-sm ml-1">({{ $item->archive_path }})</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
