<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LessonController extends Controller
{
    public function index()
    {
        return view('lessons.index', [
            'lessons' => Lesson::with('member')
                ->orderBy('day_of_week')->orderBy('start_time')->get(),
        ]);
    }

    public function create()
    {
        return view('lessons.create', [
            'members' => Member::orderBy('sort_order')->get(),
            'parents' => User::where('family_id', auth()->user()->family_id)->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Lesson::create($this->validated($request));

        return redirect()->route('lessons.index')->with('status', '習い事を登録しました。');
    }

    public function edit(Lesson $lesson)
    {
        return view('lessons.edit', [
            'lesson'  => $lesson,
            'members' => Member::orderBy('sort_order')->get(),
            'parents' => User::where('family_id', auth()->user()->family_id)->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, Lesson $lesson)
    {
        $lesson->update($this->validated($request));

        return redirect()->route('lessons.index')->with('status', '変更を保存しました。');
    }

    public function destroy(Lesson $lesson)
    {
        $lesson->delete();

        return redirect()->route('lessons.index')->with('status', '習い事を削除しました。');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'member_id'   => ['required', 'exists:members,id'],
            // お迎え担当は「同じ家族のユーザー」だけ選べるようにする
            'pickup_user_id' => ['nullable', Rule::in(
                User::where('family_id', auth()->user()->family_id)->pluck('id')->all()
            )],
            'title'       => ['required', 'string', 'max:40'],
            'place'       => ['nullable', 'string', 'max:40'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time'  => ['required', 'date_format:H:i'],
            'end_time'    => ['required', 'date_format:H:i', 'after:start_time'],
            'starts_on'   => ['required', 'date'],
            'ends_on'     => ['nullable', 'date', 'after:starts_on'],
        ], [], [
            'member_id'      => 'だれの',
            'title'          => '習い事の名前',
            'day_of_week'    => '曜日',
            'start_time'     => 'はじまる時間',
            'end_time'       => 'おわる時間',
            'starts_on'      => '通いはじめた日',
            'ends_on'        => 'おわる日',
            'pickup_user_id' => 'お迎え担当',
        ]);
    }
}
