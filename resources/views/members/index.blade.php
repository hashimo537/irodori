@extends('layouts.irodori')
@section('title', '家族の登録 — いろどり')
@section('wrap-class', 'narrow')

@section('content')

    <div class="card">
        <h2>お子さんを登録する</h2>
        <p class="lead">登録した色で、予定が色分けされます。</p>

        <form method="post" action="{{ route('members.store') }}">
            @csrf
            <div class="field">
                <label for="name">なまえ<span class="req">必須</span> <span class="hint">よび名でOK</span></label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="例）ゆい" required>
            </div>

            <div class="field">
                <label>色<span class="req">必須</span></label>
                <div class="colors">
                    @foreach ($palette as $hex => $info)
                        <label>
                            <input type="radio" name="color" value="{{ $hex }}" {{ $loop->first ? 'checked' : '' }}>
                            <span class="swatch" style="background:{{ $hex }}"></span>
                            {{ $info['label'] }}
                        </label>
                    @endforeach
                </div>
            </div>

            <button type="submit" class="btn primary block">登録する</button>
        </form>
    </div>

    @if ($members->isNotEmpty())
        <h3 class="section-title">登録ずみ</h3>
        <div class="list">
            @foreach ($members as $member)
                <div class="item">
                    <div class="bar" style="background:{{ $member->color }}"></div>
                    <div class="body">
                        <b>{{ $member->name }}</b>
                        <span>{{ $member->lessons()->count() }}件の習い事</span>
                    </div>
                    <div class="actions">
                        <a href="{{ route('members.edit', $member) }}">編集</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="btn-row">
        <a class="btn primary" href="{{ route('home') }}">カレンダーへ</a>
        <a class="btn" href="{{ route('family.invite') }}">家族をまねく</a>
    </div>

@endsection