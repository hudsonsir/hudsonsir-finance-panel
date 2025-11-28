<?php

namespace app\controller\admin;

use app\controller\Base;
use think\facade\Request;
use think\facade\Validate;
use think\facade\Db;

class ExchangeRate extends Base
{
    public function get()
    {
        $rate = Db::name('exchange_rate')->order('rate_id', 'desc')->find();
        if (empty($rate)) {
            // 返回默认值（USD作为基准货币）
            $rate = [
                'rate_id' => 0,
                'api_source' => 'exchangerate-api',
                'api_key' => 'cae4f2372db0138adff664e4',
                'rate_cny' => 7.200000,  // 1 USD = 7.2 CNY
                'rate_usd' => 1.000000,  // USD基准
                'rate_eur' => 0.910000,  // 1 USD = 0.91 EUR
                'updated_at' => date('Y-m-d H:i:s')
            ];
        } else {
            // 确保返回API KEY（如果不存在则使用默认值）
            if (!isset($rate['api_key']) || empty($rate['api_key'])) {
                $rate['api_key'] = 'cae4f2372db0138adff664e4';
            }
        }
        return msg('ok', 'success', $rate);
    }

    public function set()
    {
        $params = Request::param();

        $validate = Validate::rule([
            'api_source|汇率来源' => 'require|in:exchangerate-api',
            'rate_cny|人民币汇率' => 'require|float',
            'rate_usd|美元汇率' => 'require|float',
            'rate_eur|欧元汇率' => 'require|float',
        ]);
        if (!$validate->check($params)) {
            return msg('error', $validate->getError());
        }

        $apiSource = 'exchangerate-api'; // 固定为exchangerate-api
        $apiKey = isset($params['api_key']) ? trim($params['api_key']) : '';
        
        // 如果API KEY为空，从数据库读取
        if (empty($apiKey)) {
            $rate = Db::name('exchange_rate')->order('rate_id', 'desc')->find();
            if (!empty($rate)) {
                $apiKey = $rate['api_key'] ?? 'cae4f2372db0138adff664e4';
            } else {
                $apiKey = 'cae4f2372db0138adff664e4';
            }
        }

        $rate = Db::name('exchange_rate')->order('rate_id', 'desc')->find();
        $updateData = [
            'api_source' => $apiSource,
            'api_key' => $apiKey,
            'rate_cny' => floatval($params['rate_cny']),
            'rate_usd' => floatval($params['rate_usd']),
            'rate_eur' => floatval($params['rate_eur']),
            'updated_at' => date("Y-m-d H:i:s")
        ];
        
        if (empty($rate)) {
            Db::name('exchange_rate')->insert($updateData);
        } else {
            Db::name('exchange_rate')->where('rate_id', $rate['rate_id'])->update($updateData);
        }

        return msg();
    }

    public function fetch()
    {
        $params = Request::param();

        $rate = Db::name('exchange_rate')->order('rate_id', 'desc')->find();
        if (empty($rate)) {
            return msg('error', '请先配置汇率来源');
        }

        $apiKey = $rate['api_key'] ?? '';

        if (empty($apiKey)) {
            return msg('error', 'API KEY未配置');
        }

        try {
            $url = "https://v6.exchangerate-api.com/v6/{$apiKey}/latest/USD";
            $response = @file_get_contents($url);
            if ($response === false) {
                return msg('error', 'API请求失败，请检查网络连接和API KEY');
            }
            $data = json_decode($response, true);

            if (isset($data['conversion_rates'])) {
                $rates = $data['conversion_rates'];
                // USD作为基准，计算其他币种相对于USD的汇率
                // conversion_rates中CNY表示1 USD = X CNY，所以rate_cny = X (1 USD = X CNY)
                $rateCny = isset($rates['CNY']) ? floatval($rates['CNY']) : 7.2;
                $rateUsd = 1.0; // USD基准
                // conversion_rates中EUR表示1 USD = X EUR，我们直接存储X（1 USD = X EUR）
                // 例如：如果API返回EUR=0.8607（1 USD = 0.8607 EUR），则rate_eur = 0.8607
                $rateEur = isset($rates['EUR']) ? floatval($rates['EUR']) : 0.91;

                // 更新数据库
                Db::name('exchange_rate')->where('rate_id', $rate['rate_id'])->update([
                    'rate_cny' => $rateCny,
                    'rate_usd' => $rateUsd,
                    'rate_eur' => $rateEur,
                    'updated_at' => date("Y-m-d H:i:s")
                ]);

                return msg('ok', 'success', [
                    'rate_cny' => $rateCny,
                    'rate_usd' => $rateUsd,
                    'rate_eur' => $rateEur
                ]);
            } else {
                return msg('error', 'API返回数据格式错误：' . ($data['error'] ?? '未知错误'));
            }
        } catch (\Exception $e) {
            return msg('error', '获取汇率失败：' . $e->getMessage());
        }
    }

    public function test()
    {
        $params = Request::param();

        $validate = Validate::rule([
            'api_key|API KEY' => 'require',
        ]);
        if (!$validate->check($params)) {
            return msg('error', $validate->getError());
        }

        $apiKey = $params['api_key'];

        try {
            $url = "https://v6.exchangerate-api.com/v6/{$apiKey}/latest/USD";
            $response = @file_get_contents($url);
            if ($response === false) {
                return msg('error', 'API请求失败，请检查网络连接和API KEY');
            }
            $data = json_decode($response, true);
            if (isset($data['conversion_rates'])) {
                return msg('ok', 'API测试成功');
            } else {
                return msg('error', 'API返回数据格式错误：' . ($data['error'] ?? '未知错误'));
            }
        } catch (\Exception $e) {
            return msg('error', '测试失败：' . $e->getMessage());
        }
    }
}

