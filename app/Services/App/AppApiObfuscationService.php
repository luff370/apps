<?php

namespace App\Services\App;

use App\Dao\App\AppApiObfuscationAliasDao;
use App\Dao\App\AppApiObfuscationProfileDao;
use App\Dao\App\AppsDao;
use App\Dao\System\SystemApiInterfaceDao;
use App\Exceptions\AdminException;
use App\Services\Service;
use App\Support\Services\DeviceEnvHeaderAliasService;

class AppApiObfuscationService extends Service
{
    private const GATEWAY_SUFFIX_WORDS = [
        'atlas', 'bridge', 'center', 'cloud', 'field', 'flow', 'garden', 'harbor',
        'hub', 'lane', 'light', 'matrix', 'orbit', 'portal', 'river', 'stone',
        'stream', 'summit', 'tower', 'valley', 'wave', 'zone',
    ];

    public function __construct(AppApiObfuscationProfileDao $dao, private AppApiObfuscationAliasDao $aliasDao, private SystemApiInterfaceDao $interfaceDao, private AppsDao $appsDao) { $this->dao = $dao; }

    public function listByApps(array $w): array
    {
        $page=(int)request()->get('page',1); $limit=(int)request()->get('limit',15); $appsQuery=$this->appsDao->search(['keyword'=>$w['keyword']??'']);
        $count=$appsQuery->count(); $apps=$appsQuery->with('merchant')->orderByDesc('id')->offset(($page-1)*$limit)->limit($limit)->get()->toArray(); $profiles=[];
        foreach($this->dao->search()->whereIn('app_id',array_column($apps,'id'))->get()->toArray() as $profile){$profiles[(int)$profile['app_id']]=$profile;}
        $list=array_map(function($app)use($profiles){$profile=$profiles[(int)$app['id']]??[];$image=$profile['image_url']??[];$merchant=$app['merchant']??[];return ['app_id'=>(int)$app['id'],'app_name'=>(string)($app['name']??''),'package_name'=>(string)($profile['package_name']??$app['package_name']??''),'enabled'=>(int)($profile['enabled']??0),'allow_plaintext_request'=>(int)($profile['allow_plaintext_request']??1),'image_url_enabled'=>(int)($profile['image_url_enabled']??($image['enabled']??0)),'image_path_alias_enabled'=>(int)($profile['image_path_alias_enabled']??($image['path_alias_enabled']??0)),'image_domain'=>$this->profileImageDomain($profile,$merchant),'api_domain'=>$this->profileApiDomain($profile,$merchant),'merchant_image_domain'=>(string)($merchant['image_domain']??''),'merchant_api_domain'=>(string)($merchant['api_domain']??''),'profile_id'=>(int)($profile['id']??0)];},$apps);
        return ['list'=>$list,'count'=>$count];
    }

    /**
     * 协议访问链接使用的接口域名根地址。
     * 优先取该应用 API 混淆配置的接口域名；未配置时按商户接口域名，再按 api.{商户域名} 生成，并补全 https://。
     */
    public function effectiveApiDomainRoot(int $appId, string $packageName = ''): string
    {
        $app = $this->appWithMerchant($appId);
        $merchant = (array) ($app['merchant'] ?? []);
        if ($packageName === '') {
            $packageName = (string) ($app['package_name'] ?? '');
        }
        $profile = $this->findProfile($appId, $packageName);
        $domain = $this->profileApiDomain($profile ? $profile->toArray() : [], $merchant);
        if ($domain === '') {
            $domain = $this->generatedApiDomainFromMerchant($merchant);
        }

        return $this->exportApiDomain($domain);
    }

    public function getProfile(int $appId = 0, string $packageName = ''): array
    {
        $app = $this->appWithMerchant($appId);
        $merchant = $app ? (array)($app['merchant'] ?? []) : [];
        $p = $this->findProfile($appId, $packageName);
        if (!$p) { $r = array_merge(['id' => 0, 'app_id' => $appId, 'package_name' => $packageName, 'image_url_enabled' => 0, 'image_path_alias_enabled' => 0], config('api_obfuscation.profiles.default', [])); return $this->withDefaultImageRewrite($this->withDefaultKeyMaps($this->attachProfileDomains($r, $merchant))); }
        $r = $p->toArray(); $r['route_aliases'] = $this->buildRouteAliasesByProfile((int) $r['id']); return $this->withDefaultImageRewrite($this->withDefaultKeyMaps($this->attachProfileDomains($r, $merchant)));
    }

