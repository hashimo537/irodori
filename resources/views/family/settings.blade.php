@extends('layouts.irodori')
@section('title', '地域の設定 — いろどり')
@section('wrap-class', 'narrow')

@section('content')

    <div class="card">
        <h2>天気予報の地域</h2>
        <p class="lead">
            住んでいる市区町村を入れて検索してください。
            設定すると、月カレンダーに16日先までの天気が出ます。
        </p>

        @if ($family->hasLocation())
            <div class="nowset">
                いまの設定：<strong>{{ $family->location_name }}</strong>
            </div>
        @endif

        <form method="get" action="{{ route('family.settings') }}">
            <div class="field" style="margin-top:18px;">
                <label for="q">地名<span class="req">必須</span></label>
                <input type="text" id="q" name="q" value="{{ $q }}" placeholder="例）横浜市" required autofocus>
            </div>
            <button type="submit" class="btn primary block">さがす</button>
        </form>

        @if ($q !== '')
            @if ($candidates)
                <h3 class="section-title">みつかった地名</h3>
                <div class="cand">
                    @foreach ($candidates as $c)
                        <form method="post" action="{{ route('family.location') }}">
                            @csrf
                            <input type="hidden" name="location_name" value="{{ $c['name'] }}">
                            <input type="hidden" name="latitude" value="{{ $c['latitude'] }}">
                            <input type="hidden" name="longitude" value="{{ $c['longitude'] }}">
                            <button type="submit">
                                {{ $c['name'] }}
                                <small>{{ $c['admin'] }}（{{ number_format($c['latitude'], 2) }},
                                    {{ number_format($c['longitude'], 2) }}）</small>
                            </button>
                        </form>
                    @endforeach
                </div>
            @else
                <p class="empty">
                    みつかりませんでした。<br>
                    市区町村の名前（「横浜市」「藤沢」など）で試してみてください。
                </p>
            @endif
        @endif
    </div>

    @if ($family->hasLocation())
        <form method="post" action="{{ route('family.location.clear') }}"
            onsubmit="return confirm('天気予報を出さない設定にします。よろしいですか？');">
            @csrf @method('delete')
            <div class="btn-row">
                <button type="submit" class="btn danger">天気を出さない</button>
                <a class="btn" href="{{ route('month') }}">カレンダーへ</a>
            </div>
        </form>
    @else
        <div class="btn-row">
            <a class="btn" href="{{ route('month') }}">カレンダーへ</a>
        </div>
    @endif

@endsection