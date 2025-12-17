<?php

namespace App\Http\Controllers\Admin\File;

use Illuminate\Http\Request;
use App\Http\Controllers\Admin\Controller;
use App\Services\System\Attachment\SystemAttachmentServices;

/**
 * 附件管理类
 * Class SystemAttachment
 *
 * @package App\Http\Controllers\file
 */
class SystemAttachmentController extends Controller
{
    /**
     * @var SystemAttachmentServices
     */
    protected $service;

    /**
     * @param SystemAttachmentServices $service
     */
    public function __construct(SystemAttachmentServices $service)
    {
        $this->service = $service;
    }

    /**
     * 显示列表
     */
    public function index()
    {
        $where = $this->getMore([
            ['pid', 0],
        ]);

        return $this->success($this->service->getImageList($where));
    }

    /**
     * 删除指定资源
     */
    public function delete()
    {
        [$ids] = $this->getMore([
            ['ids', ''],
        ], true);
        $this->service->del($ids);

        return $this->success(100002);
    }

    /**
     * 图片上传
     */
    public function upload(Request $request): \Illuminate\Http\JsonResponse
    {
        $pid = $request->get('pid', 0);
        $menuName = $request->get('menu_name', '');
        $type = $request->get('type', '');
        $upload_type = $request->get('upload_type', 0);
        $file = $request->file('file');

        if (empty($file)) {
            return $this->fail('请选择上传的文件');
        }

        $fileUrl = $this->service->upload((int) $pid, $file, $type, $upload_type, $menuName);

        return $this->success(['src' => $fileUrl], '上传成功');
    }

    /**
     * 移动图片
     */
    public function moveImageCate()
    {
        $data = $this->getMore([
            ['pid', 0],
            ['images', ''],
        ]);
        $data['images'] = explode(',', $data['images']);

        $this->service->move($data);

        return $this->success(100034);
    }

    /**
     * 修改文件名
     *
     * @param $id
     */
    public function update($id)
    {
        $realName = request()->post('real_name', '');
        if (!$realName) {
            return $this->fail(400104);
        }
        $this->service->update($id, ['real_name' => $realName]);

        return $this->success(100001);
    }

    /**
     * 获取上传类型
     */
    public function uploadType()
    {
        $data['upload_type'] = (string) sys_config('upload_type', 1);

        return $this->success($data);
    }

    /**
     * 视频分片上传
     */
    public function videoUpload()
    {
        $data = $this->getMore([
            ['chunkNumber', 0],     //第几分片
            ['currentChunkSize', 0],//分片大小
            ['chunkSize', 0],       //总大小
            ['totalChunks', 0],     //分片总数
            ['file', 'file'],       //文件
            ['md5', ''],            //MD5
            ['filename', ''],       //文件名称
        ]);
        $res = $this->service->videoUpload($data, $_FILES['file']);

        return $this->success($res);
    }
}
