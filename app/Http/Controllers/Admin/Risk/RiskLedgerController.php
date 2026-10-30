<?php

namespace App\Http\Controllers\Admin\Risk;

use App\Exceptions\AdminException;
use App\Http\Controllers\Admin\Controller;
use App\Services\Risk\Ledger\ReleaseRule;
use App\Services\Risk\MonitorAccountService;
use App\Services\Risk\PublishingMonitorService;
use App\Services\Risk\RiskDossierService;
use App\Services\Risk\RiskProbabilityService;
use App\Services\Risk\RiskReleaseEngine;
use App\Services\Risk\RiskReleaseQuery;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

class RiskLedgerController extends Controller
{
    public function __construct(
        private RiskProbabilityService $probability,
        private RiskDossierService $dossiers,
        private RiskReleaseQuery $releases,
        private RiskReleaseEngine $engine,
        private MonitorAccountService $accounts,
        private PublishingMonitorService $publishing,
    ) {
    }

    public function probabilityBoard()
    {
        [$start, $end] = $this->dates();
        [$page, $limit] = $this->page();

        return $this->success($this->probability->board($start, $end, (int) request()->input('flow_days', 7), (int) request()->input('access_days', 7), $page, $limit));
    }

    public function ledgerStatus()
    {
        return $this->success(app(\App\Services\Risk\RiskLedgerService::class)->progress());
    }

    public function summary()
    {
        [$start, $end] = $this->dates();

        return $this->success($this->probability->summary($start, $end));
    }

    public function trends()
    {
        $kind = (string) request()->input('kind', 'flow');
        $days = (int) request()->input('days', 7);
        $end = request()->input('end_date');

        return $this->success($this->probability->trends($kind, $days, $end ? (string) $end : null));
    }

    public function ranking()
    {
        [$start, $end] = $this->dates();
        [$page, $limit] = $this->page();

        return $this->success($this->probability->ranking($start, $end, $page, $limit));
    }

    public function dossiers()
    {
        [$page, $limit] = $this->page();

        return $this->success($this->dossiers->list([
            'keyword' => request()->input('keyword', ''),
            'market_channel' => request()->input('market_channel', ''),
            'app_id' => request()->input('app_id', ''),
            'reason_code' => request()->input('reason_code', ''),
            'state' => request()->input('state', ''),
            'compact' => request()->boolean('compact'),
            'include_options' => request()->boolean('include_options', true),
        ], $page, $limit));
    }

    public function dossierOptions()
    {
        return $this->success($this->dossiers->options());
    }

    public function dossier($id)
    {
        $data = $this->dossiers->detail((string) $id, request()->boolean('include_relations', true));
        if (! $data) {
            return $this->fail('IP 档案不存在');
        }

        return $this->success($data);
    }

    public function relations($id)
    {
        [$page, $limit] = $this->page();

        return $this->success($this->dossiers->relations((string) $id, (string) request()->input('kind', 'apps'), $page, $limit));
    }

    public function timeline($id)
    {
        [$page, $limit] = $this->page();
        $data = $this->dossiers->timeline((string) $id, [
            'day' => (string) request()->input('day', ''),
            'app_id' => (string) request()->input('app_id', ''),
            'event_kind' => (string) request()->input('event_kind', ''),
            'include_days' => request()->boolean('include_days', true),
            'reason_code' => (string) request()->input('reason_code', ''),
        ], $page, $limit);
        if ($data === null) {
            return $this->fail('IP 档案不存在');
        }

        return $this->success($data);
    }

    public function releaseList()
    {
        [$page, $limit] = $this->page();
        $unregistered = request()->input('unregistered');

        return $this->success($this->releases->list([
            'ip' => request()->input('ip', ''),
            'app_id' => request()->input('app_id', ''),
            'start_date' => request()->input('start_date', ''),
            'end_date' => request()->input('end_date', ''),
            'device_keyword' => request()->input('device_keyword', ''),
            'user_keyword' => request()->input('user_keyword', ''),
            'unregistered' => $unregistered === '1' || $unregistered === 'true' || $unregistered === true,
            'market_channel' => request()->input('market_channel', ''),
            'reason_code' => request()->input('reason_code', ''),
        ], $page, $limit));
    }

    public function releaseDetail($id)
    {
        $data = $this->releases->detail((int) $id);
        if (! $data) {
            return $this->fail('释放记录不存在');
        }

        return $this->success($data);
    }

    public function revoke($id)
    {
        try {
            $result = $this->engine->revoke(
                (int) $id,
                (string) request()->input('request_id', ''),
                (int) request()->input('expected_version', 0),
                (int) adminId(),
                (string) (adminInfo()['real_name'] ?? adminInfo()['account'] ?? '管理员')
            );
        } catch (AdminException $e) {
            return $this->fail($e->getMessage(), null, $e->getCode() ?: 400);
        }

        return $this->success($result);
    }

    public function rules()
    {
        return $this->success(ReleaseRule::payload());
    }

    public function monitorAccounts()
    {
        return $this->success(['list' => $this->accounts->list()]);
    }

    public function storeMonitorAccount()
    {
        try {
            $row = $this->accounts->save(null, (string) request()->input('account', ''), (string) request()->input('subject', request()->input('subject_name', '')), (int) request()->input('app_id', 0));
        } catch (AdminException $e) {
            return $this->fail($e->getMessage());
        } catch (QueryException $e) {
            return $this->fail('测试账号保存失败');
        }

        return $this->success($row);
    }

    public function updateMonitorAccount($id)
    {
        try {
            $row = $this->accounts->save((int) $id, (string) request()->input('account', ''), (string) request()->input('subject', request()->input('subject_name', '')), (int) request()->input('app_id', 0));
        } catch (AdminException $e) {
            return $this->fail($e->getMessage());
        }

        return $this->success($row);
    }

    public function destroyMonitorAccount($id)
    {
        $this->accounts->disable((int) $id);

        return $this->success('已停止监控，历史记录仍保留');
    }

    public function publishingBoard()
    {
        $end = (string) (request()->input('end_date') ?: Carbon::now('Asia/Shanghai')->toDateString());
        $days = (int) request()->input('days', 30);
        if (! in_array($days, [30, 180, 365], true)) {
            $days = 30;
        }
        $start = Carbon::parse($end, 'Asia/Shanghai')->subDays($days - 1)->toDateString();
        $accountId = request()->input('monitor_account_id');

        return $this->success($this->publishing->board($start, $end, $accountId ? (int) $accountId : null, (string) request()->input('cursor', ''), (int) request()->input('limit', 500), (string) request()->input('market_channel', '')));
    }

    public function publishingActivities($id)
    {
        [$page, $limit] = $this->page();
        [$start, $end] = $this->dates();

        return $this->success(app(\App\Services\Risk\PublishingQuery::class)->page($start, $end,
            request()->input('monitor_account_id') ? (int) request()->input('monitor_account_id') : null,
            (string) request()->input('market_channel', ''), $page, $limit, (string) $id, (string) request()->input('category', 'all'), request()->has('anchor_id') ? (int) request()->input('anchor_id') : null));
    }

    private function dates(): array
    {
        $today = Carbon::now('Asia/Shanghai')->toDateString();
        $end = (string) (request()->input('end_date') ?: $today);
        $start = (string) (request()->input('start_date') ?: Carbon::parse($end)->subDays(6)->toDateString());

        return [$start, $end];
    }

    private function page(): array
    {
        $page = max(1, (int) request()->input('page', 1));
        $limit = (int) request()->input('limit', 20);
        $limit = min(100, max(1, $limit));

        return [$page, $limit];
    }
}
