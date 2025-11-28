<?php

namespace app\controller\admin;

use app\controller\Base;
use think\facade\Request;
use think\facade\Db;

class Finance extends Base
{
    public function dashboard()
    {
        $params = Request::param();
        $targetCurrency = isset($params['currency']) ? $params['currency'] : 'USD';
        $budgetPeriod = isset($params['budget_period']) ? $params['budget_period'] : '月付';

        // 获取汇率
        $rate = Db::name('exchange_rate')->order('rate_id', 'desc')->find();
        if (empty($rate)) {
            // 默认汇率（USD作为基准货币）
            $rate = [
                'rate_cny' => 7.2,  // 1 USD = 7.2 CNY
                'rate_usd' => 1.0,   // USD基准
                'rate_eur' => 0.91  // 1 USD = 0.91 EUR
            ];
        }

        // 计算汇率转换系数（统一转换为USD，再转换为目标币种）
        // rate_cny = 7.2 (1 USD = 7.2 CNY)
        // rate_usd = 1.0 (USD基准)
        // rate_eur = 0.8607 (1 USD = 0.8607 EUR)
        $rateCny = $rate['rate_cny']; // 7.2 (1 USD = 7.2 CNY)
        $rateUsd = $rate['rate_usd']; // 1.0 (USD基准)
        $rateEur = $rate['rate_eur']; // 0.8607 (1 USD = 0.8607 EUR)
        
        // 转换为USD的汇率映射
        // rate_eur表示1 USD = X EUR，所以EUR转USD的系数是1/X（即1/rate_eur）
        $toUsdMap = [
            'CNY' => 1.0 / $rateCny,  // CNY转USD: 1 CNY = 1/rate_cny USD
            'USD' => 1.0,              // USD基准
            'EUR' => 1.0 / $rateEur    // EUR转USD: 1 EUR = 1/rate_eur USD
        ];
        
        // 从USD转换为目标币种的汇率映射
        // rate_eur表示1 USD = X EUR，所以USD转EUR的系数是X（即rate_eur本身）
        $fromUsdMap = [
            'CNY' => $rateCny,        // USD转CNY: 1 USD = rate_cny CNY
            'USD' => 1.0,             // USD基准
            'EUR' => $rateEur         // USD转EUR: 1 USD = rate_eur EUR
        ];

        // 本月总支出
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $monthExpenses = Db::name('renew_record')
            ->where('created_at', '>=', $monthStart . ' 00:00:00')
            ->where('created_at', '<=', $monthEnd . ' 23:59:59')
            ->select();

        $monthTotal = 0;
        foreach ($monthExpenses as $expense) {
            $amount = floatval($expense['amount']);
            $currency = $expense['currency'];
            // 先转换为USD，再转换为目标币种
            $amountInUsd = $amount * ($toUsdMap[$currency] ?? 1.0);
            $convertedAmount = $amountInUsd * ($fromUsdMap[$targetCurrency] ?? 1.0);
            $monthTotal += $convertedAmount;
        }

        // 本年总支出
        $yearStart = date('Y-01-01');
        $yearEnd = date('Y-12-31');
        $yearExpenses = Db::name('renew_record')
            ->where('created_at', '>=', $yearStart . ' 00:00:00')
            ->where('created_at', '<=', $yearEnd . ' 23:59:59')
            ->select();

        $yearTotal = 0;
        foreach ($yearExpenses as $expense) {
            $amount = floatval($expense['amount']);
            $currency = $expense['currency'];
            // 先转换为USD，再转换为目标币种
            $amountInUsd = $amount * ($toUsdMap[$currency] ?? 1.0);
            $convertedAmount = $amountInUsd * ($fromUsdMap[$targetCurrency] ?? 1.0);
            $yearTotal += $convertedAmount;
        }

        // 即将到期产品数量（7天内）
        $today = date('Y-m-d');
        $expiring7Days = Db::name('renew_record')
            ->where('expire_date', '>=', $today)
            ->where('expire_date', '<=', date('Y-m-d', strtotime('+7 days')))
            ->count();

        // 已到期数量
        $expired = Db::name('renew_record')
            ->where('expire_date', '<', $today)
            ->count();

        // 三天内到期产品列表
        $expiring3Days = Db::name('renew_record')
            ->alias('r')
            ->join('product p', 'r.product_id = p.product_id', 'LEFT')
            ->field('r.*, p.product_name, p.product_category')
            ->where('r.expire_date', '>=', $today)
            ->where('r.expire_date', '<=', date('Y-m-d', strtotime('+3 days')))
            ->order('r.expire_date', 'asc')
            ->select();

        // 按分类统计
        $categoryStats = Db::name('renew_record')
            ->alias('r')
            ->join('product p', 'r.product_id = p.product_id', 'LEFT')
            ->field('p.product_category, SUM(r.amount) as total_amount, r.currency')
            ->where('r.created_at', '>=', $yearStart . ' 00:00:00')
            ->where('r.created_at', '<=', $yearEnd . ' 23:59:59')
            ->group('p.product_category, r.currency')
            ->select();

        $categoryData = [];
        foreach ($categoryStats as $stat) {
            $category = $stat['product_category'] ?? '其他';
            if (!isset($categoryData[$category])) {
                $categoryData[$category] = 0;
            }
            $amount = floatval($stat['total_amount']);
            $currency = $stat['currency'];
            // 先转换为USD，再转换为目标币种
            $amountInUsd = $amount * ($toUsdMap[$currency] ?? 1.0);
            $convertedAmount = $amountInUsd * ($fromUsdMap[$targetCurrency] ?? 1.0);
            $categoryData[$category] += $convertedAmount;
        }

        // 本月支出趋势（按天）
        $monthTrend = [];
        $currentMonth = date('Y-m');
        for ($day = 1; $day <= date('t'); $day++) {
            $date = $currentMonth . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
            $dayExpenses = Db::name('renew_record')
                ->where('created_at', '>=', $date . ' 00:00:00')
                ->where('created_at', '<=', $date . ' 23:59:59')
                ->select();

            $dayTotal = 0;
            foreach ($dayExpenses as $expense) {
                $amount = floatval($expense['amount']);
                $currency = $expense['currency'];
                // 先转换为USD，再转换为目标币种
                $amountInUsd = $amount * ($toUsdMap[$currency] ?? 1.0);
                $convertedAmount = $amountInUsd * ($fromUsdMap[$targetCurrency] ?? 1.0);
                $dayTotal += $convertedAmount;
            }
            $monthTrend[] = [
                'date' => $date,
                'amount' => round($dayTotal, 2)
            ];
        }

        // 币种占比
        $currencyStats = Db::name('renew_record')
            ->field('currency, SUM(amount) as total_amount')
            ->where('created_at', '>=', $yearStart . ' 00:00:00')
            ->where('created_at', '<=', $yearEnd . ' 23:59:59')
            ->group('currency')
            ->select();

        $currencyData = [];
        $currencyTotal = 0;
        foreach ($currencyStats as $stat) {
            $amount = floatval($stat['total_amount']);
            $currency = $stat['currency'];
            // 先转换为USD，再转换为目标币种
            $amountInUsd = $amount * ($toUsdMap[$currency] ?? 1.0);
            $convertedAmount = $amountInUsd * ($fromUsdMap[$targetCurrency] ?? 1.0);
            $currencyData[$currency] = round($convertedAmount, 2);
            $currencyTotal += $convertedAmount;
        }

        // 预算费用计算（根据周期）
        // 周期到月数的映射
        $periodToMonths = [
            '月付' => 1,
            '季付' => 3,
            '半年付' => 6,
            '年付' => 12
        ];
        $targetPeriodMonths = $periodToMonths[$budgetPeriod] ?? 1;

        // 获取所有启用的产品
        $products = Db::name('product')
            ->where('status', 1)
            ->select();

        $budgetTotal = 0;
        $budgetRanking = [];
        foreach ($products as $product) {
            $originalPrice = floatval($product['price']);
            $currency = $product['currency'];
            $productDuration = $product['duration'] ?? '月付'; // 默认月付
            
            // 获取产品周期的月数
            $productPeriodMonths = $periodToMonths[$productDuration] ?? 1;
            
            // 将产品价格转换为目标周期的价格
            // 例如：产品是月付100元，要转换为年付：100 * (12/1) = 1200元
            // 例如：产品是年付1000元，要转换为月付：1000 * (1/12) = 83.33元
            $periodPrice = $originalPrice * ($targetPeriodMonths / $productPeriodMonths);
            
            // 转换为USD，再转换为目标币种
            $amountInUsd = $periodPrice * ($toUsdMap[$currency] ?? 1.0);
            $convertedPrice = $amountInUsd * ($fromUsdMap[$targetCurrency] ?? 1.0);
            
            $budgetTotal += $convertedPrice;
            
            $budgetRanking[] = [
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'product_category' => $product['product_category'],
                'currency' => $currency,
                'duration' => $productDuration,
                'original_price' => $periodPrice,
                'converted_price' => $convertedPrice
            ];
        }

        // 按转换后的价格降序排序
        usort($budgetRanking, function($a, $b) {
            return $b['converted_price'] <=> $a['converted_price'];
        });

        return msg('ok', 'success', [
            'overview' => [
                'month_total' => round($monthTotal, 2),
                'year_total' => round($yearTotal, 2),
                'budget_total' => round($budgetTotal, 2),
                'expiring_7days' => $expiring7Days,
                'expired' => $expired
            ],
            'expiring_3days' => $expiring3Days,
            'category_stats' => $categoryData,
            'month_trend' => $monthTrend,
            'currency_stats' => $currencyData,
            'currency_total' => round($currencyTotal, 2),
            'budget_ranking' => $budgetRanking,
            'budget_period' => $budgetPeriod,
            'target_currency' => $targetCurrency,
            'exchange_rate' => $rate
        ]);
    }
}

