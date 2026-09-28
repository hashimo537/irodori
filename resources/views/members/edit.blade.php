@extends('layouts.irodori')
@section('title', $member->name . 'さんの設定 — いろどり')
@section('wrap-class', 'narrow')

@section('content')
    <div class="card">
        <h2>{{ $member->name }}さんの設定</h2>

        <form method="post" action="{{ route('members.update', $member) }}">
            @csrf @method('put')

            <div class="field">
                <label for="name">なまえ</label>
                <input type="text" id="name" name="name" value="{{ old('name', $member->name) }}" required>
            </div>

            <div class="field">
                <label>色</label>
                <div class="colors">
                    @foreach ($palette as $hex => $info)
                        <label>
                            <input type="radio" name="color" value="{{ $hex }}" {{ old('color', $member->color) === $hex ? 'checked' : '' }}>
                            <span class="swatch" style="background:{{ $hex }}"></span>
                            {{ $info['label'] }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn primary">保存する</button>
                <a class="btn" href="{{ route('members.index') }}">やめる</a>
            </div>
        </form>
    </div>

    <form method="post" action="{{ route('members.destroy', $member) }}"
        onsubmit="return confirm('{{ $member->name }}さんと、その予定をすべて削除します。よろしいですか？');">
        @csrf @method('delete')
        <div class="btn-row">
            <button type="submit" class="btn danger">この子を削除する</button>
        </div>
    </form>
@endsection