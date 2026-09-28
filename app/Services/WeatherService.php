<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 天気予報。Open-Meteo（無料・APIキー不要）を使う。
 *
 * ・予報が出せるのは今日から16日先まで。それより先は空になる。
 * ・外部APIは落ちることがあるので、失敗しても画面は壊さない（空配列を返す）。
 * ・同じ地点を何度も問い合わせないよう、3時間キャッシュする。
 */
class WeatherService
{
    private const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';
    private const GEOCODING_URL = 'https://geocoding-api.open-meteo.com/v1/search';

    /** 予報が出せる最大日数（Open-Meteo の上限） */
    public const MAX_DAYS = 16;

    /**
     * 天気コード → 絵文字。
     * コードの意味は WMO の世界共通の番号。
     */
    private const ICONS = [
        0 => '☀️',
        1 => '🌤',
        2 => '⛅️',
        3 => '☁️',
        45 => '🌫',
        48 => '🌫',
        51 => '🌦',
        53 => '🌦',
        55 => '🌦',
        56 => '🌦',
        57 => '🌦',
        61 => '☂️',
        63 => '☂️',
        65 => '☔️',
        66 => '🌧',
        67 => '🌧',
        71 => '❄️',
        73 => '❄️',
        75 => '⛄️',
        77 => '❄️',
        80 => '🌦',
        81 => '🌧',
        82 => '🌧',
        85 => '🌨',
        86 => '🌨',
        95 => '⛈',
        96 => '⛈',
        99 => '⛈',
    ];

    private const LABELS = [
        0 => 'はれ',
        1 => 'はれ',
        2 => 'くもり時々はれ',
        3 => 'くもり',
        45 => 'きり',
        48 => 'きり',
        51 => '小雨',
        53 => '小雨',
        55 => '小雨',
        56 => 'こおる雨',
        57 => 'こおる雨',
        61 => '雨',
        63 => '雨',
        65 => '強い雨',
        66 => 'こおる雨',
        67 => 'こおる雨',
        71 => '雪',
        73 => '雪',
        75 => '大雪',
        77 => '雪',
        80 => 'にわか雨',
        81 => 'にわか雨',
        82 => '強いにわか雨',
        85 => 'にわか雪',
        86 => 'にわか雪',
        95 => 'かみなり',
        96 => 'かみなり',
        99 => 'かみなり',
    ];

    /**
     * 日付ごとの天気を返す。
     *
     * @return array<string, array{icon:string,label:string,max:?float,min:?float}>
     *         キーは 'Y-m-d'。取れなかったときは空配列。
     */
    public function daily(?float $lat, ?float $lon): array
    {
        if (is_null($lat) || is_null($lon)) {
            return [];
        }

        // 小数2桁に丸めてキャッシュキーにする（数百mの差で別扱いにしないため）
        $key = sprintf('weather:%.2f:%.2f', $lat, $lon);

        return Cache::remember($key, now()->addHours(3), function () use ($lat, $lon) {
            try {
                $response = Http::timeout(5)->get(self::FORECAST_URL, [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min',
                    'timezone' => 'Asia/Tokyo',
                    'forecast_days' => self::MAX_DAYS,
                ]);

                if (!$response->successful()) {
                    return [];
                }

                $daily = $response->json('daily');
                $out = [];

                foreach ($daily['time'] ?? [] as $i => $date) {
                    $code = $daily['weather_code'][$i] ?? null;

                    $out[$date] = [
                        'icon' => self::ICONS[$code] ?? '・',
                        'label' => self::LABELS[$code] ?? '',
                        'max' => $daily['temperature_2m_max'][$i] ?? null,
                        'min' => $daily['temperature_2m_min'][$i] ?? null,
                    ];
                }

                return $out;
            } catch (\Throwable $e) {
                // 天気が出ないだけで、カレンダーは使えるべき
                Log::warning('天気予報の取得に失敗しました: ' . $e->getMessage());

                return [];
            }
        });
    }

    /**
     * 地名から緯度経度を調べる。
     *
     * @return array<int, array{name:string,admin:string,latitude:float,longitude:float}>
     */
    public function search(string $name): array
    {
        try {
            $response = Http::timeout(5)->get(self::GEOCODING_URL, [
                'name' => $name,
                'count' => 5,
                'language' => 'ja',
                'format' => 'json',
            ]);

            if (!$response->successful()) {
                return [];
            }

            return collect($response->json('results') ?? [])
                ->map(fn($r) => [
                    'name' => $r['name'] ?? '',
                    'admin' => $r['admin1'] ?? '',
                    'latitude' => (float) $r['latitude'],
                    'longitude' => (float) $r['longitude'],
                ])
                ->all();
        } catch (\Throwable $e) {
            Log::warning('地名の検索に失敗しました: ' . $e->getMessage());

            return [];
        }
    }
}
