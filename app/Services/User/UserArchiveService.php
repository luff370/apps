<?php

namespace App\Services\User;

use App\Dao\User\UserArchiveDao;
use App\Exceptions\AdminException;
use App\Models\SystemApp;
use App\Models\User;
use App\Services\Service;
use App\Support\Services\FormBuilder as Form;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Class UserArchiveService
 */
class UserArchiveService extends Service
{
    public function __construct(UserArchiveDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 列表数据处理
     */
    public function tidyListData($list)
    {
        $apps = SystemApp::idToNameMap();
        $marketChannels = SystemApp::marketChannelsMap();
        $rows = [];
        $needUserKeys = [];
        foreach ($list as $item) {
            $row = is_array($item) ? $item : $item->toArray();
            $hasUser = !empty($row['user']['id']);
            if (!$hasUser && !empty($row['uuid']) && !empty($row['app_id'])) {
                $needUserKeys[] = $row['app_id'] . '-' . $row['uuid'];
            }
            $rows[] = $row;
        }

        $usersByKey = [];
        if ($needUserKeys) {
            $uuids = [];
            $appIds = [];
            foreach ($needUserKeys as $key) {
                [$appId, $uuid] = explode('-', $key, 2);
                $appIds[] = $appId;
                $uuids[] = $uuid;
            }
            $users = User::query()
                ->select(['id', 'account', 'nickname', 'uuid', 'app_id', 'is_vip', 'vip_type', 'overdue_time'])
                ->whereIn('uuid', array_unique($uuids))
                ->whereIn('app_id', array_unique($appIds))
                ->get();
            foreach ($users as $user) {
                $usersByKey[$user['app_id'] . '-' . $user['uuid']] = $user->toArray();
            }
        }

        foreach ($rows as &$row) {
            $row['app_name'] = $apps[$row['app_id']] ?? '';
            $row['app_version'] = $row['version'] ?? '';
            $channel = $row['market_channel'] ?? '';
            $row['market_channel'] = $marketChannels[$channel] ?? $channel;
            $row['birth_time'] = $row['birth_date'] ?? '';
            $row['calendar'] = $this->normalizeCalendar($row['calendar'] ?? '');
            $row['create_time'] = $row['created_at'] ?? '';
            if (empty($row['user']['id'])) {
                $matched = $usersByKey[($row['app_id'] ?? '') . '-' . ($row['uuid'] ?? '')] ?? null;
                if ($matched) {
                    $row['user'] = $matched;
                    $row['user_id'] = $matched['id'];
                }
            }
            $row['user_account'] = $row['user']['account'] ?? '';
            $this->attachVipInfo($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * 编辑表单
     *
     * @throws AdminException
     */
    public function updateForm(int $id): array
    {
        $info = $this->dao->get($id, ['*'], ['user']);
        if (!$info) {
            throw new AdminException(100026);
        }

        $row = $this->tidyListData([$info])[0] ?? [];
        $gender = $this->normalizeGender($row['gender'] ?? '');
        $calendar = $this->normalizeCalendar($row['calendar'] ?? '');
        $birthDate = $this->formatDateTime($row['birth_date'] ?? ($row['birth_time'] ?? ''));

        $f = [];
        $f[] = Form::input('user_info', '用户', $this->formatUserText($row))->disabled(true);
        $f[] = Form::input('vip_info', '会员状态', $this->formatVipText($row))->disabled(true);
        $f[] = Form::input('app_info', '应用', trim(($row['app_name'] ?? '') . ' ' . ($row['app_id'] ?? '')))->disabled(true);
        $f[] = Form::input('market_channel', '应用渠道', $row['market_channel'] ?? '')->disabled(true);
        $f[] = Form::input('name', '姓名', $row['name'] ?? '')->required();
        $f[] = Form::radio('gender', '性别', $gender)->options([
            ['value' => '男', 'label' => '男'],
            ['value' => '女', 'label' => '女'],
        ]);
        $f[] = Form::radio('calendar', '历法', $calendar ?: '公历')->options([
            ['value' => '公历', 'label' => '公历'],
            ['value' => '农历', 'label' => '农历'],
        ]);
        $f[] = Form::dateTime('birth_date', '出生时间', $birthDate);
        $f[] = Form::input('birth_place', '出生地点', $row['birth_place'] ?? '');
        $f[] = Form::input('version', 'App版本', $row['version'] ?? '')->disabled(true);
        $f[] = Form::input('uuid', 'UUID', $row['uuid'] ?? '')->disabled(true);
        $f[] = Form::input('create_time', '创建时间', $row['create_time'] ?? '')->disabled(true);

        return create_form('编辑档案', $f, url('/admin/user/archive/' . $id), 'PUT');
    }

    /**
     * 保存档案
     *
     * @throws AdminException
     */
    public function updateArchive(int $id, array $data): void
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(100026);
        }

        $this->dao->update($id, [
            'name' => $data['name'] ?? '',
            'gender' => $data['gender'] ?? '',
            'calendar' => $this->normalizeCalendar($data['calendar'] ?? ''),
            'birth_date' => $data['birth_date'] ?: null,
            'birth_place' => $data['birth_place'] ?? '',
        ]);
    }

    private function attachVipInfo(array &$row): void
    {
        $user = $row['user'] ?? [];
        $hasUser = !empty($user['id']) || !empty($row['user_id']);
        $isVip = !empty($user['is_vip']) || !empty($row['is_vip']);
        $vipType = $user['vip_type'] ?? ($row['vip_type'] ?? 0);
        $overdueTime = $user['overdue_time'] ?? ($row['overdue_time'] ?? '');

        $row['is_vip'] = $isVip ? 1 : 0;
        $row['vip_type'] = $vipType;
        $row['vip_name'] = $isVip ? (User::vipTypeMap()[$vipType] ?? '会员用户') : ($hasUser ? '普通用户' : '');
        $row['overdue_time'] = $isVip ? $this->formatOverdueTime($overdueTime) : '';
    }

    private function formatOverdueTime($value): string
    {
        if ($value === '' || $value === null || $value === 0 || $value === '0') {
            return '';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        try {
            if (is_numeric($value) && (int) $value > 1000000000) {
                return Carbon::createFromTimestamp((int) $value)->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function formatVipText(array $row): string
    {
        $text = $row['vip_name'] ?? '';
        if ($text === '') {
            $text = !empty($row['is_vip']) ? '会员用户' : '普通用户';
        }
        if (!empty($row['overdue_time'])) {
            $text .= ' / 有效期 ' . $row['overdue_time'];
        }

        return $text;
    }

    private function formatUserText(array $row): string
    {
        $userId = $row['user_id'] ?? ($row['user']['id'] ?? '');
        $account = $row['user_account'] ?? ($row['user']['account'] ?? '');
        $text = $userId !== '' && $userId !== null ? ('ID: ' . $userId) : '';
        if ($account) {
            $text .= ($text ? ' / ' : '') . $account;
        }

        return $text ?: '-';
    }

    private function normalizeGender($gender): string
    {
        if (in_array($gender, [1, '1', 'male', '男'], true)) {
            return '男';
        }
        if (in_array($gender, [2, '2', 'female', '女'], true)) {
            return '女';
        }

        return (string) ($gender ?: '男');
    }

    private function normalizeCalendar($calendar): string
    {
        if (in_array($calendar, [2, '2', 'lunar', '农历'], true)) {
            return '农历';
        }
        if (in_array($calendar, [1, '1', 'solar', '公历'], true)) {
            return '公历';
        }

        return (string) ($calendar ?: '');
    }

    private function formatDateTime($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (empty($value) || $value === '-') {
            return '';
        }

        return (string) $value;
    }
}
