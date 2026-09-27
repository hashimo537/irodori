<?php

namespace App\Http\Controllers;

use App\Models\Family;
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    /** 家族をつくる or 招待コードで参加する画面 */
    public function setup(Request $request)
    {
        // すでに家族に入っている人がここへ来たら、ホームへ返す
        if ($request->user()->family_id) {
            return redirect()->route('home');
        }

        return view('family.setup');
    }

    /** 新しく家族をつくる */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30'],
        ], [], ['name' => '家族の名前']);

        $family = Family::create([
            'name' => $data['name'],
            'invite_code' => Family::generateInviteCode(),
        ]);

        // $fillable に family_id を入れていないので forceFill で明示的に書き込む
        $request->user()->forceFill(['family_id' => $family->id])->save();

        return redirect()->route('home')
            ->with('status', '家族をつくりました。');
    }

    /** 招待コードで既存の家族に参加する */
    public function join(Request $request)
    {
        $data = $request->validate([
            'invite_code' => ['required', 'string', 'size:8'],
        ], [], ['invite_code' => '招待コード']);

        // 小文字や前後の空白でも通るように整えてから探す
        $code = strtoupper(trim($data['invite_code']));

        $family = Family::where('invite_code', $code)->first();

        if (!$family) {
            return back()
                ->withErrors(['invite_code' => '招待コードが見つかりませんでした。'])
                ->withInput();
        }

        $request->user()->forceFill(['family_id' => $family->id])->save();

        return redirect()->route('home')
            ->with('status', "「{$family->name}」に参加しました。");
    }

    /** 招待コードを見せる画面 */
    public function invite(Request $request)
    {
        return view('family.invite', [
            'family' => $request->user()->family,
        ]);
    }
}