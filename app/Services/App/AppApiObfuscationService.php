<?php

namespace App\Services\App;

use App\Dao\App\AppApiObfuscationAliasDao;
use App\Dao\App\AppApiObfuscationProfileDao;
use App\Dao\App\AppsDao;
use App\Dao\System\SystemApiInterfaceDao;
use App\Exceptions\AdminException;
use App\Services\Service;
use App\Support\Services\DeviceEnvHeaderAliasService;

/**
 * 应用 API 混淆后台服务。
 * 负责应用级配置、公共 API 的接口别名、字段映射、网关前缀，以及导出给客户端的 JSON。
 */
class AppApiObfuscationService extends Service
{
    private const GATEWAY_SUFFIX_WORDS = [
        'atlas', 'bridge', 'center', 'cloud', 'field', 'flow', 'garden', 'harbor',
        'hub', 'lane', 'light', 'matrix', 'orbit', 'portal', 'river', 'stone',
        'stream', 'summit', 'tower', 'valley', 'wave', 'zone',
    ];

    public function __construct(AppApiObfuscationProfileDao $dao, private AppApiObfuscationAliasDao $aliasDao, private SystemApiInterfaceDao $interfaceDao, private AppsDao $appsDao) { $this->dao = $dao; }

    /**
     * 应用列表页：分页返回每个应用的混淆开关、域名和是否已有配置。
     */
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

    /**
     * 读取单个应用的混淆配置。
     * 没有落库记录时返回默认配置，并补上商户域名、默认字段映射。
     */
    public function getProfile(int $appId = 0, string $packageName = ''): array
    {
        $app = $this->appWithMerchant($appId);
        $merchant = $app ? (array)($app['merchant'] ?? []) : [];
        $p = $this->findProfile($appId, $packageName);
        if (!$p) { $r = array_merge(['id' => 0, 'app_id' => $appId, 'package_name' => $packageName, 'image_url_enabled' => 0, 'image_path_alias_enabled' => 0], config('api_obfuscation.profiles.default', [])); return $this->withDefaultImageRewrite($this->withDefaultKeyMaps($this->attachProfileDomains($r, $merchant))); }
        $r = $p->toArray(); $r['route_aliases'] = $this->buildRouteAliasesByProfile((int) $r['id']); return $this->withDefaultImageRewrite($this->withDefaultKeyMaps($this->attachProfileDomains($r, $merchant)));
    }

    /**
     * 保存应用混淆开关、加解密、全局字段映射和域名。
     * 与商户默认域名相同的值不落库，避免商户改域名后应用仍钉死旧值。保存后刷新路由别名缓存。
     */
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

    /**
     * 分页列出该应用已生成的接口别名，可按别名、接口名、模块、路径搜索。
     */
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

