<?php

use App\Support\Services\CacheService;
use Illuminate\Support\Facades\Config;
use App\Support\Services\FormBuilder as Form;
use Fastknife\Service\ClickWordCaptchaService;
use Fastknife\Service\BlockPuzzleCaptchaService;

function adminId()
{
    return \Illuminate\Http\Request::adminId();
}

function adminType()
{
    return \Illuminate\Http\Request::adminType();
}

function adminInfo()
{
    return \Illuminate\Http\Request::adminInfo();
}

function authUserId(): int
{
    return \Illuminate\Http\Request::authUserId();
}

function redis()
{
    return \Illuminate\Support\Facades\Redis::connection()->client();
}

function generateOrderNo($userId = 0): string
{
    $time = date('YmdHi');
    $suffix = empty($userId) ? rand(100000, 999999) : str_pad($userId, 6, "0", STR_PAD_LEFT);

    return $time . $suffix;
}

/**
 * 计算百分比
 *
 * @param $dividend
 * @param $divisor
 *
 * @return float|int
 */
function computePercent($dividend, $divisor)
{
    if (empty($dividend) || empty($divisor)) {
        return 0;
    }

    $rate = ($dividend / $divisor) * 100;

    return round($rate, 2);
}

/**
 * 计算概率
 *
 * @param $rateStr
 * @param string $default
 *
 * @return string
 */
function computeProbability($rateStr, $default='A'): string
{
    $rateArr = explode(':', $rateStr);
    if (count($rateArr) != 2) {
        return $default;
    }

    $total = intval($rateArr[0]) + intval($rateArr[1]);
    $randNum = rand(1, $total);

    return $randNum <= intval($rateArr[0]) ? 'A' : 'B';
}

function ip2region($ip)
{
    $ip2region = new \Ip2Region();
    $geo = $ip2region->memorySearch($ip);
    $arr = explode('|', str_replace(['0|'], '|', $geo['region'] ?? ''));
    //    if (($last = array_pop($arr)) === '内网IP') $last = '';
    //    return join('', $arr) . (empty($last) ? '' : "【{$last}】");
    array_pop($arr);

    return join('', $arr);
}


if (!function_exists('object2array')) {
    /**
     * 对象转数组
     *
     * @param $object
     *
     * @return array|mixed
     */
    function object2array($object)
    {
        $array = [];
        if (is_object($object)) {
            foreach ($object as $key => $value) {
                $array[$key] = $value;
            }
        } else {
            $array = $object;
        }

        return $array;
    }
}


if (!function_exists('sys_config')) {
    /**
     * 获取系统单个配置
     *
     * @param string $key
     * @param int $appId
     * @return string
     */
    function sys_config(string $key, int $appId = 0): string
    {
        return \App\Support\Services\SystemConfigService::get($appId, $key);
    }
}

if (!function_exists('sys_config_more')) {
    function sys_config_more($key, int $appId = 0): array
    {
        return \App\Support\Services\SystemConfigService::more($appId, $key);
    }
}

/**
 * 获取系统单个配置
 */
function sys_data(string $name): array
{
    return \App\Support\Services\GroupDataService::getData($name);
}


if (!function_exists('getRandomStr')) {
    function getRandomStr($len, $special = true)
    {
        $chars = [
            "a",
            "b",
            "c",
            "d",
            "e",
            "f",
            "g",
            "h",
            "i",
            "j",
            "k",
            "l",
            "m",
            "n",
            "o",
            "p",
            "q",
            "r",
            "s",
            "t",
            "u",
            "v",
            "w",
            "x",
            "y",
            "z",
            "A",
            "B",
            "C",
            "D",
            "E",
            "F",
            "G",
            "H",
            "I",
            "J",
            "K",
            "L",
            "M",
            "N",
            "O",
            "P",
            "Q",
            "R",
            "S",
            "T",
            "U",
            "V",
            "W",
            "X",
            "Y",
            "Z",
            "0",
            "1",
            "2",
            "3",
            "4",
            "5",
            "6",
            "7",
            "8",
            "9",
        ];

        if ($special) {
            $chars = array_merge($chars, [
                "!",
                "@",
                "#",
                "$",
                "?",
                "|",
                "{",
                "/",
                ":",
                ";",
                "%",
                "^",
                "&",
                "*",
                "(",
                ")",
                "-",
                "_",
                "[",
                "]",
                "}",
                "<",
                ">",
                "~",
                "+",
                "=",
                ",",
                ".",
            ]);
        }

        $charsLen = count($chars) - 1;
        shuffle($chars);                            //打乱数组顺序
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $charsLen)];    //随机取出一位
        }

        return $str;
    }
}


