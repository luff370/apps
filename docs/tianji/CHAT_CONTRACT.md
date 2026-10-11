# 智能体对话接口

客户端按智能体调用。一个智能体对应一类对话，用 `agent_key` 区分。`content` 是每轮用户提问。应用自己的额外字段写在该智能体的后台配置里，服务端只接收它声明过的字段。

应用 10025 的紫微排盘智能体标识是 `ziwei`，额外字段是选填对象 `report`，内容为用户在应用本地已经排好的命盘报告。

Apifox 导入 `apifox-tianji-chat.openapi.json`。

## 发送一轮对话

`POST /api/chatAI/agent/message`

响应是 `text/event-stream`。参数错误在开流前返回 JSON，HTTP 200，用 `status` 区分。

第一次提问，有命盘就带上 `report`。`session_id` 和 `new_session` 可以不传。

```json
{
  "agent_key": "ziwei",
  "content": "我的事业怎么样？",
  "report": {
    "命宫": "紫微",
    "官禄宫": "武曲",
    "财帛宫": "天府"
  }
}
```

同一条对话接着问时，`report` 可以不带，服务端使用已经保存的那一份。下面两种写法都可以，不必再传 `"new_session": false`。不传 `new_session` 就等于 `false`。

按智能体续接最近一条：

```json
{
  "agent_key": "ziwei",
  "content": "那财运呢"
}
```

指定某一条对话。这时可以不传 `agent_key`：

```json
{
  "session_id": 1,
  "content": "那财运呢"
}
```

| 字段 | 说明 |
|---|---|
| `agent_key` | 智能体标识。不传 `session_id` 时必填 |
| `content` | 用户问题，1 到 2000 字 |
| `session_id` | 续接指定的一条对话。不传时，按当前用户、当前应用和 `agent_key` 续接最近一条 |
| `new_session` | 不传等于 `false`。只有要新开同一智能体的另一条对话时才传 `true` |
| `report` | 紫微排盘声明的本地命盘，选填。第一次做个人分析时传入。同一条对话后续可省略。换一份命盘时再传，会覆盖这条对话里保存的报告 |

`new_session` 为 `true` 时新开一条，不续接最近对话。传入的 `agent_key` 如果和 `session_id` 对应的智能体不一致，会拒绝。

事件顺序：

| 事件 | 内容 |
|---|---|
| `meta` | `session_id`、`agent_key` |
| `delta` | `content`，回答增量 |
| `replace` | `content` 为空字符串。首轮回答缺少免责声明时发出，客户端清空已显示文字 |
| `done` | 完整结果，字段与下方 JSON 相同 |
| `error` | 生成中途失败，例如 `{"status":503,"msg":"智能对话暂时不可用"}` |

`done` 的数据：

```json
{
  "session_id": 1,
  "message_id": 2,
  "agent_key": "ziwei",
  "result": "官禄宫见武曲，事业宜按已有安排推进。\n说明：内容仅供传统文化研究和自我观察参考",
  "sections": {
    "已知资料": "",
    "计算结果": "",
    "知识依据": "",
    "综合解读": "",
    "行动建议": ""
  },
  "valid": true,
  "missing": [],
  "corrected": false
}
```

客户端展示 `result`。回答是简短的大师口吻，结尾保留「说明：内容仅供传统文化研究和自我观察参考」。`sections` 只在回答里出现对应标题时有正文，简短回答这些值为空字符串。

## 对话列表

`POST /api/chatAI/agent/sessions`

请求体可以是空对象。返回当前用户最近 20 条对话，按更新时间倒序。每条包含 `id`、`agent_key`、`title`、`has_report`、`updated_at`。`has_report` 表示这条对话是否已经保存排盘报告。

## 对话记录

`POST /api/chatAI/agent/history`

```json
{ "session_id": 1 }
```

返回该对话最近 100 条消息，以及会话上已保存的参数。用户消息只保留原问题。只能读取当前用户自己的会话。

```json
{
  "session": {
    "id": 1,
    "agent_key": "ziwei",
    "title": "我的事业怎么样",
    "has_report": true,
    "report": {
      "命宫": "紫微",
      "官禄宫": "武曲",
      "财帛宫": "天府"
    },
    "inputs": {
      "report": {
        "命宫": "紫微",
        "官禄宫": "武曲",
        "财帛宫": "天府"
      }
    },
    "updated_at": "2026-10-11 15:00:00"
  },
  "messages": []
}
```

`inputs` 是这条对话里已经保存的额外参数，内容由该智能体在后台声明的字段决定。紫微排盘只声明了 `report`，所以 `inputs` 是 `{ "report": { ...命盘... } }`。

字段名正好是 `report` 时，服务端会把同一份数据再抄到 `session.report`，`has_report` 也看这一份。客户端读命盘用 `session.report`。其它应用如果声明的是别的字段，那些字段只出现在 `inputs` 里，不会再有一个 `report`。
