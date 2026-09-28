<div class="field">
    <label for="title">習い事の名前<span class="req">必須</span></label>
    <input type="text" id="title" name="title" value="{{ old('title', $lesson->title ?? '') }}" placeholder="例）スイミング"
        required>
</div>

<div class="field">
    <label for="member_id">だれの<span class="req">必須</span></label>
    <select id="member_id" name="member_id" required>
        @foreach ($members as $member)
            <option value="{{ $member->id }}" {{ (string) old('member_id', $lesson->member_id ?? '') === (string) $member->id ? 'selected' : '' }}>
                {{ $member->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="field">
    <label>なん曜日<span class="req">必須</span></label>
    <div class="weekdays">
        @foreach (['日', '月', '火', '水', '木', '金', '土'] as $i => $label)
            <label>
                <input type="radio" name="day_of_week" value="{{ $i }}" {{ (string) old('day_of_week', $lesson->day_of_week ?? '') === (string) $i ? 'checked' : '' }}>
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
</div>

<div class="field-row">
    <div class="field">
        <label for="start_time">なんじから<span class="req">必須</span></label>
        <input type="time" id="start_time" name="start_time" step="300" required
            value="{{ old('start_time', isset($lesson) ? $lesson->start_time->format('H:i') : '') }}">
    </div>
    <div class="field">
        <label for="end_time">なんじまで<span class="req">必須</span></label>
        <input type="time" id="end_time" name="end_time" step="300" required
            value="{{ old('end_time', isset($lesson) ? $lesson->end_time->format('H:i') : '') }}">
    </div>
</div>


<div class="field">
    <label for="pickup_user_id">お迎え<span class="opt">任意</span></label>
    <select id="pickup_user_id" name="pickup_user_id">
        <option value="">きめていない</option>
        @foreach ($parents as $parent)
            <option value="{{ $parent->id }}" {{ (string) old('pickup_user_id', $lesson->pickup_user_id ?? '') === (string) $parent->id ? 'selected' : '' }}>
                {{ $parent->name }}
            </option>
        @endforeach
    </select>
    <p class="prefill">家族として登録している人から選べます。招待コードで参加すると増えます。</p>
</div>

<div class="field">
    <label for="place">どこで<span class="opt">任意</span></label>
    <input type="text" id="place" name="place" value="{{ old('place', $lesson->place ?? '') }}" placeholder="例）市民プール">
</div>

<div class="field-row">
    <div class="field">
        <label for="starts_on">いつから<span class="req">必須</span></label>
        <input type="date" id="starts_on" name="starts_on" required
            value="{{ old('starts_on', isset($lesson) ? $lesson->starts_on->toDateString() : now()->toDateString()) }}">
        <p class="prefill">きょうの日付を入れてあります。通いはじめた日に変えてください。</p>
    </div>
    <div class="field">
        <label for="ends_on">いつまで<span class="opt">任意</span></label>
        <input type="date" id="ends_on" name="ends_on"
            value="{{ old('ends_on', isset($lesson) && $lesson->ends_on ? $lesson->ends_on->toDateString() : '') }}">
        <p class="prefill">やめるまでは空のままでかまいません。</p>
    </div>
</div>