    public function saveProfile(array $d): array
    {
        $appId = intval($d['app_id'] ?? 0); $pkg = (string) ($d['package_name'] ?? ''); $p = $this->findProfile($appId, $pkg);
        $merchant = (array)(($this->appWithMerchant($appId)['merchant'] ?? []));
        $imageDomain = $this->persistDomain((string)($d['image_domain']??''), (string)($merchant['image_domain']??''));
        $apiDomain = $this->persistDomain((string)($d['api_domain']??''), (string)($merchant['api_domain']??''));
        $this->assertRequiredDomains([
            'api_domain' => trim((string)($d['api_domain'] ?? '')) ?: (string)($merchant['api_domain'] ?? ''),
            'image_domain' => trim((string)($d['image_domain'] ?? '')) ?: (string)($merchant['image_domain'] ?? ''),
            'image_url_enabled' => intval($d['image_url_enabled']??0),
        ], 'save');
        $shortMaps = $this->defaultShortMaps();
        $requestMap = $this->decodeMap($d['request_key_map']??[]);
        $responseMap = $this->decodeMap($d['response_key_map']??[]);
        if ($requestMap === []) $requestMap = $shortMaps['request_key_map'];
        if ($responseMap === []) $responseMap = $shortMaps['response_key_map'];
        $imageUrl = (array) config('api_obfuscation.profiles.default.image_url', []);
        $save = ['enabled'=>intval($d['enabled']??0),'encrypt_request'=>intval($d['encrypt_request']??0),'encrypt_response'=>intval($d['encrypt_response']??0),'allow_plaintext_request'=>intval($d['allow_plaintext_request']??1),'image_url_enabled'=>intval($d['image_url_enabled']??0),'image_path_alias_enabled'=>intval($d['image_path_alias_enabled']??0),'image_domain'=>$imageDomain,'api_domain'=>$apiDomain,'request_key_map'=>$requestMap,'response_key_map'=>$responseMap,'protocol'=>['encrypt_request'=>(bool)($d['encrypt_request']??0),'encrypt_response'=>(bool)($d['encrypt_response']??0),'allow_plaintext_request'=>(bool)($d['allow_plaintext_request']??1),'payload_field'=>(string)($d['payload_field']??'payload'),'sign_field'=>(string)($d['sign_field']??'sign'),'timestamp_field'=>(string)($d['timestamp_field']??'ts'),'nonce_field'=>(string)($d['nonce_field']??'nonce'),'version_field'=>(string)($d['version_field']??'ver')],'security'=>['timestamp_window_seconds'=>intval($d['timestamp_window_seconds']??300),'nonce_ttl_seconds'=>intval($d['nonce_ttl_seconds']??300)],'crypto'=>['cipher'=>(string)($d['cipher']??'AES-256-CBC'),'key'=>(string)($d['crypto_key']??''),'iv'=>(string)($d['crypto_iv']??''),'sign_key'=>(string)($d['crypto_sign_key']??'')],'image_url'=>['enabled'=>(bool)($d['image_url_enabled']??0),'path_alias_enabled'=>(bool)($d['image_path_alias_enabled']??0),'domain'=>$imageDomain,'path_prefixes'=>(array)($imageUrl['path_prefixes']??[])]];
        $p ? $this->dao->update($p['id'], $save) : $this->dao->save(array_merge(['app_id'=>$appId,'package_name'=>$pkg], $save));
        $p = $this->findProfile($appId, $pkg); if ($p) $this->refreshRouteAliases((int)$p['id']); return $this->getProfile($appId, $pkg);
    }

    public function aliases(array $w): array
    {
        $p = $this->findProfile((int)($w['app_id']??0), (string)($w['package_name']??'')); if (!$p) return ['list'=>[],'count'=>0];
        $q = $this->aliasDao->search(['profile_id'=>$p['id']])->with('apiInterface');
        $keyword = trim((string)($w['keyword'] ?? ''));
        if ($keyword !== '') {
            $q->where(function ($sub) use ($keyword) {
                $sub->where('alias', 'like', '%' . $keyword . '%')
                    ->orWhereHas('apiInterface', function ($api) use ($keyword) {
                        $api->where('name', 'like', '%' . $keyword . '%')
                            ->orWhere('module', 'like', '%' . $keyword . '%')
                            ->orWhere('path', 'like', '%' . $keyword . '%');
                    });
            });
        }
        $count = $q->count(); $page=(int)request()->get('page',1); $limit=(int)request()->get('limit',15);
        $list = $q->orderByDesc('id')->offset(($page-1)*$limit)->limit($limit)->get()->toArray();
        return ['list'=>array_map(fn($x)=>$this->formatAliasRow($x), $list),'count'=>$count];
    }

    public function saveAlias(array $d): void
    {
        $p = $this->ensureProfile((int)($d['app_id']??0), (string)($d['package_name']??''));
        // 新建或手动保存接口别名时，立即从公共 API 表复制一份参数快照。
        // 后续公共 API 参数如果被调整，已下发给客户端的旧别名仍按自己的快照预览/导出；
        // 管理员需要最新参数时，再点列表里的“同步”按钮覆盖这份快照。
        $interface=$this->interfaceDao->get(intval($d['interface_id']??0)); $snap=$this->originSnapshot($interface?$interface->toArray():[]);
        $s = [
            'profile_id' => (int) $p['id'],
            'interface_id' => intval($d['interface_id'] ?? 0),
            'alias' => (string) ($d['alias'] ?? ''),
            'request_origin_params' => $snap['request_origin_params'],
            'response_origin_params' => $snap['response_origin_params'],
            'request_key_map' => $this->decodeMap($d['request_key_map'] ?? []),
            'response_key_map' => $this->decodeMap($d['response_key_map'] ?? []),
            'is_enable' => intval($d['is_enable'] ?? 1),
            'remark' => (string) ($d['remark'] ?? ''),
        ];
        $id = intval($d['id']??0); if ($id>0) $this->aliasDao->update($id,$s); else { $old=$this->aliasDao->search(['profile_id'=>$s['profile_id'],'interface_id'=>$s['interface_id']])->first(); $old?$this->aliasDao->update($old['id'],$s):$this->aliasDao->save($s); }
        $this->refreshRouteAliases((int)$p['id']);
    }

