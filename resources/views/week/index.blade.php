@extends('layouts.irodori')
@section('title', 'いろどり — 週のよてい')

@php
  // タイムラインの高さ。1分 = 1px なので、分の差がそのまま px になる
  $height   = $endMin - $startMin + 20;
  $prevWeek = $start->copy()->subWeek()->toDateString();
  $nextWeek = $start->copy()->addWeek()->toDateString();

  // スマホの1日表示で、どの日を開いているか
  $selected = request('d');
  if (! $selected || ! $days->contains(fn ($d) => $d->toDateString() === $selected)) {
      $today    = now()->toDateString();
      $selected = $days->contains(fn ($d) => $d->toDateString() === $today)
                  ? $today
                  : $days->first()->toDateString();
  }
@endphp

@section('header')
  <div class="weeknav">
    <a class="navbtn" href="{{ route('home', ['date' => $prevWeek]) }}" aria-label="前の週">&lsaquo;</a>
    <div class="range">
      {{ $start->format('n月j日') }} — {{ $days->last()->format('n月j日') }}
      <small>{{ $start->format('Y年 n月') }}</small>
    </div>
    <a class="navbtn text" href="{{ route('home') }}">今週</a>
    <a class="navbtn" href="{{ route('home', ['date' => $nextWeek]) }}" aria-label="次の週">&rsaquo;</a>
  </div>

  <div class="viewswitch">
    <a href="{{ route('home') }}" class="on">週で見る</a>
    <a href="{{ route('month') }}">月で見る</a>
  </div>

  @if ($members->isNotEmpty())
    <div class="legend">
      @foreach ($members as $member)
        <span class="chip"><i class="dot" style="background:{{ $member->color }}"></i>{{ $member->name }}</span>
      @endforeach
    </div>
  @endif
@endsection

