<?php

namespace Tests\Unit;

use App\Services\Tianji\EloquentTianjiChatRepository;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class EloquentTianjiChatRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('sqlite unavailable');
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_10_08_140000_create_tianji_chat_tables.php',
            '--force' => true,
        ]);
    }

    public function test_it_stores_a_report_and_returns_messages_in_order(): void
    {
        $repository = new EloquentTianjiChatRepository();
        $session = $repository->createSession(7, 10048, 'data', 'chart-1', null, '我的事业');
        $repository->saveReport($session->id, ['官禄宫' => '武曲']);
        $repository->addMessage($session->id, 'user', '我的事业怎么样', 0, 0, 0, false, true);
        $repository->addMessage($session->id, 'assistant', '【综合解读】', 10, 20, 30, true, true);

        $loaded = $repository->findOwned($session->id, 7, 10048);
        $this->assertSame(['官禄宫' => '武曲'], $loaded->report);
        $this->assertNull($repository->findOwned($session->id, 8, 10048));
        $messages = $repository->messages($session->id, 20);
        $this->assertSame(['user', 'assistant'], [$messages[0]->role, $messages[1]->role]);
        $this->assertSame('我的事业怎么样', $messages[0]->content);
        $this->assertTrue($messages[1]->corrected);

        $latest = $repository->findLatest(7, 10048, 'data', 'chart-1');
        $this->assertSame($session->id, $latest->id);
        $this->assertSame(['官禄宫' => '武曲'], $latest->report);
    }
}
