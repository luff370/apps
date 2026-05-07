<?php

namespace App\Services\App;

use App\Dao\App\AppApiObfuscationAliasDao;
use App\Dao\App\AppApiObfuscationProfileDao;
use App\Dao\System\SystemApiInterfaceDao;
use App\Services\Service;

class AppApiObfuscationService extends Service
{
    public function __construct(AppApiObfuscationProfileDao $dao, private AppApiObfuscationAliasDao $aliasDao, private SystemApiInterfaceDao $interfaceDao) { $this->dao = $dao; }

    public function getProfile(int $appId = 0, string $packageName = ''): array
    {
        $p = $this->findProfile($appId, $packageName);
        if (!$p) return array_merge(['id' => 0, 'app_id' => $appId, 'package_name' => $packageName, 'alias_rule' => 'hash4'], config('api_obfuscation.profiles.default', []));
        $r = $p->toArray(); $r['route_aliases'] = $this->buildRouteAliasesByProfile((int) $r['id']); return $r;
    }

    public function saveProfile(array $d): array
    {
        $appId = intval($d['app_id'] ?? 0); $pkg = (string) ($d['package_name'] ?? ''); $p = $this->findProfile($appId, $pkg);
        $save = ['enabled'=>intval($d['enabled']??0),'encrypt_request'=>intval($d['encrypt_request']??0),'encrypt_response'=>intval($d['encrypt_response']??0),'allow_plaintext_request'=>intval($d['allow_plaintext_request']??1),'image_url_enabled'=>intval($d['image_url_enabled']??0),'image_domain'=>(string)($d['image_domain']??''),'alias_rule'=>(string)($d['alias_rule']??'hash4'),'request_key_map'=>$this->decodeJson((string)($d['request_key_map']??'{}')),'response_key_map'=>$this->decodeJson((string)($d['response_key_map']??'{}')),'response_data_key_map'=>$this->decodeJson((string)($d['response_data_key_map']??'{}')),'protocol'=>['encrypt_request'=>(bool)($d['encrypt_request']??0),'encrypt_response'=>(bool)($d['encrypt_response']??0),'allow_plaintext_request'=>(bool)($d['allow_plaintext_request']??1),'payload_field'=>(string)($d['payload_field']??'payload'),'sign_field'=>(string)($d['sign_field']??'sign'),'timestamp_field'=>(string)($d['timestamp_field']??'ts'),'nonce_field'=>(string)($d['nonce_field']??'nonce'),'version_field'=>(string)($d['version_field']??'ver')],'security'=>['timestamp_window_seconds'=>intval($d['timestamp_window_seconds']??300),'nonce_ttl_seconds'=>intval($d['nonce_ttl_seconds']??300)],'crypto'=>['cipher'=>(string)($d['cipher']??'AES-256-CBC'),'key'=>(string)($d['crypto_key']??''),'iv'=>(string)($d['crypto_iv']??''),'sign_key'=>(string)($d['crypto_sign_key']??'')],'image_url'=>['enabled'=>(bool)($d['image_url_enabled']??0),'domain'=>(string)($d['image_domain']??''),'fields'=>$this->lines((string)($d['image_fields']??'')),'path_prefixes'=>$this->lines((string)($d['image_prefixes']??''))]];
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
        $s = ['profile_id'=>(int)$p['id'],'interface_id'=>intval($d['interface_id']??0),'alias'=>(string)($d['alias']??''),'request_key_map'=>$this->decodeMap($d['request_key_map']??[]),'response_key_map'=>$this->decodeMap($d['response_key_map']??[]),'response_data_key_map'=>$this->decodeMap($d['response_data_key_map']??[]),'is_enable'=>intval($d['is_enable']??1),'remark'=>(string)($d['remark']??'')];
        $id = intval($d['id']??0); if ($id>0) $this->aliasDao->update($id,$s); else { $old=$this->aliasDao->search(['profile_id'=>$s['profile_id'],'interface_id'=>$s['interface_id']])->first(); $old?$this->aliasDao->update($old['id'],$s):$this->aliasDao->save($s); }
        $this->refreshRouteAliases((int)$p['id']);
    }

