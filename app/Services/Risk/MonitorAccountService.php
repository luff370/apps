<?php

namespace App\Services\Risk;

use App\Exceptions\AdminException;
use Illuminate\Support\Facades\DB;

class MonitorAccountService
{
    public function list(): array
    {
        return DB::table('risk_monitor_accounts')
            ->where('status', 1)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => $this->present($row))
            ->all();
    }

    public function save(?int $id, string $account, string $subject): array
    {
        $account = trim($account);
        $subject = trim($subject);
        if ($account === '' || preg_match('/\s/', $account)) {
            throw new AdminException('请输入不含空格的测试账号');
        }
        $duplicate = DB::table('risk_monitor_accounts')
            ->where('account', $account)
            ->where('subject_name', $subject)
            ->when($id, fn ($query) => $query->where('id', '<>', $id))
            ->where('status', 1)
            ->exists();
        if ($duplicate) {
            throw new AdminException('该测试账号已在监控中');
        }
        if ($id) {
            $current = DB::table('risk_monitor_accounts')->where('id', $id)->first();
            if (!$current) {
                throw new AdminException('监控账号不存在');
            }
            DB::table('risk_monitor_accounts')->where('id', $id)->update([
                'account' => $account,
                'subject_name' => $subject,
                'status' => 1,
                'updated_at' => now(),
            ]);
        } else {
            $existing = DB::table('risk_monitor_accounts')->where('account', $account)->where('subject_name', $subject)->first();
            if ($existing) {
                $id = (int) $existing->id;
                DB::table('risk_monitor_accounts')->where('id', $id)->update(['status' => 1, 'updated_at' => now()]);
            } else {
                $id = DB::table('risk_monitor_accounts')->insertGetId([
                    'account' => $account,
                    'subject_name' => $subject,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $this->present(DB::table('risk_monitor_accounts')->where('id', $id)->first());
    }

    public function disable(int $id): void
    {
        DB::table('risk_monitor_accounts')->where('id', $id)->update(['status' => 0, 'updated_at' => now()]);
    }

    private function present(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'account' => $row->account,
            'subject' => $row->subject_name,
            'subject_name' => $row->subject_name,
            'status' => (int) $row->status,
        ];
    }
}