    public function updateAliasParams(array $d): array
    {
        // 编辑弹窗只维护“原始字段 => 别名字段”的映射和别名基础信息。
        // origin 参数快照不在这里保存，避免用户编辑映射时误把公共 API 的原始参数覆盖掉；
        // 原始参数统一通过 syncAliasParams 从公共 API 重新同步。
        $row=$this->aliasDao->get((int)($d['id']??0)); if(!$row)return[];
        $save=['request_key_map'=>$this->decodeMap($d['request_key_map']??[]),'response_key_map'=>$this->decodeMap($d['response_key_map']??[])];
        if(array_key_exists('alias',$d))$save['alias']=(string)$d['alias'];
        if(array_key_exists('is_enable',$d))$save['is_enable']=intval($d['is_enable']);
        if(array_key_exists('remark',$d))$save['remark']=(string)$d['remark'];
        $this->aliasDao->update((int)$row['id'],$save); $this->refreshRouteAliases((int)$row['profile_id']);
        return $this->aliasDetail((int)$row['id']);
    }

    public function syncAliasParams(int $id): array
    {
        // 单独同步按钮使用：只刷新公共 API 参数快照，不改 alias、request_key_map、response_key_map 等客户端已使用的别名规则。
        // 这样公共 API 新增字段后，管理员可以先同步看到最新 origin，再按需生成或手工维护 alias。
        $row=$this->aliasDao->get($id,['*'],['apiInterface']); if(!$row||!$row->apiInterface)return[];
        $snap=$this->originSnapshot($row->apiInterface->toArray());
        $this->aliasDao->update($id,$snap);
        return $this->aliasDetail($id);
    }

    public function generateAliasParams(array $d): array
    {
        $row=$this->aliasDao->get((int)($d['id']??0),['*'],['apiInterface']); if(!$row)return[];
        $profile=$this->dao->get((int)$row['profile_id']); $profileArr=$profile?$profile->toArray():[];
        // 生成别名时优先用公共 API 最新参数，避免 origin 快照还是旧的外层 status/msg/data。
        $requestOrigin=$this->latestParamsForGeneration($row->toArray(),'request');
        $responseOrigin=$this->latestParamsForGeneration($row->toArray(),'response');
        return [
            'request_key_map'=>$this->stableParamsMap($requestOrigin,$profileArr,'request'),
            'response_key_map'=>$this->stableParamsMap($responseOrigin,$profileArr,'response'),
        ];
    }

    public function generateAllAliasParams(array $d): array
    {
        $p=$this->findProfile((int)($d['app_id']??0),(string)($d['package_name']??'')); if(!$p)return['updated'=>0];
        $updated=0;
        foreach($this->aliasDao->search(['profile_id'=>$p['id']])->orderBy('id')->get() as $row){
            $this->syncAliasParams((int)$row['id']);
            $maps=$this->generateAliasParams(['id'=>(int)$row['id']]);
            if($maps===[]) continue;
            $this->aliasDao->update((int)$row['id'],[
                'request_key_map'=>(array)($maps['request_key_map']??[]),
                'response_key_map'=>(array)($maps['response_key_map']??[]),
            ]);
            $updated++;
        }
        $this->refreshRouteAliases((int)$p['id']);
        return ['updated'=>$updated];
    }

    public function deleteAlias(int $id): void { $r=$this->aliasDao->get($id); if(!$r)return; $pid=(int)$r['profile_id']; $this->aliasDao->delete($id); $this->refreshRouteAliases($pid); }

    public function deleteAliasesByInterfaceId(int $interfaceId): void
    {
        if ($interfaceId <= 0) {
            return;
        }

        $profileIds = $this->aliasDao->search(['interface_id' => $interfaceId])
            ->pluck('profile_id')
            ->unique()
            ->filter()
            ->all();
        $this->aliasDao->search(['interface_id' => $interfaceId])->delete();
        foreach ($profileIds as $profileId) {
            $this->refreshRouteAliases((int) $profileId);
        }
    }

