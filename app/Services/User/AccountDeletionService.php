<?php

namespace App\Services\User;

use App\Dao\User\UserDeletionRequestDao;
use App\Exceptions\AdminException;
use App\Models\AppVersionPlanTask;
use App\Models\SystemApp;
use App\Models\User;
use App\Models\UserDeletionRequest;
use App\Models\UserProfile;
use App\Services\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountDeletionService extends Service
{
    public function __construct(UserDeletionRequestDao $dao)
    {
        $this->dao = $dao;
    }

    public function publicUrl(string $packageName): string
    {
        $packageName = trim($packageName);
        if ($packageName === '') {
            return '';
        }

        return url('account-deletion/' . $packageName);
    }

    public function resolveApp(string $packageName): ?SystemApp
    {
        $packageName = trim($packageName);
        if ($packageName === '') {
            return null;
        }

        return SystemApp::query()
            ->where('is_del', 0)
            ->where('package_name', $packageName)
            ->with(['merchant'])
            ->orderByDesc('is_enable')
            ->orderBy('id')
            ->first();
    }

    public function pageData(SystemApp $app): array
    {
        $merchant = $app->merchant;
        $contactEmail = (string) ($app->contact_email ?? '');
        if ($merchant && $contactEmail === '') {
            $contactEmail = (string) ($merchant->contact_email ?? '');
        }

        return [
            'app' => $app,
            'app_name' => $this->googleChannelAppName($app),
            'contact_email' => $contactEmail,
            'package_name' => (string) ($app->package_name ?? ''),
            'logo' => (string) ($app->logo ?? ''),
            'developer_name' => $merchant ? trim((string) ($merchant->name ?? '')) : '',
            'developer_address' => $merchant ? trim((string) ($merchant->registered_address ?? '')) : '',
            'developer_phone' => $merchant ? trim((string) ($merchant->corporate_phone ?? '')) : '',
        ];
    }

    /**
     * 删除页展示名优先用版本规划里最新谷歌渠道的上架名称，和 Play 商品详情对齐。
     */
    public function googleChannelAppName(SystemApp $app): string
    {
        $fromPlan = $this->latestGooglePlanName($app);
        if ($fromPlan !== '') {
            return $fromPlan;
        }

        $fromMarkets = $this->googleNameFromMarkets($app->markets ?? []);
        if ($fromMarkets !== '') {
            return $fromMarkets;
        }

        return trim((string) ($app->name ?? ''));
    }

    private function latestGooglePlanName(SystemApp $app): string
    {
        if (!Schema::hasTable('app_version_plan_tasks') || !Schema::hasTable('app_version_plans')) {
            return '';
        }

        $appIds = $this->appIdsFor($app);
        if (!$appIds) {
            return '';
        }

        $query = AppVersionPlanTask::query()
            ->select('app_version_plan_tasks.name')
            ->join('app_version_plans', 'app_version_plans.id', '=', 'app_version_plan_tasks.plan_id')
            ->whereIn('app_version_plans.app_id', $appIds)
            ->where('app_version_plan_tasks.market_channel', 'google')
            ->where('app_version_plan_tasks.name', '!=', '')
            ->orderByRaw('COALESCE(app_version_plan_tasks.listed_at, app_version_plan_tasks.updated_at) DESC')
            ->orderByDesc('app_version_plan_tasks.id');

        $listed = (clone $query)
            ->where('app_version_plan_tasks.status', '已上架')
            ->value('name');
        if (trim((string) $listed) !== '') {
            return trim((string) $listed);
        }

        return trim((string) $query->value('name'));
    }

    private function googleNameFromMarkets($markets): string
    {
        foreach (is_array($markets) ? $markets : [] as $market) {
            if (!is_array($market)) {
                continue;
            }
            if ((string) ($market['market_channel'] ?? '') !== 'google') {
                continue;
            }

            $name = trim((string) ($market['name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    public function submit(SystemApp $app, array $input, string $ip = '', string $userAgent = ''): UserDeletionRequest
    {
        $identifier = trim((string) ($input['identifier'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $reason = trim((string) ($input['reason'] ?? ''));

        $request = UserDeletionRequest::query()->create([
            'app_id' => (int) $app->id,
            'user_id' => 0,
            'identifier' => $identifier,
            'email' => $email,
            'reason' => mb_substr($reason, 0, 500),
            'status' => UserDeletionRequest::STATUS_PENDING,
            'ip' => mb_substr($ip, 0, 64),
            'user_agent' => mb_substr($userAgent, 0, 500),
            'remark' => '',
        ]);

        $this->fulfill($request, $app);

        return $request->refresh();
    }

    public function deleteAccount(User $user): void
    {
        $userId = (int) $user->id;
        if ($userId <= 0) {
            return;
        }

        $payload = $this->anonymizePayload($user);
        if (Schema::hasColumn('users', 'alipay_user_id')) {
            $payload['alipay_user_id'] = '';
        }
        if (Schema::hasColumn('users', 'device_token')) {
            $payload['device_token'] = '';
        }
        User::query()->where('id', $userId)->update($payload);

        if (Schema::hasTable('user_profile')) {
            UserProfile::query()->where('user_id', $userId)->update([
                'name' => '',
                'birth_place' => '',
                'gender' => '',
            ]);
        }

        $this->forgetThirdLogin($userId);
    }

    public function anonymizePayload(User $user): array
    {
        $deleted = 'deleted_' . (int) $user->id;

        return [
            'is_del' => 1,
            'account' => $deleted,
            'email' => $deleted . '@deleted.invalid',
            'phone' => '',
            'password' => '',
            'token' => '',
            'uuid' => $deleted,
            'nickname' => 'Deleted User',
            'avatar' => '',
            'device_sn' => '',
            'update_time' => time(),
        ];
    }

    public function findUser(int|array $appIds, string $identifier, bool $includeDeleted = false): ?User
    {
        $identifier = trim($identifier);
        $appIds = $this->normalizeAppIds($appIds);
        if ($identifier === '' || !$appIds) {
            return null;
        }

        $query = User::query()->whereIn('app_id', $appIds);
        if (!$includeDeleted) {
            $query->where('is_del', 0);
        }

        if (ctype_digit($identifier)) {
            $byId = (clone $query)->where('id', (int) $identifier)->first();
            if ($byId) {
                return $byId;
            }
        }

        $matched = (clone $query)->where(function ($q) use ($identifier) {
            $q->where('account', $identifier)
                ->orWhere('email', $identifier)
                ->orWhere('uuid', $identifier);
        })->orderByDesc('id')->first();
        if ($matched) {
            return $matched;
        }

        return $this->findUserByThirdEmail($appIds, $identifier, $includeDeleted);
    }

    public function tidyListData($list)
    {
        $apps = SystemApp::query()->where('is_del', 0)->get(['id', 'name', 'package_name'])->keyBy('id');
        $statusMap = UserDeletionRequest::statusMap();
        foreach ($list as &$item) {
            $app = $apps[$item['app_id']] ?? null;
            $item['app_name'] = $app['name'] ?? '';
            $item['package_name'] = $app['package_name'] ?? '';
            $item['status_text'] = $statusMap[$item['status']] ?? '';
            $item['user_account'] = data_get($item, 'user.account', '');
            $item['create_time_text'] = !empty($item['create_time']) ? date('Y-m-d H:i:s', (int) $item['create_time']) : '';
            $item['processed_at_text'] = !empty($item['processed_at']) ? date('Y-m-d H:i:s', (int) $item['processed_at']) : '';
        }

        return $list;
    }

    /**
     * @throws AdminException
     */
    public function processRequest(int $id, int $userId = 0, string $remark = ''): void
    {
        /** @var UserDeletionRequest|null $request */
        $request = $this->dao->get($id);
        if (!$request) {
            throw new AdminException(100026);
        }

        $app = SystemApp::query()->find($request['app_id']);
        if (!$app) {
            throw new AdminException(100026);
        }

        if ($userId > 0) {
            $user = User::query()->whereIn('app_id', $this->appIdsFor($app))->where('id', $userId)->first();
            if (!$user) {
                throw new AdminException('未找到该用户');
            }
            $this->applyDeletion($request, $user, $remark ?: '后台指定用户删除');

            return;
        }

        $this->fulfill($request, $app, $remark ?: '后台再次处理');
        $request->refresh();
        if ((int) $request['status'] !== UserDeletionRequest::STATUS_PROCESSED) {
            throw new AdminException('仍未匹配到账号，请指定用户ID后处理');
        }
    }

    private function fulfill(UserDeletionRequest $request, SystemApp $app, string $remark = ''): void
    {
        $appIds = $this->appIdsFor($app);
        $user = $this->findUser($appIds, (string) $request->identifier, false);
        if (!$user && $request->email !== '') {
            $user = $this->findUser($appIds, (string) $request->email, false);
        }

        if ($user) {
            $this->applyDeletion($request, $user, $remark ?: '网页申请自动删除');

            return;
        }

        $deleted = $this->findUser($appIds, (string) $request->identifier, true);
        if (!$deleted && $request->email !== '') {
            $deleted = $this->findUser($appIds, (string) $request->email, true);
        }
        if ($deleted && (int) $deleted->is_del === 1) {
            $request->update([
                'user_id' => (int) $deleted->id,
                'status' => UserDeletionRequest::STATUS_PROCESSED,
                'processed_at' => time(),
                'remark' => $remark !== '' ? $remark : '账号此前已删除',
            ]);

            return;
        }

        $request->update([
            'status' => UserDeletionRequest::STATUS_UNMATCHED,
            'remark' => $remark !== '' ? $remark : '未匹配到账号，待人工核实',
        ]);
    }

    private function applyDeletion(UserDeletionRequest $request, User $user, string $remark): void
    {
        $this->deleteAccount($user);
        $request->update([
            'user_id' => (int) $user->id,
            'status' => UserDeletionRequest::STATUS_PROCESSED,
            'processed_at' => time(),
            'remark' => $remark,
        ]);
    }

    private function findUserByThirdEmail(array $appIds, string $identifier, bool $includeDeleted): ?User
    {
        if (!filter_var($identifier, FILTER_VALIDATE_EMAIL) || !Schema::hasTable('third_login_users')) {
            return null;
        }

        $userIds = DB::table('third_login_users')
            ->where('email', $identifier)
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->all();
        if (!$userIds) {
            return null;
        }

        $query = User::query()->whereIn('app_id', $appIds)->whereIn('id', $userIds);
        if (!$includeDeleted) {
            $query->where('is_del', 0);
        }

        return $query->orderByDesc('id')->first();
    }

    private function appIdsFor(SystemApp $app): array
    {
        $packageName = trim((string) $app->package_name);
        if ($packageName === '') {
            return $this->normalizeAppIds((int) $app->id);
        }

        $ids = SystemApp::query()
            ->where('is_del', 0)
            ->where('package_name', $packageName)
            ->pluck('id')
            ->all();

        return $this->normalizeAppIds($ids ?: [(int) $app->id]);
    }

    private function normalizeAppIds(int|array $appIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $appIds))));
    }

    private function forgetThirdLogin(int $userId): void
    {
        if (Schema::hasTable('third_login_users')) {
            DB::table('third_login_users')->where('user_id', $userId)->delete();
        }
        if (Schema::hasTable('user_third')) {
            DB::table('user_third')->where('user_id', $userId)->update([
                'nickname' => '',
                'avatar' => '',
                'openid' => 'deleted_' . $userId,
                'unionid' => '',
                'status' => 0,
            ]);
        }
    }
}
