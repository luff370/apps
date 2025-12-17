<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Support\Traits\ServicesTrait;
use App\Support\Services\FormOptions;
use App\Services\System\SystemMenuServices;

/**
 * 公共接口
 * Class Common
 */
class CommonController extends Controller
{
    use ServicesTrait;

    /**
     * 获取logo 站点名称信息
     *
     * @return mixed
     */
    public function siteInfo()
    {
        return $this->success([
            'logo' => sys_config('site_logo'),
            'logo_square' => sys_config('site_logo_square'),
            'site_name' => sys_config('site_name'),
        ]);
    }

    /**
     * 首页头部统计数据
     *
     * @return mixed
     */
    public function homeStatics()
    {
        // $info = $this->orderStatisticService()->homeStatics(Auth::search());
        $info = json_decode('[
            {
                "today": 0,
                "yesterday": 0,
                "today_ratio": 0,
                "total": "0元",
                "date": "今日",
                "title": "销售额",
                "total_name": "本月销售额"
            },
            {
                "today": 0,
                "yesterday": 0,
                "today_ratio": 0,
                "total": "0Pv",
                "date": "今日",
                "title": "用户访问量",
                "total_name": "本月访问量"
            },
            {
                "today": 0,
                "yesterday": 0,
                "today_ratio": 0,
                "total": "0单",
                "date": "今日",
                "title": "订单量",
                "total_name": "本月订单量"
            },
            {
                "today": 0,
                "yesterday": 0,
                "today_ratio": 0,
                "total": "0人",
                "date": "今日",
                "title": "新增用户",
                "total_name": "本月新增用户"
            }
        ]', true);

        return $this->success(compact('info'));
    }

    /**
     * 订单图表
     */
    public function orderChart(): \Illuminate\Http\JsonResponse
    {
        // $cycle = request()->get('cycle', 'thirtyday');//默认30天
        // $data = $this->orderStatisticService()->orderCharts($cycle, Auth::search());
        $data = json_decode('{
  "yAxis": {
    "maxnum": 23,
    "maxprice": "1012525.57"
  },
  "legend": [
    "订单金额",
    "订单数"
  ],
  "xAxis": [
    "01-06",
    "01-07",
    "01-08",
    "01-09",
    "01-10",
    "01-11",
    "01-12",
    "01-13",
    "01-14",
    "01-15",
    "01-16",
    "01-17",
    "01-18",
    "01-19",
    "01-20",
    "01-21",
    "01-22",
    "01-23",
    "01-24",
    "01-25",
    "01-26",
    "01-27",
    "01-28",
    "01-29",
    "01-30",
    "01-31",
    "02-01",
    "02-02",
    "02-03",
    "02-04"
  ],
  "series": [
    {
      "name": "订单金额",
      "type": "bar",
      "itemStyle": {
        "normal": {
          "color": {
            "x": 0,
            "y": 0,
            "x2": 0,
            "y2": 1,
            "colorStops": [
              {
                "offset": 0,
                "color": "#69cdff"
              },
              {
                "offset": 0.5,
                "color": "#3eb3f7"
              },
              {
                "offset": 1,
                "color": "#1495eb"
              }
            ]
          }
        }
      },
      "data": [
        316,
        0,
        0,
        41.22,
        14155,
        0,
        988,
        118631.8,
        0,
        6893,
        1012525.57,
        650267,
        9969.2,
        0,
        564,
        2739,
        3000,
        10063,
        0,
        9511,
        100,
        0,
        0,
        158,
        595,
        12310,
        299.01,
        0,
        44,
        0
      ]
    },
    {
      "name": "订单数",
      "type": "line",
      "itemStyle": {
        "normal": {
          "color": {
            "x": 0,
            "y": 0,
            "x2": 0,
            "y2": 1,
            "colorStops": [
              {
                "offset": 0,
                "color": "#6fdeab"
              },
              {
                "offset": 0.5,
                "color": "#44d693"
              },
              {
                "offset": 1,
                "color": "#2cc981"
              }
            ]
          }
        }
      },
      "data": [
        3,
        0,
        0,
        4,
        4,
        1,
        10,
        10,
        0,
        11,
        23,
        4,
        2,
        0,
        3,
        1,
        1,
        2,
        3,
        2,
        4,
        1,
        0,
        1,
        4,
        5,
        3,
        0,
        1,
        0
      ],
      "yAxisIndex": 1
    }
  ],
  "pre_cycle": {
    "count": {
      "data": 1216
    },
    "price": {
      "data": "3395214.20"
    }
  },
  "cycle": {
    "count": {
      "data": 913,
      "percent": 24.92,
      "is_plus": -1
    },
    "price": {
      "data": "4083264.80",
      "percent": 20.27,
      "is_plus": 1
    }
  }
}', true);

        return $this->success($data);
    }