    public function generateAliases(array $d): array
    {
        // URL 别名不再使用 hash4/hex6/restful 等可选规则。
        // 统一由「应用ID + 包名 + METHOD + 原始 path」派生：同一应用同一原始 URL 重复生成保持不变，
        // 不同应用即使 path 相同，也会因为应用身份不同而生成不同别名。
        // 批量生成时同时保存公共 API 的 request/response 参数快照，供前端导出的 request.origin_params、
        // response.origin 使用；旧数据没有快照时会在 paramsFromAliasRow 中回退读取公共 API，保证旧别名不受影响。
        $p=$this->ensureProfile((int)($d['app_id']??0),(string)($d['package_name']??'')); $overwrite=intval($d['overwrite']??0)===1; $used=[]; $updated=0;
        if($overwrite)$this->aliasDao->search(['profile_id'=>$p['id']])->update(['is_enable'=>0]);
        foreach($this->interfaceDao->search(['is_enable'=>1])->orderBy('path')->orderBy('method')->orderBy('id')->get() as $i){ $old=$this->aliasDao->search(['profile_id'=>$p['id'],'interface_id'=>$i['id']])->first(); $identity=$this->aliasIdentity($p->toArray(),strtoupper((string)$i['method']),(string)$i['path']); if(!$overwrite&&$old&&!empty($old['alias'])){$used[$old['alias']]=$identity;continue;} $alias=$this->makeAlias($p->toArray(),strtoupper((string)$i['method']),(string)$i['path'],$used); $save=array_merge(['profile_id'=>(int)$p['id'],'interface_id'=>(int)$i['id'],'alias'=>$alias,'is_enable'=>1],$this->originSnapshot($i->toArray()),$this->generateMapsForInterface($i->toArray(),(string)($d['map_rule']??'short'),$p->toArray())); $old?$this->aliasDao->update($old['id'],$save):$this->aliasDao->save($save); $updated++; }
        $this->refreshRouteAliases((int)$p['id']); return ['updated'=>$updated];
    }

    public function generateDefaultProfileFields(array $d): array
    {
        $imageUrl = (array) config('api_obfuscation.profiles.default.image_url', []);
        $img = (array) ($imageUrl['fields'] ?? []);
        $pre = (array) ($imageUrl['path_prefixes'] ?? []);
        $shortMaps = $this->defaultShortMaps();
        $rule=(string)($d['map_rule']??'short');
        if($rule==='stable'){
            $profile=['app_id'=>(int)($d['app_id']??0),'package_name'=>(string)($d['package_name']??'')];
            return [
                'request_key_map'=>$this->stableParamsMap([['key'=>'page'],['key'=>'limit'],['key'=>'keywords'],['key'=>'uuid'],['key'=>'token']],$profile,'request'),
                'response_key_map'=>$this->stableParamsMap([['key'=>'status'],['key'=>'msg'],['key'=>'data']],$profile,'response'),
                'image_fields'=>$img,
                'image_prefixes'=>$pre,
            ];
        }
        if($rule==='biz') return ['request_key_map'=>['page'=>'cursor','limit'=>'batch','keywords'=>'query','uuid'=>'deviceCode','token'=>'sessionCode'],'response_key_map'=>['status'=>'code','msg'=>'message','data'=>'result'],'image_fields'=>$img,'image_prefixes'=>$pre];
        return ['request_key_map'=>$shortMaps['request_key_map'],'response_key_map'=>$shortMaps['response_key_map'],'image_fields'=>$img,'image_prefixes'=>$pre];
    }

    public function previewAlias(int $id): array
    {
        $r=$this->aliasDao->get($id,['*'],['apiInterface']); if(!$r)return[]; $raw=$r->toArray(); $r=$this->formatAliasRow($raw); $req=$this->example($this->paramsFromAliasRow($raw,'request')); $res=$this->example($this->paramsFromAliasRow($raw,'response'));
        $profile=$this->dao->get((int)($raw['profile_id']??0));
        $prefix = $this->gatewayPrefixForProfile($profile ? $profile->toArray() : []);
        return ['request'=>['origin_path'=>'/api/'.ltrim((string)$r['path'],'/'),'alias_path'=>$prefix.(string)$r['alias'],'origin_params'=>$req,'alias_params'=>$this->applyMap($req,(array)($r['request_key_map']??[]))],'response'=>['origin'=>$res,'alias'=>$this->applyMap($res,(array)($r['response_key_map']??[]))]];
    }

    public function exportAliases(array $d): array
    {
        $appId = (int) ($d['app_id'] ?? 0);
        $pkg = (string) ($d['package_name'] ?? '');
        $p = $this->findProfile($appId, $pkg);
        $profile = $p ? $p->toArray() : ['app_id' => $appId, 'package_name' => $pkg, 'image_url_enabled' => 0];
        $merchant = $this->merchantForProfile($profile);
        $this->assertRequiredDomains([
            'api_domain' => $this->profileApiDomain($profile, $merchant),
            'image_domain' => $this->profileImageDomain($profile, $merchant),
            'image_url_enabled' => (int) ($profile['image_url_enabled'] ?? 0),
        ], 'export');
        if (!$p) {
            return [];
        }
        $rows = $this->aliasDao->search(['profile_id' => $p['id'], 'is_enable' => 1])->with('apiInterface')->get()->toArray();
        return ['app_id'=>(int)$p['app_id'],'package_name'=>(string)$p['package_name'],'api_domain'=>$this->exportApiDomain($this->profileApiDomain($profile,$merchant)),'gateway_prefix'=>$this->gatewayPrefixForProfile($profile),'gateway_prefixes'=>$this->gatewayPrefixes($profile),'device_env_header'=>$this->deviceEnvHeaderAlias($profile),'items'=>array_map(fn($x)=>$this->formatExportAliasItem($x),$rows)];
    }

