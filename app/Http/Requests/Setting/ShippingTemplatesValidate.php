<?php

namespace App\Http\Requests\Setting;

class ShippingTemplatesValidate
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
            'region_info' => 'array',
            'appoint_info' => 'array',
            'no_delivery_info' => 'array',
            'type' => 'number',
            'appoint' => 'number',
            'no_delivery' => 'number',
            'sort' => 'number',
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
            'name.require' => '400025',
            'region_info.array' => '400026',
            'appoint_info.array' => '400027',
            'no_delivery_info.array' => '400028',
            'type.number' => '400029',
            'appoint.number' => '400030',
            'no_delivery.number' => '400031',
            'sort.number' => '400032',
        ];
    }

    public function scenes(): array
    {
        return [
            'save' => ['name', 'type', 'appoint', 'sort', 'region_info', 'appoint_info', 'no_delivery_info', 'no_delivery'],
        ];
    }
}
