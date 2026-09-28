<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
   public function index()
    {
        return view('members.index', [
            'members' => Member::orderBy('sort_order')->orderBy('id')->get(),
            'palette' => Member::PALETTE,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = (Member::max('sort_order') ?? 0) + 1;

        Member::create($data);

        return back()->with('status', "{$data['name']}さんを登録しました。");
    }

    public function edit(Member $member)
    {
        return view('members.edit', [
            'member'  => $member,
            'palette' => Member::PALETTE,
        ]);
    }

    public function update(Request $request, Member $member)
    {
        $member->update($this->validated($request));

        return redirect()->route('members.index')->with('status', '変更を保存しました。');
    }

    public function destroy(Member $member)
    {
        $name = $member->name;
        $member->delete();

        return redirect()->route('members.index')
            ->with('status', "{$name}さんと、その予定を削除しました。");
    }

    /** store と update で同じルールを使う */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name'  => ['required', 'string', 'max:20'],
            // 用意した6色のいずれか。自由入力にすると変な色が入る
            'color' => ['required', Rule::in(array_keys(Member::PALETTE))],
        ], [], [
            'name'  => 'なまえ',
            'color' => '色',
        ]);
    }
}
