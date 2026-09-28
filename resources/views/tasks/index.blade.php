@extends('layouts.irodori')
@section('title', '提出物・やること — いろどり')
@section('wrap-class', 'narrow')

@section('content')

  <div class="card">
    <h2>提出物・やること</h2>
    <p class="lead">給食袋、集金、プリントの提出など。</p>

    <form method="post" action="{{ route('tasks.store') }}">
      @csrf
      <div class="field">
        <label for="title">やること<span class="req">必須</span></label>
        <input type="text" id="title" name="title" value="{{ old('title') }}"
               placeholder="例）給食袋を持たせる" required>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="member_id">だれの<span class="opt">任意</span></label>
          <select id="member_id" name="member_id">
            <option value="">家族ぜんいん</option>
            @foreach ($members as $member)
              <option value="{{ $member->id }}">{{ $member->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="due_date">期限<span class="opt">任意</span></label>
          <input type="date" id="due_date" name="due_date" value="{{ old('due_date') }}">
        </div>
      </div>
      <button type="submit" class="btn primary block">追加する</button>
    </form>
  </div>

  <h3 class="section-title">のこっているもの</h3>
  <div class="list">
    @forelse ($todo as $task)
      <div class="item">
        <form method="post" action="{{ route('tasks.update', $task) }}">
          @csrf @method('patch')
          <input type="hidden" name="is_done" value="1">
          <button type="submit" class="btn" style="height:44px;padding:0 14px;">おわった</button>
        </form>
        <div class="body">
          <b>{{ $task->title }}</b>
          <span>
            {{ $task->member->name ?? '家族ぜんいん' }}
            @if ($task->due_label)　<strong>{{ $task->due_label }}</strong>@endif
          </span>
        </div>
        <div class="actions">
          <form method="post" action="{{ route('tasks.destroy', $task) }}"
                onsubmit="return confirm('削除しますか？');">
            @csrf @method('delete')
            <button type="submit">削除</button>
          </form>
        </div>
      </div>
    @empty
      <p class="empty">のこっているものはありません。</p>
    @endforelse
  </div>

  @if ($done->isNotEmpty())
    <h3 class="section-title">おわったもの</h3>
    <div class="list">
      @foreach ($done as $task)
        <div class="item done">
          <form method="post" action="{{ route('tasks.update', $task) }}">
            @csrf @method('patch')
            <input type="hidden" name="is_done" value="0">
            <button type="submit" class="btn" style="height:44px;padding:0 14px;">もどす</button>
          </form>
          <div class="body">
            <b>{{ $task->title }}</b>
            <span>{{ $task->member->name ?? '家族ぜんいん' }}</span>
          </div>
        </div>
      @endforeach
    </div>
  @endif

@endsection
