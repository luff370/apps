<?php

declare (strict_types = 1);

namespace App\Services\User;

use App\Services\Service;
use App\Dao\User\UserGroupDao;
use App\Exceptions\AdminException;
use Illuminate\Support\Facades\Route as Url;
use App\Support\Services\FormBuilder as Form;

/**
 *
 * Class UserGroupServices
 *
 * @package App\Services\User
 */
class UserGroupServices extends Service
{
    /**
     * UserGroupServices constructor.
     *
     * @param UserGroupDao $dao
     */
    public function __construct(UserGroupDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取某一个分组
     *
     * @param int $id
     *
     * @return array|\Illuminate\Database\Eloquent\Model|null
     */
    public function getgroupBy(int $id)
    {
        return $this->dao->get($id);
    }

    /**
     * 获取分组列表
     *
     * @param string $field
     *
     * @return array
     */
    public function getGroupList(string $field = 'id,group_name', bool $is_page = false): array
    {
        $page = $limit = 0;
        if ($is_page) {
            [$page, $limit] = $this->getPageValue();
            $count = $this->dao->count([]);
        }
        $list = $this->dao->getList([], $field, $page, $limit);

        return $is_page ? compact('list', 'count') : $list;
    }

    /**
     * 获取一些用户的分组名称
     *
     * @param array $ids
     *
     * @return array
     */
    public function getUsersGroupName(array $ids)
    {
        return $this->dao->getColumn([['id', 'IN', $ids]], 'group_name', 'id');
    }

    /**
     * 添加/修改分组页面
     *
     * @param int $id
     *
     * @return array
     */
    public function add(int $id)
    {
        $group = $this->getgroupBy($id);
        $field = [];
        if (!$group) {
            $title = '添加分组';
            $field[] = Form::input('group_name', '分组名称', '')->required();
        } else {
            $title = '修改分组';
            $field[] = Form::hidden('id', $id);
            $field[] = Form::input('group_name', '分组名称', $group->getData('group_name'))->required();
        }

        return create_form($title, $field, url('/admin/user/user_group/save'), 'POST');
    }

    /**
     * 添加|修改
     *
     * @param int $id
     * @param array $data
     *
     * @return mixed
     */
    public function save(int $id, array $data)
    {
        $groupName = $this->dao->getOne(['group_name' => $data['group_name']]);
        if ($id) {
            if (!$this->getgroupBy($id)) {
                throw new AdminException(100026);
            }
            if ($groupName && $id != $groupName['id']) {
                throw new AdminException(400666);
            }
            if ($this->dao->update($id, $data)) {
                return true;
            } else {
                throw new AdminException(100007);
            }
        } else {
            unset($data['id']);
            if ($groupName) {
                throw new AdminException(400666);
            }
            if ($this->dao->save($data)) {
                return true;
            } else {
                throw new AdminException(100022);
            }
        }
    }

    /**
     * 删除
     *
     * @param int $id
     *
     * @return string
     */
    public function delgroupBy(int $id)
    {
        if ($this->getgroupBy($id)) {
            if (!$this->dao->delete($id)) {
                throw new AdminException(100008);
            }
        }

        return '删除成功!';
    }
}
