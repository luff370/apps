<?php

namespace App\Http\Controllers\Web;

use App\Models\Article;
use App\Models\AppAgreement;
use App\Models\ArticleContent;
use App\Http\Controllers\Controller;
use App\Support\Services\AgreementUrlAliasService;
use Illuminate\Http\Request;

class CommonController extends Controller
{
    public function appAgreementByAlias($alias, $platform, AgreementUrlAliasService $aliasService)
    {
        $resolved = $aliasService->resolve((string) $alias);
        if (!$resolved) {
            return abort(404);
        }

        return $this->appAgreement($resolved['type'], $resolved['app_id'], $platform);
    }

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

    public function ip(Request $request)
    {
        $ip = (string) $request->ip();
        $geo = $ip !== '' ? (new \Ip2Region())->memorySearch($ip) : [];

        return response()->json([
            'ip' => $ip,
            'remote_addr' => $request->server('REMOTE_ADDR'),
            'x_forwarded_for' => $request->header('X-Forwarded-For'),
            'x_real_ip' => $request->header('X-Real-IP'),
            'region' => $ip !== '' ? ip2region($ip) : '',
            'ip2region' => $geo,
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
