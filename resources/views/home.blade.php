@extends('layouts.main')

@section('title', 'Evoting Home')

@section('body')
<div class="container mx-auto">
    <h1>Elections:</h1>
    <section>
        <ul>
            @foreach ($elections as $election)
            <li><a href="/election/{{ $election->id }}">{{ $election->title }}</a></li>
            @endforeach
        </ul>
    </section>
</div>
@endsection
