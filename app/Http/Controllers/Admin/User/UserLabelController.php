<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Admin\Controller;
use App\Services\User\UserLabelServices;
use App\Services\User\UserLabelCateServices;
use App\Services\User\UserLabelRelationServices;

/**
 * 用户标签控制器
 * Class UserLabel
 *
 * @package App\Http\Controllers\Admin\User
 */
class UserLabelController extends Controller
{
    /**
     * UserLabel constructor.
     *
     * @param UserLabelServices $service
     */
    public function __construct(UserLabelServices $services)
    {
        $this->service = $services;
    }

    /**
     * 标签列表
     */
    public function index($label_cate = 0)
    {
        return $this->success($this->service->getList(['label_cate' => $label_cate]));
    }

    /**
     * 添加修改标签表单
     *
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function add()
    {
        [$id, $cateId] = $this->getMore([
            ['id', 0],
            ['cate_id', 0],
        ], true);

        return $this->success($this->service->add((int) $id, (int) $cateId));
    }

    /**
     * 保存标签表单数据
     *
     * @param int $id
     */
    public function save()
    {
        $data = $this->getMore([
            ['id', 0],
            ['label_cate', 0],
            ['label_name', ''],
        ]);
        if (!$data['label_name'] = trim($data['label_name'])) {
            return $this->fail(400322);
        }
        $this->service->save((int) $data['id'], $data);

        return $this->success(100000);
    }

    /**
     * 删除
     *
     * @param $id
     *
     * @throws \Exception
     */
    public function delete()
    {
        [$id] = $this->getMore([
            ['id', 0],
        ], true);
        if (!$id) {
            return $this->fail(100100);
        }
        $this->service->delLabel((int) $id);

        return $this->success(100002);
    }

    /**
     * 标签分类
     *
     * @param UserLabelCateServices $services
     */
    public function getUserLabel(UserLabelCateServices $services, $uid)
    {
        return $this->success($services->getUserLabel((int) $uid));
    }

    /**
     * 设置用户标签
     *
     * @param UserLabelRelationServices $services
     * @param $uid
     */
    public function setUserLabel(UserLabelRelationServices $services, $uid)
    {
        [$labels, $unLabelIds] = $this->getMore([
            ['label_ids', []],
            ['un_label_ids', []],
        ], true);
        if (!count($labels) && !count($unLabelIds)) {
            return $this->fail(100100);
        }
        if ($services->setUserLable($uid, $labels) && $services->unUserLabel($uid, $unLabelIds)) {
            return $this->success(100014);
        } else {
            return $this->fail(100015);
        }
    }

    /**
     * 获取带分类的用户标签列表
     *
     * @param \App\Services\User\label\UserLabelCateServices $userLabelCateServices
     */
    public function tree_list(UserLabelCateServices $userLabelCateServices)
    {
        $cate = $userLabelCateServices->getLabelCateAll();
        $data = [];
        $label = [];
        if ($cate) {
            foreach ($cate as $value) {
                $data[] = [
                    'id' => $value['id'] ?? 0,
                    'value' => $value['id'] ?? 0,
                    'label_cate' => 0,
                    'label_name' => $value['name'] ?? '',
                    'label' => $value['name'] ?? '',
                    'store_id' => $value['store_id'] ?? 0,
                    'type' => $value['type'] ?? 1,
                ];
            }
            $label = $this->service->getList(['type' => 1]);
            $label = $label['list'] ?? [];
            if ($label) {
                foreach ($label as &$item) {
                    $item['label'] = $item['label_name'];
                    $item['value'] = $item['id'];
                }
            }
        }

        return $this->success($this->service->get_tree_children($data, $label));
    }
}
