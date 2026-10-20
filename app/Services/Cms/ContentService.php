<?php

namespace App\Services\Cms;

use App\Models\Article;
use App\Models\SystemApp;
use App\Services\Service;
use App\Dao\Cms\ContentDao;
use App\Models\ArticleCourse;
use App\Models\ArticleContent;
use App\Models\ContentCategory;
use App\Support\Utils\Bilibili;
use App\Models\ArticleAiCreation;
use App\Models\ArticleAiDialogue;
use App\Exceptions\AdminException;

/**
 * Class ArticleServices
 */
class ContentService extends Service
{
    /**
     * ContentService constructor.
     */
    public function __construct(ContentDao $dao)
    {
        $this->dao = $dao;
    }

    public function tidyListData($list)
    {
        if ($list instanceof \Illuminate\Support\Collection){
            $list = $list->toArray();
        }
        foreach ($list as &$item) {
            // $item['show_release_time'] = time_tran(strtotime($item['show_time'] ?? $item['created_at']), '发布');
            $item['show_release_time'] = '5分钟前发布';
            if ($item['app_id'] == 10012) {
                $item['type'] = $item['type'] . '';
            }
        }

        return $list;
    }

    /**
     * 分页获取列表数据
     *
     * @return array
     */
    public function getList(array $args): array
    {
        [$page, $limit] = $this->getPageValue();
        $count = $this->dao->count($args);
        $typeMap = Article::typeMap();
        $sourceMap = Article::sourceMap();
        $list = [];
        if ($count > 0) {
            $list = $this->dao->getAll($args, ['*'], ['sort' => 'desc', 'id' => 'desc'], ['cate'], $limit, $page);
            $apps = SystemApp::query()->pluck('name', 'id');

            foreach ($list as &$item) {
                $item['type_name'] = $typeMap[$item['type']] ?? '';
                $item['source_name'] = $sourceMap[$item['source']] ?? '';
                $item['cate_name'] = $item['cate']['title'] ?? '';
                $item['app_name'] = $apps[$item['app_id']] ?? '';
                $item['preview_url'] = url(sprintf('/article/%s', $item['id']));
            }
        }

        return compact('list', 'count');
    }

    /**
     * 分页获取列表数据
     *
     * @param $appId
     * @param $column
     *
     * @return array
     */
    public function getAllGroupCate($appId, $column): array
    {
        $list = Article::query()->with(['cate'])
            ->where('app_id', $appId)
            ->where("column", $column)
            ->orderBy("sort", "desc")
            ->orderBy("id", "desc")
            ->get()
            ->groupBy('cate_id')
            ->values();

        return compact('list');
    }

    /**
     * 新增编辑文章
     *
     * @param array $data
     *
     * @return mixed
     * @throws \Throwable
     */
    public function save(array $data)
    {
        $copyFrom = (int) ($data['copy_from'] ?? 0);
        unset($data['copy_from']);

        return \DB::transaction(function () use ($data, $copyFrom) {
            $isNew = empty($data['id']);
            $data['column'] = $this->resolveColumn($data);

            if (!empty($data['id'])) {
                $info = $this->dao->newQuery()->findOrFail($data['id']);
                $info->fill($data);

                $info->save();
            } else {
                $info = $this->dao->save($data);
            }

            // 内容ID
            $nid = $info['id'];

            switch ($data['type']) {
                case Article::TypeArticle:
                case Article::TypeSingleCourse:
                    ArticleContent::query()->where('nid', $nid)->updateOrCreate(['nid' => $nid], ['content' => $data['content']]);
                    break;
                case Article::TypeAIDialogue:
                    ArticleAiDialogue::query()->where('nid', $nid)->updateOrCreate([
                        'nid' => $nid,
                        'prompt' => $data['prompt'],
                        'greeting' => $data['greeting'],
                        'params' => array_filter($data['params']),
                    ]);
                    break;
                case Article::TypeAICreation:
                    ArticleAiCreation::query()->where('nid', $nid)->updateOrCreate([
                        'nid' => $nid,
                        'prompt' => $data['prompt'],
                        'copy_writing' => $data['copy_writing'],
                        // 'params' => array_filter($data['params2'], function ($item) {
                        //     if (!empty($item['title']) && !empty($item['value'])) {
                        //         return $item;
                        //     }
                        // }),
                        'params' => $data['params2'],
                        'is_return_limit' => $data['is_return_limit'],
                        'return_limit_values' => $data['return_limit_values'],
                    ]);
                case Article::TypeCollectionType: // 合集课程
                    ArticleContent::query()->where('nid', $nid)->updateOrCreate(['nid' => $nid], ['content' => $data['content']]);
                    // AI生成没有 break，会落到这里；课节只在真正的合集课程上处理
                    if ((int) $data['type'] === Article::TypeCollectionType && $isNew && $copyFrom > 0) {
                        $this->copyArticleCourses($copyFrom, (int) $nid);
                        break;
                    }
                    // 判断是B站数据来源，生成课节数据
                    if ($data['source'] == 'bilibili' && !empty($data['code'])) {
                        $this->articleCourseService()->generateBilibiliCourse($nid, $data['code']);
                    }
                    break;
            }

            return $info;
        });
    }

