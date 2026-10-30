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

## 风控活动采集与释放协议（第二版，2026-10-30）

服务端已实现；本机没有原生 App 代码，本节用于后续客户端联调。所有业务时间展示为北京时间 `Asia/Shanghai`；Unix 时间戳仍表示同一绝对时刻，不要手动加 8 小时。

`POST /api/risk/activity-events/batch` 复用现有 `Device-Env` 验签/防重放链路。请求携带 App-Id、Uuid（或 Device-Sn）、App-Version、Market-Channel；已登录用户携带实际 Token。账号只能由有效登录态解析，服务端不信任请求体自行声明的 user_id。

```json
{
  "events": [
    {
      "event_id": "installation-random-event-id-001",
      "session_id": "foreground-session-random-id",
      "event_type": "interaction",
      "occurred_at": 1793363400,
      "event_seq": 1,
      "monotonic_elapsed_ms": 0,
      "foreground": true,
      "screen_interactive": true,
      "interaction": true
    }
  ]
}
```

- `event_id`、`session_id` 各不超过 128 字节。event_id 在 App/设备内唯一，网络重试沿用原值；返回 accepted/duplicated/rejected。每批最多处理 100 条，客户端超量需自行分批。
- `occurred_at` 支持 Unix 秒或毫秒；`event_seq` 在同一会话中严格递增且连续，`monotonic_elapsed_ms` 来自原生单调时钟。切会话使用新 session_id。发生丢包可重试同事件，不得重新排序后伪造连续序号。
- `event_type` 支持 login/access/interaction/foreground/background/heartbeat。login 需要有效 Token；无登录态时只记匿名访问。登录/访问事实与纯心跳分开，统计不能从任意请求或触摸累计值猜访问量。
- 前台建议每 30 秒报告一次，在前后台、锁屏/解锁和实际用户交互时报告真实状态；空闲时不伪造 interaction。退出后台和锁屏时间不累计。断档大于 90 秒不补算；空闲 300 秒停止计时；后台持续 300 秒切断连续使用（均为当前联调默认参数）。
- 在线验证要求本次 Device-Env 校验成功、字段齐全、事件与服务器接收相差不超过 120 秒。有效时长同时受事件时间差、单调时钟差和服务器接收时间差限制。离线补传可存档，但不能按客户端声称的历史时长直接累计 30 分钟。
- 达到有效前台 1800 秒后仍需新的在线实际交互，且不存在测试账号/审核访问/强制黑名单等禁止条件，才会释放对应 App/设备。未知审核状态、旧采集协议或证据不足保持不释放。
- 撤销释放后必须使用撤销之后的新证据重新累计；不能重复利用上次那 30 分钟。服务端会轮转检查有效范围，也在新活动入库后评估当前设备。

如果只需要给一次实际登录/访问生成事实，可以在已签名探针中增加 `vi`（visit_id）与 `et`（login/access），同一业务访问重试沿用 visit_id。它与批量活动事件若描述同一次访问，应使用相同 event_id/visit_id，以便服务端按 App、设备、事件 ID 去重。不能把 heartbeat 报成 access。

验收至少覆盖：正常连续前台、锁屏、长后台、空闲、断网补传、时钟跳变、丢序号、跨设备/跨 App 同 ID、重复请求、匿名登录事件、审核中访问后上架、测试账号删除配置、释放后新风险及撤销后的重试。服务端验签和接收时间约束不能替代原生真实传感器采集，也不证明设备不可被篡改。

### 异步消费补充

活动接口 accepted 表示原始事件已保存，衍生事实、账号关联、时间线和释放评估由独立 Laravel 风控 Worker 异步完成。客户端不应把 accepted 当作已经解除风控；后续 `/app/info` 以服务端实际返回策略为准。事件本身的发生时间、服务器接收时间和在线验证结果不会因为排队而重写，后台处理耗时不计入有效使用时长。

部署 Worker 与恢复命令见 `docs/risk-management-performance-plan-2026-10-30.md` 第 13 节。
