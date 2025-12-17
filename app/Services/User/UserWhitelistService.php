<?php

namespace App\Services\User;

use App\Models\User;
use App\Models\SystemApp;
use App\Services\Service;
use App\Models\UserWhitelist;
use App\Models\UserWhitelistLog;
use App\Exceptions\AdminException;
use App\Dao\User\UserWhitelistDao;
use App\Support\Traits\ExcelTrait;
use App\Support\Services\FormOptions;
use App\Support\Services\FormBuilder as Form;

/**
 * Class UserWhitelistService
 */
class UserWhitelistService extends Service
{
    use ExcelTrait;

    /**
     * UserWhitelistService constructor.
     */
    public function __construct(UserWhitelistDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 列表数据处理
     */
    public function tidyListData($list)
    {
        $apps = SystemApp::idToNameMap();
        $platforms = SystemApp::platformsMap();
        $marketChannels = SystemApp::marketChannelsMap();
        $sourceTypeMap = UserWhitelist::sourceTypeMap();

        if (!empty($list)) {
            $list = $list->toArray();
            $way = $list[0]['way'];
            $content = array_column($list, 'content');
            $loginLogsCount = UserWhitelistLog::query()->selectRaw("source, count(*) as access_count, count(DISTINCT device) as device_count, count(DISTINCT ip) as ip_count")
                ->whereIn('source', $content)
                ->where('source_type', $way)
                ->groupBy('source')
                ->get()
                ->keyBy('source');

            foreach ($list as &$item) {
                $item['source_name'] = $sourceTypeMap[$item['source']] ?? '';
                $item['type_name'] = self::conversionTypeName($item['type']);
                $item['source_ip'] = empty($item['source_ip']) ? '--' : $item['source_ip'];

                $lastLoginInfo = [];
                switch ($item['way']) {
                    case UserWhitelist::WAY_DEVICE:
                        $lastLoginInfo = UserWhitelistLog::query()->where('source', $item['content'])
                            ->where('source_type', UserWhitelist::WAY_DEVICE)
                            ->orderBy('id', 'desc')
                            ->first();
                        break;
                    case UserWhitelist::WAY_IP:
                        $lastLoginInfo = UserWhitelistLog::query()->where('source', $item['content'])
                            ->where('source_type', UserWhitelist::WAY_IP)
                            ->orderBy('id', 'desc')
                            ->first();
                        break;
                    case UserWhitelist::WAY_REGION:
                        $lastLoginInfo = UserWhitelistLog::query()->where('source', $item['content'])
                            ->where('source_type', UserWhitelist::WAY_REGION)
                            ->orderBy('id', 'desc')
                            ->first();
                        break;
                }

                $item['last_login_ip'] = $lastLoginInfo['ip'] ?? '';
                $item['last_login_device'] = $lastLoginInfo['device'] ?? ($item['source_device'] ?? '');
                $item['last_login_time'] = empty($lastLoginInfo['created_at']) ? $item['created_at'] : $lastLoginInfo['created_at']->format('Y-m-d H:i:s');
                $item['last_login_region'] = $lastLoginInfo['region'] ?? '';
                $item['last_login_app_id'] = $lastLoginInfo['app_id'] ?? 0;
                $item['last_login_platform'] = $lastLoginInfo['platform'] ?? '--';
                $item['last_login_version'] = $lastLoginInfo['version'] ?? '--';
                $item['last_login_market_channel'] = empty($lastLoginInfo['market_channel']) ? '--' : ($marketChannels[$lastLoginInfo['market_channel']] ?? $lastLoginInfo['market_channel']);
                $item['access_count'] = $loginLogsCount[$item['content']]['access_count'] ?? 0;
                $item['device_count'] = $loginLogsCount[$item['content']]['device_count'] ?? 0;
                $item['ip_count'] = $loginLogsCount[$item['content']]['ip_count'] ?? 0;
                $item['last_login_app_name'] = $apps[$item['last_login_app_id']] ?? '';

                $item['app_id'] = empty($item['app_id']) ? $item['last_login_app_id'] : $item['app_id'];
                $item['app_name'] = $apps[$item['app_id']] ?? $item['last_login_app_name'];
                $item['platform_name'] = $platforms[$item['platform']] ?? $item['last_login_platform'];
                $item['market_channel'] = $marketChannels[$item['market_channel']] ?? $item['last_login_market_channel'];
                $item['version'] = !empty($item['version']) ? $item['version'] : $item['last_login_version'];
            }
        }

        return $list;
    }

    /**
     * 新增表单获取
     *
     * @return array
     * @throws \App\Exceptions\AdminException
     */
    public function createForm(string $way): array
    {
        return create_form('添加', $this->createUpdateForm($way), url('/admin/user/user_whitelist'));
    }

    /**
     * 编辑表单获取
     *
     * @param int $id
     *
     * @return array
     * @throws \App\Exceptions\AdminException
     */
    public function updateForm(int $id): array
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(400594);
        }

