@extends('layouts.main')

@section('title', 'Election')

@section('body')
<div class="container mx-auto">
    <div class="max-w-md text-center mx-auto">
        Election! - {{ $election->title }}
    </div>
    <div class="max-w-md text-center mx-auto">
        <ul>
            @forelse ($election->ballots as $ballot)
            <li><a href="/election/{{ $ballot->election->id }}/ballot/{{ $ballot->id }}/preview">{{ $ballot->title }}</a></li>
            @empty
            <li>No ballots!</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
