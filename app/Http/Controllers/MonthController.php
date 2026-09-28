<?php

namespace App\Http\Controllers;

use App\Models\Anniversary;
use App\Models\Event;
use App\Models\Lesson;
use App\Models\Member;
use App\Models\Task;
use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * 月カレンダー表示。
 *
 * 表示の切り替えを2つ持つ（どちらも URL のパラメータで持ち回る）。
 *   ?names=0   行事名をかくして、色まるだけにする
 *   ?member=3  その子の予定だけにしぼる
 */
class MonthController extends Controller
{
    public function index(Request $request, WeatherService $weather)
    {
        // ---- 1. 表示する月と、表示の切り替え ------------------------
        $base = $request->filled('month')
            ? Carbon::parse($request->month.'-01')
            : Carbon::today()->startOfMonth();

        $month = $base->copy()->startOfMonth();

        $showNames = $request->input('names', '1') !== '0';   // 行事名を出すか
        $onlyMember = $request->filled('member') ? (int) $request->member : null;

        // カレンダーのマス目は、月をまたいで月曜はじまり〜日曜おわりで埋める
        $gridStart = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $days = collect();
        for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay()) {
            $days->push($d->copy());
        }

        // ---- 2. データをまとめて取る --------------------------------
        $members = Member::orderBy('sort_order')->orderBy('id')->get();

        // しぼり込みは、DBの検索条件として渡す（画面側で弾くより速い）
        $lessons = Lesson::with('member')
            ->when($onlyMember, fn ($q) => $q->where('member_id', $onlyMember))
            ->get();

        $events = Event::with('member')
            ->whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->when($onlyMember, fn ($q) => $q->where('member_id', $onlyMember))
            ->get();

        $anniversaries = Anniversary::with('member')
            ->when($onlyMember, fn ($q) => $q->where('member_id', $onlyMember))
            ->get();

        $dues = Task::with('member')
            ->where('is_done', false)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->when($onlyMember, fn ($q) => $q->where('member_id', $onlyMember))
            ->get();

        // ---- 3. 天気予報（今日から16日先まで） -----------------------
        $family = $request->user()->family;
        $forecast = $weather->daily($family->latitude, $family->longitude);

        // ---- 4. 1日ぶんの中身を組み立てる ---------------------------
        $cells = [];

        foreach ($days as $day) {
            $key = $day->toDateString();
            $colors = [];      // その日に予定がある子の色（重複なし）
            $chips = [];      // 終日の予定・記念日・しめきり

            foreach ($lessons as $lesson) {
                if ($lesson->activeOn($day)) {
                    $colors[] = $lesson->member->color;
                }
            }

            foreach ($events as $event) {
                if (! $event->date->isSameDay($day)) {
                    continue;
                }
                $colors[] = $event->member->color ?? '#A79BC0';

                if ($event->is_all_day) {
                    $chips[] = [
                        'kind' => 'event',
                        'title' => $event->title,
                        'color' => $event->member->color ?? '#A79BC0',
                        'light' => $event->member->light_color ?? '#EAE6F1',
                    ];
                }
            }

            foreach ($anniversaries as $anniv) {
                if (! $anniv->fallsOn($day)) {
                    continue;
                }
                $age = $anniv->ageOn($day);
                $colors[] = $anniv->member->color ?? '#C9A227';
                $chips[] = [
                    'kind' => 'anniv',
                    'title' => $anniv->title.($age !== null ? "（{$age}）" : ''),
                    'color' => $anniv->member->color ?? '#C9A227',
                    'light' => '#FBF2D8',
                ];
            }

            foreach ($dues as $due) {
                if ($due->due_date->isSameDay($day)) {
                    $chips[] = [
                        'kind' => 'due',
                        'title' => $due->title,
                        'color' => $due->member->color ?? '#A79BC0',
                        'light' => '#FFF6EC',
                    ];
                }
            }

            $cells[$key] = [
                'colors' => array_values(array_unique($colors)),
                'chips' => $chips,
                'weather' => $forecast[$key] ?? null,
            ];
        }

        return view('month.index', [
            'month' => $month,
            'days' => $days,
            'cells' => $cells,
            'members' => $members,
            'showNames' => $showNames,
            'onlyMember' => $onlyMember,
            'hasWeather' => ! empty($forecast),
            'family' => $family,
            'prev' => $month->copy()->subMonth()->format('Y-m'),
            'next' => $month->copy()->addMonth()->format('Y-m'),
        ]);
    }
}
