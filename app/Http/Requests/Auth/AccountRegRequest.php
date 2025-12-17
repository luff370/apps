<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Request;

class AccountRegRequest extends Request
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
            // 'account' => ['required', 'integer', 'between:13000000000,19999999999', 'unique:users,account'],
            'account' => ['required', 'alpha_num:ascii','between:4,11'],
            'password' => ['required','between:6,16', 'confirmed'],
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
            'account.require' => '请输入账号',
            // 'account.integer' => '请输入正确的手机号',
            // 'account.between' => '请输入正确的手机号',
            'account.alpha_num' => '账号只能为大小写字母和数字',
            'account.between' => '账号长度在4到11个字符之间',
            'account.unique' => '该账户已创建，可直接登录',
            'password.require' => '请输入登录密码',
            'password.between' => '密码长度不能少于6位或大于16位',
            'password.confirmed' => '确认密码与输入密码不一致',
        ];
    }
}
