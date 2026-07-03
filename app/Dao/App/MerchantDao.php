<?php

namespace App\Dao\App;

use App\Dao\BaseDao;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Builder;

class MerchantDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return Merchant::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (isset($where['type']) && $where['type'] !== '') {
            $query->where('type', $where['type']);
        }

        if (isset($where['is_enable']) && $where['is_enable'] !== '') {
            $query->where('is_enable', $where['is_enable']);
        }

        if (!empty($where['keyword'])) {
            $keyword = $where['keyword'];
            $query->where(function (Builder $query) use ($keyword) {
                $query->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('domain', 'like', '%' . $keyword . '%')
                    ->orWhere('corporate_phone', 'like', '%' . $keyword . '%')
                    ->orWhere('contact_email', 'like', '%' . $keyword . '%');
            });
        }

        if (!empty($where['time'])) {
            $this->searchDate($query, 'created_at', $where['time']);
        }

        return $query;
    }
}
