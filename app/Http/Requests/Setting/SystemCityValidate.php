<?php

namespace App\Http\Requests\Setting;

class SystemCityValidate
{
    /**
     * 定义验证规则
     * 格式：'字段名'    =>    ['规则1','规则2'...]
     *
     * @var array
     */
    public function rules(): array
    {
        return [
            'name' => 'require',
            'level' => 'number',
            'parent_id' => 'number',
        ];
    }

    /**
     * 定义错误信息
     * 格式：'字段名.规则名'    =>    '错误信息'
     *
     * @var array
     */
    public function messages(): array
    {
        return [
            'name.require' => '400038',
            'level.number' => '400039',
            'parent_id.number' => '400040',
        ];
    }

    public function scenes(): array
    {
        return ['save' => ['name', 'level', 'parent_id']];
    }
}
