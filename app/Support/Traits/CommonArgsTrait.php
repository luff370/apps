<?php

namespace App\Support\Traits;

use App\Support\Services\ClientRequestContext;

trait CommonArgsTrait
{
    /**
     * 应用ID。老客户端走 App-Id Header，新客户端用包名映射。
     */
    public function getAppId(): string|null
    {
        return ClientRequestContext::appId();
    }

    /**
     * Uuid客户端唯一标识
     */
    public function getUuid(): string|null
    {
        return ClientRequestContext::uuid();
    }

    /**
     * AppVersion应用版本
     */
    public function getAppVersion(): string|null
    {
        return ClientRequestContext::appVersion();
    }

    /**
     * 系统平台
     */
    public function getPlatform(): string|null
    {
        if ($this->getAppId() == 10002) {
            return 'ios';
        }

        return ClientRequestContext::platform();
    }

    /**
     * 终端系统版本
     */
    public function getOsVersion(): string|null
    {
        return ClientRequestContext::osVersion();
    }

    /**
     * 应用包名
     */
    public function getAppPackageName(): string|null
    {
        return ClientRequestContext::packageName();
    }

    /**
     * 应用市场
     */
    public function getMarketChannel(): string|null
    {
        return ClientRequestContext::marketChannel();
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
        return ClientRequestContext::token();
    }

    /**
     * 设备编码
     */
    public function getDevice(): string|null
    {
        return ClientRequestContext::deviceSn();
    }

    /**
     * 客户端Ip
     */
    public function getClientIp(): ?string
    {
        return request()->getClientIp();
    }
}