    public function userChart(Request $request): \Illuminate\Http\JsonResponse
    {
        $days = $request->get('cycle', 30); //默认30天
        $appId = $request->get('app_id', 0);
        $userData = $this->userStatisticsService()->userCharts($days, $appId)->reverse();

        $xAxis = [];
        $newUsersArr = [];
        $activeUsersArr = [];
        $maxNewUsersCount = 0;
        $maxActiveUsersCount = 0;

        foreach ($userData as $item) {
            $xAxis[] = $item->date->format('m-d');
            $newUsersArr[] = $item->new_users_count;
            $activeUsersArr[] = $item->active_users_count;

            if ($item->new_users_count > $maxNewUsersCount) {
                $maxNewUsersCount = $item->new_users_count;
            }
            if ($item->active_users_count > $maxActiveUsersCount) {
                $maxActiveUsersCount = $item->active_users_count;
            }
        }

        $data = [
            "yAxis" => [
                "max_active_users_count" => $maxActiveUsersCount,
                "max_new_users_count" => $maxNewUsersCount,
            ],
            "legend" => [
                "活跃用户",
                "新增用户",
            ],
            "xAxis" => $xAxis,
            "series" => [
                [
                    "name" => "活跃用户",
                    "type" => "line",
                    'smooth' => true,
                    "data" => $activeUsersArr,
                ],
                [
                    "name" => "新增用户",
                    "type" => "line",
                    'smooth' => true,
                    "data" => $newUsersArr,
                ],
            ],
        ];

        return $this->success($data);
    }

    /**
     * 待办事统计
     *
     * @return mixed
     */
    public function notice()
    {
        return $this->success();
    }

    /**
     * 消息返回格式
     *
     * @param array $data
     *
     * @return array
     */
    private function noticeData(array $data): array
    {
        // 消息图标
        $iconColor = [
            // 邮件 消息
            'mail' => [
                'icon' => 'md-mail',
                'color' => '#3391e5',
            ],
            // 普通 消息
            'bulb' => [
                'icon' => 'md-bulb',
                'color' => '#87d068',
            ],
            // 警告 消息
            'information' => [
                'icon' => 'md-information',
                'color' => '#fe5c57',
            ],
            // 关注 消息
            'star' => [
                'icon' => 'md-star',
                'color' => '#ff9900',
            ],
            // 申请 消息
            'people' => [
                'icon' => 'md-people',
                'color' => '#f06292',
            ],
        ];
        // 消息类型
        $type = array_keys($iconColor);
        // 默认数据格式
        $default = [
            'icon' => 'md-bulb',
            'iconColor' => '#87d068',
            'title' => '',
            'url' => '',
            'type' => 'bulb',
            'read' => 0,
            'time' => 0,
        ];
        $value = [];
        foreach ($data as $item) {
            $val = array_merge($default, $item);
            if (isset($item['type']) && in_array($item['type'], $type)) {
                $val['type'] = $item['type'];
                $val['iconColor'] = $iconColor[$item['type']]['color'] ?? '';
                $val['icon'] = $iconColor[$item['type']]['icon'] ?? '';
            }
            $value[] = $val;
        }

        return $value;
    }

    /**
     * 格式化菜单
     *
     * @return mixed
     */
    public function menusList()
    {
        /** @var SystemMenuServices $menusServices */
        $menusServices = app(SystemMenuServices::class);
        $list = $menusServices->getSearchList();
        $counts = $menusServices->getColumn([
            ['is_show', '=', 1],
            ['auth_type', '=', 1],
            ['is_del', '=', 0],
            ['is_show_path', '=', 0],
        ], 'pid');
        $data = [];
        foreach ($list as $key => $item) {
            $pid = $item->getData('pid');
            $data[$key] = json_decode($item, true);
            $data[$key]['pid'] = $pid;
            if (in_array($item->id, $counts)) {
                $data[$key]['type'] = 1;
            } else {
                $data[$key]['type'] = 0;
            }
        }

        return $this->success(sort_list_tier($data));
    }

    /**
     * 获取菜单数据
     */
    public function menus()
    {
        /** @var SystemMenuServices $menusServices */
        $menusServices = app(SystemMenuServices::class);
        [$menus, $unique] = $menusServices->getMenusList(adminInfo()['roles'], (int)adminInfo()['level']);

        return $this->success(['menus' => $menus, 'unique' => $unique]);
    }

    /**
     * Form select列表数据获取
     */
    public function formSelectList(Request $request): \Illuminate\Http\JsonResponse
    {
        $type = $request->get('type');
        $firstLabel = $request->get('first_label');
        $firstValue = $request->get('first_value', '');
        $firstOption = !empty($firstLabel) ? ['label' => $firstLabel, 'value' => $firstValue] : [];

        $data = FormOptions::getAllByType($type, $firstOption);

        return $this->success($data);
    }
}
