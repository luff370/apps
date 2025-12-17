<?php

namespace App\Services\Cms;

use App\Models\Article;
use App\Models\ArticleCourse;
use App\Services\Service;
use App\Support\Utils\Bilibili;

class ArticleCourseService extends Service
{
    public function generateBilibiliCourse($nid, $bv)
    {
        $content = Article::query()->where('id', $nid)->first();
        if (empty($content)) {
            return false;
        }

        $videoInfo = Bilibili::getBilibiliVideoInfo($bv);
        if (empty($videoInfo)) {
            return false;
        }

        // 视频数量
        if (!empty($videoInfo['videos'])) {
            $content['collections'] = $videoInfo['videos'];
        }
        // 封面图片
        if (!empty($videoInfo['pic'])) {
            $content['image'] = $videoInfo['pic'];
        }
        // 视频时长
        if (!empty($videoInfo['duration'])) {
            $content['duration'] = $videoInfo['duration'];
        }
        $content->save();

        if (!empty($content['pages']) && count($content['pages']) > 0) {
            $course = [];
            foreach ($content['pages'] as $item) {
                $course[] = [
                    'nid' => $nid,
                    'lesson_number' => $item['page'],
                    'title' => $item['part'],
                    'image' => $item['title'],
                    'url' => '',
                    'duration' => $item['duration'],
                    'source' => $content['source'],
                    'code' => $item['cid'],
                ];
            }

            ArticleCourse::query()->where('nid', $nid)->delete();
            ArticleCourse::query()->insert($course);
        }

        return true;
    }

}