    /**
     * 栏目只取当前应用下所选分类，不沿用原文栏目。
     */
    private function resolveColumn(array $data): string
    {
        $category = ContentCategory::query()
            ->where('id', (int) ($data['cate_id'] ?? 0))
            ->where('app_id', (int) ($data['app_id'] ?? 0))
            ->first(['column']);

        if (!$category) {
            throw new AdminException('请选择文章分类');
        }

        return (string) $category->column;
    }

    /**
     * 复制合集时，把原文章下的课节挂到新文章上。
     */
    private function copyArticleCourses(int $fromNid, int $toNid): void
    {
        if ($fromNid <= 0 || $toNid <= 0 || $fromNid === $toNid) {
            return;
        }

        $courses = ArticleCourse::query()
            ->where('nid', $fromNid)
            ->orderBy('lesson_number')
            ->orderBy('id')
            ->get();

        if ($courses->isEmpty()) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($courses as $course) {
            $rows[] = [
                'nid' => $toNid,
                'lesson_number' => $course->lesson_number,
                'title' => $course->title,
                'image' => $course->image,
                'url' => $course->url,
                'duration' => $course->duration,
                'source' => $course->source,
                'code' => $course->code,
                'is_enable' => $course->is_enable,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ArticleCourse::query()->insert($rows);
    }

    /**
     * 获取详情
     */
    public function detail(int $id)
    {
        $info = $this->dao->newQuery()->findOrFail($id);
        $info['images'] = $info['images'] ?? [];
        switch ($info['type']) {
            case Article::TypeArticle:
            case Article::TypeSingleCourse:
                $info['content'];
                break;
            case Article::TypeAIDialogue:
                $info['ai_dialogue'] = $info->ai_dialogue;
                break;
            case Article::TypeAICreation:
                $info['ai_creation'] = $info->ai_creation;
                $info['ai_creation']['return_limit_arr'] = explode(';', str_replace('；', ';', $info['ai_creation']['return_limit_values']));
                break;
            //            case Article::TypeAIPicture:
            //                break;
            case Article::TypeCollectionType:
                $info['content'];
                $info['course_list'] = ArticleCourse::query()->where('nid', $info['id'])->orderBy('lesson_number')->orderBy('id')->get();
                break;
        }

        return $info;
    }

    /**
     * 生成文章
     *
     * @throws AdminException
     */
    public function generate(array $data)
    {
        if (!empty($data['article_id'])) {
            $article = $this->dao->newQuery()->findOrFail($data['article_id']);
        } else {
            if (empty($data['app_id'])) {
                throw new AdminException('创建内容，请选择所属应用');
            }
            if (empty($data['cate_id'])) {
                throw new AdminException('创建内容，请选择内容分类');
            }

            $article = new Article;
            $article->app_id = $data['app_id'];
            $article->cate_id = $data['cate_id'];
            $article->code = $data['code'];
        }

        switch ($data['source']) {
            case 'bilibili':
                $content = Bilibili::getBilibiliVideoInfo($data['code']);
                if (empty($content)) {
                    throw new AdminException('获取数据失败，请确认BV号是否正确');
                }

                // 创建新文章
                if (empty($data['article_id'])) {
                    $article->title = $content['title'];
                    $article->source = 'bilibili';
                    $article->image = $content['pic'];
                    $article->keyword = $content['tname'] ?? '';
                    $article->collections = $content['videos'] ?? 0;
                    $article->duration = $content['duration'] ?? 0;
                    $article->code = $data['code'] ?? '';
                    // $article->view = $content['stat']['view'] ?? 0;

                    $article->save();

                    if ($data['type'] == 'collections') {
                        // 合集课程
                        $article->type = Article::TypeCollectionType;

                        $course = [];
                        foreach ($content['pages'] as $item) {
                            $course[] = [
                                'nid' => $article->id,
                                'lesson_number' => $item['page'],
                                'title' => $item['part'],
                                'source' => 'bilibili',
                                'image' => $content['pic'],
                                'code' => $item['cid'] ?? '',
                                'duration' => $item['duration'] ?? 0,
                            ];
                        }

                        ArticleCourse::query()->insert($course);
                    } else {
                        // 单课程
                        $article->type = Article::TypeSingleCourse;
                    }
                    $article->save();
                } else {
                    // 原有内容添加课节信息
                    if ($article->type == Article::TypeCollectionType) {
                        $course = [];
                        $courseCount = ArticleCourse::query()->where('nid', $article->id)->count();

                        foreach ($content['pages'] as $item) {
                            $course[] = [
                                'nid' => $article->id,
                                'lesson_number' => $courseCount + $item['page'],
                                'title' => $item['part'],
                                'source' => 'bilibili',
                                'image' => $content['pic'],
                                'code' => $item['cid'] ?? '',
                                'duration' => $item['duration'] ?? 0,
                            ];
                        }

                        ArticleCourse::query()->insert($course);

                        $article->collections += $content['videos'];
                        $article->duration += $content['duration'];
                    } else {
                        $article->collections = 1;
                        $article->duration = $content['duration'];
                    }

                    if (($article->code === '' || $article->code === null) && !empty($data['code'])) {
                        $article->code = $data['code'];
                    }

                    $article->save();
                }

                break;
            default:
                throw new AdminException('未定义的数据来源类型');
        }
    }
}
