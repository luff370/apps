<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class TransferOrder
 *
 * @property int $id
 * @property int $app_id
 * @property int $user_id
 * @property string $order_no
 * @property string $order_title
 * @property int $amount
 * @property string $payment_channel
 * @property string $product_code
 * @property string $payee_account_type
 * @property string $payee_account
 * @property string $payee_name
 * @property Carbon|null $trans_date
 * @property string $trade_no
 * @property string $settle_serial_no
 * @property string $status
 * @property string $error_code
 * @property string $error_msg
 * @property string $operator
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class TransferOrder extends BaseModel
{
    protected $table = 'transfer_orders';

    const StatusPending = 0;

    const StatusSuccess = 1;

    const StatusFailed = 2;

    protected $casts = [
        'app_id' => 'int',
        'user_id' => 'int',
        'trans_date' => 'datetime',
    ];

    protected $fillable = [
        'app_id',
        'user_id',
        'order_no',
        'order_title',
        'amount',
        'payment_channel',
        'product_code',
        'payee_account_type',
        'payee_account',
        'payee_name',
        'trans_date',
        'trade_no',
        'settle_serial_no',
        'status',
        'error_code',
        'error_msg',
        'operator',
    ];

    public static function statusMap()
    {
        return [
            self::StatusPending => '待转账',
            self::StatusSuccess => '转账成功',
            self::StatusFailed => '转账失败',
        ];
    }
}
