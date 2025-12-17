<?php

namespace App\Services\Other;

use App\Services\Service;
use App\Models\Order\StoreOrder;
use App\Models\Order\StoreOrderRefund;
use App\Support\Services\SpreadsheetExcelService;

class ExportServices extends Service
{
    /**
     * 真实请求导出
     *
     * @param array $header excel表头
     * @param array $export 填充数据
     * @param string $filename 保存文件名称
     * @param string $suffix 保存文件后缀
     * @param bool $is_save true|false 是否保存到本地
     *
     * @return mixed
     */
    public function export(array $header, $title_arr, $export = [], $filename = '', $suffix = 'xlsx', $is_save = false)
    {
        $title = isset($title_arr[0]) && !empty($title_arr[0]) ? $title_arr[0] : '导出数据';
        $name = isset($title_arr[1]) && !empty($title_arr[1]) ? $title_arr[1] : '导出数据';
        $info = isset($title_arr[2]) && !empty($title_arr[2]) ? $title_arr[2] : date('Y-m-d H:i:s', time());

        $path = SpreadsheetExcelService::instance()->setExcelHeader($header)
            ->setExcelTile($title, $name, $info)
            ->setExcelContent($export)
            ->excelSave($filename, $suffix, $is_save);

        return $this->siteUrl() . $path;
    }

