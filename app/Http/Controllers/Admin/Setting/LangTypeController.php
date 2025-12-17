<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Admin\Controller;
use App\Support\Services\CacheService;
use App\Services\System\Lang\LangTypeServices;

class LangTypeController extends Controller
{
    /**
     * @param LangTypeServices $services
     */
    public function __construct(LangTypeServices $services)
    {
        $this->service = $services;
    }

    /**
     * 获取语言类型列表
     */
    public function langTypeList()
    {
        $where['is_del'] = 0;

        return $this->success($this->service->langTypeList($where));
    }

    /**
     * 添加语言类型表单
     *
     * @param int $id
     *
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function langTypeForm(int $id = 0)
    {
        return $this->success($this->service->langTypeForm($id));
    }

    /**
     * 保存语言类型
     */
    public function langTypeSave()
    {
        $data = $this->getMore([
            ['id', 0],
            ['language_name', ''],
            ['file_name', ''],
            ['is_default', 0],
            ['status', 0],
        ]);
        $this->service->langTypeSave($data);
        CacheService::redisHandler()->delete('lang_type_data');

        return $this->success(100000);
    }

    /**
     * 修改语言类型状态
     *
     * @param $id
     * @param $status
     */
    public function langTypeStatus($id, $status)
    {
        $this->service->langTypeStatus($id, $status);

        return $this->success(100014);
    }

    /**
     * 删除语言类型
     *
     * @param int $id
     */
    public function langTypeDel(int $id = 0)
    {
        $this->service->langTypeDel($id);
        CacheService::redisHandler()->delete('lang_type_data');

        return $this->success(100002);
    }
}
