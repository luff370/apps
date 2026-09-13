-- 从 user_access_log 回填每个 (app_id, uuid) 的最早出现时间。
-- 已有记录只把 created_at 改得更早。

INSERT INTO user_uuids (app_id, uuid, market_channel, created_at, updated_at)
SELECT
    app_id,
    uuid,
    MIN(IFNULL(market_channel, '')),
    MIN(created_at),
    NOW()
FROM user_access_log
WHERE uuid <> ''
  AND app_id > 0
GROUP BY app_id, uuid
ON DUPLICATE KEY UPDATE
    created_at = LEAST(created_at, VALUES(created_at)),
    updated_at = NOW();
