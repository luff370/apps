# API 混淆配置客户端使用说明

## 1. 客户端需要拿到的配置文件

后台 `API混淆管理` 页面提供两个导出：

### 1.1 导出完整配置

用于客户端运行时全局配置，主要包含：

```json
{
  "app_id": 10001,
  "package_name": "com.xxx.app",
  "gateway_prefix": "/api/client/",
  "gateway_prefixes": [
    "/api/v/",
    "/api/gateway/",
    "/api/client/",
    "/api/service/",
    "/api/open/"
  ],
  "enabled": true,
  "route_aliases": {
    "contentList2a": {
      "path": "content/list",
      "method": "POST"
    }
  },
  "request_key_map": {
    "page": "pg",
    "limit": "sz",
    "keywords": "kw"
  },
  "response_key_map": {
    "status": "s",
    "msg": "m",
    "data": "d"
  },
  "response_data_key_map": {
    "list": "ls",
    "count": "ct"
  },
  "protocol": {
    "encrypt_request": false,
    "encrypt_response": false,
    "allow_plaintext_request": true,
    "payload_field": "payload",
    "sign_field": "sign",
    "timestamp_field": "ts",
    "nonce_field": "nonce",
    "version_field": "ver"
  },
  "security": {
    "timestamp_window_seconds": 300,
    "nonce_ttl_seconds": 300
  },
  "crypto": {
    "cipher": "AES-256-CBC",
    "key": "",
    "iv": "",
    "sign_key": ""
  },
  "image_url": {
    "enabled": true,
    "domain": "https://img.xxx.com",
    "fields": ["image", "images", "avatar", "cover", "url"],
    "path_prefixes": ["attach/", "/attach/", "storage/attach/", "/storage/attach/"]
  }
}
```

### 1.2 导出 JSON

用于客户端查看“真实接口和别名接口对应关系”。

一般情况下客户端只需要使用“导出完整配置”，因为完整配置里已经包含 `route_aliases`。

---

## 2. 请求地址替换规则

### 2.1 原始接口

例如原接口是：

```text
/api/content/list
```

完整配置中：

```json
{
  "gateway_prefix": "/api/client/",
  "route_aliases": {
    "contentList2a": {
      "path": "content/list",
      "method": "POST"
    }
  }
}
```

客户端需要把原始接口：

```text
/api/content/list
```

替换为：

```text
{gateway_prefix}{alias}
```

最终请求：

```text
/api/client/contentList2a
```

---

## 3. 反向查找别名

客户端可以启动时把 `route_aliases` 反转成 `path -> alias` 映射。

### 原配置

```json
{
  "route_aliases": {
    "contentList2a": {
      "path": "content/list",
      "method": "POST"
    },
    "loginAuth8x": {
      "path": "auth/login_by_uuid",
      "method": "POST"
    }
  }
}
```

### 客户端转换为

```json
{
  "content/list": "contentList2a",
  "auth/login_by_uuid": "loginAuth8x"
}
```

请求时：

```text
/api/{path}
```

替换为：

```text
{gateway_prefix}{alias}
```

---

## 4. 请求参数映射规则

配置：

```json
{
  "request_key_map": {
    "page": "pg",
    "limit": "sz",
    "keywords": "kw"
  }
}
```

客户端原请求参数：

```json
{
  "page": 1,
  "limit": 10,
  "keywords": "test"
}
```

发送前转换为：

```json
{
  "pg": 1,
  "sz": 10,
  "kw": "test"
}
```

### 规则

- key 在 `request_key_map` 中存在：替换为映射后的 key。
- key 不存在：保持原样。
- 只替换 key，不改变 value。

---

## 5. 响应字段解析规则

服务端返回时会按配置映射响应字段。

配置：

```json
{
  "response_key_map": {
    "status": "s",
    "msg": "m",
    "data": "d"
  },
  "response_data_key_map": {
    "list": "ls",
    "count": "ct"
  }
}
```

服务端可能返回：

```json
{
  "s": 200,
  "m": "success",
  "d": {
    "ls": [],
    "ct": 0
  }
}
```

客户端解析时反向转换为：

```json
{
  "status": 200,
  "msg": "success",
  "data": {
    "list": [],
    "count": 0
  }
}
```

### 建议客户端本地生成反向 map

```js
const reverseResponseKeyMap = {
  s: 'status',
  m: 'msg',
  d: 'data'
};

const reverseResponseDataKeyMap = {
  ls: 'list',
  ct: 'count'
};
```

---

## 6. 动态路径参数

如果真实接口是：

```text
/api/common/get_group_data/{name}
```

配置可能是：

```json
{
  "route_aliases": {
    "commonGroup9a": {
      "path": "common/get_group_data/{name}",
      "method": "POST"
    }
  }
}
```

客户端原请求：

```text
/api/common/get_group_data/banner
```

需要替换为：

```text
/api/client/commonGroup9a/banner
```

也就是：

```text
{gateway_prefix}{alias}/{动态参数}
```

