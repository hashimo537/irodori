<?php

namespace App\Http\Controllers;

use App\Models\Anniversary;
use App\Models\Event;
use App\Models\Lesson;
use App\Models\Member;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WeekController extends Controller
{
    /** タイムラインの表示範囲：6:00 〜 22:00、1分 = 1px */
    public const START_MIN = 6 * 60;
    public const END_MIN = 22 * 60;

    public function index(Request $request)
    {
        // ---- 1. 表示する週を決める ----------------------------------
        $base = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();
        $start = $base->copy()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->addDays(6);

        $days = collect(range(0, 6))->map(fn($i) => $start->copy()->addDays($i));

        // ---- 2. データをまとめて取る（N+1を避ける） -------------------
        $members = Member::orderBy('sort_order')->orderBy('id')->get();
        $lessons = Lesson::with('member', 'pickup')->get();

        $events = Event::with('member', 'pickup')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $anniversaries = Anniversary::with('member')->get();

        // ---- 3. 1日ぶんの「帯」を組み立てる ---------------------------
        $slotsByDate = [];
        $allDayByDate = [];   // 終日の予定＋記念日

        foreach ($days as $day) {
            $key = $day->toDateString();
            $slots = [];
            $allDay = [];

            // 習い事（毎週くりかえし）をこの日に展開する
            foreach ($lessons as $lesson) {
                if (!$lesson->activeOn($day)) {
                    continue;
                }
                $slots[] = $this->makeSlot(
                    type: 'lesson',
                    id: $lesson->id,
                    title: $lesson->title,
                    place: $lesson->place,
                    member: $lesson->member,
                    start: $lesson->start_time->format('H:i'),
                    end: $lesson->end_time->format('H:i'),
                    pickup: $lesson->pickup?->name,
                );
            }

            // 単発の予定
            foreach ($events as $event) {
                if (!$event->date->isSameDay($day)) {
                    continue;
                }

                if ($event->is_all_day) {
                    $allDay[] = [
                        'kind' => 'event',
                        'id' => $event->id,
                        'title' => $event->title,
                        'place' => $event->place,
                        'name' => $event->member->name ?? '家族ぜんいん',
                        'color' => $event->member->color ?? '#A79BC0',
                        'light' => $event->member->light_color ?? '#EAE6F1',
                        'note' => filled($event->note),
                        'pickup' => $event->pickup?->name,
                    ];
                    continue;
                }

                $slots[] = $this->makeSlot(
                    type: 'event',
                    id: $event->id,
                    title: $event->title,
                    place: $event->place,
                    member: $event->member,
                    start: $event->start_time->format('H:i'),
                    end: optional($event->end_time)->format('H:i')
                    ?? $event->start_time->copy()->addHour()->format('H:i'),
                    note: filled($event->note),
                    pickup: $event->pickup?->name,
                );
            }

            // 記念日（毎年おなじ月日）
            foreach ($anniversaries as $anniv) {
                if (!$anniv->fallsOn($day)) {
                    continue;
                }
                $age = $anniv->ageOn($day);
                $allDay[] = [
                    'kind' => 'anniv',
                    'id' => $anniv->id,
                    'title' => $anniv->title . ($age !== null ? "（{$age}さい）" : ''),
                    'place' => null,
                    'name' => $anniv->member->name ?? '家族',
                    'color' => $anniv->member->color ?? '#C9A227',
                    'light' => $anniv->member->light_color ?? '#FBF2D8',
                    'note' => false,
                    'pickup' => null,
                ];
            }

            // 時間が重なっている予定を、横に並べる
            $slotsByDate[$key] = $this->layout($slots);
            $allDayByDate[$key] = $allDay;
        }

        // ---- 4. 今週の提出物（画面いちばん上のバー） -----------------
        $todos = Task::with('member')
            ->where('is_done', false)
            ->where(function ($q) use ($end) {
                $q->whereNull('due_date')->orWhere('due_date', '<=', $end->toDateString());
            })
            ->orderByRaw('due_date IS NULL, due_date')
            ->limit(5)
            ->get();

        return view('week.index', [
            'start' => $start,
            'days' => $days,
            'members' => $members,
            'slotsByDate' => $slotsByDate,
            'allDayByDate' => $allDayByDate,
            'todos' => $todos,
            'startMin' => self::START_MIN,
            'endMin' => self::END_MIN,
        ]);
    }

    /**
     * 重なっている帯を横に並べるための計算。
     *
     * ① 時間がつながっている塊（クラスタ）に分ける
     * ② 塊の中で、予定を左から順に「空いている列」へ入れる
     * ③ 塊の列数が決まるので、1本あたりの幅が決まる
     *
     * 結果として col（何列目か）と cols（その塊の列数）が付く。
     */
    private function layout(array $slots): array
    {
        usort($slots, fn($a, $b) =>
            [$a['start_min'], $a['end_min']] <=> [$b['start_min'], $b['end_min']]);

        // ① 塊に分ける
        $clusters = [];
        $current = [];
        $clusterEnd = 0;

        foreach ($slots as $slot) {
            // いま作っている塊のどれとも重ならない → 塊を切る
            if ($current && $slot['start_min'] >= $clusterEnd) {
                $clusters[] = $current;
                $current = [];
                $clusterEnd = 0;
            }
            $current[] = $slot;
            $clusterEnd = max($clusterEnd, $slot['end_min']);
        }
        if ($current) {
            $clusters[] = $current;
        }

        // ② 塊ごとに列を割りあてる
        $out = [];
        foreach ($clusters as $cluster) {
            $columnEnds = [];   // 各列の「最後に入れた予定の終わり時刻」
            $assigned = [];

            foreach ($cluster as $i => $slot) {
                $placed = null;
                foreach ($columnEnds as $col => $endMin) {
                    if ($slot['start_min'] >= $endMin) {   // その列は空いている
                        $placed = $col;
                        break;
                    }
                }
                if ($placed === null) {
                    $placed = count($columnEnds);          // 新しい列を作る
                }
                $columnEnds[$placed] = $slot['end_min'];
                $assigned[$i] = $placed;
            }

            // ③ 幅を決める
            $total = count($columnEnds);
            foreach ($cluster as $i => $slot) {
                $slot['col'] = $assigned[$i];
                $slot['cols'] = $total;
                $out[] = $slot;
            }
        }

        return $out;
    }

    /**
     * 帯1本ぶんのデータ。
     * start_min / end_min がそのまま CSS の top / height (px) になる。
     */
    private function makeSlot(
        string $type,
        int $id,
        string $title,
        ?string $place,
        ?Member $member,
        string $start,
        string $end,
        bool $note = false,
        ?string $pickup = null
    ): array {
        return [
            'type' => $type,
            'id' => $id,
            'title' => $title,
            'place' => $place,
            'note' => $note,
            'pickup' => $pickup,
            'name' => $member->name ?? '家族ぜんいん',
            'color' => $member->color ?? '#A79BC0',
            'light' => $member->light_color ?? '#EAE6F1',
            'start' => $start,
            'end' => $end,
            'start_min' => $this->toMin($start),
            'end_min' => $this->toMin($end),
        ];
    }

    private function toMin(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }
}
