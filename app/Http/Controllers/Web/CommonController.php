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
        $realIp = trim(strtok((string) $request->header('X-Real-IP'), ',') ?: '');
        $geo = $this->ipGeo($ip);
        $realGeo = $this->ipGeo($realIp);

        return response()->json([
            'business_ip' => $ip,
            'business_ip_source' => $realIp !== '' && $ip === $realIp ? 'X-Real-IP' : 'REMOTE_ADDR',
            'ip' => $ip,
            'remote_addr' => $request->attributes->get('original_remote_addr', $request->server('REMOTE_ADDR')),
            'x_forwarded_for' => $request->header('X-Forwarded-For'),
            'x_real_ip' => $realIp,
            'region' => $geo['region'],
            'ip2region' => $geo['ip2region'],
            'x_real_ip_region' => $realGeo['region'],
            'x_real_ip2region' => $realGeo['ip2region'],
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function ipGeo(string $ip): array
    {
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return ['region' => '', 'ip2region' => []];
        }

        return [
            'region' => ip2region($ip),
            'ip2region' => (new \Ip2Region())->memorySearch($ip),
        ];
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
