<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * APP 广告收益日统计
 *
 * @property int $id
 * @property Carbon $date
 * @property int $app_id
 * @property string $platform
 * @property string $platform_name
 * @property string $slot_id
 * @property string $slot_name
 * @property string $ad_type
 * @property int $request_count
 * @property int $success_count
 * @property int $show_count
 * @property int $click_count
 * @property float $ad_revenue
 * @property string $data_status
 * @property string $collect_message
 * @property Carbon|null $collected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AppAdRevenueDaily extends BaseModel
{
    // 广告收益日报采集状态，用于报表筛选、异常提示和重新采集流程。
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_COLLECTING = 'collecting';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';

    protected $table = 'app_ad_revenue_daily';

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $casts = [
        'date' => 'date',
        'app_id' => 'int',
        'request_count' => 'int',
        'success_count' => 'int',
        'show_count' => 'int',
        'click_count' => 'int',
        'ad_revenue' => 'float',
        'collected_at' => 'datetime',
    ];

    protected $fillable = [
        'date',
        'app_id',
        'platform',
        'platform_name',
        'slot_id',
        'slot_name',
        'ad_type',
        'request_count',
        'success_count',
        'show_count',
        'click_count',
        'ad_revenue',
        'data_status',
        'collect_message',
        'collected_at',
    ];

    /**
     * 广告平台枚举。报表筛选、平台汇总和展示名称都复用这份映射。
     */
    public static function platformMap(): array
    {
        return [
            'topon' => 'TopOn',
            'pangolin' => '穿山甲',
            'youlianghui' => '优量汇',
            'tencent' => '优量汇',
            'kuaishou' => '快手广告',
            'google' => 'Google广告',
        ];
    }

    /**
     * 采集状态展示文案。
     */
    public static function statusMap(): array
    {
        return [
            self::STATUS_COMPLETED => '成功',
            self::STATUS_COLLECTING => '采集中',
            self::STATUS_PARTIAL => '部分缺失',
            self::STATUS_FAILED => '失败',
        ];
    }
}
