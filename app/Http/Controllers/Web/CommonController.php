<?php

namespace App\Http\Controllers\Web;

use App\Models\Article;
use App\Models\AppAgreement;
use App\Models\ArticleContent;
use App\Http\Controllers\Controller;

class CommonController extends Controller
{
    public function appAgreement($type, $appId, $platform)
    {
        $agreements = AppAgreement::query()
            ->where('app_id', $appId)
            ->where('type', $type)
            ->where('status', 1)
            ->get();
        if (empty($agreements->count())) {
            return abort(404);
        }

        $agreement = $agreements[0];
        foreach ($agreements as $item) {
            if ($platform == $item['platform']) {
                $agreement = $item;
                break;
            } elseif ('all' == $item['platform']) {
                $agreement = $item;
            }
        }

        return view('common.agreement', $agreement);
    }

    public function article($id)
    {
        $articleContent = ArticleContent::query()->find($id);

        if (empty($articleContent['content'])) {
            $articleUrl = Article::query()->where('id', $id)->value('url');
            if (empty($articleUrl)){
                return abort(404);
            }

            return redirect($articleUrl);
        }

        return view('common.agreement', $articleContent);
    }
}
