<?php

namespace App\Http\Controllers\Api;

use App\Models\Favorite;
use Illuminate\Http\Request;
use App\Services\Cms\ContentService;

class FavoritesController extends Controller
{
    protected $contentService;

    public function __construct(ContentService $contentService)
    {
        $this->contentService = $contentService;
    }

    public function list(Request $request)
    {
        $args = $request->all(['cate_id']);
        $args['app_id'] = $this->getAppId();
        $args['ids'] = Favorite::query()->where('user_id', authUserId())->pluck('nid')->toArray();
        $data = $this->contentService->getAllByPage($args, ['id', 'title', 'sub_title', 'image', 'score', 'label', 'views'], ['sort' => 'desc', 'id' => 'desc']);

        return $this->success($data);
    }

    public function collect(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type', 1);
        Favorite::query()->updateOrCreate(['user_id' => authUserId(), 'nid' => $id, 'type' => $type]);

        return $this->success();
    }

    public function cancel(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type', 1);
        Favorite::query()->where('user_id', authUserId())->where('nid', $id)->where('type', $type)->delete();

        return $this->success();
    }
}
