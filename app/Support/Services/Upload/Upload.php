<?php

namespace App\Support\Services\Upload;

use Illuminate\Support\Facades\Config;

/**
 * Class Upload
 *
 * @package App\Support\Services\Upload
 * @mixin \App\Support\Services\Upload\Storage\Local
 * @mixin \App\Support\Services\Upload\Storage\OSS
 * @mixin \App\Support\Services\Upload\Storage\COS
 * @mixin \App\Support\Services\Upload\Storage\Qiniu
 */
class Upload
{

    /**
     * 设置默认上传类型
     *
     * @return mixed
     */
    protected function getDefaultDriver()
    {
        return Config::get('upload.default', 'local');
    }
}
