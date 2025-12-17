<?php

namespace App\Services\Message;

use App\Services\Service;
use App\Dao\Other\TemplateMessageDao;

/**
 * 模板消息管理类
 * Class TemplateMessageServices
 *
 * @package App\Services\Other
 * @method getOne(array $where, ?string $field = '*')  获取一条信息
 * @method save(array $data) 添加
 * @method get(int $id, ?array $field = []) 获取一条信息
 * @method update($id, array $data, ?string $key = null) 更新数据
 * @method delete($id, ?string $key = null) 删除
 */
class TemplateMessageServices extends Service
{
    /**
     * 模板消息
     * TemplateMessageServices constructor.
     *
     * @param TemplateMessageDao $dao
     */
    public function __construct(TemplateMessageDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取模板消息列表
     *
     * @param array $where
     *
     * @return array
     */
    public function getTemplateList(array $where)
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getTemplateList($where, $page, $limit);
        foreach ($list as &$item) {
            if ($item['content']) {
                $item['content'] = explode("\n", $item['content']);
            }
        }
        $count = $this->dao->count($where);

        return compact('list', 'count');
    }

    /**
     * 获取模板消息id
     *
     * @param string $templateId
     * @param int $type
     *
     * @return mixed
     */
    public function getTempId(string $templateId, int $type = 0)
    {
        return $this->dao->value(['type' => $type, 'tempkey' => $templateId, 'status' => 1], 'tempid');
    }
}
