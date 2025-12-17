<?php

namespace App\Http\Requests\Setting;

class SystemMenuValidate
{
    /**
     * 定义验证规则
     * 格式：'字段名'    =>    ['规则1','规则2'...]
     *
     * @var array
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * 定义错误信息
     * 格式：'字段名.规则名'    =>    '错误信息'
     *
     * @var array
     */
    public function messages(): array
    {
        return [];
    }
}
