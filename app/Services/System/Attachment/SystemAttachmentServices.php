<?php

declare (strict_types = 1);

namespace App\Services\System\Attachment;

use App\Services\Service;
use App\Exceptions\AdminException;
use App\Exceptions\UploadException;
use App\Services\Other\UploadService;
use Illuminate\Support\Facades\Storage;
use App\Dao\System\Attachment\SystemAttachmentDao;

/**
 *
 * Class SystemAttachmentServices
 *
 * @package App\Services\attachment
 * @method getYesterday() 获取昨日生成数据
 * @method delYesterday() 删除昨日生成数据
 */
class SystemAttachmentServices extends Service
{
    /**
     * SystemAttachmentServices constructor.
     *
     * @param SystemAttachmentDao $dao
     */
    public function __construct(SystemAttachmentDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取单个资源
     *
     * @param array $where
     * @param string $field
     *
     * @return array
     */
    public function getInfo(array $where, string $field = '*')
    {
        return $this->dao->getOne($where, $field);
    }

    /**
     * 获取图片列表
     *
     * @param array $where
     *
     * @return array
     *
     */
    public function getImageList(array $where)
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getList($where, $page, $limit);
        $site_url = sys_config('site_url');
        foreach ($list as &$item) {
            if ($site_url) {
                $item['satt_dir'] = (strpos($item['satt_dir'], $site_url) !== false || strstr($item['satt_dir'], 'http') !== false) ? $item['satt_dir'] : $site_url . $item['satt_dir'];
                $item['att_dir'] = (strpos($item['att_dir'], $site_url) !== false || strstr($item['att_dir'], 'http') !== false) ? $item['satt_dir'] : $site_url . $item['att_dir'];
            }
        }
        $where['module_type'] = 1;
        $count = $this->dao->count($where);

        return compact('list', 'count');
    }

    /**
     * 删除图片
     *
     * @param string $ids
     */
    public function del(string $ids)
    {
        $ids = explode(',', $ids);
        if (empty($ids)) {
            throw new AdminException(400599);
        }
        foreach ($ids as $v) {
            $attinfo = $this->dao->get((int) $v);
            if ($attinfo) {
                try {
                    $upload = UploadService::init($attinfo['image_type']);
                    if ($attinfo['image_type'] == 1) {
                        if (strpos($attinfo['att_dir'], '/') == 0) {
                            $attinfo['att_dir'] = substr($attinfo['att_dir'], 1);
                        }
                        if ($attinfo['att_dir']) {
                            $upload->delete($attinfo['att_dir']);
                        }
                    } else {
                        if ($attinfo['name']) {
                            $upload->delete($attinfo['name']);
                        }
                    }
                } catch (\Throwable $e) {
                }
                $this->dao->delete((int) $v);
            }
        }
    }

    /**
     * 图片上传
     *
     * @param int $pid
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $upload_type
     * @param string $type
     * @param $menuName
     *
     * @return string
     * @throws \App\Exceptions\UploadException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function upload(int $pid, \Illuminate\Http\UploadedFile $file, string $type,  $upload_type = 0,   $menuName = ''): string
    {
        // 阿里云类型
        // if ($upload_type == 0) {
        //     $upload_type = sys_config('upload_type', 1);
        // }
        // if ($menuName == 'weixin_ckeck_file' || $menuName == 'ico_path') {
        //     $upload_type = 1;
        //     $realName = true;
        // }

        // 证书上传
        if ($type == 'cert') {
            $storage = Storage::disk('local');
            $uploadPath = DS . 'cert' . DS . request()->get('cert_type', date('Y')) . DS . request()->get('app_id',date('Ymd'));

            return $storage->putFileAs($uploadPath, $file, $file->getClientOriginalName());
        }

        // excel上传
        if ($type == 'excel') {
            $storage = Storage::disk('local');
            $uploadPath = DS . 'excel' . DS . request()->get('app_id',date('Ymd'));

            return $storage->putFileAs($uploadPath, $file, $file->getClientOriginalName());
        }

        $upload_type = 1;
        try {
            $storage = Storage::disk('public');
            $uploadPath = DS . 'attach' . DS . date('Y') . DS . date('m');
            $path = $storage->putFile($uploadPath, $file);
            $url = $storage->url($path);

            $fileType = $file->getClientOriginalExtension();
            if (in_array($fileType, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'heif', 'heic', 'svg'])) {
                $data['name'] = $path;
                $data['real_name'] = $file->getClientOriginalName();
                $data['att_dir'] = $url;
                $data['satt_dir'] = $url;
                $data['att_size'] = $file->getSize();
                $data['att_type'] = $file->getClientMimeType();
                $data['image_type'] = $upload_type;
                $data['module_type'] = 1;
                $data['time'] = time();
                $data['pid'] = $pid;
                $this->dao->save($data);
            }

            return $url;
        } catch (\Exception $e) {
            throw new UploadException($e->getMessage());
        }
    }

    /**
     * @param array $data
     *
     * @return \App\Models\Model
     */
    public function move(array $data)
    {
        $res = $this->dao->move($data);
        if (!$res) {
            throw new AdminException(400600);
        }
    }

