<?php

namespace App\Http\Controllers;

use App\Models\Anniversary;
use App\Models\Member;
use Illuminate\Http\Request;

class AnniversaryController extends Controller
{
    public function index()
    {
        return view('anniversaries.index', [
            'anniversaries' => Anniversary::with('member')
                ->orderBy('month')->orderBy('day')->get(),
            'members' => Member::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Anniversary::create($this->validated($request));

        return back()->with('status', '記念日を登録しました。');
    }

    public function edit(Anniversary $anniversary)
    {
        return view('anniversaries.edit', [
            'anniversary' => $anniversary,
            'members' => Member::orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Anniversary $anniversary)
    {
        $anniversary->update($this->validated($request));

        return redirect()->route('anniversaries.index')->with('status', '変更を保存しました。');
    }

    public function destroy(Anniversary $anniversary)
    {
        $anniversary->delete();

        return redirect()->route('anniversaries.index')->with('status', '削除しました。');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'member_id' => ['nullable', 'exists:members,id'],
            'title' => ['required', 'string', 'max:40'],
            'month' => ['required', 'integer', 'between:1,12'],
            'day' => ['required', 'integer', 'between:1,31'],
            'start_year' => ['nullable', 'integer', 'between:1900,2100'],
        ], [], [
            'title' => '記念日の名前',
            'month' => '月',
            'day' => '日',
            'start_year' => '始まった年',
        ]);
    }
}
