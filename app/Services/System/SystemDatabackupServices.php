<?php

namespace App\Services\System;

use App\Services\Service;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use App\Support\Services\MysqlBackupServices;

/**
 * 数据库备份
 * Class SystemDatabackupServices
 *
 * @package App\Services\System
 */
class SystemDatabackupServices extends Service
{
    /**
     *
     * @var MysqlBackupServices
     */
    protected $dbBackup;

    /**
     * 构造方法
     * SystemDatabackupServices constructor.
     */
    public function __construct()
    {
        $this->dbBackup = app(MysqlBackupServices::class, [
            [
                //数据库备份卷大小
                'compress' => 1,
                //数据库备份文件是否启用压缩 0不压缩 1 压缩
                'level' => 5,
            ],
        ]);
    }

    /**
     * 获取数据库列表
     *
     * @return array
     * @throws \think\db\exception\BindParamException
     */
    public function getDataList()
    {
        $list = $this->dbBackup->dataList();
        $count = count($list);

        return compact('list', 'count');
    }

    /**
     * 获取表详情
     *
     * @param string $tablename
     *
     * @return array
     */
    public function getRead(string $tablename)
    {
        $database = Env::get("database.database");
        $list = Db::query("select * from information_schema.columns where table_name = '" . $tablename . "' and table_schema = '" . $database . "'");
        $count = count($list);
        foreach ($list as $key => $f) {
            $list[$key]['EXTRA'] = ($f['EXTRA'] == 'auto_increment' ? '是' : ' ');
        }

        return compact('list', 'count');
    }

    /**
     * @return MysqlBackupServices
     */
    public function getDbBackup()
    {
        return $this->dbBackup;
    }

    /**
     * 备份表
     *
     * @param string $tables
     *
     * @return string
     * @throws \think\db\exception\BindParamException
     */
    public function backup(string $tables)
    {
        $tables = explode(',', $tables);
        $data = '';
        ini_set("memory_limit", "-1");
        foreach ($tables as $t) {
            $res = $this->dbBackup->backup($t, 0);
            if ($res == false && $res != 0) {
                $data .= $t . '|';
            }
        }

        return $data;
    }

    /**
     * 获取备份列表
     *
     * @return array
     */
    public function getBackup()
    {
        $files = $this->dbBackup->fileList();
        $data = [];
        foreach ($files as $key => $t) {
            $data[$key]['filename'] = $t['filename'];
            $data[$key]['part'] = $t['part'];
            $data[$key]['size'] = $t['size'] . 'B';
            $data[$key]['compress'] = $t['compress'];
            $data[$key]['backtime'] = $key;
            $data[$key]['time'] = $t['time'];
        }
        krsort($data);//根据时间降序

        return ['count' => count($data), 'list' => array_values($data)];
    }
}
