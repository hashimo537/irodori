@php
    $isAllDay = old('all_day', ($event ?? null) && $event->is_all_day);
@endphp

<div class="field">
    <label for="title">なにを<span class="req">必須</span></label>
    <input type="text" id="title" name="title" value="{{ old('title', $event->title ?? '') }}"
        placeholder="例）運動会、歯医者、お迎え" required>
</div>

<div class="field">
    <label for="member_id">だれの<span class="opt">任意</span></label>
    <select id="member_id" name="member_id">
        <option value="">家族ぜんいん</option>
        @foreach ($members as $member)
            <option value="{{ $member->id }}" {{ (string) old('member_id', $event->member_id ?? '') === (string) $member->id ? 'selected' : '' }}>
                {{ $member->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="field">
    <label for="date">いつ<span class="req">必須</span></label>
    <input type="date" id="date" name="date"
        value="{{ old('date', isset($event) ? $event->date->toDateString() : ($date ?? now()->toDateString())) }}"
        required>
    @unless (isset($event))
        <p class="prefill">カレンダーで開いていた日を入れてあります。ちがう日なら変えてください。</p>
    @endunless
</div>

<div class="field">
    <label class="check">
        <input type="checkbox" name="all_day" value="1" id="all_day" {{ $isAllDay ? 'checked' : '' }}>
        終日（時間をきめない）
    </label>
</div>

<div class="field-row" id="times" @if ($isAllDay) style="display:none;" @endif>
    <div class="field">
        <label for="start_time">なんじから<span class="req">必須</span></label>
        <input type="time" id="start_time" name="start_time" step="300"
            value="{{ old('start_time', isset($event) && $event->start_time ? $event->start_time->format('H:i') : '') }}">
    </div>
    <div class="field">
        <label for="end_time">なんじまで<span class="opt">任意</span></label>
        <input type="time" id="end_time" name="end_time" step="300"
            value="{{ old('end_time', isset($event) && $event->end_time ? $event->end_time->format('H:i') : '') }}">
    </div>
</div>


<div class="field">
    <label for="pickup_user_id">お迎え<span class="opt">任意</span></label>
    <select id="pickup_user_id" name="pickup_user_id">
        <option value="">きめていない</option>
        @foreach ($parents as $parent)
            <option value="{{ $parent->id }}" {{ (string) old('pickup_user_id', $event->pickup_user_id ?? '') === (string) $parent->id ? 'selected' : '' }}>
                {{ $parent->name }}
            </option>
        @endforeach
    </select>
    <p class="prefill">家族として登録している人から選べます。招待コードで参加すると増えます。</p>
</div>

<div class="field">
    <label for="place">どこで<span class="opt">任意</span></label>
    <input type="text" id="place" name="place" value="{{ old('place', $event->place ?? '') }}" placeholder="例）市民プール">
</div>

<div class="field">
    <label for="note">メモ<span class="opt">任意</span></label>
    <textarea id="note" name="note" rows="3"
        placeholder="例）水筒とタオルを持たせる">{{ old('note', $event->note ?? '') }}</textarea>
</div>

@push('scripts')
    <script>
        // 「終日」にチェックすると時間の欄を隠す
        const allDay = document.getElementById('all_day');
        const times = document.getElementById('times');
        allDay.addEventListener('change', () => {
            times.style.display = allDay.checked ? 'none' : '';
        });
    </script>
@endpush