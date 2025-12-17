<?php

return [
    'attributes'=>[
		'user_id' => '用户',
		'app_id' => '应用',
		'account_type' => '账号类型',
		'amount' => '提现金额',
		'use_integral' => '使用积分数量',
		'use_balance' => '使用余额数量',
		'fund_source' => '资金来源',
		'account' => '提现账号',
		'account_name' => '提现人姓名',
		'apply_time' => '申请时间',
		'audit_time' => '审核时间',
		'audit_user_id' => '审核用户',
		'audit_status' => '状态',
		'reply_content' => '审核回复',
		'status' => '状态 （0-删除 ，1-正常）',
		'product_id' => '产品',

    ],

	'account_type_map' => [
		'alipay' => '支付宝',
		'weixin' => '微信',
		'bank' => '银行转账',
	],
	'fund_source_map' => [
		'balance' => '账号余额',
		'integral' => '积分兑换',
	],
	'audit_status_map' => [
		'0' => '待审核',
		'1' => '审核通过',
		'2' => '审核不通过',
	],

];