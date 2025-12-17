<?php

namespace App\Support\Traits;

trait CommonArgsTrait
{
    /**
     * 应用ID
     */
    public function getAppId(): array|string|null
    {
        return request()->header('App-Id');
    }

    /**
     * Uuid客户端唯一标识
     */
    public function getUuid(): array|string|null
    {
        return request()->header('Uuid');
    }

    /**
     * AppVersion应用版本
     */
    public function getAppVersion(): array|string|null
    {
        return request()->header('App-Version');
    }

    /**
     * 系统平台
     */
    public function getPlatform(): array|string|null
    {
        if ($this->getAppId() == 10002) {
            return 'ios';
        }

        return strtolower(request()->header('Platform'));
    }

    /**
     * 终端系统版本
     */
    public function getOsVersion(): array|string|null
    {
        return request()->header('OS-Version');
    }

    /**
     * 应用包名
     */
    public function getAppPackageName(): array|string|null
    {
        return request()->header('Package-Name');
    }

    /**
     * 应用市场
     */
    public function getMarketChannel(): array|string|null
    {
        return strtolower(request()->header('Market-Channel'));
    }

    /**
     * 语言
     */
    public function getLanguage(): array|string|null
    {
        return request()->header('Language');
    }

    /**
     * 请求时间
     */
    public function getRequestTime(): array|string|null
    {
        return request()->header('Time');
    }

    /**
     * 用户Token
     */
    public function getToken(): array|string|null
    {
        return request()->header('Token');
    }

    /**
     * 设备编码
     */
    public function getDevice(): array|string|null
    {
        $device = request()->header('Device-Sn');
        if (empty($device)) {
            $device = $this->getUuid();
        }

        return $device;
    }

    /**
     * 客户端Ip
     */
    public function getClientIp(): ?string
    {
        return request()->getClientIp();
    }
}