    public function deleteAlias(int $id): void { $r=$this->aliasDao->get($id); if(!$r)return; $pid=(int)$r['profile_id']; $this->aliasDao->delete($id); $this->refreshRouteAliases($pid); }

    public function generateAliases(array $d): array
    {
        $p=$this->ensureProfile((int)($d['app_id']??0),(string)($d['package_name']??'')); $rule=(string)($d['rule']??$p['alias_rule']??'hash4'); $overwrite=intval($d['overwrite']??0)===1; $used=[]; $updated=0;
        foreach($this->interfaceDao->search(['is_enable'=>1])->get() as $i){ $old=$this->aliasDao->search(['profile_id'=>$p['id'],'interface_id'=>$i['id']])->first(); if(!$overwrite&&$old&&!empty($old['alias'])){$used[$old['alias']]=true;continue;} $alias=$this->makeAlias(strtoupper($i['method']).':'.trim($i['path'],'/').':'.$p['id'],$rule,$used); $save=array_merge(['profile_id'=>(int)$p['id'],'interface_id'=>(int)$i['id'],'alias'=>$alias,'is_enable'=>1],$this->generateMapsForInterface($i->toArray(),(string)($d['map_rule']??'short'))); $old?$this->aliasDao->update($old['id'],$save):$this->aliasDao->save($save); $updated++; }
        $this->dao->update($p['id'],['alias_rule'=>$rule]); $this->refreshRouteAliases((int)$p['id']); return ['updated'=>$updated,'rule'=>$rule];
    }

    public function generateDefaultProfileFields(array $d): array
    {
        $img=['image','images','avatar','cover','thumb','icon','url']; $pre=['attach/','/attach/','uploads/attach/','/uploads/attach/','storage/attach/','/storage/attach/'];
        if(($d['map_rule']??'short')==='biz') return ['request_key_map'=>['page'=>'cursor','limit'=>'batch','keywords'=>'query','uuid'=>'deviceCode','token'=>'sessionCode'],'response_key_map'=>['status'=>'code','msg'=>'message','data'=>'result'],'response_data_key_map'=>['list'=>'records','count'=>'totalCount','total'=>'total'],'image_fields'=>$img,'image_prefixes'=>$pre];
        return ['request_key_map'=>['page'=>'pg','limit'=>'sz','keywords'=>'kw','uuid'=>'ud','token'=>'tk'],'response_key_map'=>['status'=>'s','msg'=>'m','data'=>'d'],'response_data_key_map'=>['list'=>'ls','count'=>'ct','total'=>'tt'],'image_fields'=>$img,'image_prefixes'=>$pre];
    }

    public function previewAlias(int $id): array
    {
        $r=$this->aliasDao->search(['id'=>$id])->with('apiInterface')->first(); if(!$r)return[]; $raw=$r->toArray(); $r=$this->formatAliasRow($raw); $req=$this->example((array)($r['request_params']??[])); $res=$this->example((array)($r['response_params']??[]));
        $profile=$this->dao->get((int)($raw['profile_id']??0));
        $prefix = $this->gatewayPrefixForProfile($profile ? $profile->toArray() : []);
        return ['request'=>['origin_path'=>'/api/'.ltrim((string)$r['path'],'/'),'alias_path'=>$prefix.(string)$r['alias'],'origin_params'=>$req,'alias_params'=>$this->applyMap($req,(array)($r['request_key_map']??[]))],'response'=>['origin'=>$res,'alias'=>$this->applyMap($res,(array)($r['response_data_key_map']??[]))]];
    }

