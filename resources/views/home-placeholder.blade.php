{{-- resources/views/home-placeholder.blade.php --}}
{{-- 9-6 で週タイムラインに差し替える仮の画面 --}}
@extends('layouts.irodori')
@section('title', 'ホーム — いろどり')
@section('wrap-class', 'narrow')

@section('content')
    <div class="card">
        <h2>ログインできました</h2>
        <p class="lead">週タイムラインは 9-6 で作ります。</p>

        <div class="nowset">
            家族：<strong>{{ auth()->user()->family->name }}</strong>
        </div>

        <div class="invite-code">{{ auth()->user()->family->invite_code }}</div>
        <p class="lead">このコードを家族に教えると、同じ画面が見られます。</p>

        <div class="btn-row">
            <a class="btn" href="{{ route('family.invite') }}">招待コードの画面</a>
        </div>
    </div>
@endsection