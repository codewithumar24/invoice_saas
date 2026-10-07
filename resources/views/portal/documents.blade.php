@extends('layouts.customer')
@section('title', __('Documents'))

@section('content')
    <h1 class="h1 mb-6">{{ __('Documents') }}</h1>
    @if ($documents->count())
        <x-table>
            <thead><tr><th>{{ __('File') }}</th><th>{{ __('Added') }}</th><th class="text-end">{{ __('Size') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($documents as $document)
                    <tr>
                        <td data-label="{{ __('File') }}" class="font-medium">{{ $document->original_name }}</td>
                        <td data-label="{{ __('Added') }}">{{ $document->created_at->format('d M Y') }}</td>
                        <td data-label="{{ __('Size') }}" class="num text-end">{{ number_format($document->size / 1024) }} KB</td>
                        <td class="text-end"><a href="{{ route('portal.documents.download', [$portalTenant->slug, $document->id]) }}" class="btn btn-secondary btn-sm">{{ __('Download') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        {{ $documents->links() }}
    @else
        <div class="card"><x-empty :title="__('No documents yet')" :text="__('Documents that :business shares with you appear here.', ['business' => $portalTenant->name])" /></div>
    @endif
@endsection
