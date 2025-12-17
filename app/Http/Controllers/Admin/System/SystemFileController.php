<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Admin\Controller;
use App\Services\System\Log\SystemFileServices;

/**
 * 文件校验控制器
 * Class SystemFile
 *
 * @package app\admin\controller\system
 *
 */
class SystemFileController extends Controller
{
    /**
     * 构造方法
     * SystemFile constructor.
     *
     * @param SystemFileServices $services
     */
    public function __construct(SystemFileServices $services)
    {
        $this->service = $services;
    }

    /**
     * 文件校验记录
     */
    public function index()
    {
        return $this->success(['list' => $this->service->getFileList()]);
    }

    /**
     * @date 2022/09/07
     * @author yyw
     */
    public function login()
    {
        [$password] = $this->getMore([
            'password',
        ], true);

        $adminInfo = request()->adminInfo();
        if (!$adminInfo) {
            return $this->fail(100101);
        }
        if ($adminInfo['level'] != 0) {
            return $this->fail(100101);
        }
        if ($password === '') {
            return $this->fail(400256);
        }

        return $this->success($this->service->login($password, 'file_edit'));
    }

    //打开目录
    public function opendir()
    {
        return $this->success($this->service->opendir());
    }

    //读取文件
    public function openfile()
    {
        $file = request()->get('filepath');
        if (empty($file)) {
            return $this->fail(410087);
        }

        return $this->success($this->service->openfile($file));
    }

    //保存文件
    public function savefile()
    {
        $comment = request()->get('comment');
        $filepath = request()->get('filepath');
        if (empty($filepath)) {
            return $this->fail(410087);
        }
        $res = $this->service->savefile($filepath, $comment);
        if ($res) {
            return $this->success(100000);
        } else {
            return $this->fail(100006);
        }
    }

    /**
     * 创建文件夹
     *
     * @date 2022/09/17
     * @author yyw
     */
    public function createFolder()
    {
        [$path, $name] = $this->getMore([
            ['path', ''],
            ['name', ''],
        ], true);
        if (empty($path) || empty($name)) {
            return $this->fail(410087);
        }
        $data = [];
        try {
            $res = $this->service->createFolder($path, $name);
            if ($res) {
                $data = [
                    'children' => [],
                    'contextmenu' => true,
                    'isDir' => true,
                    'loading' => false,
                    'path' => $path,
                    'pathname' => $path . DS . $name,
                    'title' => $name,
                ];
            } else {
                return $this->fail(100005);
            }
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }

        return $this->success($data);
    }

    /**
     * 创建文件
     *
     * @date 2022/09/17
     * @author yyw
     */
    public function createFile()
    {
        [$path, $name] = $this->getMore([
            ['path', ''],
            ['name', ''],
        ], true);
        if (empty($path) || empty($name)) {
            return $this->fail(410087);
        }
        $data = [];
        try {
            $res = $this->service->createFile($path, $name);
            if ($res) {
                $data = [
                    'children' => [],
                    'contextmenu' => true,
                    'isDir' => false,
                    'loading' => false,
                    'path' => $path,
                    'pathname' => $path . DS . $name,
                    'title' => $name,
                ];
            } else {
                return $this->fail(100005);
            }
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }

        return $this->success($data);
    }

    /**
     * 删除文件或文件夹
     *
     * @date 2022/09/17
     * @author yyw
     */
    public function delFolder()
    {
        [$path] = $this->getMore([
            ['path', ''],
        ], true);
        if (empty($path)) {
            return $this->fail(410087);
        }
        try {
            $this->service->delFolder($path);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }

        return $this->success(100010);
    }

    /**
     * 文件重命名
     *
     * @date 2022/09/28
     * @author yyw
     */
    public function rename()
    {
        [$newname, $oldname] = $this->getMore([
            ['newname', ''],
            ['oldname', ''],
        ], true);
        if (empty($newname) || empty($oldname)) {
            return $this->fail(410087);
        }
        try {
            $this->service->rename($newname, $oldname);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }

        return $this->success(100010);
    }

    public function copyFolder()
    {
        [$surDir, $toDir] = $this->getMore([
            ['surDir', ''],
            ['toDir', ''],
        ], true);
        if (empty($surDir) || empty($toDir)) {
            return $this->fail(410087);
        }
        try {
            return $this->success($this->service->copyFolder($surDir, $toDir));
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }
}
