<?php

namespace Tests\Unit;

use App\Dao\App\AppConfigDao;
use App\Exceptions\AdminException;
use App\Models\AppConfig;
use App\Services\App\AppConfigService;
use PHPUnit\Framework\TestCase;

class AppConfigServiceTest extends TestCase
{
    public function test_save_rejects_duplicate_key_in_same_app_and_channel(): void
    {
        $data = [
            'app_id' => 10001,
            'channel' => 'huawei',
            'version' => '1.0.0',
            'name' => '开关',
            'key' => 'feature_switch',
            'value' => '1',
            'remark' => '',
            'is_enable' => 1,
        ];

        $dao = $this->createMock(AppConfigDao::class);
        $dao->expects($this->once())
            ->method('existsByUniqueKey')
            ->with($data, 0)
            ->willReturn(true);
        $dao->expects($this->never())->method('save');

        $this->expectException(AdminException::class);
        $this->expectExceptionMessage('同一应用、同一渠道下参数key不能重复');

        (new AppConfigService($dao))->save($data);
    }

    public function test_update_ignores_current_record_when_checking_unique_key(): void
    {
        $info = new AppConfig([
            'app_id' => 10001,
            'channel' => 'huawei',
            'version' => '1.0.0',
            'name' => '开关',
            'key' => 'feature_switch',
            'value' => '1',
            'remark' => '',
            'is_enable' => 1,
        ]);
        $info->id = 10;

        $updateData = [
            'value' => '0',
        ];

        $uniqueData = [
            'value' => '0',
            'app_id' => 10001,
            'channel' => 'huawei',
            'version' => '1.0.0',
            'key' => 'feature_switch',
        ];

        $dao = $this->createMock(AppConfigDao::class);
        $dao->expects($this->once())
            ->method('get')
            ->with(10)
            ->willReturn($info);
        $dao->expects($this->once())
            ->method('existsByUniqueKey')
            ->with($uniqueData, 10)
            ->willReturn(false);
        $dao->expects($this->once())
            ->method('update')
            ->with(10, $uniqueData, '')
            ->willReturn(1);

        $this->assertSame(1, (new AppConfigService($dao))->update(10, $updateData));
    }

    public function test_copy_returns_create_form_with_original_values_without_saving(): void
    {
        $info = new AppConfig([
            'app_id' => 10001,
            'channel' => 'huawei',
            'version' => '1.0.0',
            'name' => '开关',
            'key' => 'feature_switch',
            'value' => '1',
            'remark' => 'remark',
            'is_enable' => 1,
        ]);
        $info->id = 10;

        $dao = $this->createMock(AppConfigDao::class);
        $dao->expects($this->once())
            ->method('get')
            ->with(10)
            ->willReturn($info);
        $dao->expects($this->never())->method('existsByUniqueKey');
        $dao->expects($this->never())->method('save');

        $service = new class($dao) extends AppConfigService {
            public array $params = [];

            public function createForm($params = []): array
            {
                $this->params = $params;

                return [
                    'title' => '添加',
                    'action' => '/admin/app/app_config',
                    'method' => 'POST',
                ];
            }
        };

        $form = $service->copyForm(10);

        $this->assertSame('添加', $form['title']);
        $this->assertSame('POST', $form['method']);
        $this->assertSame('/admin/app/app_config', $form['action']);
        $this->assertSame([
            'app_id' => 10001,
            'channel' => 'huawei',
            'version' => '1.0.0',
            'name' => '开关',
            'key' => 'feature_switch',
            'value' => '1',
            'remark' => 'remark',
            'is_enable' => 0,
        ], $service->params);
    }
}
