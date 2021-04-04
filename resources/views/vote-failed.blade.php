@extends('layouts.main')

@section('title', 'Voted')

@section('body')
<div class="container mx-auto">
    <div class="max-w-md text-center mx-auto">
        <h1 class="text-2xl">Failed</h1>
    </div>
    {{$errors}}
</div>
@endsection
