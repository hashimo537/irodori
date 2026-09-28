@extends('layouts.irodori')
@section('title', '記念日を編集 — いろどり')
@section('wrap-class', 'narrow')

@section('content')
    <div class="card">
        <h2>記念日を編集</h2>

        <form method="post" action="{{ route('anniversaries.update', $anniversary) }}">
            @csrf @method('put')

            <div class="field">
                <label for="title">記念日の名前<span class="req">必須</span></label>
                <input type="text" id="title" name="title" value="{{ old('title', $anniversary->title) }}" required>
            </div>

            <div class="field">
                <label for="member_id">だれの<span class="opt">任意</span></label>
                <select id="member_id" name="member_id">
                    <option value="">家族の記念日</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}" {{ (string) old('member_id', $anniversary->member_id) === (string) $member->id ? 'selected' : '' }}>
                            {{ $member->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="month">月<span class="req">必須</span></label>
                    <select id="month" name="month" required>
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ (string) old('month', $anniversary->month) === (string) $m ? 'selected' : '' }}>{{ $m }}月</option>
                        @endfor
                    </select>
                </div>
                <div class="field">
                    <label for="day">日<span class="req">必須</span></label>
                    <select id="day" name="day" required>
                        @for ($d = 1; $d <= 31; $d++)
                            <option value="{{ $d }}" {{ (string) old('day', $anniversary->day) === (string) $d ? 'selected' : '' }}>{{ $d }}日</option>
                        @endfor
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="start_year">はじまった年<span class="opt">任意</span></label>
                <input type="number" id="start_year" name="start_year"
                    value="{{ old('start_year', $anniversary->start_year) }}" min="1900" max="2100">
            </div>

            <div class="btn-row">
                <button type="submit" class="btn primary">保存する</button>
                <a class="btn" href="{{ route('anniversaries.index') }}">やめる</a>
            </div>
        </form>
    </div>

    <form method="post" action="{{ route('anniversaries.destroy', $anniversary) }}"
        onsubmit="return confirm('この記念日を削除します。よろしいですか？');">
        @csrf @method('delete')
        <div class="btn-row">
            <button type="submit" class="btn danger">この記念日を削除する</button>
        </div>
    </form>
@endsection