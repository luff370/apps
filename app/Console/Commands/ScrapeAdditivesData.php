<?php
namespace App\Console\Commands;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\FoodAdditive;
use Illuminate\Console\Command;

class ScrapeAdditivesData extends Command
{
    protected $signature = 'app:scrape-additives';
    protected $description = 'Scrape GB 2760 food additives data';

    public function handle()
    {
        FoodAdditive::query()->truncate();

        // 食品添加剂
        $url = 'https://gb2760.cfsa.net.cn/addtives.html'; // 目标网址
        $client = new Client();
        $response = $client->get($url);
        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);

        // 查找表格数据
        $crawler->filter('table tbody tr')->each(function (Crawler $node) {
            $cols = $node->filter('td');
            if ($cols->count() > 0) {
                $data = [
                    'name' => $cols->eq(0)->text(),
                    'function' => $cols->eq(4)->text(),
                    'food_category' => '食品添加剂',
                    'max_usage' => $cols->eq(3)->text(),
                ];

                // 保存数据到数据库
                FoodAdditive::query()->create($data);
            }
        });

        $this->info('Data 食品添加剂 completed successfully!');

        // 加工助剂查询
        $url = 'https://gb2760.cfsa.net.cn/processing.html';
        $response = $client->get($url);
        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);

        // 查找表格数据
        $crawler->filter('table tbody tr')->each(function (Crawler $node) {
            $cols = $node->filter('td');
            if ($cols->count() > 0) {
                $data = [
                    'name' => $cols->eq(0)->text(),
                    'function' => $cols->eq(2)->text(),
                    'food_category' => '加工助剂',
                    'max_usage' => $cols->eq(3)->text(),
                ];

                // 保存数据到数据库
                FoodAdditive::query()->create($data);
            }
        });

        $this->info('Data 加工助剂查询 completed successfully!');

        // 酶制剂查询
        $url ='https://gb2760.cfsa.net.cn/enzyme.html';
        $response = $client->get($url);
        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);

        // 查找表格数据
        $crawler->filter('table tbody tr')->each(function (Crawler $node) {
            $cols = $node->filter('td');
            if ($cols->count() > 0) {
                $data = [
                    'name' => $cols->eq(0)->text(),
                    'function' => $cols->eq(2)->text(),
                    'food_category' => '酶制剂',
                    'max_usage' => $cols->eq(3)->text(),
                ];

                // 保存数据到数据库
                FoodAdditive::query()->create($data);
            }
        });

        $this->info('Data 酶制剂 completed successfully!');

        // 食品用天然香料
        $url = 'https://gb2760.cfsa.net.cn/spices/type/b2.html';
        $response = $client->get($url);
        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);

        // 查找表格数据
        $crawler->filter('table tbody tr')->each(function (Crawler $node) {
            $cols = $node->filter('td');
            if ($cols->count() > 0) {
                $data = [
                    'name' => $cols->eq(1)->text(),
                    'function' => '增香调味',
                    'food_category' => '食品用天然香料',
                    'max_usage' => ''
                ];

                // 保存数据到数据库
                FoodAdditive::query()->create($data);
            }
        });

        $this->info('Data 食品用天然香料 completed successfully!');

        // 食品用合成香料
        $url = 'https://gb2760.cfsa.net.cn/spices/type/b2.html';
        $response = $client->get($url);
        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);

        // 查找表格数据
        $crawler->filter('table tbody tr')->each(function (Crawler $node) {
            $cols = $node->filter('td');
            if ($cols->count() > 0) {
                $data = [
                    'name' => $cols->eq(1)->text(),
                    'function' => '增香调味',
                    'food_category' => '食品用合成香料',
                    'max_usage' => $cols->eq(3)->text()
                ];

                // 保存数据到数据库
                FoodAdditive::query()->create($data);
            }
        });

        $this->info('Data 食品用合成香料 completed successfully!');
    }
}
