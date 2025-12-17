<?php

namespace App\Support\Services;

use App\Exceptions\AdminException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * 缓存类
 * Class CacheService
 */
class CacheService
{
    /**
     * 标签名
     *
     * @var string
     */
    protected static $globalCacheName = 'admin';

    /**
     * 缓存队列key
     *
     * @var string[]
     */
    protected static $redisQueueKey = [
        0 => 'product',
        1 => 'seckill',
        2 => 'bargain',
        3 => 'combination',
        6 => 'advance',
    ];

    /**
     * 过期时间
     *
     * @var int
     */
    protected static $expire = 3600 * 24 * 30;

    /**
     * 获取缓存过期时间
     *
     * @param int|null $expire
     *
     * @return int
     */
    protected static function getExpire(int $expire = null): int
    {
        if (self::$expire) {
            return (int) self::$expire;
        }
        $expire = Config::get('cache.expire');
        if (!is_int($expire)) {
            $expire = (int) $expire;
        }

        return self::$expire = $expire;
    }

    /**
     * 写入缓存
     *
     * @param string $name 缓存名称
     * @param mixed $value 缓存值
     * @param int|null $expire 缓存时间，为0读取系统缓存时间
     *
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function set(string $name, $value, int $expire = null, $tag=null): bool
    {
        try {
            return self::handler($tag)->set($name, $value, $expire ?? self::getExpire($expire));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 如果不存在则写入缓存
     *
     * @param string $name
     * @param  $default
     * @param int|null $expire
     *
     * @return mixed
     */
    public static function get(string $name, $default = null, int $expire = null, $tag = null)
    {
        try {
            return self::handler($tag)->remember($name, $expire ?? self::getExpire($expire), $default);
        } catch (\Throwable $e) {
            try {
                if (is_callable($default)) {
                    return $default();
                } else {
                    return $default;
                }
            } catch (\Throwable $e) {
                return null;
            }
        }
    }

    /**
     * 删除缓存
     *
     * @param string $name
     *
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function delete(string $name,$tag = null): bool
    {
        return self::handler($tag)->delete($name);
    }

    /**
     * 缓存句柄
     *
     * @return \Illuminate\Cache\TaggedCache
     */
    public static function handler($tag = null): \Illuminate\Cache\TaggedCache
    {
        return Cache::tags($tag ?? self::$globalCacheName);
    }

    /**
     * 清空缓存池
     *
     * @return bool
     */
    public static function clear($tag = null): bool
    {
        return self::handler($tag)->flush();
    }

    /**
     * Redis缓存句柄
     */
    public static function redisHandler(string $tag = null)
    {
        if ($tag) {
            return Cache::store('redis')->tags($tag);
        } else {
            return Cache::store('redis');
        }
    }

    /**
     * 放入令牌桶
     *
     * @param string $key
     * @param array $value
     * @param int $expire
     * @param string $type
     *
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function setTokenBucket(string $key, array $value, $expire = null, string $type = 'admin'): bool
    {
        logger()->debug("setTokenBucket", [$key, $value]);
        try {
            return self::redisHandler($type)->set($key, $value, $expire);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 清除所有令牌桶
     *
     * @param string $type
     *
     * @return bool
     */
    public static function clearTokenAll(string $type = 'admin'): bool
    {
        try {
            return self::redisHandler($type)->clear();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 清除令牌桶
     *
     * @param string $key
     *
     * @return bool
     */
    public static function clearToken(string $key, string $tag = 'admin'): bool
    {
        try {
            return self::redisHandler($tag)->forget($key);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 查看令牌是否存在
     *
     * @param string $key
     *
     * @return bool
     */
    public static function hasToken(string $key, string $type = 'admin'): bool
    {
        logger()->debug('hasToken', ['key' => $key]);
        try {
            return self::redisHandler($type)->has($key);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 获取token令牌桶
     *
     * @param string $key
     *
     * @return mixed|null
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function getTokenBucket(string $key, string $type = 'admin')
    {
        logger()->debug('getTokenBucket', ['key' => $key]);
        try {
            return self::redisHandler($type)->get($key);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 清除产品详情缓存
     *
     * @param  $productId
     *
     * @return bool
     * @throws \App\Exceptions\AdminException
     */
    public static function clearProductDetail($productId): bool
    {
        try {
            $cache = Redis::connection()->client();

            $cache->hDel('product:detail-full', $productId);
            $cache->hDel('product:detail', $productId);

            return true;
        } catch (\Throwable $e) {
            Log::error('产品详情缓存清除失败----' . $e->getMessage());

            throw new AdminException('产品缓存更新失败');
        }
    }


    /**
     * 获取指定分数区间的成员
     *
     * @param $key
     * @param int $start
     * @param int $end
     * @param array $options
     *
     * @return mixed
     */
    public static function zRangeByScore($key, $start = '-inf', $end = '+inf', array $options = [])
    {
        return self::redisHandler()->zRangeByScore($key, $start, $end, $options);
    }

    /**
     * 魔术方法
     *
     * @param $name
     * @param $arguments
     *
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return self::redisHandler()->{$name}(...$arguments);
    }

    /**
     * 魔术方法
     *
     * @param $name
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return self::redisHandler()->{$name}(...$arguments);
    }

    /**
     * 设置redis入库队列
     *
     * @param string $unique
     * @param int $number
     * @param int $type
     * @param bool $isPush true :重置 false：累加
     *
     * @return bool
     */
    public static function setStock(string $unique, int $number, int $type = 1, bool $isPush = true)
    {
        if (!$unique || !$number) {
            return false;
        }
        $name = (self::$redisQueueKey[$type] ?? '') . '_' . $type . '_' . $unique;
        /** @var self $cache */
        $cache = self::redisHandler();
        $res = true;
        if ($isPush) {
            $cache->del($name);
        }
        $data = [];
        for ($i = 1; $i <= $number; $i++) {
            $data[] = $i;
        }

        return $res && $cache->lPush($name, ...$data);
    }

    /**
     * 是否有库存|返回库存
     *
     * @param string $unique
     * @param int $number
     * @param int $type
     *
     * @return bool
     */
    public static function checkStock(string $unique, int $number = 0, int $type = 1)
    {
        $name = (self::$redisQueueKey[$type] ?? '') . '_' . $type . '_' . $unique;
        if ($number) {
            return self::redisHandler()->lLen($name) >= $number;
        } else {
            return self::redisHandler()->lLen($name);
        }
    }

    /**
     * 弹出redis队列中的库存条数
     *
     * @param string $unique
     * @param int $number
     * @param int $type
     *
     * @return bool
     */
    public static function popStock(string $unique, int $number, int $type = 1)
    {
        if (!$unique || !$number) {
            return false;
        }
        $name = (self::$redisQueueKey[$type] ?? '') . '_' . $type . '_' . $unique;
        /** @var self $cache */
        $cache = self::redisHandler();
        $res = true;
        if ($number > $cache->lLen($name)) {
            return false;
        }
        for ($i = 1; $i <= $number; $i++) {
            $res = $res && $cache->lPop($name);
        }

        return $res;
    }
}
