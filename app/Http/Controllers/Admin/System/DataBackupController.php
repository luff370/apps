<?php

namespace App\Http\Controllers\Admin\System;

use think\facade\Session;
use App\Http\Controllers\Admin\Controller;
use App\Services\System\SystemDatabackupServices;

/**
 * 数据备份
 * Class SystemDatabackup
 *
 * @package app\admin\controller\system
 *
 */
class DataBackupController extends Controller
{
    /**
     * 构造方法
     * SystemDatabackup constructor.
     *
     * @param SystemDatabackupServices $services
     */
    public function __construct(SystemDatabackupServices $services)
    {
        $this->service = $services;
    }

    /**
     * 获取数据库表
     */
    public function index()
    {
        return $this->success($this->service->getDataList());
    }

    /**
     * 查看表结构 详情
     */
    public function read()
    {
        $tablename = request()->get('tablename', '', 'htmlspecialchars');

        return $this->success($this->service->getRead($tablename));
    }

    /**
     * 优化表
     */
    public function optimize()
    {
        $tables = request()->get('tables', '', 'htmlspecialchars');
        $res = $this->service->getDbBackup()->optimize($tables);

        return $this->success($res ? 100047 : 100048);
    }

    /**
     * 修复表
     */
    public function repair()
    {
        $tables = request()->get('tables', '', 'htmlspecialchars');
        $res = $this->service->getDbBackup()->repair($tables);

        return $this->success($res ? 100049 : 100050);
    }

    /**
     * 备份表
     */
    public function backup()
    {
        $tables = request()->get('tables', '', 'htmlspecialchars');
        $data = $this->service->backup($tables);

        return $this->success(100051);
    }

    /**
     * 获取备份记录表
     */
    public function fileList()
    {
        return $this->success($this->service->getBackup());
    }

    /**
     * 删除备份记录表
     */
    public function delFile()
    {
        $filename = intval(request()->post('filename'));
        $files = $this->service->getDbBackup()->delFile($filename);

        return $this->success(100002);
    }

    /**
     * 导入备份记录表
     */
    public function import()
    {
        [$part, $start, $time] = $this->getMore([
            [['part', 'd'], 0],
            [['start', 'd'], 0],
            [['time', 'd'], 0],
        ], true);
        $db = $this->service->getDbBackup();
        if (is_numeric($time) && !$start) {
            $list = $db->getFile('timeverif', $time);
            if (is_array($list)) {
                session::set('backup_list', $list);

                return $this->success(400307, ['part' => 1, 'start' => 0]);
            } else {
                return $this->fail(400308);
            }
        } else {
            if (is_numeric($part) && is_numeric($start) && $part && $start) {
                $list = session::get('backup_list');
                $start = $db->setFile($list)->import($start);
                if (false === $start) {
                    return $this->fail(400309);
                } elseif (0 === $start) {
                    if (isset($list[++$part])) {
                        $data = ['part' => $part, 'start' => 0];

                        return $this->success(400310, $data);
                    } else {
                        session::delete('backup_list');

                        return $this->success(400311);
                    }
                } else {
                    $data = ['part' => $part, 'start' => $start[0]];
                    if ($start[1]) {
                        $rate = floor(100 * ($start[0] / $start[1]));

                        return $this->success(400310, $data);
                    } else {
                        $data['gz'] = 1;

                        return $this->success(400310, $data);
                    }
                }
            } else {
                return $this->fail(100100);
            }
        }
    }

    /**
     * 下载备份记录表
     */
    public function downloadFile()
    {
        $time = intval(request()->get('time'));

        return $this->success(['key' => $this->service->getDbBackup()->downloadFile($time, 0, true)]);
    }
}
