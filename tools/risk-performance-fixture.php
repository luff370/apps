<?php

/** Creates synthetic data only in a new, uniquely named database; never modifies application tables. */
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$options = getopt('', ['create', 'drop:']);
if (! in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Fixture creation/deletion is restricted to a local MySQL server');
}
if (isset($options['drop'])) {
    if (! preg_match('/^risk_perf_fixture_[a-f0-9]{12}$/', $options['drop'])) {
        throw new RuntimeException('Invalid fixture database name');
    }
    DB::statement('DROP DATABASE IF EXISTS `'.$options['drop'].'`');
    echo 'Dropped '.$options['drop'],PHP_EOL;
    exit;
}
if (! isset($options['create'])) {
    throw new RuntimeException('Specify --create or --drop=<fixture database>');
}
$source = DB::connection()->getDatabaseName();
if (! preg_match('/^[a-zA-Z0-9_]+$/', $source)) {
    throw new RuntimeException('Unsupported source schema identifier');
}
$name = 'risk_perf_fixture_'.bin2hex(random_bytes(6));
DB::statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
echo 'Created '.$name,PHP_EOL;
try {
    $connection = config('database.connections.mysql');
    $connection['database'] = $name;
    config(['database.connections.risk_fixture' => $connection, 'database.default' => 'risk_fixture']);
    (require dirname(__DIR__).'/database/migrations/2026_10_28_120000_create_risk_ledger_tables.php')->up();
    foreach (['risk_probe_logs', 'risk_lists', 'app_version_plans', 'app_version_plan_tasks'] as $table) {
        DB::statement("CREATE TABLE `$table` LIKE `$source`.`$table`");
    }
    DB::statement('CREATE TABLE users (id BIGINT UNSIGNED PRIMARY KEY, app_id VARCHAR(20), uuid VARCHAR(255), account VARCHAR(100), phone VARCHAR(32), is_del TINYINT DEFAULT 0, INDEX (app_id, uuid), INDEX(uuid))');
    DB::statement('CREATE TABLE system_apps (id INT UNSIGNED PRIMARY KEY, name VARCHAR(100), is_del TINYINT DEFAULT 0)');
    DB::statement('CREATE TABLE _audit_seq (n INT UNSIGNED PRIMARY KEY)');
    for ($from = 1; $from <= 100000; $from += 5000) {
        $rows = [];
        for ($i = $from; $i < $from + 5000; $i++) {
            $rows[] = ['n' => $i];
        }DB::table('_audit_seq')->insert($rows);
    }
    $at = "DATE_SUB('2026-10-29 23:59:59', INTERVAL MOD(n,180) DAY)";
    $ip = "INET_NTOA(INET_ATON('198.18.0.0') + IF(n<=10000,1,2+MOD(n,20000)))";
    $appId = 'IF(MOD(n,10)<7,10027,10000+MOD(n,20))';
    $device = "MD5(CONCAT('synthetic-device-',n))";
    $state = "ELT(1+MOD(n,3),'active','active','released')";
    DB::statement("INSERT INTO risk_scopes (id,app_id,ip,device_identity,install_id,user_id,market_channel,version_name,reason_codes,risk_level,decision,risk_score,state,collected_at,created_at,updated_at) SELECT n,$appId,$ip,$device,$device,IF(MOD(n,5)=0,NULL,CAST(n AS CHAR)),'huawei','1.0.0',JSON_ARRAY('root','emulator'),'high','block',80,$state,$at,$at,$at FROM _audit_seq");
    DB::statement('INSERT INTO risk_intakes (id,scope_id,app_id,ip,device_identity,user_id,collected_at,source_log_id,created_at,updated_at) SELECT id,id,app_id,ip,device_identity,user_id,collected_at,id,created_at,updated_at FROM risk_scopes');
    DB::statement("INSERT INTO risk_scope_events (id,scope_id,ip,event_type,active_after,occurred_at,created_at,updated_at) SELECT id,id,ip,'intake',IF(state='active',1,0),collected_at,created_at,updated_at FROM risk_scopes");
    DB::statement("INSERT INTO risk_releases (id,ip,released_at,status,rule_id,rule_version,resource_version,idempotency_key,evidence,explanation,ip_fully_released,created_at,updated_at) SELECT n,$ip,$at,IF(MOD(n,5)=0,'revoked','released'),'continuous-use-release','1',1,CONCAT('fixture-release-',n),JSON_OBJECT(),'synthetic audit evidence',1,$at,$at FROM _audit_seq WHERE n<=20000");
    DB::statement("INSERT INTO risk_release_scopes (release_id,scope_id,app_id,app_name,device_identity,install_id,user_id,market_channel,reason_codes,created_at,updated_at) SELECT id,id,app_id,'Synthetic app',device_identity,install_id,user_id,market_channel,reason_codes,created_at,updated_at FROM risk_scopes WHERE id<=20000");
    DB::statement("INSERT INTO risk_revocations (release_id,request_id,operator_id,operator_name,revoked_at,result,created_at,updated_at) SELECT id,CONCAT('fixture-revoke-',id),1,'synthetic',released_at,'restored',created_at,updated_at FROM risk_releases WHERE MOD(id,5)=0");
    DB::statement("INSERT INTO risk_ip_exits (ip,exited_at,release_id,status,created_at,updated_at) SELECT ip,released_at,id,'effective',created_at,updated_at FROM risk_releases WHERE status='released'");
    DB::statement("INSERT INTO risk_ip_dossiers (ip,state,current_decision,active_scope_count,device_count,account_count,markets,apps,tags,collected_at,collection_known,first_risk_at,last_activity_at,created_at,updated_at) SELECT ip,IF(SUM(state='active')=0,'released','high'),'block',SUM(state='active'),COUNT(DISTINCT device_identity),COUNT(DISTINCT user_id),JSON_ARRAY(JSON_OBJECT('value','huawei','label','Huawei')),JSON_ARRAY(JSON_OBJECT('id','10027','name','Synthetic app')),JSON_ARRAY(JSON_OBJECT('code','root','label','Root','active',true)),MIN(collected_at),1,MIN(collected_at),MAX(updated_at),MIN(created_at),MAX(updated_at) FROM risk_scopes GROUP BY ip");
    for ($i = 0; $i < 3; $i++) {
        $offset = $i * 100000;
        DB::statement("INSERT INTO risk_visits (source_log_id,app_id,ip,occurred_at,decision,created_at,updated_at) SELECT n+$offset,$appId,$ip,$at,ELT(1+MOD(n,4),'pass','verify','limit','block'),$at,$at FROM _audit_seq");
        DB::statement("INSERT INTO risk_timeline (ip,event_type,occurred_at,received_at,app_id,app_name,version_name,market_channel,device_identity,user_id,reason_codes,decision,risk_level,risk_score,scope_id,title,created_at,updated_at) SELECT ip,'risk_hit',collected_at,collected_at,app_id,'Synthetic app',version_name,market_channel,device_identity,user_id,reason_codes,decision,risk_level,risk_score,id,'Synthetic event',created_at,updated_at FROM risk_scopes");
    }
    DB::statement("INSERT INTO users (id,app_id,uuid,account,phone) SELECT n,CAST($appId AS CHAR),$device,CONCAT('fixture-user-',n),'0000000000' FROM _audit_seq WHERE n<=1000");
    DB::statement("INSERT INTO risk_lists (id,list_type,target_type,target_value,app_id,decision,status,remark,operator,created_at,updated_at) SELECT n,IF(MOD(n,2)=0,'blacklist','watchlist'),'device_identity',$device,$appId,'block',1,'Synthetic audit entry','synthetic',$at,$at FROM _audit_seq WHERE n<=20000");
    DB::statement("INSERT INTO system_apps (id,name) SELECT DISTINCT app_id,CONCAT('Synthetic app ',app_id) FROM risk_scopes");
    DB::table('risk_probe_logs')->insert(['id' => 1, 'app_id' => 10027, 'route' => 'api/synthetic', 'request_method' => 'GET', 'status' => 'ok', 'risk_score' => 80, 'risk_reasons' => '["root"]', 'compliance_mode' => 1, 'ad_switch' => 0, 'user_uuid' => md5('synthetic-device-1'), 'device_sn' => 'synthetic-install', 'client_ip' => '198.18.0.1', 'market_channel' => 'huawei', 'app_version' => '1.0.0', 'created_at' => '2026-10-29 23:59:59', 'updated_at' => '2026-10-29 23:59:59']);
    DB::table('risk_ledger_meta')->insert(['id' => 1, 'cursor_id' => 1]);
    DB::table('risk_monitor_accounts')->insert(['account' => 'fixture-user-1', 'subject_name' => 'Synthetic']);
    echo json_encode(['database' => $name, 'scopes' => 100000, 'intakes' => 100000, 'dossiers' => DB::table('risk_ip_dossiers')->count(), 'visits' => 300000, 'timeline' => 300000, 'releases' => 20000, 'hot_ip_scopes' => 10000]),PHP_EOL;
    file_put_contents(sys_get_temp_dir().'/risk-performance-fixture-name', $name);
} catch (Throwable$e) {
    DB::purge('risk_fixture');
    config(['database.default' => 'mysql']);
    DB::statement('DROP DATABASE `'.$name.'`');
    throw $e;
}
