<?php

namespace App\Http\Controllers\Admin\Cms;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Article;
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

        if (!$nid) {
            return $this->fail('缺少内容ID');
        }
        if (!$lastLesson) {
            return $this->fail('请填写课程数量');
        }
        if (!$code) {
            return $this->fail('请填写课程编号');
        }
        if (!$source) {
            return $this->fail('请选择课程来源');
        }

        $article = Article::query()->find($nid);
        if (!$article) {
            return $this->fail('内容不存在');
        }

        $article->code = $code;
        $article->source = $source;
        $article->duration = $duration ?? '';
        $article->image = $image ?? '';
        $article->collections = (int) $lastLesson;
        $article->save();

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

    public function save(Request $request, $id = 0)
    {
        $id = (int) $id;
        $title = trim((string) $request->input('title', ''));
        $image = trim((string) $request->input('image', ''));
        $url = trim((string) $request->input('url', ''));
        $duration = trim((string) $request->input('duration', ''));
        $source = trim((string) $request->input('source', ''));
        $code = trim((string) $request->input('code', ''));

        if ($title === '') {
            return $this->fail('请填写课节标题');
        }
        if (mb_strlen($title) > 255) {
            return $this->fail('课节标题不能超过255个字');
        }

        if ($id > 0) {
            $row = ArticleCourse::query()->find($id);
            if (!$row) {
                return $this->fail('课节不存在');
            }
            $row->fill([
                'title' => $title,
                'image' => $image,
                'url' => $url,
                'duration' => $duration,
                'source' => $source,
                'code' => $code,
            ])->save();

            return $this->success('保存成功');
        }

        $nid = (int) $request->input('nid', 0);
        if ($nid <= 0 || !Article::query()->where('id', $nid)->exists()) {
            return $this->fail('内容不存在');
        }

        $lessonNumber = (int) ArticleCourse::query()->where('nid', $nid)->max('lesson_number') + 1;
        if ($lessonNumber > 255) {
            return $this->fail('课节序号不能超过255');
        }

        ArticleCourse::query()->create([
            'nid' => $nid,
            'lesson_number' => $lessonNumber,
            'title' => $title,
            'image' => $image,
            'url' => $url,
            'duration' => $duration,
            'source' => $source,
            'code' => $code,
            'is_enable' => 1,
        ]);

        return $this->success('保存成功');
    }

    public function setFieldValue($id, $field, $value)
    {
        $allowed = ['title', 'duration', 'code', 'url', 'image', 'lesson_number'];
        if (!in_array($field, $allowed, true)) {
            return $this->fail('不能修改该字段');
        }

        $row = ArticleCourse::query()->find($id);
        if (!$row) {
            return $this->fail('课节不存在');
        }

        if ($field === 'lesson_number') {
            $value = (int) $value;
            if ($value < 1 || $value > 255) {
                return $this->fail('课节序号需在 1 到 255 之间');
            }
            if ((int) $row->lesson_number === $value) {
                return $this->success('修改成功');
            }
            $this->moveLesson($row, $value);

            return $this->success('修改成功');
        }

        $row->update([$field => $value]);

        return $this->success('修改成功');
    }

    /**
     * 把课节挪到目标序号，中间课节顺延，序号重新排成连续数字。
     */
    private function moveLesson(ArticleCourse $row, int $target): void
    {
        DB::transaction(function () use ($row, $target) {
            $ids = ArticleCourse::query()
                ->where('nid', $row->nid)
                ->orderBy('lesson_number')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            $count = count($ids);
            if ($count === 0) {
                return;
            }

            $from = array_search((int) $row->id, array_map('intval', $ids), true);
            if ($from === false) {
                return;
            }

            $target = max(1, min($count, $target));
            $to = $target - 1;
            if ($from !== $to) {
                $movedId = $ids[$from];
                array_splice($ids, $from, 1);
                array_splice($ids, $to, 0, [$movedId]);
            }

            foreach ($ids as $index => $id) {
                $number = $index + 1;
                ArticleCourse::query()->where('id', $id)->where('lesson_number', '!=', $number)->update([
                    'lesson_number' => $number,
                ]);
            }
        });
    }

    public function destroy($id)
    {
        ArticleCourse::query()->where('id', $id)->delete();

        return $this->success('删除成功');
    }
}