    public function buildRouteAliasesByProfile(int $pid): array { $a=[]; foreach($this->aliasDao->search(['profile_id'=>$pid,'is_enable'=>1])->with('apiInterface')->get() as $r) if($r->apiInterface&&intval($r->apiInterface['is_enable']??0)===1&&$r['alias']) $a[$r['alias']]=['path'=>ltrim((string)$r->apiInterface['path'],'/'),'method'=>strtoupper((string)$r->apiInterface['method'])]; return $a; }
    public function exportProfile(array $d): array
    {
        $p=$this->findProfile((int)($d['app_id']??0),(string)($d['package_name']??'')); if(!$p)return[];
        $profile=$p->toArray(); $profile['route_aliases']=$this->buildRouteAliasesByProfile((int)$profile['id']);
        $merchant=$this->merchantForProfile($profile); $profile=$this->withDefaultKeyMaps($profile); $profile['image_domain']=$this->profileImageDomain($profile,$merchant); $profile['api_domain']=$this->profileApiDomain($profile,$merchant);
        $this->assertRequiredDomains($profile, 'export');
        $imageUrl = (array) ($profile['image_url'] ?? []);
        unset($imageUrl['domain']);
        return ['app_id'=>(int)$profile['app_id'],'package_name'=>(string)$profile['package_name'],'api_domain'=>$this->exportApiDomain($profile['api_domain']),'gateway_prefix'=>$this->gatewayPrefixForProfile($profile),'gateway_prefixes'=>$this->gatewayPrefixes($profile),'device_env_header'=>$this->deviceEnvHeaderAlias($profile),'enabled'=>(bool)($profile['enabled']??0),'route_aliases'=>$profile['route_aliases']??[],'request_key_map'=>$profile['request_key_map']??[],'response_key_map'=>$profile['response_key_map']??[],'response_data_key_map'=>$profile['response_data_key_map']??[],'protocol'=>$profile['protocol']??[],'security'=>$profile['security']??[],'crypto'=>$profile['crypto']??[],'image_url'=>$imageUrl];
    }
    private function deviceEnvHeaderAlias(array $profile): string
    {
        return (new DeviceEnvHeaderAliasService())->make((int) ($profile['app_id'] ?? 0), (string) ($profile['package_name'] ?? ''));
    }
    private function findProfile(int $appId,string $pkg){return $this->dao->search(['app_id'=>$appId,'package_name'=>$pkg])->first();}
    private function appWithMerchant(int $appId):array{return $appId>0?($this->appsDao->newQuery()->with('merchant')->find($appId)?->toArray()??[]):[];}
    private function merchantForProfile(array $profile):array{$app=$this->appWithMerchant((int)($profile['app_id']??0));return (array)($app['merchant']??[]);}
    private function defaultShortMaps(): array
    {
        return [
            'request_key_map' => (array) config('api_obfuscation.default_short_maps.request_key_map', ['page'=>'pg','limit'=>'sz','keywords'=>'kw','uuid'=>'ud','token'=>'tk']),
            'response_key_map' => (array) config('api_obfuscation.default_short_maps.response_key_map', ['status'=>'s','msg'=>'m','data'=>'d']),
        ];
    }
    private function withDefaultKeyMaps(array $profile): array
    {
        $defaults = $this->defaultShortMaps();
        if ($this->decodeMap($profile['request_key_map'] ?? []) === []) {
            $profile['request_key_map'] = $defaults['request_key_map'];
        }
        if ($this->decodeMap($profile['response_key_map'] ?? []) === []) {
            $profile['response_key_map'] = $defaults['response_key_map'];
        }
        $imageUrl = (array) ($profile['image_url'] ?? []);
        unset($imageUrl['fields']);
        $imageDefaults = (array) config('api_obfuscation.profiles.default.image_url', []);
        $imageUrl['path_prefixes'] = (array) ($imageDefaults['path_prefixes'] ?? []);
        $profile['image_url'] = $imageUrl;

        return $profile;
    }
    private function attachProfileDomains(array $profile, array $merchant = []): array
    {
        $profile['merchant_image_domain'] = (string) ($merchant['image_domain'] ?? '');
        $profile['merchant_api_domain'] = (string) ($merchant['api_domain'] ?? '');
        $profile['image_domain'] = $this->profileImageDomain($profile, $merchant);
        $profile['api_domain'] = $this->profileApiDomain($profile, $merchant);

        return $profile;
    }
    private function withDefaultImageRewrite(array $profile): array
    {
        if ((int) ($profile['id'] ?? 0) !== 0 || trim((string) ($profile['image_domain'] ?? '')) === '') {
            return $profile;
        }
        $profile['image_url_enabled'] = 1;
        $image = (array) ($profile['image_url'] ?? []);
        $image['enabled'] = true;
        $profile['image_url'] = $image;

        return $profile;
    }
    private function assertRequiredDomains(array $profile, string $scene = 'save'): void
    {
        $suffix = $scene === 'export' ? '后再导出配置' : '';
        if (trim((string) ($profile['api_domain'] ?? '')) === '') {
            throw new AdminException('请填写接口域名' . $suffix);
        }
        $imageEnabled = (int) ($profile['image_url_enabled'] ?? 0) === 1 || !empty($profile['image_url']['enabled']);
        if ($imageEnabled && trim((string) ($profile['image_domain'] ?? '')) === '') {
            throw new AdminException('开启图片域名替换时请填写图片域名' . $suffix);
        }
    }
    private function exportApiDomain(string $domain): string
    {
        $domain = trim($domain);
        if ($domain === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $domain) || str_starts_with($domain, '//')) {
            return $domain;
        }

