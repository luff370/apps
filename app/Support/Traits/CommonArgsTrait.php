<?php

namespace App\Support\Traits;

trait CommonArgsTrait
{
    /**
     * 应用ID
     */
    public function getAppId(): string|null
    {
        return request()->header('App-Id');
    }

    /**
     * Uuid客户端唯一标识
     */
    public function getUuid(): string|null
    {
        return request()->header('Uuid');
    }

    /**
     * AppVersion应用版本
     */
    public function getAppVersion(): string|null
    {
        return request()->header('App-Version');
    }

    /**
     * 系统平台
     */
    public function getPlatform(): string|null
    {
        if ($this->getAppId() == 10002) {
            return 'ios';
        }

        return strtolower(request()->header('Platform'));
    }

    /**
     * 终端系统版本
     */
    public function getOsVersion(): string|null
    {
        return request()->header('OS-Version');
    }

    /**
     * 应用包名
     */
    public function getAppPackageName(): string|null
    {
        return request()->header('Package-Name');
    }

    /**
     * 应用市场
     */
    public function getMarketChannel(): string|null
    {
        return strtolower(request()->header('Market-Channel'));
    }

    /**
     * 语言
     */
    public function getLanguage(): string|null
    {
        return request()->header('Language');
    }

    /**
     * 请求时间
     */
    public function getRequestTime(): string|null
    {
        return request()->header('Time');
    }

    /**
     * 用户Token
     */
    public function getToken(): string|null
    {
        return request()->header('Token');
    }

    /**
     * 设备编码
     */
    public function getDevice(): string|null
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