    /**
     * 新建或更新单条接口别名。
     * 保存时从公共 API 复制一份 origin 参数快照；之后公共 API 改字段不会自动覆盖，避免已下发客户端的旧别名对不上。
     * 需要最新 origin 时走 syncAliasParams。
     */
    public function saveAlias(array $d): void
    {
        $p = $this->ensureProfile((int)($d['app_id']??0), (string)($d['package_name']??''));
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

    /**
     * 编辑弹窗保存：只改「原始字段 => 别名字段」映射和别名本身。
     * 不写 origin 快照，避免把公共 API 当前参数误覆盖进已下发配置。
     */
    public function updateAliasParams(array $d): array
    {
        $row=$this->aliasDao->get((int)($d['id']??0)); if(!$row)return[];
        $save=['request_key_map'=>$this->decodeMap($d['request_key_map']??[]),'response_key_map'=>$this->decodeMap($d['response_key_map']??[])];
        if(array_key_exists('alias',$d))$save['alias']=(string)$d['alias'];
        if(array_key_exists('is_enable',$d))$save['is_enable']=intval($d['is_enable']);
        if(array_key_exists('remark',$d))$save['remark']=(string)$d['remark'];
        $this->aliasDao->update((int)$row['id'],$save); $this->refreshRouteAliases((int)$row['profile_id']);
        return $this->aliasDetail((int)$row['id']);
    }

    /**
     * 从公共 API 刷新 origin 参数快照，不改 alias 和字段映射。
     * 公共 API 新增字段后，可先同步看到最新 origin，再决定是否重新生成映射。
     */
    public function syncAliasParams(int $id): array
    {
        $row=$this->aliasDao->get($id,['*'],['apiInterface']); if(!$row||!$row->apiInterface)return[];
        $snap=$this->originSnapshot($row->apiInterface->toArray());
        $this->aliasDao->update($id,$snap);
        return $this->aliasDetail($id);
    }

    /**
     * 按当前公共 API 参数预览/生成该接口的字段映射，不落库。
     * 优先用公共 API 最新字段，避免 origin 快照还停在旧的 status/msg/data。
     */
    public function generateAliasParams(array $d): array
    {
        $row=$this->aliasDao->get((int)($d['id']??0),['*'],['apiInterface']); if(!$row)return[];
        $profile=$this->dao->get((int)$row['profile_id']); $profileArr=$profile?$profile->toArray():[];
        $requestOrigin=$this->latestParamsForGeneration($row->toArray(),'request');
        $responseOrigin=$this->latestParamsForGeneration($row->toArray(),'response');
        return [
            'request_key_map'=>$this->stableParamsMap($requestOrigin,$profileArr,'request'),
            'response_key_map'=>$this->stableParamsMap($responseOrigin,$profileArr,'response'),
        ];
    }

    /**
     * 批量为该应用所有接口别名同步 origin，并按稳定规则重写字段映射。
     */
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

    /**
     * 删除单条接口别名，并刷新该应用路由别名缓存。
     */
    public function deleteAlias(int $id): void { $r=$this->aliasDao->get($id); if(!$r)return; $pid=(int)$r['profile_id']; $this->aliasDao->delete($id); $this->refreshRouteAliases($pid); }

    /**
     * 公共 API 被删除时，清掉所有应用下指向该接口的别名，并刷新受影响应用的路由别名缓存。
     */
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

    /**
     * 按当前启用的公共 API 批量为该应用生成接口 URL 别名和字段映射。
     * URL 别名由「应用ID + 包名 + METHOD + path」稳定派生，不再使用 hash4/hex6/restful。
     * overwrite=1 时覆盖已有别名；否则已有别名保持不变。同时写入 origin 参数快照供导出使用。
     */
    public function generateAliases(array $d): array
    {
        $p=$this->ensureProfile((int)($d['app_id']??0),(string)($d['package_name']??'')); $overwrite=intval($d['overwrite']??0)===1; $used=[]; $updated=0;
        if($overwrite)$this->aliasDao->search(['profile_id'=>$p['id']])->update(['is_enable'=>0]);
        foreach($this->interfaceDao->search(['is_enable'=>1])->orderBy('path')->orderBy('method')->orderBy('id')->get() as $i){ $old=$this->aliasDao->search(['profile_id'=>$p['id'],'interface_id'=>$i['id']])->first(); $identity=$this->aliasIdentity($p->toArray(),strtoupper((string)$i['method']),(string)$i['path']); if(!$overwrite&&$old&&!empty($old['alias'])){$used[$old['alias']]=$identity;continue;} $alias=$this->makeAlias($p->toArray(),strtoupper((string)$i['method']),(string)$i['path'],$used); $save=array_merge(['profile_id'=>(int)$p['id'],'interface_id'=>(int)$i['id'],'alias'=>$alias,'is_enable'=>1],$this->originSnapshot($i->toArray()),$this->generateMapsForInterface($i->toArray(),(string)($d['map_rule']??'short'),$p->toArray())); $old?$this->aliasDao->update($old['id'],$save):$this->aliasDao->save($save); $updated++; }
        $this->refreshRouteAliases((int)$p['id']); return ['updated'=>$updated];
    }

    /**
     * 按所选规则预览应用级默认请求/响应字段映射，供配置页一键填充。
     */
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

    /**
     * 预览单条别名：真实路径 vs 网关别名路径，以及请求/响应 origin 与映射后的示例。
     */
    public function previewAlias(int $id): array
    {
        $r=$this->aliasDao->get($id,['*'],['apiInterface']); if(!$r)return[]; $raw=$r->toArray(); $r=$this->formatAliasRow($raw); $req=$this->example($this->paramsFromAliasRow($raw,'request')); $res=$this->example($this->paramsFromAliasRow($raw,'response'));
        $profile=$this->dao->get((int)($raw['profile_id']??0));
        $prefix = $this->gatewayPrefixForProfile($profile ? $profile->toArray() : []);
        return ['request'=>['origin_path'=>'/api/'.ltrim((string)$r['path'],'/'),'alias_path'=>$prefix.(string)$r['alias'],'origin_params'=>$req,'alias_params'=>$this->applyMap($req,(array)($r['request_key_map']??[]))],'response'=>['origin'=>$res,'alias'=>$this->applyMap($res,(array)($r['response_key_map']??[]))]];
    }

    /**
     * 导出给客户端的接口别名清单（含网关前缀、Device-Env 头别名、每条 origin/alias 示例）。
     */
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

    /**
     * 组装运行时/缓存用的 alias => {path, method}。公共接口已删或停用的别名不会进入这份映射。
     */
    public function buildRouteAliasesByProfile(int $pid): array { $a=[]; foreach($this->aliasDao->search(['profile_id'=>$pid,'is_enable'=>1])->with('apiInterface')->get() as $r) if($r->apiInterface&&intval($r->apiInterface['is_enable']??0)===1&&$r['alias']) $a[$r['alias']]=['path'=>ltrim((string)$r->apiInterface['path'],'/'),'method'=>strtoupper((string)$r->apiInterface['method'])]; return $a; }

    /**
     * 导出完整混淆配置（协议、加解密、全局映射、路由别名），不含图片域名字段。
     */
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

    /** 该应用导出/预览用的 Device-Env 请求头别名。 */
    private function deviceEnvHeaderAlias(array $profile): string
    {
        return (new DeviceEnvHeaderAliasService())->make((int) ($profile['app_id'] ?? 0), (string) ($profile['package_name'] ?? ''));
    }

    private function findProfile(int $appId,string $pkg){return $this->dao->search(['app_id'=>$appId,'package_name'=>$pkg])->first();}
    private function appWithMerchant(int $appId):array{return $appId>0?($this->appsDao->newQuery()->with('merchant')->find($appId)?->toArray()??[]):[];}
    private function merchantForProfile(array $profile):array{$app=$this->appWithMerchant((int)($profile['app_id']??0));return (array)($app['merchant']??[]);}

    /** 配置里的短字段映射默认值（page=>pg 等）。 */
    private function defaultShortMaps(): array
    {
        return [
            'request_key_map' => (array) config('api_obfuscation.default_short_maps.request_key_map', ['page'=>'pg','limit'=>'sz','keywords'=>'kw','uuid'=>'ud','token'=>'tk']),
            'response_key_map' => (array) config('api_obfuscation.default_short_maps.response_key_map', ['status'=>'s','msg'=>'m','data'=>'d']),
        ];
    }

    /**
     * 空映射补短字段默认值；图片 path_prefixes 始终用全局配置，不跟应用配置走。
     */
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

    /** 给后台表单带上应用域名和商户域名，便于对比是否覆盖。 */
    private function attachProfileDomains(array $profile, array $merchant = []): array
    {
        $profile['merchant_image_domain'] = (string) ($merchant['image_domain'] ?? '');
        $profile['merchant_api_domain'] = (string) ($merchant['api_domain'] ?? '');
        $profile['image_domain'] = $this->profileImageDomain($profile, $merchant);
        $profile['api_domain'] = $this->profileApiDomain($profile, $merchant);

        return $profile;
    }

    /** 尚未落库且已有图片域名时，后台默认打开图片域名替换。 */
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

    /** 接口域名必填；开启图片替换时图片域名也必填。 */
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

    /** 导出给客户端时补全 https://，已带协议的原样返回。 */
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

    /**
     * 入库域名：空或与商户默认相同则存空，读取时再回落到商户域名。
     */
    private function persistDomain(string $submitted, string $merchantDefault): string
    {
        $submitted = trim($submitted);
        $merchantDefault = trim($merchantDefault);

        return ($submitted === '' || $submitted === $merchantDefault) ? '' : $submitted;
    }

    private function profileImageDomain(array $profile,array $merchant=[]):string{$image=(array)($profile['image_url']??[]);$domain=(string)($profile['image_domain']??($image['domain']??''));return $domain!==''?$domain:(string)($merchant['image_domain']??'');}
    private function profileApiDomain(array $profile,array $merchant=[]):string{$domain=trim((string)($profile['api_domain']??''));return $domain!==''?$domain:trim((string)($merchant['api_domain']??''));}

    /** 没有单独配置接口域名时，用商户主域生成 api.{host}。 */
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

    /** 没有混淆配置时先插入一条关闭状态的记录，再生成别名。 */
    private function ensureProfile(int $appId,string $pkg){return $this->findProfile($appId,$pkg)?:$this->dao->save(['app_id'=>$appId,'package_name'=>$pkg,'enabled'=>0,'protocol'=>config('api_obfuscation.profiles.default.protocol',[]),'security'=>config('api_obfuscation.profiles.default.security',[]),'crypto'=>config('api_obfuscation.profiles.default.crypto',[]),'image_url'=>config('api_obfuscation.profiles.default.image_url',[]),'route_aliases'=>[]]);}

    /** 把当前别名表写回 profile.route_aliases，给旧逻辑和缓存用。 */
    private function refreshRouteAliases(int $pid):void{$this->dao->update($pid,['route_aliases'=>$this->buildRouteAliasesByProfile($pid)]);}

    /** 列表/预览行：把公共 API 的名称、路径拼到别名记录上。 */
    private function formatAliasRow(array $r):array{$i=$r['api_interface']??[];$r['response_key_map']=$this->effectiveResponseAliasMap($r);return array_merge($r,['interface_name'=>$i['name']??'','module'=>$i['module']??'','path'=>$i['path']??'','method'=>$i['method']??'','request_params'=>$i['request_params']??[],'response_params'=>$i['response_params']??[]]);}
    private function aliasDetail(int $id):array{$row=$this->aliasDao->get($id,['*'],['apiInterface']);return$row?$this->formatAliasRow($row->toArray()):[];}

    /**
     * 把公共 API 的参数定义存进别名行，作为 origin 快照。
     * 表字段是 request_origin_params / response_origin_params，导出时再转成客户端文档里的 origin_params / origin。
     */
    private function originSnapshot(array $interface):array{return ['request_origin_params'=>(array)($interface['request_params']??[]),'response_origin_params'=>(array)($interface['response_params']??[])];}

    /**
     * 预览/导出优先用别名行自己的 origin 快照；旧数据没有快照时回退到公共 API 当前参数。
     */
    private function paramsFromAliasRow(array $row,string $type):array{$field=$type==='request'?'request_origin_params':'response_origin_params';$fallback=$type==='request'?'request_params':'response_params';return (array)($row[$field]??$row['api_interface'][$fallback]??$row[$fallback]??[]);}

    /** 生成字段映射时优先用公共 API 最新参数，没有才用快照。 */
    private function latestParamsForGeneration(array $row,string $type):array{$fallback=$type==='request'?'request_params':'response_params';$live=(array)($row['api_interface'][$fallback]??[]);return $live!==[]?$live:$this->paramsFromAliasRow($row,$type);}

    /**
     * 响应字段映射兼容：新数据只写 response_key_map；旧数据若只存了 response_data_key_map 也当作响应映射。
     */
    private function effectiveResponseAliasMap(array $row):array{return $this->decodeMap($row['response_key_map']??[])?:$this->decodeMap($row['response_data_key_map']??[]);}

    /** 导出单条别名：示例参数 + 映射表，不再重复输出 snapshot 字段。 */
    private function formatExportAliasItem(array $row):array{$r=$this->formatAliasRow($row);$reqOrigin=$this->paramsFromAliasRow($row,'request');$resOrigin=$this->paramsFromAliasRow($row,'response');$req=$this->example($reqOrigin);$res=$this->example($resOrigin);$requestMap=(array)($r['request_key_map']??[]);$responseMap=(array)($r['response_key_map']??[]);return ['alias'=>(string)($r['alias']??''),'path'=>(string)($r['path']??''),'method'=>(string)($r['method']??''),'request'=>['origin_params'=>$req,'alias_params'=>$this->applyMap($req,$requestMap),'request_key_map'=>$requestMap],'response'=>['origin'=>$res,'alias'=>$this->applyMap($res,$responseMap),'response_key_map'=>$responseMap]];}

    private function generateMapsForInterface(array $i,string $rule,array $profile=[]):array{return ['request_key_map'=>$this->paramsMap((array)($i['request_params']??[]),$rule,$profile,'request'),'response_key_map'=>$this->paramsMap((array)($i['response_params']??[]),$rule,$profile,'response')];}

    /**
     * 参数别名按应用身份 + 原字段名稳定生成，不依赖 JSON 字段顺序。
     * 同一字段反复生成结果一致；前面插入新字段也不会改已有字段的别名。
     */
    private function stableParamsMap(array $params,array $profile,string $scope):array{$map=[];$used=[];foreach($this->paramKeysForScope($params,$profile,$scope) as $key){$alias=$this->stableParamAlias($profile,$scope,$key,$used);$map[$key]=$alias;}return$map;}
    private function stableParamAlias(array $profile,string $scope,string $key,array &$used):string{$identity=(string)($profile['app_id']??'').'|'.(string)($profile['package_name']??'').'|'.$scope.'|'.$key;$try=0;do{$hash=hash('sha256',$identity.'|'.$try);$alias='p'.substr($hash,0,5);$try++;}while(isset($used[$alias])&&$try<20);$used[$alias]=true;return$alias;}

    /** short/biz/mix 等旧字段映射规则；稳定规则走 stableParamsMap。 */
    private function paramsMap(array $ps,string $rule,array $profile=[],string $scope='request'):array{$m=[];$n=0;foreach($this->paramKeysForScope($ps,$profile,$scope) as $k){$n++;$m[$k]=$rule==='mix'?$this->alphaNumFromHash($k.$n,5):(($rule==='biz'?'field':substr(preg_replace('/[^a-z0-9]/i','',$k),0,1)).$n);}return$m;}

    /** 参数定义转示例对象；若本身就是 JSON 示例则原样返回。 */
    private function example(array $ps):array{$r=[];$hasDefinition=false;foreach($ps as $p){if(!is_array($p)||!$this->looksLikeParamDefinition($p))continue;$k=(string)($p['key']??$p['name']??'');if($k!==''){$hasDefinition=true;$r[$k]=$p['example']??'';}}return$hasDefinition?$r:$ps;}

    /**
     * 从公共 API 参数里抽出字段名。
     * 既支持 [{key,type}] 定义，也支持直接存的 JSON 示例；嵌套 items/properties 会继续往下走，忽略数组下标。
     */
    private function paramKeys(array $params):array{$keys=[];$walk=function($value)use(&$walk,&$keys){if(!is_array($value))return;if($this->looksLikeParamDefinition($value)){$schemaKey=(string)($value['key']??$value['name']??'');if($schemaKey!==''){$keys[$schemaKey]=true;foreach(['items','properties','children','fields'] as $nestedField){if(isset($value[$nestedField])&&is_array($value[$nestedField]))$walk($value[$nestedField]);}return;}}foreach($value as $k=>$v){if(!is_int($k)&&$k!=='')$keys[(string)$k]=true;if(is_array($v))$walk($v);}};$walk($params);return array_keys($keys);}

    /** 判断一项是参数 schema（有 key/name/type），而不是业务 JSON。 */
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

    /**
     * 接口级响应别名只处理业务字段；status/msg/data 等外层字段由 profile.response_key_map 统一映射。
     */
    private function paramKeysForScope(array $params,array $profile,string $scope):array{return $scope==='response'?$this->responseParamKeysForAlias($params,$profile):$this->paramKeys($params);}
    private function responseParamKeysForAlias(array $params,array $profile):array{$keys=$this->paramKeys($params);$outer=$this->outerResponseKeys($profile,$params);return array_values(array_filter($keys,fn($key)=>!in_array($key,$outer,true)));}
    private function outerResponseKeys(array $profile,array $params):array{$outer=array_keys(array_merge($this->decodeMap($profile['response_key_map']??[]),['status','msg','message','code','data','result','error']));$example=$this->example($params);if($this->looksLikeResponseEnvelope($example)){$outer=array_merge($outer,array_keys($example));}return array_values(array_unique(array_filter($outer,fn($key)=>$key!=='')));}
    private function looksLikeResponseEnvelope(array $example):bool{if($example===[]||array_is_list($example))return false;$keys=array_keys($example);$envelopeKeys=['status','msg','message','code','data','result','error'];if(count(array_intersect($keys,$envelopeKeys))===0)return false;foreach($example as $value){if(is_array($value)&&(array_is_list($value)||$this->hasAssociativeFields($value)))return true;}return false;}
    private function hasAssociativeFields(array $value):bool{foreach(array_keys($value) as $key){if(!is_int($key))return true;}return false;}

    /** 按映射表递归改 key，数组下标保持不变。 */
    private function applyMap(array $d,array $m):array{$r=[];foreach($d as $k=>$v){$mappedKey=is_int($k)?$k:($m[$k]??$k);$r[$mappedKey]=is_array($v)?$this->applyMap($v,$m):$v;}return$r;}
    private function decodeJson(string $j):array{$d=json_decode(trim($j),true);return is_array($d)?$d:[];}

    /** 配置里所有网关前缀，都拼上该应用的稳定 suffix。 */
    private function gatewayPrefixes(array $profile):array{$prefixes=config('api_obfuscation.gateway_prefixes',['gateway']);return array_map(fn($v)=>$this->formatGatewayPrefix((string)$v,$profile),array_values(array_filter($prefixes)));}

    /** 该应用主用网关前缀：按 app_id+包名在配置前缀列表里取一个，再拼 suffix。 */
    private function gatewayPrefixForProfile(array $profile):string{$prefixes=array_values(array_filter(config('api_obfuscation.gateway_prefixes',['gateway'])));if(empty($prefixes))$prefixes=['gateway'];$identity=$this->gatewayIdentity($profile);$index=abs(crc32($identity))%count($prefixes);return $this->formatGatewayPrefix((string)$prefixes[$index],$profile);}

    /**
     * 网关身份与 URL 别名共用 app_id|package_name，不带 profile_id。
     * 前缀形如 /api/open/atlasriver/：base 来自配置，suffix 由身份稳定算出。
     */
    private function gatewayIdentity(array $profile):string{return (string)($profile['app_id']??'').'|'.(string)($profile['package_name']??'');}
    private function formatGatewayPrefix(string $base,array $profile):string{$base=preg_replace('/[^a-zA-Z0-9]/','',trim($base));$base=$base!==''?strtolower($base):'gateway';$suffix=$this->gatewaySuffix($profile);return'/api/'.$base.'/'.$suffix.'/';}
    private function gatewaySuffix(array $profile):string{$identity=$this->gatewayIdentity($profile);$first=abs(crc32($identity.'|gateway_suffix:first'))%count(self::GATEWAY_SUFFIX_WORDS);$second=abs(crc32($identity.'|gateway_suffix:second'))%count(self::GATEWAY_SUFFIX_WORDS);if($second===$first)$second=($second+1)%count(self::GATEWAY_SUFFIX_WORDS);return self::GATEWAY_SUFFIX_WORDS[$first].self::GATEWAY_SUFFIX_WORDS[$second];}

    private function decodeMap($v):array{return is_array($v)?$v:$this->decodeJson((string)$v);}
    private function lines($t):array{if(is_array($t))return array_values(array_filter(array_map(fn($v)=>trim((string)$v),$t),fn($v)=>$v!==''));$p=preg_split('/\r\n|\r|\n/',(string)$t);return array_values(array_filter(array_map('trim',$p?:[]),fn($v)=>$v!==''));}

    /** 生成 8 位 URL 别名；同 identity 冲突时加 salt 重试。 */
    private function makeAlias(array $profile,string $method,string $path,array &$used):string{$identity=$this->aliasIdentity($profile,$method,$path);$try=0;do{$a=$this->stableUrlAlias($identity,$try);$try++;}while(isset($used[$a])&&$used[$a]!==$identity&&$try<50);$used[$a]=$identity;return$a;}

    /**
     * 稳定别名的唯一输入：应用ID + 包名 + METHOD + 归一化 path。
     * 不能带 profile_id，避免库重建后别名全部变化。
     */
    private function aliasIdentity(array $profile,string $method,string $path):string{$appId=(string)($profile['app_id']??'');$packageName=(string)($profile['package_name']??'');return $appId.'|'.$packageName.'|'.strtoupper($method).'|'.$this->normalizeAliasPath($path);}

    /** 去掉多余斜杠，保证 /api/foo、api/foo、api//foo 生成同一个别名。 */
    private function normalizeAliasPath(string $path):string{$path=preg_replace('#/+#','/',trim($path));return trim((string)$path,'/');}

    /**
     * HMAC 得到 8 位 [a-z0-9] URL 别名。salt 只在冲突时使用，正常 salt=0，重生成结果不变。
     */
    private function stableUrlAlias(string $identity,int $salt=0):string{$key='api_alias|'.$identity;$hash=hash_hmac('sha256','url'.($salt>0?'|'.$salt:''),$key,true);$chars='abcdefghijklmnopqrstuvwxyz0123456789';$alias='';for($i=0;$i<8;$i++){$alias.=$chars[ord($hash[$i])%36];}return $alias;}

    /** mix 字段规则用的短哈希；URL 别名已统一走 stableUrlAlias。 */
    private function alphaNumFromHash(string $seed,int $len):string{$c='abcdefghijklmnopqrstuvwxyz0123456789';$h=md5($seed);$r='';for($i=0;$i<$len;$i++)$r.=$c[hexdec($h[$i])%strlen($c)];return$r;}
}