if (!function_exists('make_path')) {
    /**
     * 上传路径转化,默认路径
     *
     * @param $path
     * @param int $type
     * @param bool $force
     *
     * @return string
     */
    function make_path($path, int $type = 2, bool $force = false)
    {
        $path = DS . ltrim(rtrim($path));
        switch ($type) {
            case 1:
                $path .= DS . date('Y');
                break;
            case 2:
                $path .= DS . date('Y') . DS . date('m');
                break;
            case 3:
                $path .= DS . date('Y') . DS . date('m') . DS . date('d');
                break;
        }
        try {
            if (is_dir(storage_path() . 'uploads' . $path) == true || mkdir(storage_path() . 'uploads' . $path, 0777, true) == true) {
                return trim(str_replace(DS, '/', $path), '.');
            } else {
                return '';
            }
        } catch (\Exception $e) {
            if ($force) {
                throw new \Exception($e->getMessage());
            }

            return '无法创建文件夹，请检查您的上传目录权限：' . storage_path() . 'uploads' . DS . 'attach' . DS;
        }
    }
}

function convertToPermissionArray($value): array
{
    $permissions = [];

    if ($value & 4) {
        $permissions[] = 4; // 读权限
    }
    if ($value & 2) {
        $permissions[] = 2; // 写权限
    }
    if ($value & 1) {
        $permissions[] = 1; // 执行权限
    }

    return $permissions;
}

/**
 * 将权限数值数组转换为对应的整数权限值
 *
 * @param array $permissions 权限数值数组（如 [4, 2, 1]）
 * @return int 对应的整数权限值（如 7, 6, 5 等）
 */
function convertToPermissionValue(array $permissions): int
{
    $value = 0;
    foreach ($permissions as $permission) {
        $value += $permission;
    }

    return $value;
}

if (!function_exists('set_file_url')) {
    /**
     * 设置附加路径
     *
     * @param $url
     *
     * @return bool
     */
    function set_file_url($image, $siteUrl = '')
    {
        if (!strlen(trim($siteUrl))) {
            $siteUrl = sys_config('site_url');
        }
        if (!$image) {
            return $image;
        }
        if (is_array($image)) {
            foreach ($image as &$item) {
                $domainTop1 = substr($item, 0, 4);
                $domainTop2 = substr($item, 0, 2);
                if ($domainTop1 != 'http' && $domainTop2 != '//') {
                    $item = $siteUrl . str_replace('\\', '/', $item);
                }
            }
        } else {
            $domainTop1 = substr($image, 0, 4);
            $domainTop2 = substr($image, 0, 2);
            if ($domainTop1 != 'http' && $domainTop2 != '//') {
                $image = $siteUrl . str_replace('\\', '/', $image);
            }
        }

        return $image;
    }
}

