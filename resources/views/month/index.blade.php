@extends('layouts.irodori')
@section('title', $month->format('Y年n月') . ' — いろどり')

@php
// いまの切り替え状態を保ったままリンクを作るための材料
$base = ['month' => $month->format('Y-m')];
@endphp

@section('header')
    <div class="weeknav">
        <a class="navbtn"
            href="{{ route('month', $base + ['month' => $prev, 'names' => $showNames ? null : '0', 'member' => $onlyMember]) }}"
            aria-label="前の月">&lsaquo;</a>
        <div class="range">
            {{ $month->format('n月') }}
            <small>{{ $month->format('Y年') }}</small>
        </div>
        <a class="navbtn text" href="{{ route('month') }}">今月</a>
        <a class="navbtn"
            href="{{ route('month', $base + ['month' => $next, 'names' => $showNames ? null : '0', 'member' => $onlyMember]) }}"
            aria-label="次の月">&rsaquo;</a>
    </div>

    <div class="viewswitch">
        <a href="{{ route('home') }}">週で見る</a>
        <a href="{{ route('month') }}" class="on">月で見る</a>
    </div>

    {{-- 表示の切り替え --}}
    <div class="mtoggle">
        <span class="lbl">行事名</span>
        <a href="{{ route('month', $base + ['names' => '1', 'member' => $onlyMember]) }}"
            class="{{ $showNames ? 'on' : '' }}">出す</a>
        <a href="{{ route('month', $base + ['names' => '0', 'member' => $onlyMember]) }}"
            class="{{ $showNames ? '' : 'on' }}">かくす</a>

        <span class="sep"></span>

        <span class="lbl">だれの</span>
        <a href="{{ route('month', $base + ['names' => $showNames ? null : '0']) }}"
            class="{{ $onlyMember ? '' : 'on' }}">ぜんいん</a>
        @foreach ($members as $member)
            <a href="{{ route('month', $base + ['names' => $showNames ? null : '0', 'member' => $member->id]) }}"
                class="{{ $onlyMember === $member->id ? 'on' : '' }}">
                <i style="background:{{ $member->color }}"></i>{{ $member->name }}
            </a>
        @endforeach
    </div>
@endsection

@section('content')

    <div class="month">
        <div class="mhead">
            @foreach (['月', '火', '水', '木', '金', '土', '日'] as $i => $label)
                <div class="{{ $i === 5 ? 'sat' : '' }} {{ $i === 6 ? 'sun' : '' }}">{{ $label }}</div>
            @endforeach
        </div>

        <div class="mgrid">
            @foreach ($days as $day)
                @php
    $key = $day->toDateString();
    $cell = $cells[$key];
    $other = $day->month !== $month->month;   // 前後の月のマス
                @endphp

                <a class="mcell {{ $other ? 'other' : '' }} {{ $day->isWeekend() ? 'weekend' : '' }} {{ $day->isToday() ? 'today' : '' }}"
                    href="{{ route('home', ['date' => $key, 'd' => $key]) }}">

                    <span class="top">
                        <span
                            class="d {{ $day->isSaturday() ? 'sat' : '' }} {{ $day->isSunday() ? 'sun' : '' }}">{{ $day->day }}</span>

                        {{-- 天気（今日から16日先まで。それより先は何も出ない） --}}
                        @if ($cell['weather'])
                            <span class="mw" title="{{ $cell['weather']['label'] }}">
                                {{ $cell['weather']['icon'] }}
                                @if (!is_null($cell['weather']['max']))
                                    <small>{{ round($cell['weather']['max']) }}/{{ round($cell['weather']['min']) }}</small>
                                @endif
                            </span>
                        @endif
                    </span>

                    {{-- 予定がある子の色まる --}}
                    @if ($cell['colors'])
                        <span class="mdots">
                            @foreach ($cell['colors'] as $color)
                                <i style="background:{{ $color }}"></i>
                            @endforeach
                        </span>
                    @endif

                    {{-- 終日の予定・記念日・しめきり（「かくす」を選んでいるときは出さない） --}}
                    @if ($showNames)
                        @foreach (array_slice($cell['chips'], 0, 3) as $chip)
                            @if ($chip['kind'] === 'due')
                                <span class="mchip due">{{ $chip['title'] }}</span>
                            @elseif ($chip['kind'] === 'anniv')
                                <span class="mchip anniv">{{ $chip['title'] }}</span>
                            @else
                                <span class="mchip" style="background:{{ $chip['light'] }};border-color:{{ $chip['color'] }};">
                                    {{ $chip['title'] }}
                                </span>
                            @endif
                        @endforeach
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    {{-- 色の見かた --}}
    <div class="mlegend">
        @foreach ($members as $member)
            <span><i style="background:{{ $member->color }}"></i>{{ $member->name }}</span>
        @endforeach
        <span><i style="background:#A79BC0"></i>家族ぜんいん</span>
    </div>

        <p class="prefill" style="margin-top:14px;">
            @if ($family->hasLocation())
            天気は「{{ $family->location_name }}」の予報です（16日先まで）。
            <a href="{{ route('family.settings') }}">地域を変える</a>
            @else
            <a href="{{ route('family.settings') }}">地域を設定する</a>と、天気予報が出せます。
            @endif
        </p>
        

    <p class="empty">日にちをおすと、その週のタイムラインが開きます。</p>

@endsection

@section('fab')
    <a class="fab" href="{{ route('events.create', ['date' => now()->toDateString()]) }}">＋ 予定を追加</a>
@endsection