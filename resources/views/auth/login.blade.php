@extends('layouts.irodori-guest')
@section('title', 'ログイン — いろどり')

@section('content')
    <div class="card">
        <h2>ログイン</h2>

        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif

        <form method="post" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="email">メールアドレス</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required
                    autofocus>
            </div>

            <div class="field">
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>

            <div class="field">
                <label class="check">
                    <input type="checkbox" name="remember" value="1">
                    ログインしたままにする
                </label>
            </div>

            <button type="submit" class="btn primary block">ログイン</button>
        </form>

        <p style="margin-top:18px;font-size:14px;text-align:center;">
            はじめての方は <a href="{{ route('register') }}">新規登録</a>
        </p>
    </div>
@endsection