@section('content')

  {{-- ===== 今週の提出物（オプション機能） ===== --}}
  @if ($todos->isNotEmpty())
    <section class="todo">
      <h2>こんしゅうの提出物</h2>
      <ul>
        @foreach ($todos as $todo)
          <li>
            <form method="post" action="{{ route('tasks.update', $todo) }}">
              @csrf @method('patch')
              <input type="hidden" name="is_done" value="1">
              <input type="checkbox" onchange="this.form.submit()" aria-label="おわった">
              <span>{{ $todo->title }}@if ($todo->member)（{{ $todo->member->name }}）@endif</span>
              @if ($todo->due_label)
                <span class="when {{ $todo->is_urgent ? 'urgent' : '' }}">{{ $todo->due_label }}</span>
              @endif
            </form>
          </li>
        @endforeach
      </ul>
    </section>
  @endif

  {{-- ===== 週タイムライン（パソコン・タブレット） ===== --}}
  <div class="board">

    {{-- 日付の行 --}}
    <div class="daybar">
      <div></div>
      @foreach ($days as $day)
        <div class="{{ $day->isToday() ? 'today' : '' }} {{ $day->isSaturday() ? 'sat' : '' }} {{ $day->isSunday() ? 'sun' : '' }}">
          {{ ['日','月','火','水','木','金','土'][$day->dayOfWeek] }}
          <span class="num">{{ $day->day }}</span>
        </div>
      @endforeach
    </div>

    {{-- 終日の予定 --}}
    @if (collect($allDayByDate)->flatten(1)->isNotEmpty())
      <div class="alldaybar">
        <div>終日</div>
        @foreach ($days as $day)
          <div>
            @foreach ($allDayByDate[$day->toDateString()] as $item)
              @if ($item['kind'] === 'anniv')
                <a class="allday anniv" href="{{ route('anniversaries.index') }}">
                  <span class="tag">きねんび</span>{{ $item['title'] }}
                  <span class="who">{{ $item['name'] }}</span>
                </a>
              @else
                <a class="allday" href="{{ route('events.edit', $item['id']) }}"
                   style="background:{{ $item['light'] }};border-color:{{ $item['color'] }};">
                  <span class="tag">終日</span>{{ $item['title'] }}@if ($item['note'])<span class="stamp">メモ</span>@endif
                  <span class="who">
                    {{ $item['name'] }}@if ($item['place'])・{{ $item['place'] }}@endif
                    @if ($item['pickup'])<span class="pickup">迎 {{ $item['pickup'] }}</span>@endif
                  </span>
                </a>
              @endif
            @endforeach
          </div>
        @endforeach
      </div>
    @endif

    {{-- 時間のタイムライン本体 --}}
    <div class="scroller" id="scroller">
      <div class="grid" style="height:{{ $height }}px;">

        {{-- 左はしの時間メモリ --}}
        <div class="hours" style="height:{{ $height }}px;">
          @for ($h = intdiv($startMin, 60); $h <= intdiv($endMin, 60); $h++)
            <div class="h" style="top:{{ $h * 60 - $startMin }}px;">{{ $h }}:00</div>
          @endfor
        </div>

        {{-- 曜日ごとの列 --}}
        @foreach ($days as $day)
          @php $slots = $slotsByDate[$day->toDateString()]; @endphp
          <div class="col {{ $day->isWeekend() ? 'weekend' : '' }}">

            {{-- 1時間ごとの罫線 --}}
            @for ($h = intdiv($startMin, 60); $h <= intdiv($endMin, 60); $h++)
              <div class="rule" style="top:{{ $h * 60 - $startMin }}px;"></div>
              @if ($h * 60 < $endMin)
                <div class="rule half" style="top:{{ $h * 60 - $startMin + 30 }}px;"></div>
              @endif
            @endfor

            {{-- 予定の帯。ここが このアプリの心臓 --}}
            @foreach ($slots as $slot)
              @php
                $top   = $slot['start_min'] - $startMin;                   // 上からの距離(px)
                $bandH = max($slot['end_min'] - $slot['start_min'], 34);   // 高さ(px)

                // 重なっている本数ぶんだけ横に割る
                $cols  = $slot['cols'];
                $width = 100 / $cols;
                $left  = $slot['col'] * $width;

                $route = $slot['type'] === 'lesson'
                         ? route('lessons.edit', $slot['id'])
                         : route('events.edit', $slot['id']);
              @endphp
              <a class="slot {{ $bandH < 52 ? 'narrow' : '' }} {{ $cols > 1 ? 'side' : '' }}"
                 href="{{ $route }}"
                 style="top:{{ $top }}px; height:{{ $bandH }}px;
                        left:calc({{ $left }}% + 3px);
                        width:calc({{ $width }}% - 6px);
                        background:{{ $slot['light'] }};
                        border-left:4px solid {{ $slot['color'] }};">
                <span class="t">{{ $slot['title'] }}@if ($slot['note'])<span class="stamp">メモ</span>@endif</span>
                <span class="m">{{ $slot['name'] }}@if ($slot['pickup'])<span class="pickup">迎 {{ $slot['pickup'] }}</span>@endif</span>
                <span class="time">{{ $slot['start'] }}–{{ $slot['end'] }}@if ($slot['place']) ・{{ $slot['place'] }}@endif</span>
              </a>
            @endforeach

          </div>
        @endforeach

      </div>
    </div>
  </div>

  {{-- ===== スマホ：1日表示 ===== --}}
  <div class="dayview">
    <div class="daytabs">
      @foreach ($days as $day)
        @php $key = $day->toDateString(); @endphp
        <a class="daytab {{ $key === $selected ? 'on' : '' }}"
           href="{{ route('home', ['date' => $start->toDateString(), 'd' => $key]) }}#day">
          {{ ['日','月','火','水','木','金','土'][$day->dayOfWeek] }}
          <b>{{ $day->day }}</b>
          <span class="pips">
            @foreach (collect($slotsByDate[$key])->pluck('color')->unique()->take(4) as $c)
              <i style="background:{{ $c }}"></i>
            @endforeach
          </span>
        </a>
      @endforeach
    </div>

    <div class="daylist" id="day">
      @php
        $items = collect($allDayByDate[$selected])->map(fn ($a) => $a + ['start' => null, 'end' => null])
                 ->concat(collect($slotsByDate[$selected])->map(fn ($x) => $x + ['kind' => 'slot']));
      @endphp

      @forelse ($items as $item)
        @php
          $href = match ($item['kind']) {
              'anniv' => route('anniversaries.index'),
              'event' => route('events.edit', $item['id']),
              default => $item['type'] === 'lesson'
                         ? route('lessons.edit', $item['id'])
                         : route('events.edit', $item['id']),
          };
        @endphp
        <a class="drow" href="{{ $href }}">
          <div class="clock">
            @if ($item['kind'] === 'anniv')
              <b>きねんび</b>
            @elseif ($item['start'] === null)
              <b>終日</b>
            @else
              <b>{{ $item['start'] }}</b>{{ $item['end'] }}
            @endif
          </div>
          <div class="bar" style="background:{{ $item['color'] }}"></div>
          <div class="body">
            <b>{{ $item['title'] }}@if (! empty($item['note']))<span class="stamp">メモ</span>@endif</b>
            <span>
              {{ $item['name'] }}@if (! empty($item['place']))　{{ $item['place'] }}@endif
              @if (! empty($item['pickup']))<span class="pickup">迎 {{ $item['pickup'] }}</span>@endif

            </span>
          </div>
        </a>
      @empty
        <p class="empty">この日の予定はありません</p>
      @endforelse
    </div>
  </div>

@endsection

@section('fab')
  <a class="fab" href="{{ route('events.create', ['date' => $selected]) }}">＋ 予定を追加</a>
@endsection

@push('scripts')
<script>
  // 夕方あたりが最初に見えるようにスクロールしておく
  const sc = document.getElementById('scroller');
  if (sc) sc.scrollTop = {{ 14 * 60 - $startMin }} - 60;
</script>
@endpush
