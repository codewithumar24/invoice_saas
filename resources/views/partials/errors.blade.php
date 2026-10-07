{{-- Summary of validation errors; each field also shows its own message. --}}
@if ($errors->any())
    <x-alert tone="danger" class="mb-6">
        <p class="font-medium">{{ __('Please fix the following:') }}</p>
        <ul class="mt-1 list-disc ps-5">
            @foreach (array_slice($errors->all(), 0, 6) as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
