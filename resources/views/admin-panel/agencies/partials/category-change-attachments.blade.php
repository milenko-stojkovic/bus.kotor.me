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
            <li>
                @if (! empty($item->legacy))
                    <a class="text-red-700 underline font-medium"
                       href="{{ route('panel_admin.agencies.vehicle_category_change_requests.document', ['user' => $user->id, 'request' => $req->id], false) }}"
                       target="_blank" rel="noopener">
                        Dokument {{ $index + 1 }} / {{ $item->original_name }} / Preview
                    </a>
                @else
                    <a class="text-red-700 underline font-medium"
                       href="{{ route('panel_admin.agencies.vehicle_category_change_requests.attachments.preview', ['user' => $user->id, 'request' => $req->id, 'attachment' => $item->id], false) }}"
                       target="_blank" rel="noopener">
                        Dokument {{ $index + 1 }} / {{ $item->original_name }} / Preview
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
@endif
