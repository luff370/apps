<?php

namespace App\Http\Controllers\Admin\Cms;

use Illuminate\Http\Request;
use App\Models\ArticleCourse;
use App\Http\Controllers\Admin\Controller;

class CourseController extends Controller
{
    public function generate(Request $request)
    {
        $nid = $request->get('nid');
        $code = $request->get('code');
        $source = $request->get('source');
        $duration = $request->get('duration', '');
        $image = $request->get('image', '');
        $lastLesson = $request->get('collections');

        if (!$lastLesson) {
            return $this->fail('请填写课程数量');
        }
        if (!$code) {
            return $this->fail('请填写课程编号');
        }
        if (!$source) {
            return $this->fail('请选择课程来源');
        }

        for ($i = 1; $i <= $lastLesson; $i++) {
            ArticleCourse::query()->updateOrCreate(
                [
                    'nid' => $nid,
                    'lesson_number' => $i,
                ],
                [
                    'title' => "第{$i}课",
                    'code' => $code,
                    'source' => $source,
                    'duration' => $duration ?? '',
                    'image' => $image ?? '',
                    'url' => "https://www.bilibili.com/video/{$code}?p={$i}",
                ]);
        }

        // 删除多余课程
        ArticleCourse::query()->where('nid', $nid)->where('lesson_number', '>', $lastLesson)->delete();

        return $this->success();
    }

    public function setFieldValue($id, $field, $value)
    {
        ArticleCourse::query()->where('id', $id)->update([$field => $value]);

        return $this->success('修改成功');
    }

    public function destroy($id)
    {
        ArticleCourse::query()->where('id', $id)->delete();

        return $this->success('删除成功');
    }
}
