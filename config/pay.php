<?php
return [
    'alipay' => [
        'default' => [
            // 必填-支付宝分配的 app_id
            'app_id' => '2021004167629043',
            // 必填-应用私钥 字符串或路径
            // 在 https://open.alipay.com/develop/manage 《应用详情->开发设置->接口加签方式》中设置
            'app_secret_cert'=> 'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDZAqteaRNGcLDAJszID5s6z2hifXN0hxtu9t+XPVwIjbkCbuxRZu4pwfbx4qi/0HnMqzmfiv3qmZhAGNl6cffrdOfF531qyHXnt1bRnZNHjOmOYf3q8UVJ4sbfxbW545lWPEoYWKQvt6QOmPoHRFnQYVkeAWvTBWrAEprBm/3TuM6AW0LhKwmuolkHbVZLV+OobADHN+vsTTY+uvo6tAOFq/fIglNQtSX9DB43rmVj7QKPXuCT9h/+jn0RjQ3rH4KU57sDA4ngf35KiqMkYzku/3Vqhg+5VnL6o0BXOy+teODviIcvzKAEJBbC7V5e7gPft7RkCo3WJvp90YKw+XiZAgMBAAECggEAWCYycCYkcz+BPHxGQJm+R1RKpX8p79KVvSxAxJFjGgJffXKLQcGaypGEetrECy5m21FOFdWCsNH1O0wcwxUaTKVvJD+U/VQyOI6LkJf+ZFkD4hdB6jZZG9snR2xrFfgyqAnTWzJedxeUZ22SWnNUPEXeykZvgwc3RpAIFs8DewVeBGRL3efjWrKFlUeRIqSeB8EcqtA3vFMGyQg98uYUq5Og1L87PStYQm3iJa7dqCMBXb1/2IOL9SXAcGjbTlSTvsZFsX4c1I2rwfENGFy9wP/Z+wETuC6FtYnhcxE5Eg1+mOnk0eMzoN0+aU/moiQYQCeqDPAM7B4moN+9cJoH5QKBgQD2ygJ9WbxO9IbL9699m/+QmAB6FQAtUV7iYoZE3SgL1xaQpZ2VoWMDGcud3PjLR/56sGGC5veCwBftHquAE1Li/e7eRU2Y8jRqLfgwaM+YkO6/ruLw43GVPwE7ID8tjCxC2EXBdVUUOJjYrxQerCZDy2l9j+hLb0GG91xFdI4gNwKBgQDhHCJSF0wZFncitIwTxDmJIkuxDD0WBfbsdCfLMYWzo54ysbAXRxKdic7M4uhElIj+x/gcFOp99u3UaCarnR7pKXWpCRsOITA8av8HAUwx9payQ0bkPWm3/NsQvF4zZIDbiUJj6WoT+54stucHnV30rdLaJiHqHwMyi+gujGKlrwKBgQDvePKVhEAQNYOf5MarknaZboX38OztDKqP7p7vr9KuOpaw4aRaj/IuDhfhJY1eZIvxrbnRdiHzBEezVjGA5D9n+JSTppg3s79c0SKmhu3605h49FFPAsUy16JwJ0hnAD/q1UZLBXn7Vzgp+yoA4Xd4DahdqQj2Og1R1DH7S7CmXQKBgFz22nGeb23Y6kBp4YN0QPKSOEIYtM14jx3dZmWywO2L/5Qd74PddSGcPMw+VP+le+IEQUGPnbuBk3xpuraav5444f955DN0n1AEO+fvsEDd/iQGRquRdSxnfyytLhX5RoHjcIiEZ6ty+UKsEkMByUB33KD8qHrgbdidELjQfxi/AoGBALDf69ykk5wWy5RxmpnjCHIJL6DMRpAVKvVwjw8Lk8mPA08u9PQBROfv4/vbDx2jvFevJjHuIw+14YunuVk3QF/Jap/7VPk/r0tJb1VznMhmObdtwzwJ3LcTL0Kf/V5mmdHUOb2YQ6mIx4Z2CGwnl70miC8zDmSdctMsjfUii8wg',
            // 必填-应用公钥证书 路径
            // 设置应用私钥后，即可下载得到以下3个证书
            'app_public_cert_path' => storage_path() . '/cert/alipay/2021004167629043/appCertPublicKey.crt',
            // 必填-支付宝公钥证书 路径
            'alipay_public_cert_path' => storage_path() . '/cert/alipay/2021004167629043/alipayCertPublicKey_RSA2.crt',
            // 必填-支付宝根证书 路径
            'alipay_root_cert_path' => storage_path() . '/cert/alipay/alipayRootCert.crt',
            'return_url' => '',
            'notify_url' => '',
            // 选填-第三方应用授权token
            'app_auth_token' => '',
            // 选填-服务商模式下的服务商 id，当 mode 为 Pay::MODE_SERVICE 时使用该参数
            'service_provider_id' => '',
        ]
    ],
    'wechat' => [
        'default' => [
            // 必填-商户号，服务商模式下为服务商商户号
            // 可在 https://pay.weixin.qq.com/ 账户中心->商户信息 查看
            'mch_id' => '',
            // 选填-v2商户私钥
            'mch_secret_key_v2' => '',
            // 必填-v3 商户秘钥
            // 即 API v3 密钥(32字节，形如md5值)，可在 账户中心->API安全 中设置
            'mch_secret_key' => '',
            // 必填-商户私钥 字符串或路径
            // 即 API证书 PRIVATE KEY，可在 账户中心->API安全->申请API证书 里获得
            // 文件名形如：apiclient_key.pem
            'mch_secret_cert' =>'',
            // 必填-商户公钥证书路径
            // 即 API证书 CERTIFICATE，可在 账户中心->API安全->申请API证书 里获得
            // 文件名形如：apiclient_cert.pem
            'mch_public_cert_path' => '',
            // 必填-微信回调url
            // 不能有参数，如?号，空格等，否则会无法正确回调
            'notify_url' => '',
            // 选填-公众号 的 app_id
            // 可在 mp.weixin.qq.com 设置与开发->基本配置->开发者ID(AppID) 查看
            'mp_app_id' => '',
            // 选填-小程序 的 app_id
            'mini_app_id' => '',
            // 选填-app 的 app_id
            'app_id' => '',
            // 选填-合单 app_id
            'combine_app_id' => '',
            // 选填-合单商户号
            'combine_mch_id' => '',
            // 选填-服务商模式下，子公众号 的 app_id
            'sub_mp_app_id' => '',
            // 选填-服务商模式下，子 app 的 app_id
            'sub_app_id' => '',
            // 选填-服务商模式下，子小程序 的 app_id
            'sub_mini_app_id' => '',
            // 选填-服务商模式下，子商户id
            'sub_mch_id' => '',
            // 选填-微信平台公钥证书路径, optional，强烈建议 php-fpm 模式下配置此参数
            'wechat_public_cert_path' => [
                // '3111ADB163EC87500AD57B492FBA5E958223BB48' => storage_path().'/cert/wechat/wechatPublicKey.crt',
            ],
        ]
    ],
    'unipay' => [
        'default' => [
            // 必填-商户号
            'mch_id' => '777290058167151',
            // 必填-商户公私钥
            'mch_cert_path' => storage_path() . '/cert/uniPay/AppCert.pfx',
            // 必填-商户公私钥密码
            'mch_cert_password' => '000000',
            // 必填-银联公钥证书路径
            'unipay_public_cert_path' => storage_path() . '/cert/uniPay/CertPublicKey.cer',
            // 必填
            'return_url' => 'https://yansongda.cn/unipay/return',
            // 必填
            'notify_url' => 'https://yansongda.cn/unipay/notify',
        ],
    ],
    'apple' => [
        'app_shared_secret' => '0f4270d30b3049a5893e0ab1323ace89',
        'public_key' => <<<EOD
-----BEGIN PUBLIC KEY-----
teUbLrwScsjVrcFAvSrfben3eQaEca3ESBegGh_wdGuLKw6QgwDxY3fC1_WeSVnkJXx72ddw3j2inoADnTyzuNa_PwDSmvJhOhmzOmoltmtKHteGdaXrqMohO6A85WxVKbN7pzDqwZJNrdY12LOltlI8PHIG-elAbKM2XOHiJaZnLpAVckKy6MQYsEExpPB3plGxWZElqwNZY6SUDVeN-o9qg5FJOFg7T7iTVVEagws4DM6uZNMDQGtqg9V9VqPQkUzC-sYd5eqbB9LqH4iN5F6OB7BmD3g3jCu9zgh3O9V24N43EruBCNrmP0xLP5ZliKqozoAcd1nv71HuVm6mgQ
-----END PUBLIC KEY-----
EOD,
    ],
    'logger' => [
        'enable' => false,
        'file' => storage_path() . '/logs/pay.log',
        'level' => 'info', // 建议生产环境等级调整为 info，开发环境为 debug
        'type' => 'single', // optional, 可选 daily.
        'max_file' => 30, // optional, 当 type 为 daily 时有效，默认 30 天
    ],
    'http' => [ // optional
        'timeout' => 5.0,
        'connect_timeout' => 5.0,
        // 更多配置项请参考 [Guzzle](https://guzzle-cn.readthedocs.io/zh_CN/latest/request-options.html)
    ],
];