    /**
     * 添加信息
     *
     * @param array $data
     */
    public function save(array $data)
    {
        $this->dao->save($data);
    }

    /**
     * TODO 添加附件记录
     *
     * @param $name
     * @param $att_size
     * @param $att_type
     * @param $att_dir
     * @param string $satt_dir
     * @param int $pid
     * @param int $imageType
     * @param int $time
     *
     * @return SystemAttachment
     */
    public function attachmentAdd($name, $att_size, $att_type, $att_dir, $satt_dir = '', $pid = 0, $imageType = 1, $time = 0, $module_type = 1)
    {
        $data['name'] = $name;
        $data['att_dir'] = $att_dir;
        $data['satt_dir'] = $satt_dir;
        $data['att_size'] = $att_size;
        $data['att_type'] = $att_type;
        $data['image_type'] = $imageType;
        $data['module_type'] = $module_type;
        $data['time'] = $time ?: time();
        $data['pid'] = $pid;
        if (!$this->dao->save($data)) {
            throw new AdminException(100022);
        }

        return true;
    }

    /**
     * 推广名片生成
     *
     * @param $name
     */
    public function getLikeNameList($name)
    {
        return $this->dao->getLikeNameList(['like_name' => $name], 0, 0);
    }

    /**
     * 清除昨日海报
     *
     * @return bool
     * @throws \Exception
     */
    public function emptyYesterdayAttachment()
    {
        try {
            $list = $this->dao->getYesterday();
            foreach ($list as $key => $item) {
                $upload = UploadService::init((int) $item['image_type']);
                if ($item['image_type'] == 1) {
                    $att_dir = $item['att_dir'];
                    if ($att_dir && strstr($att_dir, 'uploads') !== false) {
                        if (strstr($att_dir, 'http') === false) {
                            $upload->delete($att_dir);
                        } else {
                            $filedir = substr($att_dir, strpos($att_dir, 'uploads'));
                            if ($filedir) {
                                $upload->delete($filedir);
                            }
                        }
                    }
                } else {
                    if ($item['name']) {
                        $upload->delete($item['name']);
                    }
                }
            }
            $this->dao->delYesterday();

            return true;
        } catch (\Exception $e) {
            $this->dao->delYesterday();

            return true;
        }
    }

    /**
     * 视频分片上传
     *
     * @param $data
     * @param $file
     *
     * @return mixed
     */
    public function videoUpload($data, $file)
    {
        $public_dir = app()->getRootPath() . 'public';
        $dir = '/uploads/attach/' . date('Y') . DIRECTORY_SEPARATOR . date('m') . DIRECTORY_SEPARATOR . date('d');
        $all_dir = $public_dir . $dir;
        if (!is_dir($all_dir)) {
            mkdir($all_dir, 0777, true);
        }
        $filename = $all_dir . '/' . $data['filename'] . '__' . $data['chunkNumber'];
        move_uploaded_file($file['tmp_name'], $filename);
        $res['code'] = 0;
        $res['msg'] = 'error';
        $res['file_path'] = '';
        if ($data['chunkNumber'] == $data['totalChunks']) {
            $blob = '';
            for ($i = 1; $i <= $data['totalChunks']; $i++) {
                $blob .= file_get_contents($all_dir . '/' . $data['filename'] . '__' . $i);
            }
            file_put_contents($all_dir . '/' . $data['filename'], $blob);
            for ($i = 1; $i <= $data['totalChunks']; $i++) {
                @unlink($all_dir . '/' . $data['filename'] . '__' . $i);
            }
            if (file_exists($all_dir . '/' . $data['filename'])) {
                $res['code'] = 2;
                $res['msg'] = 'success';
                $res['file_path'] = sys_config('site_url') . $dir . '/' . $data['filename'];
            }
        } else {
            if (file_exists($all_dir . '/' . $data['filename'] . '__' . $data['chunkNumber'])) {
                $res['code'] = 1;
                $res['msg'] = 'waiting';
                $res['file_path'] = '';
            }
        }

        return $res;
    }
}
