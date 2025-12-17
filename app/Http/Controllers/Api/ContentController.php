<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Services\Cms\ContentService;
use App\Services\Cms\CategoryService;

class ContentController extends Controller
{
    protected $categoryService;

    protected $contentService;

    public function __construct(CategoryService $categoryService, ContentService $contentService)
    {
        $this->categoryService = $categoryService;
        $this->contentService = $contentService;
    }

    public function cate(Request $request)
    {
        $args = $request->all();
        $args['app_id'] = $this->getAppId();
        $data = $this->categoryService->getAll($args, ['id', 'title', 'image'], ['sort' => 'desc', 'id' => 'desc']);

        return $this->success($data);
    }

    public function list(Request $request)
    {
        $args = $request->all();
        $args['app_id'] = $this->getAppId();
        $data = $this->contentService->getAllByPage($args, ['*'], ['sort' => 'desc', 'show_time' => 'desc', 'id' => 'desc'], ['cate']);

        return $this->success($data);
    }

    public function listByCate(Request $request)
    {
        $appId = $this->getAppId();
        $column = $request->get("column");
        $data = $this->contentService->getAllGroupCate($appId,$column);

        return $this->success($data);
    }

    public function detail(Request $request)
    {
        $id = $request->get('id');
        $data = $this->contentService->detail($id);
        $data['show_release_time'] = time_tran(strtotime($data['show_time'] ?? $data['created_at']), '发布');
        $data['show_release_time'] = '5分钟前发布';

        return $this->success($data);
    }

    public function hotWords()
    {
        $words = [
            '行为准则',
            '安全教育',
            'MAS',
            '防腐',
            '反诈安全',
            '危化品',
            '交通安全',
            '产品安全',
        ];

        return $this->success($words);
    }
}
