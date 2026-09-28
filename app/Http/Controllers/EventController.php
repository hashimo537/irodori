<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function create(Request $request)
    {
        return view('events.create', [
            'members' => Member::orderBy('sort_order')->get(),
            'parents' => User::where('family_id', auth()->user()->family_id)->orderBy('id')->get(),
            // カレンダーで開いていた日を初期値にする
            'date' => $request->input('date', now()->toDateString()),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        Event::create($data);

        return redirect()->route('home', ['date' => $data['date']])
            ->with('status', '予定を追加しました。');
    }

    public function edit(Event $event)
    {
        return view('events.edit', [
            'event' => $event,
            'members' => Member::orderBy('sort_order')->get(),
            'parents' => User::where('family_id', auth()->user()->family_id)->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, Event $event)
    {
        $event->update($this->validated($request));

        return redirect()->route('home', ['date' => $event->date->toDateString()])
            ->with('status', '予定を変更しました。');
    }

    public function destroy(Event $event)
    {
        $date = $event->date->toDateString();
        $event->delete();

        return redirect()->route('home', ['date' => $date])
            ->with('status', '予定を削除しました。');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'member_id' => ['nullable', 'exists:members,id'],
            'pickup_user_id' => [
                'nullable',
                Rule::in(
                    User::where('family_id', auth()->user()->family_id)->pluck('id')->all()
                )
            ],
            'title' => ['required', 'string', 'max:40'],
            'place' => ['nullable', 'string', 'max:40'],
            'date' => ['required', 'date'],
            'all_day' => ['nullable', 'boolean'],
            // 終日でなければ時刻は必須
            'start_time' => ['nullable', 'required_without:all_day', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], [
            'title' => 'タイトル',
            'date' => '日付',
            'start_time' => 'はじまる時間',
            'end_time' => 'おわる時間',
        ]);

        // 終日にチェックが入っていたら時刻を消す
        if ($request->boolean('all_day')) {
            $data['start_time'] = null;
            $data['end_time'] = null;
        }
        unset($data['all_day']);   // DBにない列なので取り除く

        return $data;
    }
}
