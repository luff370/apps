<?php

namespace App\Http\Requests\Setting;

class SystemAdminValidata
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
            'account' => ['required', 'alphaDash'],
            'conf_pwd' => 'required',
            'pwd' => 'required',
            'real_name' => 'required',
            'roles' => ['required', 'array'],
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
            'account.required' => '400033',
            'account.alphaDash' => '400034',
            'conf_pwd.required' => '400263',
            'pwd.required' => '400256',
            'real_name.required' => '400035',
            'roles.required' => '400036',
            'roles.array' => '400037',
        ];
    }

    public function scenes(): array
    {
        return [
            'get' => ['account', 'pwd'],
            'update' => ['account', 'roles'],
        ];
    }
}
