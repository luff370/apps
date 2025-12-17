<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Admin\Controller;
use App\Services\System\Lang\LangCountryServices;

class LangCountryController extends Controller
{
    /**
     * @param LangCountryServices $services
     */
    public function __construct(LangCountryServices $services)
    {
        $this->service = $services;
    }

    /**
     * 国家语言列表
     */
    public function langCountryList()
    {
        $where = $this->getMore([
            ['keyword', ''],
        ]);

        return $this->success($this->service->langCountryList($where));
    }

    /**
     * 设置国家语言类型表单
     *
     * @param $id
     *
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function langCountryForm($id)
    {
        return $this->success($this->service->langCountryForm($id));
    }

    /**
     * 地区语言修改
     *
     * @param $id
     */
    public function langCountrySave($id)
    {
        $data = $this->getMore([
            ['name', ''],
            ['code', ''],
            ['type_id', 0],
        ]);
        $this->service->langCountrySave($id, $data);

        return $this->success(100000);
    }

    public function langCountryDel($id)
    {
        $this->service->langCountryDel($id);

        return $this->success(100002);
    }
}
