<?php

namespace App\Services\User;

use App\Dao\User\UserDeletionRequestDao;
use App\Exceptions\AdminException;
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

    public function publicUrl(int $appId, ?string $packageName = null): string
    {
        $key = $packageName !== null && $packageName !== '' ? $packageName : (string) $appId;

        return url('account-deletion/' . $key);
    }

    public function resolveApp(string $appKey): ?SystemApp
    {
        $appKey = trim($appKey);
        if ($appKey === '') {
            return null;
        }

        $query = SystemApp::query()->where('is_del', 0)->with(['merchant']);
        if (ctype_digit($appKey)) {
            return $query->where('id', (int) $appKey)->first();
        }

        return $query->where('package_name', $appKey)->first();
    }

    public function pageData(SystemApp $app): array
    {
        $merchant = $app->merchant;
        $developer = (string) $app->name;
        $contactEmail = (string) ($app->contact_email ?? '');
        if ($merchant) {
            $developer = (string) ($merchant->corporate ?: $merchant->name ?: $app->name);
            if ($contactEmail === '') {
                $contactEmail = (string) ($merchant->contact_email ?? '');
            }
        }

        return [
            'app' => $app,
            'app_name' => (string) $app->name,
            'developer_name' => $developer,
            'contact_email' => $contactEmail,
            'package_name' => (string) ($app->package_name ?? ''),
            'logo' => (string) ($app->logo ?? ''),
        ];
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

    public function findUser(int $appId, string $identifier, bool $includeDeleted = false): ?User
    {
        $identifier = trim($identifier);
        if ($identifier === '' || $appId <= 0) {
            return null;
        }

        $query = User::query()->where('app_id', $appId);
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

        return $this->findUserByThirdEmail($appId, $identifier, $includeDeleted);
    }

    public function tidyListData($list)
    {
        $apps = SystemApp::idToNameMap();
        $statusMap = UserDeletionRequest::statusMap();
        foreach ($list as &$item) {
            $item['app_name'] = $apps[$item['app_id']] ?? '';
            $item['status_text'] = $statusMap[$item['status']] ?? '';
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
            $user = User::query()->where('app_id', $app->id)->where('id', $userId)->first();
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
        $user = $this->findUser((int) $app->id, (string) $request->identifier, false);
        if (!$user && $request->email !== '') {
            $user = $this->findUser((int) $app->id, (string) $request->email, false);
        }

        if ($user) {
            $this->applyDeletion($request, $user, $remark ?: '网页申请自动删除');

            return;
        }

        $deleted = $this->findUser((int) $app->id, (string) $request->identifier, true);
        if (!$deleted && $request->email !== '') {
            $deleted = $this->findUser((int) $app->id, (string) $request->email, true);
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

    private function findUserByThirdEmail(int $appId, string $identifier, bool $includeDeleted): ?User
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

        $query = User::query()->where('app_id', $appId)->whereIn('id', $userIds);
        if (!$includeDeleted) {
            $query->where('is_del', 0);
        }

        return $query->orderByDesc('id')->first();
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
