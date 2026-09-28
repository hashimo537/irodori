@extends('layouts.irodori')
@section('title', '記念日 — いろどり')
@section('wrap-class', 'narrow')

@section('content')

  <div class="card">
    <h2>記念日を登録</h2>
    <p class="lead">誕生日や入学記念日など。毎年おなじ日にカレンダーへ出ます。</p>

    <form method="post" action="{{ route('anniversaries.store') }}">
      @csrf

      <div class="field">
        <label for="title">記念日の名前<span class="req">必須</span></label>
        <input type="text" id="title" name="title" value="{{ old('title') }}"
               placeholder="例）ゆいの誕生日" required>
      </div>

      <div class="field">
        <label for="member_id">だれの<span class="opt">任意</span></label>
        <select id="member_id" name="member_id">
          <option value="">家族の記念日</option>
          @foreach ($members as $member)
            <option value="{{ $member->id }}">{{ $member->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="month">月<span class="req">必須</span></label>
          <select id="month" name="month" required>
            <option value="">えらぶ</option>
            @for ($m = 1; $m <= 12; $m++)
              <option value="{{ $m }}" {{ (string) old('month') === (string) $m ? 'selected' : '' }}>{{ $m }}月</option>
            @endfor
          </select>
        </div>
        <div class="field">
          <label for="day">日<span class="req">必須</span></label>
          <select id="day" name="day" required>
            <option value="">えらぶ</option>
            @for ($d = 1; $d <= 31; $d++)
              <option value="{{ $d }}" {{ (string) old('day') === (string) $d ? 'selected' : '' }}>{{ $d }}日</option>
            @endfor
          </select>
        </div>
      </div>

      <div class="field">
        <label for="start_year">はじまった年<span class="opt">任意</span></label>
        <input type="number" id="start_year" name="start_year" value="{{ old('start_year') }}"
               min="1900" max="2100" placeholder="例）2019">
        <p class="prefill">生まれた年を入れると「7さい」のように年齢が出ます。</p>
      </div>

      <button type="submit" class="btn primary block">登録する</button>
    </form>
  </div>

  @php $grouped = $anniversaries->groupBy('month'); @endphp

  @forelse ($grouped as $m => $group)
    <h3 class="section-title">{{ $m }}月</h3>
    <div class="list">
      @foreach ($group as $anniv)
        <div class="item">
          <div class="bar" style="background:{{ $anniv->member->color ?? '#D8BE6A' }}"></div>
          <div class="body">
            <b>{{ $anniv->title }}</b>
            <span>
              {{ $anniv->date_label }}
              @if ($anniv->member)　{{ $anniv->member->name }}@endif
              @if ($anniv->start_year)　{{ $anniv->start_year }}年から@endif
            </span>
          </div>
          <div class="actions">
            <a href="{{ route('anniversaries.edit', $anniv) }}">編集</a>
          </div>
        </div>
      @endforeach
    </div>
  @empty
    <p class="empty">まだ記念日が登録されていません。</p>
  @endforelse

  <div class="btn-row">
    <a class="btn" href="{{ route('month') }}">カレンダーへ</a>
  </div>

@endsection
