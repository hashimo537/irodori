<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index()
    {
        return view('tasks.index', [
            'todo' => Task::with('member')->where('is_done', false)
                ->orderByRaw('due_date IS NULL, due_date')->get(),
            'done' => Task::with('member')->where('is_done', true)
                ->latest('updated_at')->limit(20)->get(),
            'members' => Member::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Task::create($request->validate([
            'member_id' => ['nullable', 'exists:members,id'],
            'title' => ['required', 'string', 'max:40'],
            'due_date' => ['nullable', 'date'],
        ], [], [
            'title' => 'やること',
            'due_date' => '期限',
        ]));

        return back()->with('status', '追加しました。');
    }

    /** チェックのオン・オフ */
    public function update(Request $request, Task $task)
    {
        $task->update(['is_done' => $request->boolean('is_done')]);

        return back();
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return back()->with('status', '削除しました。');
    }
}