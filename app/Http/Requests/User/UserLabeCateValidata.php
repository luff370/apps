<?php

namespace App\Http\Requests\User;

/**
 * Class UserLabeCateValidata
 *
 * @package App\Http\Requests\user
 */
class UserLabeCateValidata
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
            'sort' => 'require|number',
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
            'name.require' => '400086',
            'sort.require' => '400087',
            'sort.number' => '400088',
        ];
    }
}
