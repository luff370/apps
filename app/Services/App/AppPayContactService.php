<?php

namespace App\Services\App;

use App\Dao\App\AppPayContactDao;
use App\Models\AppPayContact;
use App\Services\Service;
use Illuminate\Support\Facades\DB;

class AppPayContactService extends Service
{
    public function __construct(AppPayContactDao $dao)
    {
        $this->dao = $dao;
    }

    public function saveWithApps(array $data, array $appIds, int $id = 0): AppPayContact
    {
        return DB::transaction(function () use ($data, $appIds, $id) {
            if ($id > 0) {
                $row = AppPayContact::query()->find($id);
                if (!$row) {
                    throw new \RuntimeException('联系人不存在');
                }
                $row->fill($data)->save();
            } else {
                $row = AppPayContact::query()->create($data);
            }

            $row->apps()->sync($appIds);

            return $row;
        });
    }

    /**
     * 在当前应用已关联、且启用的联系人里轮询。
     * 每个应用单独计数，优先返回该应用下分配次数最少的一张。
     */
    public function pickNext(int $appId): ?AppPayContact
    {
        return DB::transaction(function () use ($appId) {
            $pivot = DB::table('app_pay_contact_apps as rel')
                ->join('app_pay_contacts as c', 'c.id', '=', 'rel.contact_id')
                ->where('rel.app_id', $appId)
                ->where('c.is_enable', 1)
                ->orderBy('rel.assign_count')
                ->orderBy('c.id')
                ->lockForUpdate()
                ->first(['rel.id as rel_id', 'c.id as contact_id']);

            if (!$pivot) {
                return null;
            }

            DB::table('app_pay_contact_apps')->where('id', $pivot->rel_id)->increment('assign_count');

            return AppPayContact::query()->find($pivot->contact_id);
        });
    }
}
