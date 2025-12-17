<?php

declare (strict_types = 1);

namespace App\Dao\System\Attachment;

use App\Dao\BaseDao;
use App\Models\SystemAttachment;

/**
 *
 * Class SystemAttachmentDao
 *
 * @package App\Dao\attachment
 */
class SystemAttachmentDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemAttachment::class;
    }

    /**
     * 获取图片列表
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getList(array $where, int $page, int $limit)
    {
        return $this->search($where)->where('module_type', 1)->offset(($page - 1) * $limit)->limit($limit)->orderByRaw('att_id desc')->get()->toArray();
    }

    /**
     * 移动图片
     *
     * @param array $data
     */
    public function move(array $data)
    {
        return $this->getModel()->newQuery()->whereIn('att_id', $data['images'])->update(['pid' => $data['pid']]);
    }

    /**
     * 获取名称
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getLikeNameList(array $where, int $page, int $limit)
    {
        return $this->search($where)->page($page, $limit)->orderByRaw('att_id desc')->get()->toArray();
    }

    /**
     * 获取昨日系统生成
     */
    public function getYesterday()
    {
        return $this->getModel()->newQuery()->whereTime('time', 'yesterday')->where('module_type', 2)->select(['name', 'att_dir', 'att_id', 'image_type'])->get();
    }

    /**
     * 删除昨日生成海报
     *
     * @throws \Exception
     */
    public function delYesterday()
    {
        $this->getModel()->newQuery()->whereTime('time', 'yesterday')->where('module_type', 2)->delete();
    }
}