    /**
     * 获取系统接口域名
     *
     * @return string
     */
    public function siteUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];

        return $protocol . $domainName;
    }

    /**
     * 用户资金导出
     *
     * @param $data 导出数据
     */
    public function userFinance($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as $value) {
                $export[] = [
                    $value['uid'],
                    $value['nickname'],
                    $value['pm'] == 0 ? '-' . $value['number'] : $value['number'],
                    $value['title'],
                    $value['mark'],
                    $value['add_time'],
                ];
            }
        }
        $header = ['会员ID', '昵称', '金额/积分', '类型', '备注', '创建时间'];
        $title = ['资金监控', '资金监控', date('Y-m-d H:i:s', time())];
        $filename = '资金监控_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    /**
     * 用户佣金导出
     *
     * @param $data 导出数据
     */
    public function userCommission($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as &$value) {
                $export[] = [
                    $value['nickname'],
                    $value['sum_number'],
                    $value['now_money'],
                    $value['brokerage_price'],
                    $value['extract_price'],
                ];
            }
        }
        $header = ['昵称/姓名', '总佣金金额', '账户余额', '账户佣金', '提现到账佣金'];
        $title = ['拥金记录', '拥金记录' . time(), ' 生成时间：' . date('Y-m-d H:i:s', time())];
        $filename = '拥金记录_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    /**
     * 用户积分导出
     *
     * @param $data 导出数据
     */
    public function userPoint($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as $key => $item) {
                $export[] = [
                    $item['id'],
                    $item['title'],
                    $item['balance'],
                    $item['number'],
                    $item['mark'],
                    $item['nickname'],
                    $item['add_time'],
                ];
            }
        }
        $header = ['编号', '标题', '变动前积分', '积分变动', '备注', '用户微信昵称', '添加时间'];
        $title = ['积分日志', '积分日志' . time(), '生成时间：' . date('Y-m-d H:i:s', time())];
        $filename = '积分日志_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    /**
     * 用户充值导出
     *
     * @param $data 导出数据
     */
    public function userRecharge($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as $item) {
                switch ($item['recharge_type']) {
                    case 'routine':
                        $item['_recharge_type'] = '小程序充值';
                        break;
                    case 'weixin':
                        $item['_recharge_type'] = '公众号充值';
                        break;
                    default:
                        $item['_recharge_type'] = '其他充值';
                        break;
                }
                $item['_pay_time'] = $item['pay_time'] ? date('Y-m-d H:i:s', $item['pay_time']) : '暂无';
                $item['_add_time'] = $item['add_time'] ? date('Y-m-d H:i:s', $item['add_time']) : '暂无';
                $item['paid_type'] = $item['paid'] ? '已支付' : '未支付';

                $export[] = [
                    $item['nickname'],
                    $item['price'],
                    $item['paid_type'],
                    $item['_recharge_type'],
                    $item['_pay_time'],
                    $item['paid'] == 1 && $item['refund_price'] == $item['price'] ? '已退款' : '未退款',
                    $item['_add_time'],
                ];
            }
        }
        $header = ['昵称/姓名', '充值金额', '是否支付', '充值类型', '支付时间', '是否退款', '添加时间'];
        $title = ['充值记录', '充值记录' . time(), ' 生成时间：' . date('Y-m-d H:i:s', time())];
        $filename = '充值记录_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    /**
     * 用户推广导出
     *
     * @param $data 导出数据
     */
    public function userAgent($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as $index => $item) {
                $export[] = [
                    $item['uid'],
                    $item['nickname'],
                    $item['phone'],
                    $item['spread_count'],
                    $item['order_count'],
                    $item['order_price'],
                    $item['brokerage_money'],
                    $item['extract_count_price'],
                    $item['extract_count_num'],
                    $item['brokerage_price'],
                    $item['spread_name'],
                ];
            }
        }
        $header = ['用户编号', '昵称', '电话号码', '推广用户数量', '订单数量', '推广订单金额', '佣金金额', '已提现金额', '提现次数', '未提现金额', '上级推广人'];
        $title = ['推广用户', '推广用户导出' . time(), ' 生成时间：' . date('Y-m-d H:i:s', time())];
        $filename = '推广用户_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    /**
     * 微信用户导出
     *
     * @param $data 导出数据
     */
    public function wechatUser($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as $index => $item) {
                $export[] = [
                    $item['nickname'],
                    $item['sex'],
                    $item['country'] . $item['province'] . $item['city'],
                    $item['subscribe'] == 1 ? '关注' : '未关注',
                ];
            }
        }
        $header = ['名称', '性别', '地区', '是否关注公众号'];
        $title = ['微信用户导出', '微信用户导出' . time(), ' 生成时间：' . date('Y-m-d H:i:s', time())];
        $filename = '微信用户导出_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    /**
     * 订单资金导出
     *
     * @param $data 导出数据
     */
    public function orderFinance($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as $info) {
                $time = $info['pay_time'];
                $price = $info['total_price'] + $info['pay_postage'];
                $zhichu = $info['coupon_price'] + $info['deduction_price'] + $info['cost'];
                $profit = ($info['total_price'] + $info['pay_postage']) - ($info['coupon_price'] + $info['deduction_price'] + $info['cost']);
                $deduction = $info['deduction_price'];//积分抵扣
                $coupon = $info['coupon_price'];      //优惠
                $cost = $info['cost'];                //成本
                $export[] = [$time, $price, $zhichu, $cost, $coupon, $deduction, $profit];
            }
        }
        $header = ['时间', '营业额(元)', '支出(元)', '成本', '优惠', '积分抵扣', '盈利(元)'];
        $title = ['财务统计', '财务统计', date('Y-m-d H:i:s', time())];
        $filename = '财务统计_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    /**
     * 商铺砍价活动导出
     *
     * @param $data 导出数据
     */
    public function storeBargain($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as $index => $item) {
                $export[] = [
                    $item['title'],
                    $item['info'],
                    '￥' . $item['price'],
                    $item['bargain_num'],
                    $item['status'] ? '开启' : '关闭',
                    empty($item['start_time']) ? '' : date('Y-m-d H:i:s', (int) $item['start_time']),
                    empty($item['stop_time']) ? '' : date('Y-m-d H:i:s', (int) $item['stop_time']),
                    $item['sales'],
                    $item['quota'],
                    empty($item['add_time']) ? '' : $item['add_time'],
                ];
            }
        }
        $header = ['砍价活动名称', '砍价活动简介', '砍价金额', '用户每次砍价的次数', '砍价状态', '砍价开启时间', '砍价结束时间', '销量', '限量', '添加时间'];
        $title = ['砍价商品导出', '商品信息' . time(), ' 生成时间：' . date('Y-m-d H:i:s', time())];
        $filename = '砍价商品导出_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    /**
     * 商铺拼团导出
     *
     * @param $data 导出数据
     */
    public function storeCombination($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as $item) {
                $export[] = [
                    $item['id'],
                    $item['title'],
                    $item['ot_price'],
                    $item['price'],
                    $item['quota'],
                    $item['count_people'],
                    $item['count_people_all'],
                    $item['count_people_pink'],
                    $item['sales'] ?? 0,
                    $item['is_show'] ? '开启' : '关闭',
                    empty($item['stop_time']) ? '' : date('Y/m/d H:i:s', (int) $item['stop_time']),
                ];
            }
        }
        $header = ['编号', '拼团名称', '原价', '拼团价', '限量', '拼团人数', '参与人数', '成团数量', '销量', '商品状态', '结束时间'];
        $title = ['拼团商品导出', '商品信息' . time(), ' 生成时间：' . date('Y-m-d H:i:s', time())];
        $filename = '拼团商品导出_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    /**
     * 商铺秒杀活动导出
     *
     * @param $data 导出数据
     */
    public function storeSeckill($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as $item) {
                if ($item['status']) {
                    if ($item['start_time'] > time()) {
                        $item['start_name'] = '活动未开始';
                    } else {
                        if ($item['stop_time'] < time()) {
                            $item['start_name'] = '活动已结束';
                        } else {
                            if ($item['stop_time'] > time() && $item['start_time'] < time()) {
                                $item['start_name'] = '正在进行中';
                            }
                        }
                    }
                } else {
                    $item['start_name'] = '活动已结束';
                }
                $export[] = [
                    $item['id'],
                    $item['title'],
                    $item['info'],
                    $item['ot_price'],
                    $item['price'],
                    $item['quota'],
                    $item['sales'],
                    $item['start_name'],
                    $item['stop_time'] ? date('Y-m-d H:i:s', $item['stop_time']) : '/',
                    $item['status'] ? '开启' : '关闭',
                ];
            }
        }
        $header = ['编号', '活动标题', '活动简介', '原价', '秒杀价', '限量', '销量', '秒杀状态', '结束时间', '状态'];
        $title = ['秒杀商品导出', ' ', ' 生成时间：' . date('Y-m-d H:i:s', time())];
        $filename = '秒杀商品导出_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    /**
     * 商铺商品导出
     *
     * @param $data 导出数据
     */
    public function storeProduct($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as $index => $item) {
                $export[] = [
                    $item['store_name'],
                    $item['store_info'],
                    $item['cate_name'],
                    '￥' . $item['price'],
                    $item['stock'],
                    $item['sales'],
                    $item['visitor'],
                ];
            }
        }
        $header = ['商品名称', '商品简介', '商品分类', '价格', '库存', '销量', '浏览量'];
        $title = ['商品导出', '商品信息' . time(), ' 生成时间：' . date('Y-m-d H:i:s', time())];
        $filename = '商品导出_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    /**
     * 商铺订单导出
     *
     * @param $data 导出数据
     *
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function exportOrder($data = [], $action = 'default'): string
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as $item) {
                $goodsName = [];
                $goodsSku = [];
                $goodsBar = [];
                foreach ($item['product'] as $k => $v) {
                    if (isset($v['store_name'])) {
                        $goodsName[] = $v['store_name'];
                        $goodsSku[] = $v['suk'];
                        if (!empty($v['bar_code'])) {
                            $goodsBar[] = $v['bar_code'];
                        }
                    }
                }

                $userAddress = $item['province'] . ' ' . $item['city'] . ' ' . $item['district'] . ' ' . $item['user_address'] . ',' . $item['real_name'] . ',' . $item['user_phone'];

                switch ($action) {
                    case 'supplier_deliver':
                        $export[] = [
                            $item['order_id'],
                            '',
                            $userAddress,
                            $item['real_name'],
                            $item['user_phone'],
                            $item['province'],
                            $item['city'],
                            $item['district'],
                            $item['user_address'],
                            $goodsName ? implode("\n", $goodsName) : '',
                            ($item['product_info']['keyword'] ?? '') . ((empty($item['product_info']['keyword']) || empty($goodsBar)) ? '' : ' | ') . ($goodsBar ? implode("\n", $goodsBar) : ''),
                            $goodsSku ? implode("\n", $goodsSku) : '',
                            $item['product_id'] ?? '',
                            $item['cost'] > 0 ? $item['cost'] : ($item['product_info']['cost'] ?? 0),
                            $item['remark'] ?? '',
                            // date('Y-m-d H:i:s', $item['pay_time'] ?? 0),
                        ];
                        break;
                    default:
                        $export[] = [
                            $item['order_id'],
                            $item['real_name'],
                            $item['user_phone'],
                            $goodsName ? implode("\n", $goodsName) : '',
                            ($item['product_info']['keyword'] ?? '') . ((empty($item['product_info']['keyword']) || empty($goodsBar)) ? '' : ' | ') . ($goodsBar ? implode("\n", $goodsBar) : ''),
                            $goodsSku ? implode("\n", $goodsSku) : '',
                            $item['product_id'] ?? '',
                            $item['pay_price'],
                            $item['cost'],
                            StoreOrder::statusName($item['paid'], $item['status'], $item['refund_status'])['name'] ?? '未知',
                            $item['add_time'],
                        ];
                }
            }
        }

        // 表头信息
        switch ($action) {
            case 'supplier_deliver':
                $header = [
                    '订单号',
                    // '快递公司',
                    '快递单号',
                    '完整收货信息',
                    '收货人姓名',
                    '收货人电话',
                    '省',
                    '市',
                    '区',
                    '详细地址',
                    '商品信息',
                    '商品简称',
                    '商品规格',
                    '商品ID',
                    '价格',
                    '备注',
                    // '下单时间',
                ];
                break;
            default:
                $header = [
                    '订单号',
                    '收货人姓名',
                    '收货人电话',
                    '商品信息',
                    '商品简称',
                    '商品规格',
                    '商品ID',
                    '实际支付',
                    '成本价',
                    '订单状态',
                    '下单时间',
                ];
        }

        $filename = '订单导出_' . date('YmdHis', time());
        // $title = ['订单导出', '订单信息' . time(), ' 生成时间：' . date('Y-m-d H:i:s', time())];
        $suffix = 'xlsx';
        $is_save = true;

        return SpreadsheetExcelService::instance()
            ->setHeaderLine(1)
            ->setExcelHeader($header)
            ->setExcelContent($export)
            ->excelSave($filename, $suffix, $is_save);
    }

    /**
     * 商铺订单导出
     *
     * @param $data 导出数据
     *
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function exportRefundOrder($data = []): string
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as $item) {
                $export[] = [
                    $item['order']['order_id'],
                    $item['order']['real_name'],
                    $item['order']['user_phone'],
                    $item['refund_back_address'],
                    $item['order']['product_info'],
                    $item['order']['sku']['bar_code'] ?? ($item['order']['sku']['suk'] ?? ''),
                    !empty($item['order']['logistics']) ? $item['order']['logistics']->pluck('express_num')->implode('|') . " " : '',
                    ($item['return_back_express']['express_num'] ?? ($item['apply_type'] == StoreOrderRefund::ApplyTypeIntercept ? '拦截物流' : '')) . " ",
                    $item['return_back_express']['receiving_time'] ?? '',
                    $item['is_deduction'] ? '是' : '否',
                    $item['order']['cost'],
                ];
            }
        }

        // 表头信息
        $header = [
            '订单号',
            '退货人姓名',
            '退货人电话',
            '退货地址',
            '商品名称',
            '商品编号',
            '寄出快递单号',
            '退货快递单号',
            '退回签收时间',
            '是否已抵扣',
            '价格',
        ];

        $filename = '售后订单导出_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = true;

        return SpreadsheetExcelService::instance()
            ->setHeaderLine(1)
            ->setExcelHeader($header)
            ->setExcelContent($export)
            ->excelSave($filename, $suffix, $is_save);
    }

    /**
     * 商铺自提点导出
     *
     * @param $data 导出数据
     */
    public function storeMerchant($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as $index => $item) {
                $export[] = [
                    $item['name'],
                    $item['phone'],
                    $item['address'] . '' . $item['detailed_address'],
                    $item['day_time'],
                    $item['is_show'] ? '开启' : '关闭',
                ];
            }
        }
        $header = ['提货点名称', '提货点', '地址', '营业时间', '状态'];
        $title = ['提货点导出', '提货点信息' . time(), ' 生成时间：' . date('Y-m-d H:i:s', time())];
        $filename = '提货点导出_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    public function memberCard($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data['data'] as $index => $item) {
                $export[] = [
                    $item['card_number'],
                    $item['card_password'],
                    $item['user_name'],
                    $item['user_phone'],
                    $item['use_time'],
                    $item['use_uid'] ? '已领取' : '未领取',
                ];
            }
        }
        $header = ['会员卡号', '密码', '领取人', '领取人手机号', '领取时间', '是否使用'];
        $title = ['会员卡导出', '会员卡导出' . time(), ' 生成时间：' . date('Y-m-d H:i:s', time())];
        $filename = $data['title'] ? ("卡密会员_" . trim(str_replace(["\r\n", "\r", "\\", "\n", "/", "<", ">", "=", " "], '', $data['title']))) : "";
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    public function tradeData($data = [], $tradeTitle = "交易统计")
    {
        $export = $header = [];
        if (!empty($data)) {
            $header = ['时间'];
            $headerArray = array_column($data['series'], 'name');
            $header = array_merge($header, $headerArray);
            $export = [];
            foreach ($data['series'] as $index => $item) {
                foreach ($data['x'] as $k => $v) {
                    $export[$v]['time'] = $v;
                    $export[$v][] = $item['value'][$k];
                }
            }
        }
        $title = [$tradeTitle, $tradeTitle, ' 生成时间：' . date('Y-m-d H:i:s', time())];
        $filename = $tradeTitle;
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    /**
     * 商品统计
     *
     * @param $data 导出数据
     */
    public function productTrade($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as &$value) {
                $export[] = [
                    $value['time'],
                    $value['browse'],
                    $value['user'],
                    $value['cart'],
                    $value['order'],
                    $value['payNum'],
                    $value['pay'],
                    $value['cost'],
                    $value['refund'],
                    $value['refundNum'],
                    $value['changes'] . '%',
                ];
            }
        }
        $header = ['日期/时间', '商品浏览量', '商品访客数', '加购件数', '下单件数', '支付件数', '支付金额', '成本金额', '退款金额', '退款件数', '访客-支付转化率'];
        $title = ['商品统计', '商品统计' . time(), ' 生成时间：' . date('Y-m-d H:i:s', time())];
        $filename = '商品统计_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    public function userTrade($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as &$value) {
                $export[] = [
                    $value['time'],
                    $value['user'],
                    $value['browse'],
                    $value['new'],
                    $value['paid'],
                    $value['changes'] . '%',
                    $value['vip'],
                    $value['recharge'],
                    $value['payPrice'],
                ];
            }
        }
        $header = ['日期/时间', '访客数', '浏览量', '新增用户数', '成交用户数', '访客-支付转化率', '付费会员数', '充值用户数', '客单价'];
        $title = ['用户统计', '用户统计' . time(), ' 生成时间：' . date('Y-m-d H:i:s', time())];
        $filename = '用户统计_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = true;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    /**
     * 商铺商品导出
     *
     * @param $data 导出数据
     */
    public function productStat($data = [])
    {
        $export = [];
        if (!empty($data)) {
            foreach ($data as $index => $item) {
                $export[] = [
                    $item['name'],
                    $item['product_id'],
                    $item['view_count'] ?? 0,
                    $item['order_count'] ?? 0,
                    $this->getRate($item['order_count'], $item['view_count']),
                    $item['buy_count'] ?? 0,
                    $this->getRate($item['buy_count'], $item['view_count']),
                    $this->getRate($item['buy_count'], $item['order_count']),
                    $item['buy_amount'] ?? 0,
                    $item['refund_count'] ?? 0,
                ];
            }
        }
        $header = ['商品名称', '商品Id', '点击次数', '下单次数', '下单率', '成交次数', '成交率', '下单成交率', '成交总金额', '退货次数'];
        $title = ['商品统计导出', '商品统计信息', ' 生成时间：' . date('Y-m-d H:i:s', time())];
        $filename = '商品统计导出_' . date('YmdHis', time());
        $suffix = 'xlsx';
        $is_save = false;

        return $this->export($header, $title, $export, $filename, $suffix, $is_save);
    }

    public function getRate($divisor, $dividend)
    {
        if (empty($divisor) || empty($dividend)) {
            return 0;
        }

        return round($divisor / $dividend, 3);
    }
}
