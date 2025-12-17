<?php

namespace App\Support\Services;

use App\Models\Article;
use App\Models\Merchant;
use App\Models\SystemApp;
use App\Models\MemberOrder;
use App\Models\AppAgreement;
use App\Models\MemberProduct;
use App\Models\AppAdvertisement;
use App\Models\SubscriptionOrder;

class FormOptions
{
    /**
     * 格式化select下拉表单数据
     *
     * @param array|\Illuminate\Support\Collection $idNameArr key=>name 数组
     * @param array $firstOption
     *
     * @return array
     */
    public static function toFormOptions(array|\Illuminate\Support\Collection $idNameArr, array $firstOption = []): array
    {
        $result = [];

        if (!empty($firstOption)) {
            $result[] = $firstOption;
        }

        foreach ($idNameArr as $id => $name) {
            $result[] = [
                'label' => $name,
                'value' => $id,
            ];
        }

        return $result;
    }

    public static function getAllByType(string $type, array $firstOption = []): array
    {
        return match ($type) {
            'system_apps' => FormOptions::systemApps($firstOption),
            'platforms' => self::platforms($firstOption),
            'market_channel' => self::marketChannel($firstOption),
            'merchants' => self::merchants($firstOption),
            'member_validity_type' => self::memberValidityType($firstOption),
            'agreement_type' => self::agreementType($firstOption),
            'ad_channels' => self::adChannels($firstOption),
            'ad_types' => self::adTypes($firstOption),
            'article_source' => self::articleSource($firstOption),
            'pay_type' => self::payType($firstOption),
            'member_type' => self::memberType($firstOption),
            'member_status' => self::memberStatus($firstOption),
            'subscribe_status' => self::subscribeStatus($firstOption),
            'is_enable' => self::isEnable($firstOption),
            'languages' => self::languages($firstOption),
            default => [],
        };
    }

    public static function systemApps($firstOption = []): array
    {
        $arr = SystemApp::query()->where('is_del', 0)->pluck('name', 'id');
        foreach ($arr as $id => &$name) {
            $name = $name . '(' . $id . ')';
            $arr[$id] = $name;
        }

        return self::toFormOptions($arr, $firstOption);
    }

    public static function merchants($firstOption = []): array
    {
        $arr = Merchant::query()->pluck('name', 'id');

        return self::toFormOptions($arr, $firstOption);
    }

    public static function platforms($firstOption = []): array
    {
        $arr = SystemApp::platformsMap();

        return self::toFormOptions($arr, $firstOption);
    }

    public static function marketChannel($firstOption = []): array
    {
        $arr = SystemApp::marketChannelsMap();

        return self::toFormOptions($arr, $firstOption);
    }

    public static function agreementType($firstOption = []): array
    {
        $arr = AppAgreement::typesMap();

        return self::toFormOptions($arr, $firstOption);
    }

    public static function memberValidityType($firstOption = []): array
    {
        $arr = MemberProduct::validityTypesMap();

        return self::toFormOptions($arr, $firstOption);
    }

    public static function adChannels($firstOption = []): array
    {
        $arr = AppAdvertisement::adChannelsMap();

        return self::toFormOptions($arr, $firstOption);
    }

    public static function adTypes($firstOption = []): array
    {
        $arr = AppAdvertisement::typesMap();

        return self::toFormOptions($arr, $firstOption);
    }

    public static function articleSource($firstOption = []): array
    {
        $arr = Article::SourceMap();

        return self::toFormOptions($arr, $firstOption);
    }

    public static function memberType($firstOption = []): array
    {
        $arr = MemberOrder::memberTypeMap();

        return self::toFormOptions($arr, $firstOption);
    }

    public static function memberStatus($firstOption = []): array
    {
        $arr = MemberOrder::memberStatusMap();

        return self::toFormOptions($arr, $firstOption);
    }

    public static function languages($firstOption = []): array
    {
        return self::toFormOptions(MemberProduct::$languages, $firstOption);
    }

    public static function payType($firstOption = []): array
    {
        $arr = MemberOrder::payTypeMap();

        return self::toFormOptions($arr, $firstOption);
    }

    public static function subscribeStatus($firstOption = []): array
    {
        $arr = SubscriptionOrder::SubscribeStatusMap();

        return self::toFormOptions($arr, $firstOption);
    }

    public static function isEnable($firstOption = []): array
    {
        return self::toFormOptions([1 => '是', 0 => '否'], $firstOption);
    }
}
