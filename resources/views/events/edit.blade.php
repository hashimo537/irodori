@extends('layouts.irodori')
@section('title', '予定を編集 — いろどり')
@section('wrap-class', 'narrow')

@section('content')
    <div class="card">
        <h2>予定を編集</h2>

        <form method="post" action="{{ route('events.update', $event) }}">
            @csrf @method('put')
            @include('events._form')

            <div class="btn-row">
                <button type="submit" class="btn primary">保存する</button>
                <a class="btn" href="{{ route('home', ['date' => $event->date->toDateString()]) }}">やめる</a>
            </div>
        </form>
    </div>

    <form method="post" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('この予定を削除します。よろしいですか？');">
        @csrf @method('delete')
        <div class="btn-row">
            <button type="submit" class="btn danger">この予定を削除する</button>
        </div>
    </form>
@endsection