if (!function_exists('check_card')) {
    /**
     * 身份证验证
     *
     * @param $card
     *
     * @return bool
     */
    function check_card($card)
    {
        $city =
            [
                11 => "北京",
                12 => "天津",
                13 => "河北",
                14 => "山西",
                15 => "内蒙古",
                21 => "辽宁",
                22 => "吉林",
                23 => "黑龙江 ",
                31 => "上海",
                32 => "江苏",
                33 => "浙江",
                34 => "安徽",
                35 => "福建",
                36 => "江西",
                37 => "山东",
                41 => "河南",
                42 => "湖北 ",
                43 => "湖南",
                44 => "广东",
                45 => "广西",
                46 => "海南",
                50 => "重庆",
                51 => "四川",
                52 => "贵州",
                53 => "云南",
                54 => "西藏 ",
                61 => "陕西",
                62 => "甘肃",
                63 => "青海",
                64 => "宁夏",
                65 => "新疆",
                71 => "台湾",
                81 => "香港",
                82 => "澳门",
                91 => "国外 ",
            ];
        $tip = "";
        $match = "/^\d{6}(18|19|20)?\d{2}(0[1-9]|1[012])(0[1-9]|[12]\d|3[01])\d{3}(\d|X)$/";
        $pass = true;
        if (!$card || !preg_match($match, $card)) {
            //身份证格式错误
            $pass = false;
        } else {
            if (!$city[substr($card, 0, 2)]) {
                //地址错误
                $pass = false;
            } else {
                //18位身份证需要验证最后一位校验位
                if (strlen($card) == 18) {
                    $card = str_split($card);
                    //∑(ai×Wi)(mod 11)
                    //加权因子
                    $factor = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
                    //校验位
                    $parity = [1, 0, 'X', 9, 8, 7, 6, 5, 4, 3, 2];
                    $sum = 0;
                    $ai = 0;
                    $wi = 0;
                    for ($i = 0; $i < 17; $i++) {
                        $ai = $card[$i];
                        $wi = $factor[$i];
                        $sum += $ai * $wi;
                    }
                    $last = $parity[$sum % 11];
                    if ($parity[$sum % 11] != $card[17]) {
                        //                        $tip = "校验位错误";
                        $pass = false;
                    }
                } else {
                    $pass = false;
                }
            }
        }
        if (!$pass) {
            return false;
        }/* 身份证格式错误*/

        return true;/* 身份证格式正确*/
    }
}
if (!function_exists('check_link')) {
    /**
     * 地址验证
     *
     * @param string $link
     *
     * @return false|int
     */
    function check_link(string $link)
    {
        return preg_match("/^(http|https|ftp):\/\/[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+[\/=\?%\-&_~`@[\]\’:+!]*([^<>\”])*$/", $link);
    }
}
if (!function_exists('check_phone')) {
    /**
     * 手机号验证
     *
     * @param $phone
     *
     * @return false|int
     */
    function check_phone($phone)
    {
        return preg_match("/^1[3456789]\d{9}$/", $phone);
    }
}
if (!function_exists('sort_list_tier')) {
    /**
     * 分级排序
     *
     * @param $data
     * @param int $pid
     * @param string $field
     * @param string $pk
     * @param string $html
     * @param int $level
     * @param bool $clear
     *
     * @return array
     */
    function sort_list_tier($data, $pid = 0, $field = 'pid', $pk = 'id', $html = '|-----', $level = 1, $clear = true)
    {
        static $list = [];
        if ($clear) {
            $list = [];
        }
        foreach ($data as $k => $res) {
            if ($res[$field] == $pid) {
                $res['html'] = str_repeat($html, $level);
                $list[] = $res;
                unset($data[$k]);
                sort_list_tier($data, $res[$pk], $field, $pk, $html, $level + 1, false);
            }
        }

        return $list;
    }
}

/**
 * 分解数字为一组二进制单一位的数值
 *
 * @param int $number 需要分解的数字
 * @return array 分解后的数组
 */
function decomposeNumber($number)
{
    $result = [];
    $bit = 1; // 初始化为最低有效位

    while ($number > 0) {
        if ($number & $bit) {
            $result[] = $bit; // 如果当前位为1，加入结果集
        }
        $number &= ~$bit; // 清除已处理的最低位
        $bit <<= 1; // 移到下一个有效位
    }

    return $result;
}

if (!function_exists('sort_city_tier')) {
    /**
     * 城市数据整理
     *
     * @param $data
     * @param int $pid
     * @param string $field
     * @param string $pk
     * @param string $html
     * @param int $level
     * @param bool $clear
     *
     * @return array
     */
    function sort_city_tier($data, $pid = 0, $navList = [])
    {
        foreach ($data as $k => $menu) {
            if ($menu['parent_id'] == $pid) {
                unset($menu['parent_id']);
                unset($data[$k]);
                $menu['c'] = sort_city_tier($data, $menu['v']);
                $navList[] = $menu;
            }
        }

        return $navList;
    }
}

if (!function_exists('time_tran')) {
    /**
     * 时间戳人性化转化
     *
     * @param $time
     *
     * @return string
     */
    function time_tran($time, $actionName = '')
    {
        $t = time() - $time;
        $f = [
            '31536000' => '年',
            '2592000' => '个月',
            '604800' => '星期',
            '86400' => '天',
            '3600' => '小时',
            '60' => '分钟',
            '1' => '秒',
        ];
        foreach ($f as $k => $v) {
            if (0 != $c = floor($t / (int)$k)) {
                return $c . $v . '前' . $actionName;
            }
        }
    }
}

