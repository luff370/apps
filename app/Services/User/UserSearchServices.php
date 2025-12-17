<?php

declare (strict_types = 1);

namespace App\Services\User;

use App\Services\Service;
use App\Dao\User\UserSearchDao;

/**
 *
 * Class UserLabelServices
 *
 * @package App\Services\User
 *  * @method getColumn(array $where, string $field, string $key = '') 获取某个字段数组
 *  * @method getKeywordResult(int $uid, string $keyword, int $preTime = 7200) 获取全局|用户某个关键词搜素结果
 */
class UserSearchServices extends Service
{
    /**
     * UserSearchServices constructor.
     *
     * @param UserSearchDao $dao
     */
    public function __construct(UserSearchDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取用户搜索关键词列表
     *
     * @param int $uid
     *
     * @return array
     */
    public function getUserList(int $uid)
    {
        if (!$uid) {
            return [];
        }
        [$page, $limit] = $this->getPageValue();

        return $this->dao->getList(['uid' => $uid, 'is_del' => 0], 'add_time desc,num desc', $page, $limit);
    }

    /**
     * 用户增加搜索记录
     *
     * @param int $uid
     * @param string $key
     * @param array $result
     */
    public function saveUserSearch(int $uid, string $keyword, array $vicword, array $result)
    {
        $result = json_encode($result);
        $vicword = json_encode($vicword, JSON_UNESCAPED_UNICODE);
        $userkeyword = $this->dao->getKeywordResult($uid, $keyword, 0);
        $data = [];
        $data['result'] = $result;
        $data['vicword'] = $vicword;
        $data['add_time'] = time();
        if ($userkeyword) {
            $data['num'] = $userkeyword['num'] + 1;
            $this->dao->update(['id' => $userkeyword['id']], $data);
        } else {
            $data['uid'] = $uid;
            $data['keyword'] = $keyword;
            $this->dao->save($data);
        }

        return true;
    }
}
