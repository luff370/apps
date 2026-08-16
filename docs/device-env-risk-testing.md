# Device-Env 风控逻辑与测试指南

## 1. 整体链路

每个 API 请求先经过 `DeviceEnvRiskMiddleware`：

1. 读取 `Package-Name`、`App-Id` 和 `Device-Env`。
2. 校验线传版本和 HMAC-SHA256 签名。
3. 使用 `Package-Name + App-Id` 派生 AES key/IV，执行 AES-256-CBC 解密。
4. 把短键还原为 `is_monkey`、`touch_sample_count` 等逻辑字段。
5. 校验 `ts` 时间窗口并消费 `nc`，阻止同一密文重放。
6. 对 `probe_v=9` 校验 schema、字段数、摘要和原生协议状态。
7. 对环境信号和行为组合评分，得到 `risk_score`、`reasons`、`compliance_mode`、`ad_switch`。
8. 将结果写入 `risk_probe_logs`。完整探针写入 `probe_json`，行为子集写入 `behavior_json`。
9. `/app/info` 在返回前根据风险结论覆盖 `compliance_mode` 和 `ad_switch`。

缺少或无法解密 `Device-Env` 时，默认不阻断原业务，但会以 `missing` 或 `error` 状态落库。

## 2. 从哪里开始测试

### 第一步：运行自动化测试

不依赖本地 MySQL，先使用内存 SQLite 验证解密、评分和落库：

```bash
DB_CONNECTION=sqlite DB_DATABASE=':memory:' php artisan test \
  tests/Unit/DeviceEnvRiskServiceTest.php \
  tests/Unit/RiskProbeAuditServiceTest.php
```

再运行完整 Unit 套件检查回归：

```bash
DB_CONNECTION=sqlite DB_DATABASE=':memory:' php artisan test --testsuite=Unit
```

重点测试文件：

- `tests/Unit/DeviceEnvRiskServiceTest.php`：加解密、防重放、v9 schema 和评分组合。
- `tests/Unit/RiskProbeAuditServiceTest.php`：完整探针、行为字段、设备码和 nonce 脱敏落库。

### 第二步：执行数据库迁移

在连接到开发数据库后执行：

```bash
php artisan migrate
```

确认存在 `risk_probe_logs`，重点检查以下列：

- `device_sn`：未登录用户的设备标识，`Device-Sn` 缺失时回退 `Uuid`。
- `probe_json`：解密后的完整 Device-Env，不包含明文 `nc`。
- `behavior_json`：触摸、点击、滑动、IMU 行为字段。
- `risk_score` / `risk_reasons`：评分与命中规则。
- `validation_errors`：v9 schema、字段数、摘要或原生协议异常。

### 第三步：使用真实客户端联调

客户端请求任意普通 API 时应携带：

```http
Package-Name: 客户端实际包名
App-Id: 客户端实际 App ID
Device-Sn: 设备码
Uuid: 客户端 UUID
Device-Env: 1.{64位HMAC}.{Base64密文}
```

建议先测试 `/api/app/info`，因为它同时覆盖解密、评分、落库和策略返回。

正常设备预期：

- `risk_probe_logs.status = ok`
- `probe_v = 9`
- `env_digest_ok = 1`
- `env_field_count_ok = 1`
- `risk_score < 40`
- 返回的广告配置不被风控覆盖

高风险设备预期：

- `risk_reasons` 包含对应原因，例如 `monkey`、`hook`、`user_ca`
- `risk_score >= 60`
- `compliance_mode = 1`
- `ad_switch = 0`
- `/app/info` 响应中的 `compliance_mode=1`、`ad_switch=0`

### 第四步：验证行为数据逐步完善

首次 `/app/info` 时用户通常还没有操作，行为样本为 0 属于正常现象。启动后进行点击和滑动，随后请求另一个 API，再查询同一 `device_sn` 的最新记录：

```sql
SELECT id, route, device_sn, risk_score, risk_reasons,
       touch_sample_count, click_sample_count, swipe_sample_count,
       behavior_json, created_at
FROM risk_probe_logs
WHERE device_sn = '实际设备码'
ORDER BY id DESC
LIMIT 20;
```

应看到后续请求中的样本数和 `behavior_json` 逐步增加。

## 3. 必测异常场景

1. 不带 `Device-Env`：业务继续返回，审计记录 `status=missing`。
2. 修改密文任意字符：记录 `status=error`、`device env sign mismatch`。
3. 重复发送完全相同的头：第二次记录 `replayed device env`。
4. 使用错误的 `Package-Name` 或 `App-Id`：验签失败。
5. 使用超过时间窗口的 `ts`：记录 `device env expired`。
6. `env_allows_ads=false`：风险分至少提升到合规阈值。
7. 只有单一行为异常：不应直接进入合规模式。
8. 满足完整行为组合且总分达到阈值：应关闭广告或进入合规模式。

## 4. 评分阈值

默认配置：

```env
DEVICE_ENV_TS_WINDOW=300
DEVICE_ENV_NONCE_TTL=600
DEVICE_ENV_AUDIT_ENABLED=true
DEVICE_ENV_AD_BLOCK_SCORE=40
DEVICE_ENV_COMPLIANCE_SCORE=60
```

- 分数 `< 40`：正常策略。
- 分数 `40-59`：`ad_switch=0`。
- 分数 `>= 60`：`compliance_mode=1` 且 `ad_switch=0`。

修改阈值后需要清理配置缓存：

```bash
php artisan config:clear
```

## 5. 排查顺序

联调失败时按以下顺序检查：

1. `Package-Name`、`App-Id` 是否与客户端生成密文时完全一致。
2. `risk_probe_logs.status` 和 `error`。
3. `validation_errors`、`env_digest_ok`、`env_field_count_ok`。
4. `probe_json` 中短键是否已还原成逻辑字段名。
5. `risk_reasons` 是否符合实际探针值。
6. `/app/info` 是否在最终响应阶段调用 `applyAppInfoPolicy()`。
