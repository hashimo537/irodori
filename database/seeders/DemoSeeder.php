<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 動作確認用のデモデータ。
 *   php artisan db:seed --class=DemoSeeder
 *
 * ログイン：  demo@example.com  /  password
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $family = Family::create([
            'name' => 'はしもと家',
            'invite_code' => Family::generateInviteCode(),
        ]);

        $user = User::updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'まゆ',
                'password' => Hash::make('password'),
                'family_id' => $family->id,
            ]
        );

        $haruto = $family->members()->create(['name' => 'はると', 'color' => '#D79372', 'sort_order' => 1]);
        $yui = $family->members()->create(['name' => 'ゆい', 'color' => '#8FB2C9', 'sort_order' => 2]);
        $aoi = $family->members()->create(['name' => 'あおい', 'color' => '#8FAF86', 'sort_order' => 3]);

        $from = Carbon::today()->subMonths(3)->toDateString();

        // 習い事（毎週くりかえし）
        $lessons = [
            [$haruto, 'そろばん', 'なかまち教室', 1, '16:00', '17:30'],
            [$yui, 'えいご', null, 1, '15:30', '16:20'],
            [$yui, 'スイミング', '市民プール', 2, '16:30', '18:00'],
            [$aoi, 'たいそう', null, 2, '15:30', '16:30'],
            [$haruto, 'えいご', null, 3, '17:00', '18:00'],
            [$haruto, 'ピアノ', 'みどり音楽', 4, '16:30', '18:00'],
            [$yui, 'リトミック', null, 4, '15:00', '16:00'],
            [$aoi, 'ダンス', '公民館', 5, '16:00', '17:00'],
            [$haruto, 'そろばん', 'なかまち教室', 5, '17:30', '19:00'],
            [$haruto, 'サッカー', '東小 校庭', 6, '09:00', '11:00'],
            [$yui, '絵画きょうしつ', null, 6, '10:00', '11:30'],
        ];

        foreach ($lessons as [$member, $title, $place, $dow, $start, $end]) {
            $family->lessons()->create([
                'member_id' => $member->id,
                // 夕方の習い事だけ、お迎え担当を入れておく
                'pickup_user_id' => $start >= '16:00' ? $user->id : null,
                'title' => $title,
                'place' => $place,
                'day_of_week' => $dow,
                'start_time' => $start,
                'end_time' => $end,
                'starts_on' => $from,
            ]);
        }

        // 単発の予定
        $saturday = Carbon::today()->startOfWeek(Carbon::MONDAY)->addDays(5);

        $family->events()->create([
            'member_id' => null,
            'title' => 'じいじの家',
            'date' => $saturday->toDateString(),
            'start_time' => '14:00',
            'end_time' => '16:30',
            'note' => "おみやげを持っていく。\n帰りにスーパーへ寄る。",
            'created_by' => $user->id,
        ]);

        $family->events()->create([
            'member_id' => $aoi->id,
            'title' => 'たいそう発表会',
            'place' => '総合体育館',
            'date' => $saturday->copy()->addDay()->toDateString(),
            'start_time' => null,          // 終日
            'note' => '8:30 集合。体操服とお茶を忘れずに。',
            'created_by' => $user->id,
        ]);

        // 記念日（毎年おなじ日）
        $family->anniversaries()->create([
            'member_id' => $yui->id,
            'title' => 'ゆいの誕生日',
            'month' => 4,
            'day' => 12,
            'start_year' => 2018,
        ]);
        $family->anniversaries()->create([
            'member_id' => $haruto->id,
            'title' => 'はるとの誕生日',
            'month' => 9,
            'day' => 20,
            'start_year' => 2016,
        ]);
        $family->anniversaries()->create([
            'member_id' => null,
            'title' => '結婚記念日',
            'month' => 11,
            'day' => 3,
            'start_year' => 2014,
        ]);

        // 提出物
        $family->tasks()->create([
            'member_id' => $haruto->id,
            'title' => '給食袋',
            'due_date' => Carbon::tomorrow()->toDateString(),
        ]);
        $family->tasks()->create([
            'member_id' => $yui->id,
            'title' => '遠足のしおり 提出',
            'due_date' => Carbon::today()->addDays(2)->toDateString(),
        ]);
        $family->tasks()->create([
            'member_id' => $aoi->id,
            'title' => '集金 500円',
            'due_date' => Carbon::today()->addDays(3)->toDateString(),
        ]);

        $this->command->info("デモデータを作りました。 招待コード: {$family->invite_code}");
        $this->command->info('ログイン: demo@example.com / password');
    }
}
