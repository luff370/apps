<?php

namespace App\Support\Services;

use App\Models\ContentCategory;

class CategoryService
{
    public static function getAllByAppIdAndColumn($appId, $column): array
    {
        return ContentCategory::query()
            ->where('app_id', $appId)
            ->where('column', $column)
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'desc')
            ->get(['id', 'title', 'image'])
            ->toArray();
    }

    public static function getIdToTitleByAppIdAndColumn($appId, $column): array
    {
        $data = self::getAllByAppIdAndColumn($appId, $column);

        return array_column($data, 'title', 'id');
    }
}
