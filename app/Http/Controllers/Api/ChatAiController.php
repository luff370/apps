<?php

namespace App\Http\Controllers\Api;

use App\Models\AiImage;
use App\Services\ChatAI\TencentAiartService;
use App\Support\Utils\Baidu\ImageRecognition;
use Illuminate\Http\Request;
use App\Services\ChatAI\AiTaskService;

class ChatAiController extends Controller
{
    public function __construct(AiTaskService $service)
    {
        $this->service = $service;
    }

    /**
     * AI 生成任务
     */
    public function task(Request $request)
    {
        $params = [
            'user_id' => authUserId(),
            'app_id' => $this->getAppId(),
            'version' => $this->getAppVersion(),
            'market_channel' => $this->getMarketChannel(),
            'source_id' => $request->get('source_id'),
            'dialogue_id' => $request->get('dialogue_id'),
            'type' => $request->get('type'),
            'input_content' => $request->get('content'),
        ];

        $res = $this->service->task($params);

        return $this->success($res);
    }

    /**
     * 内容评价
     */
    public function evaluate(Request $request)
    {
        $taskId = $request->get('task_id');
        $mark = $request->get('mark');
        $this->service->update($taskId, ['mark' => $mark]);

        return $this->success('success');
    }

    public function imageToImage(Request $request, TencentAiartService $service)
    {
        $image = $request->get('image');
        $prompt = $request->get('prompt', '');
        $style = $request->get('style', '201');

        try {
            $params = [
                'image_url' => $image,
                'prompt' => $prompt,
                'styles' => [$style],
                'logoAdd' => 0,
                'resolution' => '768:768',
                'strength' => 0.6,
                'rsp_img_type' => 'url',
            ];
            $response = $service->stylizeImage($params);

            AiImage::query()->create([
                'user_id' => authUserId(),
                'app_id' => $this->getAppId(),
                'platform' => 'tencent',
                'type' => 'ImageToImage',
                'prompt' => $prompt,
                'params' => $params,
                'input_image' => $image,
                'output_image' => $response->getResultImage()
            ]);

            return $this->success(json_decode($response->toJsonString()));
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function getImages()
    {
        $images = AiImage::query()->where('app_id', $this->getAppId())
            ->where('user_id', authUserId())
            ->where('created_at', '>', now()->subHour()->toDateTimeString())
            ->pluck('output_image');

        return $this->success($images);
    }

    public function imageRecognize(Request $request)
    {
        $request->validate([
            'image' => 'required', // 支持上传 or 网络图
            'type' => 'required|in:animal,plant,logo,car,dish,object,ingredient',
        ]);

        // 本地文件上传
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('temp');
            $imagePath = storage_path("app/{$path}");
        } else {
            $imagePath = $request->input('image'); // 网络 URL
        }
        $type = $request->get('type');
        $result = ImageRecognition::recognize($type, $imagePath,[
            'top_num' => 3
        ]);

        return $this->success($result);
    }
}
