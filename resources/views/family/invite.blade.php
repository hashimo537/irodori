@extends('layouts.irodori')
@section('title', '家族をまねく — いろどり')
@section('wrap-class', 'narrow')

@section('content')
    <div class="card">
        <h2>家族をまねく</h2>
        <p class="lead">
            このコードを パパや おじいちゃん・おばあちゃんに教えてください。<br>
            新規登録のあと「招待コードで参加する」に入力すると、同じ予定が見られます。
        </p>

        <div class="invite-code">{{ $family->invite_code }}</div>

        <p class="lead">まちがえやすい 0（ゼロ）と O、1 と I は使っていません。</p>

        <div class="btn-row">
            <a class="btn" href="{{ route('home') }}">もどる</a>
        </div>
    </div>
@endsection