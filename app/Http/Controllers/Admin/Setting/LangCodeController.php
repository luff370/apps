<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Admin\Controller;
use App\Services\System\Lang\LangCodeServices;

class LangCodeController extends Controller
{
    /**
     * @param LangCodeServices $services
     */
    public function __construct(LangCodeServices $services)
    {
        $this->service = $services;
    }

    /**
     * 获取语言列表
     */
    public function langCodeList()
    {
        $where = $this->getMore([
            ['is_admin', 0],
            ['type_id', 0],
            ['code', ''],
            ['remarks', ''],
        ]);

        return $this->success($this->service->langCodeList($where));
    }

    /**
     * 获取语言详情
     */
    public function langCodeInfo()
    {
        [$code] = $this->getMore([
            ['code', ''],
        ], true);

        return $this->success($this->service->langCodeInfo($code));
    }

    /**
     * 新增编辑语言
     *
     * @throws \Exception
     */
    public function langCodeSave()
    {
        $data = $this->getMore([
            ['is_admin', 0],
            ['code', ''],
            ['remarks', ''],
            ['edit', 0],
            ['list', []],
        ]);
        $this->service->langCodeSave($data);

        return $this->success(100000);
    }

    /**
     * 删除语言
     *
     * @param $id
     */
    public function langCodeDel($id)
    {
        $this->service->langCodeDel($id);

        return $this->success(100002);
    }
}