if (!function_exists('local_path_from_url')) {
    /**
     * 将完整 URL 或 /storage/... 路径转换为本地绝对路径
     *
     * @param string $url
     * @return string|null
     */
    function local_path_from_url(string $url): ?string
    {
        // 去掉域名部分（如果有）
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';

        // 只处理 /storage 开头的路径
        if (!str_starts_with($path, '/storage/')) {
            return null;
        }

        $relativePath = str_replace('/storage/', '', $path);
        return storage_path('app/public/' . $relativePath);
    }
}

if (!function_exists('url_from_local_path')) {
    /**
     * 将本地文件路径转换为带域名的 storage URL
     *
     * @param string $path storage/app/public 下的绝对路径或相对路径
     * @return string|null
     */
    function url_from_local_path(string $path): ?string
    {
        // 如果是绝对路径，则先转换为相对路径
        $storageRoot = storage_path('app/public/');
        if (str_starts_with($path, $storageRoot)) {
            $relative = str_replace($storageRoot, '', $path);
        } else {
            $relative = $path;
        }

        // 构建 URL
        return asset('storage/' . ltrim($relative, '/'));
    }
}

if (!function_exists('image_to_base64')) {
    /**
     * 获取图片转为base64
     *
     * @param string $avatar
     *
     * @return bool|string
     */
    function image_to_base64($avatar = '', $timeout = 9)
    {
        $avatar = str_replace('https', 'http', $avatar);
        try {
            $url = parse_url($avatar);
            $url = $url['host'];
            $header = [
                'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:45.0) Gecko/20100101 Firefox/45.0',
                'Accept-Language: zh-CN,zh;q=0.8,en-US;q=0.5,en;q=0.3',
                'Accept-Encoding: gzip, deflate, br',
                'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                'Host:' . $url,
            ];
            $dir = pathinfo($url);
            $host = $dir['dirname'];
            $refer = $host . '/';
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_REFERER, $refer);
            curl_setopt($curl, CURLOPT_URL, $avatar);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            $data = curl_exec($curl);
            $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            if ($code == 200) {
                return "data:image/jpeg;base64," . base64_encode($data);
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('put_image')) {
    /**
     * 获取图片转为base64
     *
     * @param string $avatar
     *
     * @return bool|string
     */
    function put_image($url, $filename = '')
    {
        if ($url == '') {
            return false;
        }
        try {
            if ($filename == '') {
                $ext = pathinfo($url);
                if ($ext['extension'] != "jpg" && $ext['extension'] != "png" && $ext['extension'] != "jpeg") {
                    return false;
                }
                $filename = time() . "." . $ext['extension'];
            }

            //文件保存路径
            ob_start();
            readfile($url);
            $img = ob_get_contents();
            ob_end_clean();
            $path = 'uploads/qrcode';
            $fp2 = fopen($path . '/' . $filename, 'a');
            fwrite($fp2, $img);
            fclose($fp2);

            return $path . '/' . $filename;
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('debug_file')) {
    /**
     * 文件调试
     *
     * @param $content
     */
    function debug_file($content, string $fileName = 'error', string $ext = 'txt')
    {
        $msg = '[' . date('Y-m-d H:i:s', time()) . '] [ DEBUG ] ';
        $pach = app()->getRuntimePath();
        file_put_contents($pach . $fileName . '.' . $ext, $msg . print_r($content, true) . "\r\n", FILE_APPEND);
    }
}


if (!function_exists('array_unique_fb')) {
    /**
     * 二维数组去掉重复值
     *
     * @param $array
     *
     * @return array
     */
    function array_unique_fb($array)
    {
        $out = [];
        foreach ($array as $key => $value) {
            if (!in_array($value, $out)) {
                $out[$key] = $value;
            }
        }
        $out = array_values($out);

        return $out;
    }
}

if (!function_exists('get_admin_version')) {
    /**
     * 获取系统版本号
     *
     * @param string $default
     *
     * @return string
     */
    function get_admin_version(string $default = 'v1.0.0'): string
    {
        return $default;
    }
}

if (!function_exists('get_file_link')) {
    /**
     * 获取文件带域名的完整路径
     *
     * @param string $link
     *
     * @return string
     */
    function get_file_link(string $link)
    {
        if (!$link) {
            return '';
        }
        if (strstr('http', $link) === false) {
            return request()->getHost() . $link;
        } else {
            return $link;
        }
    }
}

if (!function_exists('tidy_tree')) {
    /**
     * 格式化分类
     *
     * @param $menusList
     * @param int $pid
     * @param array $navList
     *
     * @return array
     */
    function tidy_tree($menusList, $pid = 0, $navList = []): array
    {
        foreach ($menusList as $k => $menu) {
            if ($menu['parent_id'] == $pid) {
                unset($menusList[$k]);
                $menu['children'] = tidy_tree($menusList, $menu['id']);
                if ($menu['children']) {
                    $menu['expand'] = true;
                }
                $navList[] = $menu;
            }
        }

        return $navList;
    }
}

if (!function_exists('create_form')) {
    /**
     * 表单生成方法
     *
     * @param string $title
     * @param array $field
     * @param $url
     * @param string $method
     *
     * @return array
     * @throws \App\Exceptions\AdminException
     */
    function create_form(string $title, array $field, $url, string $method = 'POST'): array
    {
        try {
            $form = Form::createForm((string)$url);//提交地址
            $form->setMethod($method);              //提交方式
            $form->setRule($field);                 //表单字段
            $form->setTitle($title);                //表单标题
            $rules = $form->formRule();
            $title = $form->getTitle();
            $action = $form->getAction();
            $method = $form->getMethod();
        } catch (\Exception $exception) {
            throw new \App\Exceptions\AdminException($exception->getMessage());
        }

        $info = '';
        $status = true;
        $methodData = ['POST', 'PUT', 'GET', 'DELETE'];
        if (!in_array(strtoupper($method), $methodData)) {
            throw new \App\Exceptions\AdminException('请求方式有误');
        }

        return compact('rules', 'title', 'action', 'method', 'info', 'status');
    }
}

if (!function_exists('array_bc_sum')) {
    /**
     * 获取一维数组的总合高精度
     *
     * @param array $data
     *
     * @return string
     */
    function array_bc_sum(array $data)
    {
        $sum = '0';
        foreach ($data as $item) {
            $sum = bcadd($sum, (string)$item, 2);
        }

        return $sum;
    }
}

if (!function_exists('get_tree_children')) {
    /**
     * tree 子菜单
     *
     * @param array $data 数据
     * @param string $childrenname 子数据名
     * @param string $keyName 数据key名
     * @param string $pidName 数据上级key名
     *
     * @return array
     */
    function get_tree_children(array $data, string $childrenname = 'children', string $keyName = 'id', string $pidName = 'pid')
    {
        $list = [];
        foreach ($data as $value) {
            $list[$value[$keyName]] = $value;
        }
        static $tree = []; //格式化好的树
        foreach ($list as $item) {
            if (isset($list[$item[$pidName]])) {
                $list[$item[$pidName]][$childrenname][] = &$list[$item[$keyName]];
            } else {
                $tree[] = &$list[$item[$keyName]];
            }
        }

        return $tree;
    }
}

if (!function_exists('get_tree_children_value')) {
    function get_tree_children_value(array $data, $value, string $childrenname = 'children', string $keyName = 'id')
    {
        static $childrenValue = [];
        foreach ($data as $item) {
            $childrenData = $item[$childrenname] ?? [];
            if (count($childrenData)) {
                return get_tree_children_value($childrenData, $childrenname, $keyName);
            } else {
                if ($item[$keyName] == $value) {
                    $childrenValue[] = $item['value'];
                }
            }
        }

        return $childrenValue;
    }
}

if (!function_exists('get_tree_value')) {
    /**
     * 获取
     *
     * @param array $data
     * @param int|string $value
     *
     * @return array
     */
    function get_tree_value(array $data, $value)
    {
        static $childrenValue = [];
        foreach ($data as &$item) {
            if ($item['value'] == $value) {
                $childrenValue[] = $item['value'];
                if ($item['pid']) {
                    $value = $item['pid'];
                    unset($item);

                    return get_tree_value($data, $value);
                }
            }
        }

        return $childrenValue;
    }
}

if (!function_exists('get_image_thumb')) {
    /**
     * 获取缩略图
     *
     * @param $filePath
     * @param string $type all|big|mid|small
     * @param bool $is_remote_down
     *
     * @return mixed|string|string[]
     */
    function get_image_thumb($filePath, string $type = 'all', bool $is_remote_down = false)
    {
        if (!$filePath || !is_string($filePath) || strpos($filePath, '?') !== false) {
            return $filePath;
        }
        try {
            $upload = UploadService::getOssInit($filePath, $is_remote_down);
            $fileArr = explode('/', $filePath);
            $data = $upload->thumb($filePath, end($fileArr), $type);
            $image = $type == 'all' ? $data : $data[$type] ?? $filePath;
        } catch (\Throwable $e) {
            $image = $filePath;
            \Illuminate\Support\Facades\Log::error('获取缩略图失败，原因：' . $e->getMessage() . '----' . $e->getFile() . '----' . $e->getLine() . '----' . $filePath);
        }
        $data = parse_url($image);
        if (!isset($data['host']) && (substr($image, 0, 2) == './' || substr($image, 0, 1) == '/')) {//不是完整地址
            $image = sys_config('site_url') . $image;
        }
        //请求是https 图片是http 需要改变图片地址
        if (strpos(request()->domain(), 'https:') !== false && strpos($image, 'https:') === false) {
            $image = str_replace('http:', 'https:', $image);
        }

        return $image;
    }
}

if (!function_exists('get_thumb_water')) {
    /**
     * 处理数组获取缩略图、水印
     *
     * @param $list
     * @param string $type
     * @param array|string[] $field 1、['image','images'] type 取值参数:type 2、['small'=>'image','mid'=>'images'] type 取field数组的key
     * @param bool $is_remote_down
     *
     * @return array|mixed|string|string[]
     */
    function get_thumb_water($list, string $type = 'small', array $field = ['image'], bool $is_remote_down = false)
    {
        if (!$list || !$field) {
            return $list;
        }
        $baseType = $type;
        $data = $list;
        if (is_string($list)) {
            $field = [$type => 'image'];
            $data = ['image' => $list];
        }
        if (is_array($data)) {
            foreach ($field as $type => $key) {
                if (is_integer($type)) {//索引数组，默认type
                    $type = $baseType;
                }
                //一维数组
                if (isset($data[$key])) {
                    if (is_array($data[$key])) {
                        $path_data = [];
                        foreach ($data[$key] as $k => $path) {
                            $path_data[] = get_image_thumb($path, $type, $is_remote_down);
                        }
                        $data[$key] = $path_data;
                    } else {
                        $data[$key] = get_image_thumb($data[$key], $type, $is_remote_down);
                    }
                } else {
                    foreach ($data as &$item) {
                        if (!isset($item[$key])) {
                            continue;
                        }
                        if (is_array($item[$key])) {
                            $path_data = [];
                            foreach ($item[$key] as $k => $path) {
                                $path_data[] = get_image_thumb($path, $type, $is_remote_down);
                            }
                            $item[$key] = $path_data;
                        } else {
                            $item[$key] = get_image_thumb($item[$key], $type, $is_remote_down);
                        }
                    }
                }
            }
        }

        return is_string($list) ? ($data['image'] ?? '') : $data;
    }
}

if (!function_exists('aj_captcha_check_one')) {
    /**
     * 验证滑块1次验证
     *
     * @param string $token
     * @param string $pointJson
     *
     * @return bool
     */
    function aj_captcha_check_one(string $captchaType, string $token, string $pointJson)
    {
        aj_get_serevice($captchaType)->check($token, $pointJson);

        return true;
    }
}

if (!function_exists('aj_captcha_check_two')) {
    /**
     * 验证滑块2次验证
     *
     * @param string $token
     * @param string $pointJson
     *
     * @return bool
     */
    function aj_captcha_check_two(string $captchaType, string $captchaVerification)
    {
        aj_get_serevice($captchaType)->verificationByEncryptCode($captchaVerification);

        return true;
    }
}

if (!function_exists('aj_captcha_create')) {
    /**
     * 创建验证码
     *
     * @return array
     */
    function aj_captcha_create(string $captchaType)
    {
        return aj_get_serevice($captchaType)->get();
    }
}

if (!function_exists('aj_get_serevice')) {
    /**
     * @param string $captchaType
     *
     * @return ClickWordCaptchaService|BlockPuzzleCaptchaService
     * @throws \App\Exceptions\AdminException
     */
    function aj_get_serevice(string $captchaType)
    {
        $config = Config::get('ajcaptcha');
        switch ($captchaType) {
            case "clickWord":
                $service = new ClickWordCaptchaService($config);
                break;
            case "blockPuzzle":
                $service = new BlockPuzzleCaptchaService($config);
                break;
            default:
                throw new \App\Exceptions\AdminException('captchaType参数不正确！');
        }

        return $service;
    }
}
