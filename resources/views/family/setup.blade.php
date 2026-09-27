@extends('layouts.irodori-guest')
@section('title', '家族をつくる — いろどり')

@section('content')

    <div class="card">
        <h2>家族をつくる</h2>
        <p class="lead">はじめての方はこちら。あとで家族を招待できます。</p>
        <form method="post" action="{{ route('family.store') }}">
            @csrf
            <div class="field">
                <label for="name">家族の名前</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="例）はしもと家" required>
            </div>
            <button type="submit" class="btn primary block">つくる</button>
        </form>
    </div>

    <div class="card">
        <h2>招待コードで参加する</h2>
        <p class="lead">家族から8文字のコードを受けとった方はこちら。</p>
        <form method="post" action="{{ route('family.join') }}">
            @csrf
            <div class="field">
                <label for="invite_code">招待コード</label>
                <input type="text" id="invite_code" name="invite_code" value="{{ old('invite_code') }}"
                    placeholder="ABCD2345" maxlength="8" style="text-transform:uppercase;letter-spacing:.2em;" required>
            </div>
            <button type="submit" class="btn block">参加する</button>
        </form>
    </div>

@endsection