@extends('layouts.irodori-guest')
@section('title', '新規登録 — いろどり')

@section('content')
    <div class="card">
        <h2>新規登録</h2>
        <p class="lead">登録がすんだら、家族をつくるか、招待コードで参加します。</p>

        <form method="post" action="{{ route('register') }}">
            @csrf

            <div class="field">
                <label for="name">おなまえ</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>
            </div>

            <div class="field">
                <label for="email">メールアドレス</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
            </div>

            <div class="field">
                <label for="password">パスワード <span class="hint">（8文字以上）</span></label>
                <input type="password" id="password" name="password" autocomplete="new-password" required>
            </div>

            <div class="field">
                <label for="password_confirmation">パスワード（確認）</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password"
                    required>
            </div>

            <button type="submit" class="btn primary block">登録する</button>
        </form>

        <p style="margin-top:18px;font-size:14px;text-align:center;">
            すでにお持ちの方は <a href="{{ route('login') }}">ログイン</a>
        </p>
    </div>
@endsection