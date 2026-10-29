<?php

namespace Tests\Unit;

use App\Services\Risk\Ledger\IpNormalizer;
use App\Services\Risk\Ledger\ScopeReplay;
use App\Services\Risk\Ledger\UsageWindow;
use PHPUnit\Framework\TestCase;

class RiskLedgerRulesTest extends TestCase
{
    public function test_ip_normalization_collapses_aliases_and_rejects_chains(): void
    {
        $this->assertSame('1.2.3.4', IpNormalizer::normalize('01.2.3.4'));
        $this->assertSame('', IpNormalizer::normalize('192.168.1.1, 10.0.0.1'));
        $this->assertSame('', IpNormalizer::normalize('192.168.0.0/24'));
        $this->assertSame(
            IpNormalizer::normalize('2001:db8::1'),
            IpNormalizer::normalize('2001:0db8:0000:0000:0000:0000:0000:0001')
        );
        $this->assertSame('2001:db8::1', IpNormalizer::ipFor(IpNormalizer::idFor('2001:db8::1')));
    }

    public function test_continuous_foreground_use_qualifies_only_after_a_later_interaction(): void
    {
        $events = [];
        for ($i = 0; $i <= 61; $i++) {
            $events[] = $this->point($i * 30, $i, 'session-a');
        }
        $result = UsageWindow::measure($events);

        $this->assertTrue($result['qualified']);
        $this->assertGreaterThanOrEqual(1800, $result['effective_seconds']);
        $this->assertGreaterThan($result['threshold_reached_at'], $result['qualifying_interaction_at']);
    }

    public function test_sparse_requests_and_split_sessions_do_not_add_up(): void
    {
        $sparse = [
            $this->point(0, 1, 's'),
            $this->point(1800, 2, 's'),
            $this->point(3600, 3, 's'),
        ];
        $this->assertFalse(UsageWindow::measure($sparse)['qualified']);

        $split = [];
        for ($i = 0; $i <= 40; $i++) {
            $split[] = $this->point($i * 30, $i, 'a');
        }
        for ($i = 0; $i <= 40; $i++) {
            $split[] = $this->point(2000 + $i * 30, 100 + $i, 'b');
        }
        $this->assertFalse(UsageWindow::measure($split)['qualified']);

        $idle = [$this->point(0, 1, 's', true)];
        for ($i = 1; $i <= 20; $i++) {
            $idle[] = $this->point($i * 30, $i + 1, 's', false);
        }
        $idleResult = UsageWindow::measure($idle);
        $this->assertFalse($idleResult['qualified']);
        $this->assertLessThanOrEqual(300, $idleResult['effective_seconds']);
    }

    public function test_partial_release_keeps_ip_active_until_every_scope_is_gone(): void
    {
        $events = [
            ['id' => 1, 'scope_id' => 1, 'ip' => '198.51.100.10', 'at' => 10, 'active_after' => true],
            ['id' => 2, 'scope_id' => 2, 'ip' => '198.51.100.10', 'at' => 11, 'active_after' => true],
            ['id' => 3, 'scope_id' => 1, 'ip' => '198.51.100.10', 'at' => 20, 'active_after' => false],
        ];

        $this->assertSame(1, ScopeReplay::activeIpCount($events, 30));
        $events[] = ['id' => 4, 'scope_id' => 2, 'ip' => '198.51.100.10', 'at' => 40, 'active_after' => false];
        $this->assertSame(0, ScopeReplay::activeIpCount($events, 50));
        $events[] = ['id' => 5, 'scope_id' => 2, 'ip' => '198.51.100.10', 'at' => 60, 'active_after' => true];
        $this->assertSame(0, ScopeReplay::activeIpCount($events, 50));
        $this->assertSame(1, ScopeReplay::activeIpCount($events, 70));
    }

    public function test_revoking_one_exit_does_not_erase_another_exit_for_the_same_ip(): void
    {
        $exits = [
            ['ip' => '198.51.100.10', 'at' => 100, 'revoked_at' => 150],
            ['ip' => '198.51.100.10', 'at' => 120, 'revoked_at' => null],
            ['ip' => '198.51.100.11', 'at' => 130, 'revoked_at' => 180],
        ];

        $this->assertSame(1, ScopeReplay::netReleasedIps($exits, 90, 140, 200));
        $this->assertSame(2, ScopeReplay::netReleasedIps($exits, 90, 140, 140));
    }

    private function point(int $at, int $seq, string $session, bool $interaction = true): array
    {
        return [
            'at' => $at,
            'seq' => $seq,
            'session_id' => $session,
            'foreground' => true,
            'screen_interactive' => true,
            'interaction' => $interaction,
        ];
    }
}
