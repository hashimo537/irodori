@extends('layouts.irodori')
@section('title', '習い事 — いろどり')
@section('wrap-class', 'narrow')

@section('content')

  <div class="btn-row" style="margin-top:18px;">
    <a class="btn primary" href="{{ route('lessons.create') }}">＋ 習い事を登録</a>
    <a class="btn" href="{{ route('home') }}">カレンダーへ</a>
  </div>

  @php $grouped = $lessons->groupBy('day_of_week'); @endphp

  @forelse ($grouped as $dow => $group)
    <h3 class="section-title">{{ ['日','月','火','水','木','金','土'][$dow] }}曜日</h3>
    <div class="list">
      @foreach ($group as $lesson)
        <div class="item">
          <div class="bar" style="background:{{ $lesson->member->color }}"></div>
          <div class="body">
            <b>{{ $lesson->title }}</b>
            <span>
              {{ $lesson->member->name }}　{{ $lesson->start_time->format('H:i') }}–{{ $lesson->end_time->format('H:i') }}
              @if ($lesson->place)　{{ $lesson->place }}@endif
              @if ($lesson->ends_on)　<em>（{{ $lesson->ends_on->format('n/j') }}まで）</em>@endif
            </span>
          </div>
          <div class="actions">
            <a href="{{ route('lessons.edit', $lesson) }}">編集</a>
          </div>
        </div>
      @endforeach
    </div>
  @empty
    <p class="empty">まだ習い事が登録されていません。</p>
  @endforelse

@endsection
