<?php

namespace App\Models;

use Illuminate\Http\Request;

class AppConfigChangeLog extends BaseModel
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['before' => 'array', 'after' => 'array'];

    public static function record(AppConfig $config, string $action): void
    {
        $fields = $config->getFillable();
        $before = $action === 'create' ? null : array_intersect_key($config->getOriginal(), array_flip($fields));
        $after = $action === 'delete' ? null : $config->only($fields);
        // Ignore timestamp-only saves and values that are equivalent after casting.
        if ($action === 'update' && collect($fields)->every(
            fn($field) => (string)($before[$field] ?? '') === (string)($after[$field] ?? '')
        )) {
            return;
        }

        $admin = Request::hasMacro('adminInfo') ? request()->adminInfo() : [];
        static::query()->create([
            'config_id' => $config->id,
            'app_id' => $config->app_id,
            'previous_app_id' => $before['app_id'] ?? null,
            'channel' => $config->channel,
            'key' => $config->key,
            'action' => $action,
            'source' => $config->changeSource ?: ($admin ? 'admin' : 'system'),
            'admin_id' => $admin['id'] ?? 0,
            'admin_account' => $admin['account'] ?? '',
            'before' => $before,
            'after' => $after,
        ]);
    }
}
