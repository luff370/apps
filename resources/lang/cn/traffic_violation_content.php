<?php

return [
    'attributes'=>[
		'app_id' => '应用',
		'user_id' => '用户',
		'type' => '举报类型',
		'car_type' => '车辆类型',
		'images' => '违法照片',
		'city' => '违法城市',
		'address' => '违法地点',
		'description' => '违法描述',
		'province_code' => '省份简称',
		'license_plate_number' => '车牌号码',
		'violation_time' => '违法时间',
		'is_exposure' => '是否公开',
		'show_time' => '展示时间',
		'audit_status' => '审核状态',
		'audit_user_id' => '审核人',
		'audit_time' => '审核时间',
		'reply_content' => '审核回复',
		'is_get_reward' => '是否领取奖励',
		'reward_count' => '奖励数量',
		'app_platform' => '终端平台',
		'app_version' => '应用版本',
		'status' => '状态',
		'notification_status' => '审核状态',

    ],

	'audit_status_map' => [
		'0' => '待审核',
		'1' => '审核通过',
		'2' => '审核不通过',
	],
	'status_map' => [
		'0' => '停用',
		'1' => '正常',
	],
	'notification_status_map' => [
		'0' => '无消息',
		'1' => '消息未读',
		'2' => '消息已读',
	],

    'type_map' =>[
        '占用公交车道',
        '占用应急车道',
        '占用消费通道',
        '逆向行驶',
        '压实线',
        '违规变道',
        '超载',
        '超速飙车',
        '遮挡车牌上路',
        '破损车上路',
        '图片举报',
        '其他违法',
    ],

    'car_type_map'=>[
        '小型汽车',
        '大型汽车',
        '小型新能源汽车',
        '大型新能源汽车',
        '两、三轮摩托车',
        '挂车',
        '教练汽车',
        '警用汽车',
    ],

];
