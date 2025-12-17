<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class ApplicationWithdrawal
 *
 * @property int $id
 * @property int $user_id
 * @property int $app_id
 * @property string $account_type
 * @property float $amount
 * @property int $use_integral
 * @property float $use_balance
 * @property string $fund_source
 * @property string $account
 * @property string $account_name
 * @property Carbon $apply_time
 * @property Carbon|null $audit_time
 * @property int $audit_user_id
 * @property int $audit_status
 * @property string|null $reply_content
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class UserWithdrawal extends BaseModel
{
    protected $table = 'user_withdrawal';

    protected $casts = [
        'user_id' => 'int',
        'app_id' => 'int',
        'amount' => 'float',
        'use_integral' => 'int',
        'use_balance' => 'float',
        'apply_time' => 'datetime',
        'audit_time' => 'datetime',
        'audit_user_id' => 'int',
        'audit_status' => 'int',
        'status' => 'int',
        'product_id' => 'int',
    ];

    protected $fillable = [
        'user_id',
        'app_id',
        'product_id',
        'account_type',
        'amount',
        'use_integral',
        'use_balance',
        'fund_source',
        'account',
        'account_name',
        'apply_time',
        'audit_time',
        'audit_user_id',
        'audit_status',
        'reply_content',
        'remark',
        'status',
    ];

    //审核中
    const AUDIT_STATUS_UNKNOWN = 0;

    //审核成功
    const AUDIT_STATUS_SUCCESS = 1;

    //未通过
    const AUDIT_STATUS_FAIL = 2;

    /**
     * 状态
     *
     * @var string[]
     */
    public static $statusMap = [
        self::AUDIT_STATUS_UNKNOWN => '审核中',
        self::AUDIT_STATUS_SUCCESS => '审核通过',
        self::AUDIT_STATUS_FAIL => '未通过',
    ];

    // 提现方式-银行卡
    const ExtractTypeBank = 'bank';

    // 提现方式-微信
    const ExtractTypeWechat = 'wechat';

    // 提现方式-支付宝
    const ExtractTypeAliPay = 'alipay';

    // 提现方式-微信收款码
    const ExtractTypeWechatQrCode = 'wechat_qrcode';

    // 提现方式-支付宝收款码
    const ExtractTypeAlipayQrCode = 'alipay_qrcode';

    public static $extractTypeMap = [
        self::ExtractTypeBank => '银行卡',
        self::ExtractTypeWechat => '微信',
        self::ExtractTypeAliPay => '支付宝',
        self::ExtractTypeWechatQrCode => '微信收款码',
        self::ExtractTypeAlipayQrCode => '支付宝收款码',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function audit()
    {
        return $this->belongsTo(SystemAdmin::class, 'audit_user_id')->select(['id','account','real_name']);
    }

    public function searchKeywordAttr(Builder $query, $value)
    {
        if ($value === '') {
            return;
        }

        $query->where(function (Builder $query) use ($value) {
            $query->where('account',  $value )
                ->orWhere('account_name',   $value)
                ->orWhere('user_id',   $value)
                ->orWhereRaw("user_id in (select id from users where account = '{$value}')");
        });
    }
}
