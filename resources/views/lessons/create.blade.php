@extends('layouts.irodori')
@section('title', '習い事を登録 — いろどり')
@section('wrap-class', 'narrow')

@section('content')
  <div class="card">
    <h2>習い事を登録</h2>
    <p class="lead">一度だけ登録すれば、毎週おなじ曜日に自動で表示されます。</p>

    @if ($members->isEmpty())
      <p class="empty">
        さきに お子さんを登録してください。<br>
        <a href="{{ route('members.index') }}">家族の登録へ</a>
      </p>
    @else
      <form method="post" action="{{ route('lessons.store') }}">
        @csrf
        @include('lessons._form')
        <div class="btn-row">
          <button type="submit" class="btn primary">登録する</button>
          <a class="btn" href="{{ route('lessons.index') }}">やめる</a>
        </div>
      </form>
    @endif
  </div>
@endsection