        return 'https://' . $domain;
    }
    private function persistDomain(string $submitted, string $merchantDefault): string
    {
        $submitted = trim($submitted);
        $merchantDefault = trim($merchantDefault);

        return ($submitted === '' || $submitted === $merchantDefault) ? '' : $submitted;
    }
    private function profileImageDomain(array $profile,array $merchant=[]):string{$image=(array)($profile['image_url']??[]);$domain=(string)($profile['image_domain']??($image['domain']??''));return $domain!==''?$domain:(string)($merchant['image_domain']??'');}
    private function profileApiDomain(array $profile,array $merchant=[]):string{$domain=trim((string)($profile['api_domain']??''));return $domain!==''?$domain:trim((string)($merchant['api_domain']??''));}
    private function generatedApiDomainFromMerchant(array $merchant): string
    {
        $host = trim((string) ($merchant['domain'] ?? ''));
        $host = preg_replace('#^https?://#i', '', $host) ?? $host;
        $host = preg_replace('#/.*$#', '', $host) ?? $host;
        $host = rtrim((string) $host, '/');
        if ($host === '' || $host === 'api' || str_starts_with($host, 'api.')) {
            return $host;
        }

        return 'api.' . $host;
    }
    private function ensureProfile(int $appId,string $pkg){return $this->findProfile($appId,$pkg)?:$this->dao->save(['app_id'=>$appId,'package_name'=>$pkg,'enabled'=>0,'protocol'=>config('api_obfuscation.profiles.default.protocol',[]),'security'=>config('api_obfuscation.profiles.default.security',[]),'crypto'=>config('api_obfuscation.profiles.default.crypto',[]),'image_url'=>config('api_obfuscation.profiles.default.image_url',[]),'route_aliases'=>[]]);}
    private function refreshRouteAliases(int $pid):void{$this->dao->update($pid,['route_aliases'=>$this->buildRouteAliasesByProfile($pid)]);}
    private function formatAliasRow(array $r):array{$i=$r['api_interface']??[];$r['response_key_map']=$this->effectiveResponseAliasMap($r);return array_merge($r,['interface_name'=>$i['name']??'','module'=>$i['module']??'','path'=>$i['path']??'','method'=>$i['method']??'','request_params'=>$i['request_params']??[],'response_params'=>$i['response_params']??[]]);}
    private function aliasDetail(int $id):array{$row=$this->aliasDao->get($id,['*'],['apiInterface']);return$row?$this->formatAliasRow($row->toArray()):[];}
    // 将公共 API 的参数定义保存到接口别名行，形成 origin 快照。
    // 字段名保持为表字段 request_origin_params/response_origin_params，导出时再转换成客户端文档需要的
    // request.origin_params 和 response.origin 结构，不再额外带一份同内容的 snapshot 字段。
    private function originSnapshot(array $interface):array{return ['request_origin_params'=>(array)($interface['request_params']??[]),'response_origin_params'=>(array)($interface['response_params']??[])];}
    // 优先使用别名行自己的 origin 快照；旧版数据没有快照时，回退到关联公共 API 的 request_params/response_params。
    // 这保证“只加新字段、未重新生成别名”的应用仍能预览和导出，不会破坏既有别名。
    private function paramsFromAliasRow(array $row,string $type):array{$field=$type==='request'?'request_origin_params':'response_origin_params';$fallback=$type==='request'?'request_params':'response_params';return (array)($row[$field]??$row['api_interface'][$fallback]??$row[$fallback]??[]);}
    private function latestParamsForGeneration(array $row,string $type):array{$fallback=$type==='request'?'request_params':'response_params';$live=(array)($row['api_interface'][$fallback]??[]);return $live!==[]?$live:$this->paramsFromAliasRow($row,$type);}
    // 接口别名的响应映射做兼容兜底：
    // - 新数据只写 response_key_map；
    // - 旧数据如果只存了 response_data_key_map，也临时当作响应映射使用；
    // - 这样旧别名不需要重生成也能继续预览、导出和网关映射。
    private function effectiveResponseAliasMap(array $row):array{return $this->decodeMap($row['response_key_map']??[])?:$this->decodeMap($row['response_data_key_map']??[]);}
    // 导出时带上示例参数和映射表。origin / alias 已经是快照转换后的结果，不再重复输出 snapshot 字段。
    private function formatExportAliasItem(array $row):array{$r=$this->formatAliasRow($row);$reqOrigin=$this->paramsFromAliasRow($row,'request');$resOrigin=$this->paramsFromAliasRow($row,'response');$req=$this->example($reqOrigin);$res=$this->example($resOrigin);$requestMap=(array)($r['request_key_map']??[]);$responseMap=(array)($r['response_key_map']??[]);return ['alias'=>(string)($r['alias']??''),'path'=>(string)($r['path']??''),'method'=>(string)($r['method']??''),'request'=>['origin_params'=>$req,'alias_params'=>$this->applyMap($req,$requestMap),'request_key_map'=>$requestMap],'response'=>['origin'=>$res,'alias'=>$this->applyMap($res,$responseMap),'response_key_map'=>$responseMap]];}
    private function generateMapsForInterface(array $i,string $rule,array $profile=[]):array{return ['request_key_map'=>$this->paramsMap((array)($i['request_params']??[]),$rule,$profile,'request'),'response_key_map'=>$this->paramsMap((array)($i['response_params']??[]),$rule,$profile,'response')];}
    // 参数别名按应用身份 + 原字段名稳定生成，不依赖 JSON 字段顺序。
    // 同一应用同一原始字段反复点击“生成别名”结果一致；前面插入新字段也不会改已有字段的别名。
    private function stableParamsMap(array $params,array $profile,string $scope):array{$map=[];$used=[];foreach($this->paramKeysForScope($params,$profile,$scope) as $key){$alias=$this->stableParamAlias($profile,$scope,$key,$used);$map[$key]=$alias;}return$map;}
    private function stableParamAlias(array $profile,string $scope,string $key,array &$used):string{$identity=(string)($profile['app_id']??'').'|'.(string)($profile['package_name']??'').'|'.$scope.'|'.$key;$try=0;do{$hash=hash('sha256',$identity.'|'.$try);$alias='p'.substr($hash,0,5);$try++;}while(isset($used[$alias])&&$try<20);$used[$alias]=true;return$alias;}
    private function paramsMap(array $ps,string $rule,array $profile=[],string $scope='request'):array{$m=[];$n=0;foreach($this->paramKeysForScope($ps,$profile,$scope) as $k){$n++;$m[$k]=$rule==='mix'?$this->alphaNumFromHash($k.$n,5):(($rule==='biz'?'field':substr(preg_replace('/[^a-z0-9]/i','',$k),0,1)).$n);}return$m;}
    private function example(array $ps):array{$r=[];$hasDefinition=false;foreach($ps as $p){if(!is_array($p)||!$this->looksLikeParamDefinition($p))continue;$k=(string)($p['key']??$p['name']??'');if($k!==''){$hasDefinition=true;$r[$k]=$p['example']??'';}}return$hasDefinition?$r:$ps;}
    // 公共 API 参数既可能是标准定义：[{key:"page", type:"int"}]，
    // 也可能直接保存了真实 JSON 示例：[{"type":2,"channels":[{"ad_id":"..."}]}]。
    // 生成别名时统一抽取字段名：定义数组优先取 key/name，并继续读取 items/properties 等嵌套定义；
    // 真实 JSON 则递归读取对象字段，忽略 0/1 这类数组下标。
    private function paramKeys(array $params):array{$keys=[];$walk=function($value)use(&$walk,&$keys){if(!is_array($value))return;if($this->looksLikeParamDefinition($value)){$schemaKey=(string)($value['key']??$value['name']??'');if($schemaKey!==''){$keys[$schemaKey]=true;foreach(['items','properties','children','fields'] as $nestedField){if(isset($value[$nestedField])&&is_array($value[$nestedField]))$walk($value[$nestedField]);}return;}}foreach($value as $k=>$v){if(!is_int($k)&&$k!=='')$keys[(string)$k]=true;if(is_array($v))$walk($v);}};$walk($params);return array_keys($keys);}
    private function looksLikeParamDefinition(array $value):bool
    {
        if ((string) ($value['key'] ?? '') !== '') {
            return true;
        }
        $schemaOnly = ['name', 'type', 'example', 'items', 'properties', 'children', 'fields', 'required', 'remark', 'desc', 'description', 'title', 'default', 'rule'];
        if ($value === []) {
            return false;
        }
        foreach (array_keys($value) as $k) {
            if (!in_array((string) $k, $schemaOnly, true)) {
                return false;
            }
        }

        return isset($value['name']) || isset($value['items']) || isset($value['properties']) || isset($value['children']) || isset($value['fields']);
    }
    // 接口级响应别名只处理业务字段；status/msg/data 等公共外层字段由 profile.response_key_map 统一映射。
    private function paramKeysForScope(array $params,array $profile,string $scope):array{return $scope==='response'?$this->responseParamKeysForAlias($params,$profile):$this->paramKeys($params);}
    private function responseParamKeysForAlias(array $params,array $profile):array{$keys=$this->paramKeys($params);$outer=$this->outerResponseKeys($profile,$params);return array_values(array_filter($keys,fn($key)=>!in_array($key,$outer,true)));}
    private function outerResponseKeys(array $profile,array $params):array{$outer=array_keys(array_merge($this->decodeMap($profile['response_key_map']??[]),['status','msg','message','code','data','result','error']));$example=$this->example($params);if($this->looksLikeResponseEnvelope($example)){$outer=array_merge($outer,array_keys($example));}return array_values(array_unique(array_filter($outer,fn($key)=>$key!=='')));}
    private function looksLikeResponseEnvelope(array $example):bool{if($example===[]||array_is_list($example))return false;$keys=array_keys($example);$envelopeKeys=['status','msg','message','code','data','result','error'];if(count(array_intersect($keys,$envelopeKeys))===0)return false;foreach($example as $value){if(is_array($value)&&(array_is_list($value)||$this->hasAssociativeFields($value)))return true;}return false;}
    private function hasAssociativeFields(array $value):bool{foreach(array_keys($value) as $key){if(!is_int($key))return true;}return false;}
    private function applyMap(array $d,array $m):array{$r=[];foreach($d as $k=>$v){$mappedKey=is_int($k)?$k:($m[$k]??$k);$r[$mappedKey]=is_array($v)?$this->applyMap($v,$m):$v;}return$r;}
    private function decodeJson(string $j):array{$d=json_decode(trim($j),true);return is_array($d)?$d:[];}
    private function gatewayPrefixes(array $profile):array{$prefixes=config('api_obfuscation.gateway_prefixes',['gateway']);return array_map(fn($v)=>$this->formatGatewayPrefix((string)$v,$profile),array_values(array_filter($prefixes)));}
    private function gatewayPrefixForProfile(array $profile):string{$prefixes=array_values(array_filter(config('api_obfuscation.gateway_prefixes',['gateway'])));if(empty($prefixes))$prefixes=['gateway'];$identity=$this->gatewayIdentity($profile);$index=abs(crc32($identity))%count($prefixes);return $this->formatGatewayPrefix((string)$prefixes[$index],$profile);}
    // gateway_prefix 也按应用身份稳定生成，形如 /api/open/atlasriver/、/api/client/orbitstone/。
    // 基础词来自配置，语义后缀来自 app_id + package_name；同应用重导出不变，不同应用尽量分散。
    private function gatewayIdentity(array $profile):string{return (string)($profile['app_id']??'').'|'.(string)($profile['package_name']??'');}
    private function formatGatewayPrefix(string $base,array $profile):string{$base=preg_replace('/[^a-zA-Z0-9]/','',trim($base));$base=$base!==''?strtolower($base):'gateway';$suffix=$this->gatewaySuffix($profile);return'/api/'.$base.'/'.$suffix.'/';}
    private function gatewaySuffix(array $profile):string{$identity=$this->gatewayIdentity($profile);$first=abs(crc32($identity.'|gateway_suffix:first'))%count(self::GATEWAY_SUFFIX_WORDS);$second=abs(crc32($identity.'|gateway_suffix:second'))%count(self::GATEWAY_SUFFIX_WORDS);if($second===$first)$second=($second+1)%count(self::GATEWAY_SUFFIX_WORDS);return self::GATEWAY_SUFFIX_WORDS[$first].self::GATEWAY_SUFFIX_WORDS[$second];}
    private function decodeMap($v):array{return is_array($v)?$v:$this->decodeJson((string)$v);}
    private function lines($t):array{if(is_array($t))return array_values(array_filter(array_map(fn($v)=>trim((string)$v),$t),fn($v)=>$v!==''));$p=preg_split('/\r\n|\r|\n/',(string)$t);return array_values(array_filter(array_map('trim',$p?:[]),fn($v)=>$v!==''));}
    private function makeAlias(array $profile,string $method,string $path,array &$used):string{$identity=$this->aliasIdentity($profile,$method,$path);$try=0;do{$a=$this->stableUrlAlias($identity,$try);$try++;}while(isset($used[$a])&&$used[$a]!==$identity&&$try<50);$used[$a]=$identity;return$a;}
    // identity 是稳定别名的唯一输入，不能包含 profile_id 这类会随数据库重建变化的字段。
    private function aliasIdentity(array $profile,string $method,string $path):string{$appId=(string)($profile['app_id']??'');$packageName=(string)($profile['package_name']??'');return $appId.'|'.$packageName.'|'.strtoupper($method).'|'.$this->normalizeAliasPath($path);}
    // path 只做格式归一化，不改变业务语义；保证 /api/foo、api/foo、api//foo 生成同一个别名。
    private function normalizeAliasPath(string $path):string{$path=preg_replace('#/+#','/',trim($path));return trim((string)$path,'/');}
    // salt 仅用于极小概率的别名冲突兜底；正常情况下 salt=0，重生成结果完全稳定。
    private function stableUrlAlias(string $identity,int $salt=0):string{$key='api_alias|'.$identity;$hash=hash_hmac('sha256','url'.($salt>0?'|'.$salt:''),$key,true);$chars='abcdefghijklmnopqrstuvwxyz0123456789';$alias='';for($i=0;$i<8;$i++){$alias.=$chars[ord($hash[$i])%36];}return $alias;}
    // 仅供 request/response 字段映射的 mix 规则使用；URL alias 已统一走 stableUrlAlias。
    private function alphaNumFromHash(string $seed,int $len):string{$c='abcdefghijklmnopqrstuvwxyz0123456789';$h=md5($seed);$r='';for($i=0;$i<$len;$i++)$r.=$c[hexdec($h[$i])%strlen($c)];return$r;}
}
