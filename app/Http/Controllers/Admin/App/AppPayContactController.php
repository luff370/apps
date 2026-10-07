<?php

namespace App\Http\Controllers\Admin\App;

use App\Http\Controllers\Admin\Controller;
use App\Models\AppPayContact;
use App\Models\SystemApp;
use App\Services\App\AppPayContactService;
use Illuminate\Http\Request;

class AppPayContactController extends Controller
{
    public function __construct(AppPayContactService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $where = $this->getMore([
            ['app_id', ''],
            ['keyword', ''],
        ]);
        $data = $this->service->getAllByPage($where, ['*'], ['sort' => 'desc', 'id' => 'desc'], [
            'apps' => function ($query) {
                $query->select('system_apps.id', 'system_apps.name');
            },
        ]);

        foreach ($data['list'] as $index => $item) {
            $row = is_array($item) ? $item : $item->toArray();
            $apps = $row['apps'] ?? [];
            $row['app_ids'] = array_map(static fn ($app) => (int) ($app['id'] ?? 0), $apps);
            $row['app_names'] = array_map(static fn ($app) => (string) ($app['name'] ?? ''), $apps);
            $row['assign_count'] = array_sum(array_map(static function ($app) {
                return (int) ($app['pivot']['assign_count'] ?? 0);
            }, $apps));
            unset($row['apps']);
            $data['list'][$index] = $row;
        }

        return $this->success($data);
    }

    public function save(Request $request)
    {
        $id = (int) $request->input('id', 0);
        $name = trim((string) $request->input('name', ''));
        $image = trim((string) $request->input('image', ''));
        $sort = max(0, (int) $request->input('sort', 0));
        $isEnable = (int) $request->input('is_enable', 1) === 1 ? 1 : 0;
        $appIds = $this->appIds($request);

        if ($name === '') {
            return $this->fail('请填写联系人名称');
        }
        if ($image === '') {
            return $this->fail('请上传联系人二维码');
        }
        if (mb_strlen($name) > 30) {
            return $this->fail('联系人名称不能超过30个字');
        }
        if (mb_strlen($image) > 500) {
            return $this->fail('二维码地址过长');
        }
        if ($appIds === []) {
            return $this->fail('请选择关联应用');
        }
        $exists = SystemApp::query()->whereIn('id', $appIds)->where('is_del', 0)->count();
        if ($exists !== count($appIds)) {
            return $this->fail('关联应用不存在');
        }
        if ($id > 0 && !AppPayContact::query()->where('id', $id)->exists()) {
            return $this->fail('联系人不存在');
        }

        $this->service->saveWithApps([
            'name' => $name,
            'image' => $image,
            'sort' => $sort,
            'is_enable' => $isEnable,
        ], $appIds, $id);

        return $this->success('保存成功');
    }

    public function setSort($id, Request $request)
    {
        $row = AppPayContact::query()->find((int) $id);
        if (!$row) {
            return $this->fail('联系人不存在');
        }

        $row->sort = max(0, (int) $request->input('sort', 0));
        $row->save();

        return $this->success('排序已更新');
    }

    public function setStatus($id, Request $request)
    {
        $row = AppPayContact::query()->find((int) $id);
        if (!$row) {
            return $this->fail('联系人不存在');
        }

        $row->is_enable = (int) $request->input('is_enable', 0) === 1 ? 1 : 0;
        $row->save();

        return $this->success('状态已更新');
    }

    public function destroy($id)
    {
        $row = AppPayContact::query()->find((int) $id);
        if (!$row) {
            return $this->fail('联系人不存在');
        }

        $row->delete();

        return $this->success('删除成功');
    }

    private function appIds(Request $request): array
    {
        $appIds = $request->input('app_ids', []);
        if (!is_array($appIds)) {
            $appIds = explode(',', (string) $appIds);
        }

        return array_values(array_unique(array_filter(array_map('intval', $appIds))));
    }
}
