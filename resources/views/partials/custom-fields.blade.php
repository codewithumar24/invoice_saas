{{-- Custom fields defined by the workspace for this kind of record. $model may be new. --}}
@if ($customFields->count())
    @php $existing = $model->exists ? $model->customFieldValues->pluck('value', 'custom_field_id') : collect(); @endphp
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($customFields as $field)
            @if ($field->type === 'select')
                <x-select name="custom_fields[{{ $field->id }}]" :label="$field->label" :options="array_combine($field->options ?? [], $field->options ?? [])" :value="$existing[$field->id] ?? null" :placeholder="__('Choose')" :required="$field->is_required" />
            @else
                <x-input name="custom_fields[{{ $field->id }}]" :type="$field->type === 'number' ? 'number' : ($field->type === 'date' ? 'date' : 'text')" :label="$field->label" :value="$existing[$field->id] ?? null" :required="$field->is_required" step="any" />
            @endif
        @endforeach
    </div>
@endif
