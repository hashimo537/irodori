@extends('layouts.irodori')
@section('title', '予定を追加 — いろどり')
@section('wrap-class', 'narrow')

@section('content')
    <div class="card">
        <h2>予定を追加</h2>
        <p class="lead">毎週くりかえす習い事は「習い事」から登録してください。</p>

        <form method="post" action="{{ route('events.store') }}">
            @csrf
            @include('events._form')

            <div class="btn-row">
                <button type="submit" class="btn primary">追加する</button>
                <a class="btn" href="{{ route('home') }}">やめる</a>
            </div>
        </form>
    </div>
@endsection