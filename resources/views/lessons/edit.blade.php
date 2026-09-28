@extends('layouts.irodori')
@section('title', '習い事を編集 — いろどり')
@section('wrap-class', 'narrow')

@section('content')
    <div class="card">
        <h2>習い事を編集</h2>

        <form method="post" action="{{ route('lessons.update', $lesson) }}">
            @csrf @method('put')
            @include('lessons._form')
            <div class="btn-row">
                <button type="submit" class="btn primary">保存する</button>
                <a class="btn" href="{{ route('lessons.index') }}">やめる</a>
            </div>
        </form>
    </div>

    <form method="post" action="{{ route('lessons.destroy', $lesson) }}" onsubmit="return confirm('この習い事を削除します。よろしいですか？');">
        @csrf @method('delete')
        <div class="btn-row">
            <button type="submit" class="btn danger">この習い事を削除する</button>
        </div>
    </form>
@endsection