<?php

namespace App\Services\App;

use App\Dao\App\AppConfigDao;
use App\Exceptions\AdminException;
use App\Models\AppConfig;
use App\Models\SystemApp;
use App\Services\Service;
use App\Support\Services\AppConfigService as AppConfigCacheService;
use App\Support\Services\FormBuilder as Form;
use App\Support\Services\FormOptions;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AppConfigService
 */
class AppConfigService extends Service
{
    /**
     * AppConfigService constructor.
     */
    public function __construct(AppConfigDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 列表数据处理
     */
    public function tidyListData($list)
    {
        $apps = SystemApp::idToNameMap();
        $channels = SystemApp::marketChannelsMap();
        foreach ($list as &$item) {
            $item['app_name'] = $apps[$item['app_id']] ?? '';
            $item['channel_value'] = $item['channel'];
            $item['channel']  = $channels[$item['channel']] ?? '全部';
        }

        return $list;
    }

    /**
     * 新增表单获取
     *
     * @param array $params
     *
     * @return array
     * @throws \App\Exceptions\AdminException
     */
    public function createForm($params=[]): array
    {
        return create_form('添加', $this->createUpdateForm($params), url('/admin/app/app_config'));
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

        return create_form('修改', $this->createUpdateForm($info->toArray()), url('/admin/app/app_config/' . $id), 'PUT');
    }

    /**
     * 生成form表单
     */
    public function createUpdateForm(array $info = []): array
    {
        $appId = (int)($info['app_id'] ?? 0);
        $f[] = Form::select('app_id', '应用', $appId)->options(FormOptions::systemApps())->filterable(true)->requiredNum();
        $f[] = Form::select('channel', '渠道', $info['channel'] ?? 'all')->options(FormOptions::marketChannel(['label' => '全部', 'value' => 'all']));
        $f[] = Form::text('version', '版本', $info['version'] ?? 'all');
        $f[] = Form::text('name', '参数名称', $info['name'] ?? '')->required();
        $f[] = Form::text('key', '参数key', $info['key'] ?? '')->required();
        $f[] = Form::text('value', '参数值', $info['value'] ?? '')->required();
        $f[] = Form::textarea('remark', '备注', $this->autoAddWhiteListRemark($info));
        $f[] = Form::radio('is_enable', '是否启用', $info['is_enable'] ?? 1)->options(FormOptions::isEnable());

        return $f;
    }

    /**
     * 保存应用参数配置
     */
    public function save(array $data): Model
    {
        $data = $this->normalizeUniqueFields($data);
        $data = $this->applyAutoAddWhiteListDefaults($data);
        $this->ensureKeyUnique($data);

        return $this->dao->save($data);
    }

    /**
     * 更新应用参数配置
     */
    public function update($id, array $data, string $key = ''): int
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(400594);
        }

        $data = $this->normalizeUniqueFields($data, $info->toArray());
        $data = $this->applyAutoAddWhiteListDefaults($data, $info->toArray());
        $this->ensureKeyUnique($data, (int)$info->id);

        return $this->dao->update($id, $data, $key);
    }

    public function getUserWhiteListFilter(int $appId): array
    {
        $config = $this->findUserWhiteListFilter($appId);

        return $this->formatUserWhiteListFilter($appId, $config);
    }

    public function setUserWhiteListFilterEnabled(int $appId, int $enabled): array
    {
        $config = $this->ensureUserWhiteListFilter($appId);
        $enabled = $enabled ? 1 : 0;
        if ((int)$config->is_enable !== $enabled) {
            $config->is_enable = $enabled;
            $config->save();
            AppConfigCacheService::cacheByAppId($appId);
        }

        return $this->formatUserWhiteListFilter($appId, $config);
    }

    public function ensureUserWhiteListFilter(int $appId): AppConfig
    {
        if ($appId <= 0) {
            throw new AdminException('应用ID不能为空');
        }

        $config = $this->findUserWhiteListFilter($appId);
        if ($config) {
            return $config;
        }

        return AppConfig::query()->create(AppConfig::defaultUserWhiteListFilterAttributes($appId));
    }

    private function findUserWhiteListFilter(int $appId): ?AppConfig
    {
        return AppConfig::query()
            ->where('app_id', $appId)
            ->where('channel', 'all')
            ->where('key', AppConfig::USER_WHITE_LIST_FILTER_KEY)
            ->first();
    }

    private function formatUserWhiteListFilter(int $appId, ?AppConfig $config): array
    {
        return [
            'app_id' => $appId,
            'id' => $config ? (int)$config->id : 0,
            'key' => AppConfig::USER_WHITE_LIST_FILTER_KEY,
            'value' => $config ? (string)$config->value : '1',
            'is_enable' => $config ? (int)$config->is_enable : 0,
        ];
    }

    /**
     * 复制应用参数配置表单
     */
    public function copyForm(int $id): array
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(400594);
        }

        $data = $info->only([
            'app_id',
            'channel',
            'version',
            'name',
            'key',
            'value',
            'remark',
        ]);
        $data['is_enable'] = 0;

        return $this->createForm($data);
    }

    private function normalizeUniqueFields(array $data, array $origin = []): array
    {
        foreach (['app_id', 'channel', 'version', 'key'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }

            if ((!array_key_exists($field, $data) || $data[$field] === '') && array_key_exists($field, $origin)) {
                $data[$field] = $origin[$field];
            }
        }

        if (!array_key_exists('channel', $data) || $data['channel'] === '') {
            $data['channel'] = 'all';
        }

        if (!array_key_exists('version', $data) || $data['version'] === '') {
            $data['version'] = 'all';
        }

        return $data;
    }

    private function applyAutoAddWhiteListDefaults(array $data, array $origin = []): array
    {
        $key = (string)($data['key'] ?? $origin['key'] ?? '');
        if ($key !== AppConfig::AUTO_ADD_WHITE_LIST_KEY) {
            return $data;
        }

        if (!array_key_exists('remark', $data)) {
            if (trim((string)($origin['remark'] ?? '')) === '') {
                $data['remark'] = AppConfig::AUTO_ADD_WHITE_LIST_REMARK;
            }

            return $data;
        }

        if (trim((string)$data['remark']) === '') {
            $data['remark'] = AppConfig::AUTO_ADD_WHITE_LIST_REMARK;
        }

        return $data;
    }

    private function autoAddWhiteListRemark(array $info): string
    {
        $remark = (string)($info['remark'] ?? '');
        if ($remark !== '') {
            return $remark;
        }

        return (string)($info['key'] ?? '') === AppConfig::AUTO_ADD_WHITE_LIST_KEY
            ? AppConfig::AUTO_ADD_WHITE_LIST_REMARK
            : '';
    }

    private function ensureKeyUnique(array $data, int $ignoreId = 0): void
    {
        if ($this->dao->existsByUniqueKey($data, $ignoreId)) {
            throw new AdminException('同一应用、同一渠道下参数key不能重复');
        }
    }
}