多个动态参数同理。

---

## 7. 请求头要求

客户端每次请求建议带：

```http
App-Id: 10001
Package-Name: com.xxx.app
```

服务端会根据：

- `Package-Name`
- `App-Id`

匹配应用混淆配置。

建议两个都带，避免某个字段缺失导致匹配不到配置。

---

## 8. 加密请求规则

如果配置：

```json
{
  "protocol": {
    "encrypt_request": true,
    "payload_field": "payload",
    "sign_field": "sign",
    "timestamp_field": "ts",
    "nonce_field": "nonce",
    "version_field": "ver"
  },
  "crypto": {
    "cipher": "AES-256-CBC",
    "key": "xxx",
    "iv": "xxx",
    "sign_key": "xxx"
  }
}
```

客户端请求体不要直接发业务参数，而是发加密包。

### 8.1 明文业务参数

```json
{
  "pg": 1,
  "sz": 10
}
```

### 8.2 加密流程

1. JSON 序列化业务参数。
2. 使用 `AES-256-CBC` 加密。
3. 得到 `payload`。
4. 生成时间戳 `ts`。
5. 生成随机串 `nonce`。
6. 生成签名。

签名字符串：

```text
payload|ts|nonce
```

签名算法：

```text
HMAC-SHA256(sign_key, sign_string)
```

### 8.3 最终请求体

```json
{
  "payload": "加密后的字符串",
  "sign": "签名",
  "ts": 1710000000,
  "nonce": "random-string",
  "ver": "1"
}
```

字段名以配置为准，不一定叫 `payload/sign/ts/nonce/ver`。

---

## 9. 响应加密规则

如果配置：

```json
{
  "protocol": {
    "encrypt_response": true
  }
}
```

服务端返回的响应也会是加密包。

客户端需要：

1. 读取 `payload_field`。
2. AES 解密。
3. JSON 解析。
4. 再按响应字段映射反向转换。

---

## 10. 推荐客户端处理流程

### 请求流程

```text
原始接口 path
  -> 根据 route_aliases 找 alias
  -> 拼接 gateway_prefix + alias
  -> 请求参数 key 根据 request_key_map 替换
  -> 如果 encrypt_request=true，整体加密封包
  -> 发起请求
```

### 响应流程

```text
收到响应
  -> 如果 encrypt_response=true，先解密
  -> 根据 response_key_map 反向还原 status/msg/data
  -> 根据 response_data_key_map 反向还原 data 内字段
  -> 交给原业务逻辑
```

---

## 11. JavaScript 伪代码

```js
function reverseMap(map) {
  const result = {};
  Object.keys(map || {}).forEach((key) => {
    result[map[key]] = key;
  });
  return result;
}

function mapKeys(obj, map) {
  if (!obj || typeof obj !== 'object' || Array.isArray(obj)) return obj;

  const result = {};
  Object.keys(obj).forEach((key) => {
    const newKey = map[key] || key;
    result[newKey] = obj[key];
  });
  return result;
}

function restoreKeys(obj, reverseMapConfig) {
  if (!obj || typeof obj !== 'object' || Array.isArray(obj)) return obj;

  const result = {};
  Object.keys(obj).forEach((key) => {
    const newKey = reverseMapConfig[key] || key;
    result[newKey] = obj[key];
  });
  return result;
}

function buildRouteMap(routeAliases) {
  const result = {};
  Object.keys(routeAliases || {}).forEach((alias) => {
    const item = routeAliases[alias];
    result[item.path] = {
      alias,
      method: item.method
    };
  });
  return result;
}

function buildRequest(config, originPath, params) {
  const routeMap = buildRouteMap(config.route_aliases);
  const cleanPath = originPath.replace(/^\/?api\//, '').replace(/^\/+/, '');
  const route = routeMap[cleanPath];

  if (!route) {
    return {
      url: originPath,
      params
    };
  }

  return {
    url: config.gateway_prefix + route.alias,
    params: mapKeys(params, config.request_key_map)
  };
}

function parseResponse(config, response) {
  const reverseResponse = reverseMap(config.response_key_map);
  const reverseData = reverseMap(config.response_data_key_map);
  const restored = restoreKeys(response, reverseResponse);

  if (restored.data && typeof restored.data === 'object') {
    restored.data = restoreKeys(restored.data, reverseData);
  }

  return restored;
}
```

---

## 12. 注意事项

- 客户端不要写死 `/api/v/`，必须使用配置里的 `gateway_prefix`。
- 客户端不要写死 `s/m/d`，必须使用配置里的 `response_key_map`。
- 请求参数映射是全局映射，如果某些接口有特殊字段，需要服务端配置中补充对应 key。
- 动态路径参数需要拼到别名后面。
- 如果加密开启，字段名以 `protocol` 配置为准。
- 如果后台重新生成别名，客户端必须同步更新配置文件。
- 建议配置文件随 App 版本内置一份，同时支持远程热更新一份配置用于灰度修正。
