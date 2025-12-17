<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use FormBuilder\Factory\Base;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class TrafficViolationContent
 *
 * @property int $id
 * @property int $app_id
 * @property int $user_id
 * @property string $type
 * @property string $car_type
 * @property array|null $images
 * @property string $address
 * @property string $description
 * @property string $province_code
 * @property string $license_plate_number
 * @property Carbon $violation_time
 * @property int $is_exposure
 * @property int $audit_status
 * @property int|null $audit_user_id
 * @property Carbon|null $audit_time
 * @property string|null $reply_content
 * @property int $reward_type
 * @property int $reward_count
 * @property string $app_platform
 * @property string $app_version
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class TrafficViolationContent extends BaseModel
{
	protected $table = 'traffic_violation_content';

	protected $casts = [
		'app_id' => 'int',
		'user_id' => 'int',
		'images' => 'array',
		'violation_time' => 'datetime',
		'is_exposure' => 'int',
		'audit_status' => 'int',
		'audit_user_id' => 'int',
		'audit_time' => 'datetime',
		'reward_type' => 'int',
		'reward_count' => 'int',
		'status' => 'int'
	];

	protected $fillable = [
		'app_id',
		'user_id',
		'type',
		'car_type',
		'images',
		'city',
		'address',
		'description',
		'province_code',
		'license_plate_number',
		'violation_time',
		'show_time',
		'is_exposure',
		'audit_status',
		'audit_user_id',
		'audit_time',
		'reply_content',
		'reward_type',
		'reward_count',
		'app_platform',
		'app_version',
		'app_audit_data',
		'status'
	];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->select(['id', 'nickname']);
	}

    public function searchKeywordAttr(Builder $query, $value)
    {
        if ($value === '') {
            return;
        }

        $query->where(function (Builder $query) use ($value) {
            $query->where('license_plate_number',  $value )
                ->orWhere('user_id',   $value)
                ->orWhereRaw("user_id in (select id from users where account = '{$value}')");
        });
    }
}
