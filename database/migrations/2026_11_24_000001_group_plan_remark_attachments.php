<?php

use App\Models\SystemAttachment;
use App\Services\System\Attachment\SystemAttachmentCategoryServices;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_attachment_category') || !Schema::hasTable('system_attachment')) {
            return;
        }

        $categoryId = app(SystemAttachmentCategoryServices::class)->planRemarkCategoryId();
        DB::table('system_attachment')
            ->where('pid', 0)
            ->where('module_type', 1)
            ->where('name', 'like', '%/plan-remark-%')
            ->select('att_id', 'name')
            ->chunkById(500, function ($attachments) use ($categoryId) {
                $ids = $attachments->filter(fn($attachment) => SystemAttachment::isPlanRemarkPath($attachment->name))
                    ->pluck('att_id');
                if ($ids->isNotEmpty()) {
                    DB::table('system_attachment')->where('pid', 0)->whereIn('att_id', $ids)
                        ->update(['pid' => $categoryId]);
                }
            }, 'att_id');
    }

    public function down(): void
    {
        // 保留素材分类和已整理的附件。
    }
};
