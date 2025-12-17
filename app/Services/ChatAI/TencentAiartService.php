<?php

namespace App\Services\ChatAI;

use App\Services\Service;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Aiart\V20221229\AiartClient;
use TencentCloud\Aiart\V20221229\Models\ImageToImageRequest;

class TencentAiartService extends Service
{
    protected $client;

    public function __construct()
    {
        $cred = new Credential(
            config('tencent.cloud.secretId'),
            config('tencent.cloud.secretKey')
        );

        $httpProfile = new HttpProfile();
        $httpProfile->setEndpoint("aiart.tencentcloudapi.com");

        $clientProfile = new ClientProfile();
        $clientProfile->setHttpProfile($httpProfile);

        $this->client = new AiartClient($cred, env('TENCENT_REGION', 'ap-guangzhou'), $clientProfile);
    }

    public function stylizeImage(array $params)
    {
        $req = new ImageToImageRequest();

        $req->fromJsonString(json_encode([
            "InputUrl" => $params['image_url'],
            "Prompt" => $params['prompt'] ?? '',
            "Styles" => $params['styles'] ?? ['201'],
            "ResultConfig" => [
                "Resolution" => $params['resolution'] ?? '768:768'
            ],
            'LogoAdd' => $params['logoAdd'],
            "Strength" => $params['strength'] ?? 0.6,
            "RspImgType" => $params['rsp_img_type'] ?? 'url'
        ]));

        return $this->client->ImageToImage($req);
    }
}
