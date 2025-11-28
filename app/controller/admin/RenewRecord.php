<?php

namespace app\controller\admin;

use app\controller\Base;
use think\facade\Request;
use think\facade\Validate;
use think\facade\Db;

class RenewRecord extends Base
{
    public function list()
    {
        $params = Request::param();

        $validate = Validate::rule([
            'page' => 'integer',
            'limit' => 'integer',
        ]);
        if (!$validate->check($params)) {
            return msg('error', $validate->getError());
        }

        $page = isset($params['page']) ? intval($params['page']) : 1;
        $limit = isset($params['limit']) ? intval($params['limit']) : 50;

        $select = Db::name('renew_record')
            ->alias('r')
            ->join('product p', 'r.product_id = p.product_id', 'LEFT')
            ->field('r.*, p.product_name, p.product_category');

        if (!empty($params['product_id'])) {
            $select->where('r.product_id', $params['product_id']);
        }
        if (!empty($params['product_category'])) {
            $select->where('p.product_category', $params['product_category']);
        }
        if (!empty($params['currency'])) {
            $select->where('r.currency', $params['currency']);
        }
        if (!empty($params['filter'])) {
            $today = date('Y-m-d');
            if ($params['filter'] === 'expiring_3days') {
                $select->where('r.expire_date', '<=', date('Y-m-d', strtotime('+3 days')))
                    ->where('r.expire_date', '>=', $today);
            } elseif ($params['filter'] === 'expiring_7days') {
                $select->where('r.expire_date', '<=', date('Y-m-d', strtotime('+7 days')))
                    ->where('r.expire_date', '>=', $today);
            } elseif ($params['filter'] === 'expiring_30days') {
                $select->where('r.expire_date', '<=', date('Y-m-d', strtotime('+30 days')))
                    ->where('r.expire_date', '>=', $today);
            } elseif ($params['filter'] === 'expired') {
                $select->where('r.expire_date', '<', $today);
            }
        }

        $total = $select->count();
        $items = $select->order('r.expire_date', 'asc')
            ->order('r.amount', 'desc')
            ->order('p.product_category', 'asc')
            ->page($page, $limit)
            ->select();

        return msg("ok", "success", ['total' => $total, 'items' => $items]);
    }

    public function get()
    {
        $params = Request::param();

        $validate = Validate::rule([
            'renew_id' => 'require|integer',
        ]);
        if (!$validate->check($params)) {
            return msg('error', $validate->getError());
        }

        $item = Db::name('renew_record')
            ->alias('r')
            ->join('product p', 'r.product_id = p.product_id', 'LEFT')
            ->field('r.*, p.product_name, p.product_category')
            ->where('r.renew_id', $params['renew_id'])
            ->findOrEmpty();

        return msg('ok', 'success', $item);
    }

    public function add()
    {
        $params = Request::param();

        $validate = Validate::rule([
            'product_id|产品ID' => 'require|integer',
            'start_date|开始日期' => 'require|date',
            'expire_date|结束日期' => 'require|date',
            'amount|支付金额' => 'require|float',
            'currency|币种' => 'require|in:CNY,USD,EUR',
        ]);
        if (!$validate->check($params)) {
            return msg('error', $validate->getError());
        }

        // 验证产品是否存在
        $product = Db::name('product')->where('product_id', $params['product_id'])->find();
        if (empty($product)) {
            return msg('error', '产品不存在');
        }

        Db::name('renew_record')->insert([
            'product_id' => intval($params['product_id']),
            'start_date' => trim($params['start_date']),
            'expire_date' => trim($params['expire_date']),
            'duration' => isset($params['duration']) ? trim($params['duration']) : null,
            'amount' => floatval($params['amount']),
            'currency' => trim($params['currency']),
            'pay_method' => isset($params['pay_method']) ? trim($params['pay_method']) : null,
            'invoice_url' => isset($params['invoice_url']) ? trim($params['invoice_url']) : null,
            'note' => isset($params['note']) ? trim($params['note']) : null,
            'created_at' => date("Y-m-d H:i:s"),
            'updated_at' => date("Y-m-d H:i:s")
        ]);

        return msg();
    }

    public function edit()
    {
        $params = Request::param();

        $validate = Validate::rule([
            'renew_id' => 'require|integer',
            'product_id|产品ID' => 'require|integer',
            'start_date|开始日期' => 'require|date',
            'expire_date|结束日期' => 'require|date',
            'amount|支付金额' => 'require|float',
            'currency|币种' => 'require|in:CNY,USD,EUR',
        ]);
        if (!$validate->check($params)) {
            return msg('error', $validate->getError());
        }

        Db::name('renew_record')->where('renew_id', $params['renew_id'])->update([
            'product_id' => intval($params['product_id']),
            'start_date' => trim($params['start_date']),
            'expire_date' => trim($params['expire_date']),
            'duration' => isset($params['duration']) ? trim($params['duration']) : null,
            'amount' => floatval($params['amount']),
            'currency' => trim($params['currency']),
            'pay_method' => isset($params['pay_method']) ? trim($params['pay_method']) : null,
            'invoice_url' => isset($params['invoice_url']) ? trim($params['invoice_url']) : null,
            'note' => isset($params['note']) ? trim($params['note']) : null,
            'updated_at' => date("Y-m-d H:i:s")
        ]);

        return msg();
    }

    public function delete()
    {
        $params = Request::param();

        $validate = Validate::rule([
            'renew_id' => 'require|integer',
        ]);
        if (!$validate->check($params)) {
            return msg('error', $validate->getError());
        }

        Db::name('renew_record')->where('renew_id', $params['renew_id'])->delete();

        return msg('ok', 'success');
    }

    public function export()
    {
        $params = Request::param();

        $select = Db::name('renew_record')
            ->alias('r')
            ->join('product p', 'r.product_id = p.product_id', 'LEFT')
            ->field('r.*, p.product_name, p.product_category');

        if (!empty($params['product_id'])) {
            $select->where('r.product_id', $params['product_id']);
        }
        if (!empty($params['product_category'])) {
            $select->where('p.product_category', $params['product_category']);
        }
        if (!empty($params['currency'])) {
            $select->where('r.currency', $params['currency']);
        }

        $items = $select->order('r.expire_date', 'asc')->select();

        // 生成CSV
        $filename = '续费记录_' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // 添加BOM以支持Excel正确显示中文
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // 表头
        fputcsv($output, ['产品名称', '产品分类', '开始日期', '结束日期', '周期', '金额', '币种', '支付方式', '发票链接', '备注', '创建时间']);

        // 数据
        foreach ($items as $item) {
            fputcsv($output, [
                $item['product_name'],
                $item['product_category'],
                $item['start_date'],
                $item['expire_date'],
                $item['duration'] ?? '',
                $item['amount'],
                $item['currency'],
                $item['pay_method'] ?? '',
                $item['invoice_url'] ?? '',
                $item['note'] ?? '',
                $item['created_at']
            ]);
        }

        fclose($output);
        exit;
    }
}