        return create_form('修改', $this->createUpdateForm($info['way'], $info->toArray()), url('/admin/user/user_whitelist/' . $id), 'PUT');
    }

    /**
     * @throws \App\Exceptions\AdminException
     */
    public function importForm(string $way): array
    {
        switch ($way) {
            case UserWhitelist::WAY_DEVICE:
                $f[] = Form::hidden('way', UserWhitelist::WAY_DEVICE);

                break;
            case UserWhitelist::WAY_IP:
                $f[] = Form::hidden('way', UserWhitelist::WAY_IP);

                break;
        }

        $f[] = Form::uploadFile('file', '导入文件', config('admin.url') . '/admin/file/upload')
            ->headers(['Authori-Zation' => request()->header(config('cookie.token_name', 'Authori-zation'))])->data(['type' => 'excel']);
        $f[] = Form::checkbox('type', '白名单类型', decomposeNumber($info['type'] ?? 0))->options($this->toFormSelect(trans('user_whitelist.type_map')))->required('请选择白名单类型');
        $f[] = Form::textarea('remark', '备注信息', $info['remark'] ?? '');

        return create_form('白名单导入', $f, url('/admin/user/user_whitelist/import'));
    }

    /**
     * 白名单导入
     *
     * @throws \App\Exceptions\AdminException
     */
    public function import($data)
    {
        if (empty($data['file'])) {
            throw new AdminException("请选择要上传的文件");
        }

        $filePath = storage_path('app/' . ($data['file']));
        logger()->info('导入文件：' . $filePath);
        if (!file_exists($filePath)) {
            throw new AdminException("文件不存在");
        }

        $importData = $this->readerFromExcel($filePath);
        // logger()->info('导入数据：', $importData);
        if (empty($importData)) {
            throw new AdminException("导入文件内容为空");
        }

        $contentArr = [];
        $i = $data['way'] == 'device' ? 1 : 6;
        foreach ($importData as $num => $item) {
            if ($num == 0) {
                continue;
            }

            if (!empty($item[$i]) && strlen($item[$i]) > 6) {
                $contentArr[] = trim($item[$i]);
            }
        }

        if (empty($contentArr)) {
            throw new AdminException("没有有效的导入内容");
        }

        $existed = UserWhitelist::query()->whereIn('content', $contentArr)->pluck('content')->toArray();

        $newData = [];
        $createTime = now()->toDateTimeString();
        foreach ($contentArr as $content) {
            if (!in_array($content, $existed)) {
                $item = [
                    'way' => $data['way'],
                    'content' => $content,
                    'source' => 1,// 手动添加
                    'remark' => $data['remark'],
                    'type' => $data['type'],
                    'created_at' => $createTime,
                    'updated_at' => $createTime,
                ];

                $newData[] = $item;
            }
        }

        if (!empty($newData)) {
            UserWhitelist::query()->insert($newData);
        }

        return $newData;
    }

    /**
     * 生成form表单
     */
    public function createUpdateForm($way, array $info = []): array
    {
        switch ($way) {
            case UserWhitelist::WAY_DEVICE:
                $f[] = Form::hidden('way', UserWhitelist::WAY_DEVICE);
                $f[] = Form::input("content", "屏蔽设备", $info['content'] ?? '')->required();

                break;
            case UserWhitelist::WAY_IP:
                $f[] = Form::hidden('way', UserWhitelist::WAY_IP);
                $f[] = Form::input("content", "屏蔽IP", $info['content'] ?? '')->required();

                break;
            case UserWhitelist::WAY_REGION:
                $regions = User::query()->selectRaw("DISTINCT region")->where('region', '!=', '')->pluck('region', 'region')->toArray();

                $f[] = Form::hidden('way', 'region');
                $f[] = Form::select('app_id', '应用', $info['app_id'] ?? '')->options(FormOptions::systemApps(['label' => '全部', 'value' => 0]))->filterable(true);
                $f[] = Form::select('platform', '系统平台', $info['platform'] ?? '')->options(FormOptions::platforms(['label' => '全部', 'value' => 'all']));
                $f[] = Form::select('market_channel', '应用市场', $info['market_channel'] ?? '')->options(FormOptions::marketChannel(['label' => '全部', 'value' => 'all']));
                $f[] = Form::select('content', '屏蔽区域', $info['content'] ?? '')->options($this->toFormSelect($regions))->filterable(true)->required();
                break;
        }

        $f[] = Form::checkbox('type', '白名单类型', decomposeNumber($info['type'] ?? 0))->options($this->toFormSelect(trans('user_whitelist.type_map')))->required('请选择白名单类型');
        $f[] = Form::textarea('remark', '备注信息', $info['remark'] ?? '');

        return $f;
    }

    /**
     * 用户form表单
     */
    public function userForm(int $userId): array
    {
        $userInfo = User::query()->find($userId);

        $f[] = Form::hidden('app_id', $userInfo['app_id']);
        $f[] = Form::hidden('market_channel', $userInfo['market_channel']);
        $f[] = Form::hidden('device', $userInfo['device_sn']);
        $f[] = Form::hidden('reg_ip', $userInfo['reg_ip']);
        $f[] = Form::hidden('last_ip', $userInfo['last_ip']);
        $f[] = Form::hidden('version', $userInfo['app_version']);
        $f[] = Form::checkbox('type', '白名单类型', decomposeNumber($info['type'] ?? 0))->options($this->toFormSelect(trans('user_whitelist.type_map')))->required('请选择白名单类型');
        $f[] = Form::textarea('remark', '备注信息', $info['remark'] ?? '');

        return create_form('添加白名单', $f, url('/admin/user/user_whitelist/add_user'));
    }

    public static function createByDevice($device, $type, $remark, $source = 1, $appId = 0, $marketChannel = '', $sourceIp = '', $version = ''): bool
    {
        if (empty($device)) {
            return false;
        }

        if (UserWhitelist::query()->where('content', $device)->where('way', UserWhitelist::WAY_DEVICE)->exists()) {
            return false;
        }

        try {
            if (is_array($type)) {
                $type = convertToPermissionValue($type);
            }
            UserWhitelist::query()->create([
                'app_id' => $appId,
                'market_channel' => $marketChannel,
                'remark' => $remark,
                'content' => $device,
                'source' => $source,
                'version' => $version,
                'source_ip' => $sourceIp,
                'source_region' => ip2region($sourceIp),
                'type' => $type,
                'way' => UserWhitelist::WAY_DEVICE,
            ]);
            self::cacheForDevice();
        } catch (\Exception $exception) {
            logger()->error($exception->getMessage());

            return false;
        }

        return true;
    }

    public static function createByIp($ip, $type, $remark, $source = 1, $appId = 0, $marketChannel = '', $version = '', $device = ''): bool
    {
        if (empty($ip)) {
            return false;
        }

        if (UserWhitelist::query()->where('content', $ip)->where('way', UserWhitelist::WAY_IP)->exists()) {
            return false;
        }

        try {
            if (is_array($type)) {
                $type = convertToPermissionValue($type);
            }
            UserWhitelist::query()->create([
                'app_id' => $appId,
                'market_channel' => $marketChannel,
                'remark' => $remark,
                'source' => $source,
                'version' => $version,
                'content' => $ip,
                'source_region' => ip2region($ip),
                'source_device' => $device,
                'type' => $type,
                'way' => UserWhitelist::WAY_IP,
            ]);
            self::cacheForIp();
        } catch (\Exception $exception) {
            logger()->error($exception->getMessage());

            return false;
        }

        return true;
    }

    public static function cacheForIp(): void
    {
        $cacheKey = "user_whitelist:ip";
        redis()->del($cacheKey);

        $data = UserWhitelist::query()->where('status', 1)->where('way', 'ip')->pluck('type', 'content')->toArray();
        if ($data) {
            redis()->hMSet($cacheKey, $data);
        }
    }

    public static function getByIp($ip): array
    {
        $cacheKey = "user_whitelist:ip";
        $type = (int) redis()->hGet($cacheKey, $ip);
        if ($type == 0) {
            $ipArr = explode('.', $ip);
            array_pop($ipArr);
            $ipArr[] = '*';
            $ip = implode('.', $ipArr);
            $type = (int) redis()->hGet($cacheKey, $ip);
        }

        return [$ip, $type];
    }

    public static function cacheForDevice(): void
    {
        $cacheKey = "user_whitelist:device";
        redis()->del($cacheKey);

        $data = UserWhitelist::query()->where('status', 1)->where('way', 'device')->pluck('type', 'content')->toArray();
        if ($data) {
            redis()->hMSet($cacheKey, $data);
        }
    }

    public static function getByDevice($device): int
    {
        $cacheKey = "user_whitelist:device";

        return (int) redis()->hGet($cacheKey, $device);
    }

    public static function cacheForRegion(): void
    {
        $cacheKey = "user_whitelist:region";
        redis()->del($cacheKey);

        $data = UserWhitelist::query()->select(['app_id', 'platform', 'market_channel', 'type', 'content'])
            ->where('way', 'region')
            ->where('status', 1)
            ->get()
            ->groupBy('content');

        if ($data->count() > 0) {
            foreach ($data as $region => $item) {
                $data[$region] = json_encode($item);
            }

            redis()->hMSet($cacheKey, $data->toArray());
        }
    }

    public static function getByRegion($region, $appId, $platform, $marketChannel): int
    {
        $cacheKey = "user_whitelist:region";
        $data = redis()->hGet($cacheKey, $region);

        $type = 0;
        if (!empty($data) && is_array($data)) {
            foreach ($data as $item) {
                if (!empty($item['app_id']) && $item['app_id'] != $appId) {
                    continue;
                }
                if ($item['platform'] != "all" && $item['platform'] != $platform) {
                    continue;
                }
                if ($item['market_channel'] != "all" && $item['market_channel'] != $marketChannel) {
                    continue;
                }
                $type = $item['type'];
                break;
            }
        }

        return $type;
    }

    public static function getUserWhiteInfo($appId, $platform, $marketChannel, $region, $ip, $device, $version, $uuid): int
    {
        $userWhitelistLog = [
            'app_id' => $appId,
            'platform' => $platform,
            'market_channel' => $marketChannel,
            'region' => $region,
            'ip' => $ip,
            'uuid' => $uuid,
            'device' => $device,
            'version' => $version,
        ];

        $type = self::getByDevice($device);
        if ($type > 0) {
            $userWhitelistLog['source'] = $device;
            $userWhitelistLog['source_type'] = "device";
            self::whitelistUserAccessLog($userWhitelistLog);

            return $type;
        }

        [$sourceIp, $type] = self::getByIp($ip);
        if ($type > 0) {
            // ip白名单 访问设备同时也添加到白名单中
            self::createByDevice($device, $type, "", 2, $appId, $marketChannel, $ip, $version);

            $userWhitelistLog['source'] = $sourceIp;
            $userWhitelistLog['source_type'] = "ip";
            self::whitelistUserAccessLog($userWhitelistLog);

            return $type;
        }

        $type = self::getByRegion($region, $appId, $platform, $marketChannel);
        if ($type > 0) {
            $userWhitelistLog['source'] = $region;
            $userWhitelistLog['source_type'] = "region";
            self::whitelistUserAccessLog($userWhitelistLog);

            return $type;
        }

        return 0;
    }

    public static function recordWhitelistUserAccessLog($appId, $platform, $marketChannel, $region, $ip, $device, $version, $uuid)
    {
        $userWhitelistLog = [
            'app_id' => $appId,
            'platform' => $platform,
            'market_channel' => $marketChannel,
            'region' => $region,
            'ip' => $ip,
            'device' => $device,
            'version' => $version,
            'source' => $device,
            'source_type' => 'device',
            'uuid' => $uuid,
        ];

        self::whitelistUserAccessLog($userWhitelistLog);
    }

    public static function whitelistUserAccessLog(array $data): \Illuminate\Database\Eloquent\Model
    {
        return UserWhitelistLog::query()->create($data);
    }

    public static function conversionTypeToArr(int $type): array
    {
        $data = [
            'is_free_ad' => 0,
            'is_free_member' => 0,
        ];

        if ($type > 0) {
            if (UserWhitelist::TYPE_FREE_AD & $type) {
                $data['is_free_ad'] = 1;
            }

            if (UserWhitelist::TYPE_FREE_MEMBER & $type) {
                $data['is_free_member'] = 1;
            }
        }

        return $data;
    }

    public static function conversionTypeName(int $type): string
    {
        $arr = [];
        $data = self::conversionTypeToArr($type);
        if ($data['is_free_ad'] == 1) {
            $arr[] = '屏蔽广告';
        }
        if ($data['is_free_member'] == 1) {
            $arr[] = '免费试用';
        }

        return implode('|', $arr);
    }
}