    public function exportAliases(array $d): array
    {
        $p=$this->findProfile((int)($d['app_id']??0),(string)($d['package_name']??'')); if(!$p)return[]; $rows=$this->aliasDao->search(['profile_id'=>$p['id'],'is_enable'=>1])->with('apiInterface')->get()->toArray();
        return ['app_id'=>(int)$p['app_id'],'package_name'=>(string)$p['package_name'],'gateway_prefix'=>$this->gatewayPrefixForProfile($p->toArray()),'gateway_prefixes'=>$this->gatewayPrefixes(),'items'=>array_map(fn($x)=>array_intersect_key($this->formatAliasRow($x),array_flip(['alias','path','method','request_key_map','response_key_map','response_data_key_map'])),$rows)];
    }

    public function buildRouteAliasesByProfile(int $pid): array { $a=[]; foreach($this->aliasDao->search(['profile_id'=>$pid,'is_enable'=>1])->with('apiInterface')->get() as $r) if($r->apiInterface&&$r['alias']) $a[$r['alias']]=['path'=>ltrim((string)$r->apiInterface['path'],'/'),'method'=>strtoupper((string)$r->apiInterface['method'])]; return $a; }
    public function exportProfile(array $d): array
    {
        $p=$this->findProfile((int)($d['app_id']??0),(string)($d['package_name']??'')); if(!$p)return[];
        $profile=$p->toArray(); $profile['route_aliases']=$this->buildRouteAliasesByProfile((int)$profile['id']);
        return ['app_id'=>(int)$profile['app_id'],'package_name'=>(string)$profile['package_name'],'gateway_prefix'=>$this->gatewayPrefixForProfile($profile),'gateway_prefixes'=>$this->gatewayPrefixes(),'profile'=>['enabled'=>(bool)($profile['enabled']??0),'route_aliases'=>$profile['route_aliases']??[],'request_key_map'=>$profile['request_key_map']??[],'response_key_map'=>$profile['response_key_map']??[],'response_data_key_map'=>$profile['response_data_key_map']??[],'protocol'=>$profile['protocol']??[],'security'=>$profile['security']??[],'crypto'=>$profile['crypto']??[],'image_url'=>$profile['image_url']??[]],'aliases'=>$this->exportAliases($d)['items']??[]];
    }
    private function findProfile(int $appId,string $pkg){return $this->dao->search(['app_id'=>$appId,'package_name'=>$pkg])->first();}
    private function ensureProfile(int $appId,string $pkg){return $this->findProfile($appId,$pkg)?:$this->dao->save(['app_id'=>$appId,'package_name'=>$pkg,'enabled'=>0,'alias_rule'=>'hash4','protocol'=>config('api_obfuscation.profiles.default.protocol',[]),'security'=>config('api_obfuscation.profiles.default.security',[]),'crypto'=>config('api_obfuscation.profiles.default.crypto',[]),'image_url'=>config('api_obfuscation.profiles.default.image_url',[]),'route_aliases'=>[]]);}
    private function refreshRouteAliases(int $pid):void{$this->dao->update($pid,['route_aliases'=>$this->buildRouteAliasesByProfile($pid)]);}
    private function formatAliasRow(array $r):array{$i=$r['api_interface']??[];return array_merge($r,['interface_name'=>$i['name']??'','module'=>$i['module']??'','path'=>$i['path']??'','method'=>$i['method']??'','request_params'=>$i['request_params']??[],'response_params'=>$i['response_params']??[]]);}
    private function generateMapsForInterface(array $i,string $rule):array{return ['request_key_map'=>$this->paramsMap((array)($i['request_params']??[]),$rule),'response_key_map'=>[],'response_data_key_map'=>$this->paramsMap((array)($i['response_params']??[]),$rule)];}
    private function paramsMap(array $ps,string $rule):array{$m=[];$n=0;foreach($ps as $p){$k=(string)($p['key']??$p['name']??'');if($k==='')continue;$n++;$m[$k]=$rule==='mix'?$this->alphaNumFromHash($k.$n,5):(($rule==='biz'?'field':substr(preg_replace('/[^a-z0-9]/i','',$k),0,1)).$n);}return$m;}
    private function example(array $ps):array{$r=[];foreach($ps as $p){if(!is_array($p))continue;$k=(string)($p['key']??$p['name']??'');if($k!=='')$r[$k]=$p['example']??'';}return$r;}
    private function applyMap(array $d,array $m):array{$r=[];foreach($d as $k=>$v)$r[$m[$k]??$k]=$v;return$r;}
    private function decodeJson(string $j):array{$d=json_decode(trim($j),true);return is_array($d)?$d:[];}
    private function gatewayPrefixes():array{$prefixes=config('api_obfuscation.gateway_prefixes',['gateway']);return array_map(fn($v)=>'/api/'.trim((string)$v,'/').'/',array_values(array_filter($prefixes)));}
    private function gatewayPrefixForProfile(array $profile):string{$prefixes=$this->gatewayPrefixes();if(empty($prefixes))return'/api/gateway/';$seed=($profile['package_name']??'').':'.($profile['app_id']??'');$index=abs(crc32($seed))%count($prefixes);return $prefixes[$index];}
    private function decodeMap($v):array{return is_array($v)?$v:$this->decodeJson((string)$v);}
    private function lines(string $t):array{$p=preg_split('/\r\n|\r|\n/',$t);return array_values(array_filter(array_map('trim',$p?:[]),fn($v)=>$v!==''));}
    private function makeAlias(string $seed,string $rule,array &$used):string{$try=0;do{$try++;$a=match($rule){'hex6'=>substr(md5($seed.':'.$try),0,6),'mix6'=>$this->alphaNumFromHash($seed.':'.$try,6),'word4'=>$this->wordAlias($seed,$try),'path3'=>'r'.$this->alphaNumFromHash($seed.':'.$try,4),'restful'=>$this->businessAlias($seed,$try,'restful'),'actionBiz'=>$this->businessAlias($seed,$try,'actionBiz'),'moduleOp'=>$this->businessAlias($seed,$try,'moduleOp'),default=>substr(md5($seed.':'.$try),0,4)};}while(isset($used[$a])&&$try<50);$used[$a]=true;return$a;}
    private function alphaNumFromHash(string $seed,int $len):string{$c='abcdefghijklmnopqrstuvwxyz0123456789';$h=md5($seed);$r='';for($i=0;$i<$len;$i++)$r.=$c[hexdec($h[$i])%strlen($c)];return$r;}
    private function businessAlias(string $seed,int $salt,string $style):string{$parts=explode(':',$seed,3);$path=$parts[1]??$seed;$words=array_values(array_filter(array_map(fn($v)=>preg_replace('/[^a-zA-Z0-9]/','',$v),explode('/',preg_replace('/\{[^}]+\}/','',$path))),fn($v)=>$v!==''));$module=$this->camelWord($words[0]??'api');$action=$this->camelWord(end($words)?:'data');$mid=$this->camelWord($words[1]??$action);$tail=$this->alphaNumFromHash($seed.':'.$salt,2);return match($style){'actionBiz'=>$action.$this->ucFirstAscii($module).$tail,'moduleOp'=>$module.$this->ucFirstAscii($action).$tail,default=>$module.'/'.($mid===$module?$action:$mid.'/'.$action).$tail};}
    private function camelWord(string $word):string{$word=preg_replace('/[^a-zA-Z0-9]+/',' ',$word);$word=str_replace(' ','',ucwords(strtolower((string)$word)));$word=lcfirst($word);return $word!==''?$word:'api';}
    private function ucFirstAscii(string $word):string{return ucfirst($word);} 
    private function wordAlias(string $seed,int $salt):string{$p=['ax','ke','zu','vo','mi','ra','ta','ny'];$s=['q','x','k','m','z','t','v','s'];$h=crc32($seed.':'.$salt);return$p[$h%count($p)].substr(md5($seed),0,2).$s[($h>>3)%count($s)];}
}
