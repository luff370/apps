<?php

namespace App\Services\User;

use App\Dao\User\UserArchiveDao;
use App\Exceptions\AdminException;
use App\Exceptions\ApiException;
use App\Models\SystemApp;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Service;
use App\Support\Services\ClientRequestContext;
use App\Support\Services\FormBuilder as Form;
use App\Support\Utils\Token;
use DateTimeInterface;
use Illuminate\Http\Request;
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
            $row['save_to_archive'] = $this->normalizeSaveToArchive($row['save_to_archive'] ?? 0);
            $row['save_to_archive_text'] = $row['save_to_archive'] ? '是' : '否';
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
            'birth_date' => $this->normalizeBirthDate($data['birth_date'] ?? ''),
            'birth_place' => $data['birth_place'] ?? '',
        ]);
    }

    /**
     * 兼容旧端把历法写进出生时间的格式，例如：农历 1980/01/26 12:00。
     */
    public function prepareClientProfile(array $profile): array
    {
        $birthDate = trim((string) ($profile['birth_date'] ?? ''));
        if (preg_match('/^(农历|公历|阴历|阳历|lunar|solar)\s+/iu', $birthDate, $matches)) {
            if (trim((string) ($profile['calendar'] ?? '')) === '') {
                $profile['calendar'] = $matches[1];
            }
        }

        $profile['calendar'] = $this->normalizeCalendar($profile['calendar'] ?? '');
        $profile['birth_date'] = $this->normalizeBirthDate($birthDate);

        return $profile;
    }

    /**
     * 客户端新增档案。日期兼容旧端「农历 1980/01/26 12:00」。
     */
    public function saveClientProfile(array $profile): array
    {
        $profile = $this->prepareClientProfile($profile);
        $payload = $this->clientProfilePayload($profile, true);
        $this->assertArchiveLimit((int) $payload['user_id'], (string) $payload['uuid'], (int) $payload['app_id']);

        $row = UserProfile::query()->create($payload);

        return $this->formatClientArchive($row->toArray());
    }

    /**
     * 客户端修改已有档案。
     */
    public function updateClientProfile(int $id, array $profile): array
    {
        $uuid = (string) ($profile['uuid'] ?? '');
        $appId = (int) ($profile['app_id'] ?? 0);
        $userId = (int) ($profile['user_id'] ?? 0);
        $row = $this->findOwnedArchive($id, $userId, $uuid, $appId);
        $profile = $this->prepareClientProfile($profile);
        $payload = $this->clientProfilePayload($profile, false);
        unset($payload['uuid'], $payload['app_id']);
        if ((int) $row->user_id > 0 && (int) ($payload['user_id'] ?? 0) <= 0) {
            unset($payload['user_id']);
        }

        $row->fill($payload)->save();

        return $this->formatClientArchive($row->fresh()->toArray());
    }

    /**
     * 客户端分页列表。
     */
    public function listClientProfiles(int $userId, string $uuid, int $appId): array
    {
        [$page, $limit] = $this->getPageValue();
        $page = max($page, 1);
        $limit = min(max($limit, 1), 50);
        $query = $this->clientArchiveQuery($userId, $uuid, $appId);
        $count = (clone $query)->count();
        $list = [];
        if ($count > 0) {
            $rows = $query->orderByDesc('id')
                ->offset(($page - 1) * $limit)
                ->limit($limit)
                ->get()
                ->toArray();
            $list = array_map(fn (array $row) => $this->formatClientArchive($row), $rows);
        }

        return compact('count', 'list') + ['page' => $page, 'limit' => $limit];
    }

    /**
     * 会员过期超过宽限期后，只保留最新 N 条。
     */
    public function pruneExpiredExtraArchives(): int
    {
        $keep = max(1, (int) config('user_archive.guest_limit', 10));
        $graceDays = max(1, (int) config('user_archive.expire_extra_days', 30));
        $cutoff = Carbon::now()->subDays($graceDays)->timestamp;
        $deleted = 0;

        $groups = UserProfile::query()
            ->selectRaw('user_id, app_id, count(*) as total')
            ->where('user_id', '>', 0)
            ->groupBy('user_id', 'app_id')
            ->havingRaw('count(*) > ?', [$keep])
            ->get();
        if ($groups->isEmpty()) {
            return 0;
        }

        $users = User::query()
            ->whereIn('id', $groups->pluck('user_id')->all())
            ->where('is_vip', 0)
            ->where('overdue_time', '>', 0)
            ->where('overdue_time', '<', $cutoff)
            ->get(['id', 'app_id'])
            ->keyBy('id');

        foreach ($groups as $group) {
            $user = $users->get((int) $group->user_id);
            if (!$user) {
                continue;
            }
            $keepIds = UserProfile::query()
                ->where('app_id', (int) $group->app_id)
                ->where('user_id', (int) $group->user_id)
                ->orderByDesc('id')
                ->limit($keep)
                ->pluck('id');
            if ($keepIds->isEmpty()) {
                continue;
            }
            $deleted += UserProfile::query()
                ->where('app_id', (int) $group->app_id)
                ->where('user_id', (int) $group->user_id)
                ->whereNotIn('id', $keepIds->all())
                ->delete();
        }

        return $deleted;
    }

    public function normalizeBirthDate($value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $raw = preg_replace('/^(农历|公历|阴历|阳历|lunar|solar)\s*/iu', '', $raw) ?? $raw;
        $raw = trim(str_replace(['年', '月', '日'], ['-', '-', ' '], $raw));
        $raw = str_replace('/', '-', $raw);
        $raw = preg_replace('/\s+/', ' ', $raw) ?? $raw;

        try {
            return Carbon::parse($raw)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
        }

        if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})(?:\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?/', $raw, $matches)) {
            return sprintf(
                '%04d-%02d-%02d %02d:%02d:%02d',
                (int) $matches[1],
                (int) $matches[2],
                (int) $matches[3],
                (int) ($matches[4] ?? 0),
                (int) ($matches[5] ?? 0),
                (int) ($matches[6] ?? 0)
            );
        }

        return null;
    }

    /**
     * 把已登录用户 ID 写回同设备、尚未绑定的档案。
     */
    public function bindUserId(int $userId, string $uuid, int $appId): void
    {
        if ($userId <= 0 || $appId <= 0) {
            return;
        }

        $uuids = array_values(array_filter(array_unique([$uuid])));
        $userUuid = (string) User::query()->where('id', $userId)->value('uuid');
        if ($userUuid !== '') {
            $uuids[] = $userUuid;
            $uuids = array_values(array_unique($uuids));
        }
        if (!$uuids) {
            return;
        }

        UserProfile::query()
            ->where('app_id', $appId)
            ->whereIn('uuid', $uuids)
            ->where(function ($query) {
                $query->where('user_id', 0)->orWhereNull('user_id');
            })
            ->update(['user_id' => $userId]);
    }

    public function resolveUserId(Request $request, string $uuid, int $appId): int
    {
        $token = trim((string) (ClientRequestContext::token($request) ?? ''));
        if ($token !== '') {
            try {
                $userId = (int) (Token::verify($token)['user_id'] ?? 0);
                if ($userId > 0) {
                    return $userId;
                }
            } catch (\Throwable $e) {
            }
        }

        if ($uuid === '' || $appId <= 0) {
            return 0;
        }

        return (int) User::query()
            ->where('app_id', $appId)
            ->where('uuid', $uuid)
            ->orderByDesc('id')
            ->value('id');
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

    private function clientProfilePayload(array $profile, bool $forCreate): array
    {
        $payload = [
            'user_id' => (int) ($profile['user_id'] ?? 0),
            'uuid' => (string) ($profile['uuid'] ?? ''),
            'app_id' => (int) ($profile['app_id'] ?? 0),
            'market_channel' => (string) ($profile['market_channel'] ?? ''),
            'version' => (string) ($profile['version'] ?? ''),
            'name' => (string) ($profile['name'] ?? ''),
            'gender' => (string) ($profile['gender'] ?? ''),
            'calendar' => (string) ($profile['calendar'] ?? ''),
            'birth_date' => $profile['birth_date'] ?? null,
            'birth_place' => (string) ($profile['birth_place'] ?? ''),
        ];
        if ($forCreate || array_key_exists('save_to_archive', $profile)) {
            $payload['save_to_archive'] = $this->normalizeSaveToArchive($profile['save_to_archive'] ?? 0);
        }

        return $payload;
    }

    private function clientArchiveQuery(int $userId, string $uuid, int $appId)
    {
        $query = UserProfile::query()->where('app_id', $appId);
        if ($userId > 0) {
            $query->where(function ($query) use ($userId, $uuid) {
                $query->where('user_id', $userId);
                if ($uuid !== '') {
                    $query->orWhere(function ($query) use ($uuid) {
                        $query->where('uuid', $uuid)
                            ->where(function ($query) {
                                $query->where('user_id', 0)->orWhereNull('user_id');
                            });
                    });
                }
            });
        } else {
            $query->where('uuid', $uuid);
        }

        return $query;
    }

    private function assertArchiveLimit(int $userId, string $uuid, int $appId): void
    {
        $user = $this->resolveArchiveUser($userId, $uuid, $appId);
        $limit = $this->archiveLimit($user);
        $count = $this->clientArchiveQuery((int) ($user['id'] ?? $userId), $uuid, $appId)->count();
        if ($count < $limit) {
            return;
        }

        if ($this->isVipUser($user)) {
            throw new ApiException('会员档案数量已达上限');
        }

        throw new ApiException('非会员最多保存' . $limit . '条档案');
    }

    private function archiveLimit(?User $user): int
    {
        if ($this->isVipUser($user)) {
            return max(1, (int) config('user_archive.member_limit', 5000));
        }

        return max(1, (int) config('user_archive.guest_limit', 10));
    }

    private function isVipUser(?User $user): bool
    {
        return $user && !empty($user['is_vip']);
    }

    private function resolveArchiveUser(int $userId, string $uuid, int $appId): ?User
    {
        if ($userId > 0) {
            return User::query()->find($userId);
        }
        if ($uuid === '' || $appId <= 0) {
            return null;
        }

        return User::query()
            ->where('app_id', $appId)
            ->where('uuid', $uuid)
            ->orderByDesc('id')
            ->first();
    }

    private function findOwnedArchive(int $id, int $userId, string $uuid, int $appId): UserProfile
    {
        $row = UserProfile::query()->where('id', $id)->where('app_id', $appId)->first();
        if (!$row) {
            throw new ApiException('档案不存在');
        }
        if ($userId > 0 && (int) $row->user_id > 0 && (int) $row->user_id !== $userId) {
            throw new ApiException('档案不存在');
        }
        if ((int) $row->user_id <= 0 && (string) $row->uuid !== $uuid) {
            throw new ApiException('档案不存在');
        }

        return $row;
    }

    private function formatClientArchive(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'gender' => $this->normalizeGender($row['gender'] ?? ''),
            'calendar' => $this->normalizeCalendar($row['calendar'] ?? ''),
            'birth_date' => $this->formatDateTime($row['birth_date'] ?? ''),
            'birth_place' => (string) ($row['birth_place'] ?? ''),
            'save_to_archive' => (bool) $this->normalizeSaveToArchive($row['save_to_archive'] ?? 0),
            'created_at' => $this->formatDateTime($row['created_at'] ?? ''),
            'updated_at' => $this->formatDateTime($row['updated_at'] ?? ''),
        ];
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

    private function normalizeSaveToArchive($value): int
    {
        if ($value === true || $value === 1 || $value === '1') {
            return 1;
        }
        if (is_string($value) && in_array(strtolower(trim($value)), ['true', 'yes', 'on'], true)) {
            return 1;
        }

        return 0;
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
