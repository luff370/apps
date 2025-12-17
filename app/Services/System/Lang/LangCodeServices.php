<?php

namespace App\Services\System\Lang;

use App\Services\Service;
use App\Exceptions\AdminException;
use App\Dao\System\Lang\LangCodeDao;
use App\Support\Services\CacheService;

class LangCodeServices extends Service
{
    /**
     * @param LangCodeDao $dao
     */
    public function __construct(LangCodeDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 语言列表
     *
     * @param array $where
     *
     * @return array
     */
    public function langCodeList(array $where = [])
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->selectList($where, '*', $page, $limit, 'id desc', true)->toArray();
        /** @var LangTypeServices $langTypeServices */
        $langTypeServices = app(LangTypeServices::class);
        $typeList = $langTypeServices->getColumn([['status', '=', 1], ['is_del', '=', 0]], 'language_name,file_name,id', 'id');
        $langType = [
            'isAdmin' => [
                ['title' => '用户端页面', 'value' => 0],
                ['title' => '后端接口', 'value' => 1],
            ],
        ];
        foreach ($typeList as $value) {
            $langType['langType'][] = ['title' => $value['language_name'] . '(' . $value['file_name'] . ')', 'value' => $value['id']];
        }
        foreach ($list as &$item) {
            $item['language_name'] = $typeList[$item['type_id']]['language_name'] . '(' . $typeList[$item['type_id']]['file_name'] . ')';
        }
        $count = $this->dao->count($where);

        return compact('list', 'count', 'langType');
    }

    /**
     * 语言详情
     *
     * @param $code
     *
     * @return array
     */
    public function langCodeInfo($code)
    {
        if (!$code) {
            throw new AdminException(100026);
        }
        /** @var LangTypeServices $langTypeServices */
        $langTypeServices = app(LangTypeServices::class);
        $typeList = $langTypeServices->getColumn([['status', '=', 1], ['is_del', '=', 0]], 'language_name,file_name,id', 'id');
        $list = $this->dao->selectList([['code', '=', $code], ['type_id', 'in', array_column($typeList, 'id')]])->toArray();
        foreach ($list as &$item) {
            $item['language_name'] = $typeList[$item['type_id']]['language_name'] . '(' . $typeList[$item['type_id']]['file_name'] . ')';
        }
        $remarks = $list[0]['remarks'];

        return compact('list', 'code', 'remarks');
    }

    /**
     * 保存修改语言
     *
     * @param $data
     *
     * @return bool
     * @throws \Exception
     */
    public function langCodeSave($data)
    {
        if ($data['edit'] == 0) {
            if ($data['is_admin'] == 1) {
                $code = $this->dao->getMax(['is_admin' => 1], 'code');
                if ($code < 500000) {
                    $code = 500000;
                } else {
                    $code = $code + 1;
                }
            } else {
                $code = $data['remarks'];
            }
        } else {
            $code = $data['code'];
        }
        $saveData = [];
        foreach ($data['list'] as $key => $item) {
            $saveData[$key] = [
                'code' => $code,
                'remarks' => $data['remarks'],
                'lang_explain' => $item['lang_explain'],
                'type_id' => $item['type_id'],
                'is_admin' => $data['is_admin'],
            ];
            if (isset($item['id']) && $item['id'] != 0) {
                $saveData[$key]['id'] = $item['id'];
            }
        }
        $this->dao->saveAll($saveData);
        $this->clearLangCache();

        return true;
    }

    /**
     * 删除语言
     *
     * @param $id
     *
     * @return bool
     */
    public function langCodeDel($id)
    {
        $code = $this->dao->value(['id' => $id], 'code');
        $res = $this->dao->delete(['code' => $code]);
        $this->clearLangCache();
        if ($res) {
            return true;
        }
        throw new AdminException(100008);
    }

    /**
     * 清除语言缓存
     *
     * @return bool
     */
    public function clearLangCache()
    {
        /** @var LangTypeServices $langTypeServices */
        $langTypeServices = app(LangTypeServices::class);
        $typeList = $langTypeServices->getColumn(['status' => 1, 'is_del' => 0], 'file_name');
        foreach ($typeList as $value) {
            $langStr = 'api_lang_' . str_replace('-', '_', $value);
            CacheService::redisHandler()->delete($langStr);
        }

        return true;
    }
}
