@extends('layouts.guest')
@section('title', __('About'))

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <h1 class="font-serif text-4xl font-semibold tracking-tight">{{ __('About :app', ['app' => config('app.name')]) }}</h1>
        <div class="prose-basic mt-6">
            <p>{{ __('Most small businesses run on an invoice tool, a spreadsheet of expenses, another for stock and a feeling about how things are going. The numbers exist, but nobody has time to read them.') }}</p>
            <p>{{ __(':app keeps those records in one place and reads them for you. It tells you which invoices are overdue, which customers pay late, which costs are rising and where next month\'s cash will come from.', ['app' => config('app.name')]) }}</p>
            <h2>{{ __('What we believe') }}</h2>
            <ul>
                <li>{{ __('Record less by hand. Understand more automatically.') }}</li>
                <li>{{ __('Start small. A freelancer should not have to learn inventory to send an invoice.') }}</li>
                <li>{{ __('Your financial records are yours: exportable, auditable and never silently changed.') }}</li>
                <li>{{ __('Explain every number. No score without the calculation behind it.') }}</li>
            </ul>
            <p><a href="{{ route('contact') }}">{{ __('Get in touch') }}</a></p>
        </div>
    </div>
@endsection
