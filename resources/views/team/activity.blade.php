@extends('layouts.app')
@section('title', __('Activity of :name', ['name' => $member->name]))

@section('content')
    <x-page-header :title="__('Activity of :name', ['name' => $member->name])" :subtitle="$member->email" :back="route('team.index')" />
    <div class="card card-pad max-w-3xl">
        @include('partials.timeline', ['items' => $activities, 'empty' => __('This member has not done anything in this workspace yet.')])
    </div>
    {{ $activities->links() }}
@endsection
