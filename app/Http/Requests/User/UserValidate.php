<?php

namespace App\Http\Requests\User;

class UserValidate
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
            'account' => 'require|alphaNum',
            'pwd' => 'require',
            'true_pwd' => 'require',
            'nickname' => 'require',
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
            'account.require' => '400254',
            'account.alphaNum' => '400089',
            'pwd.require' => '400134',
            'true_pwd.require' => '400263',
            'nickname.number' => '400187',
        ];
    }
}
