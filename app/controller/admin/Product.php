<?php

namespace app\controller\admin;

use app\controller\Base;
use think\facade\Request;
use think\facade\Validate;
use think\facade\Db;

class Product extends Base
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

        $select = Db::name('product');
        if (!empty($params['product_name'])) {
            $select->where('product_name', 'like', '%' . addslashes(trim($params['product_name'])) . '%');
        }
        if (!empty($params['product_category'])) {
            $select->where('product_category', $params['product_category']);
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $select->where('status', $params['status']);
        }
        $total = $select->count();
        $items = $select->order('product_id', 'desc')->page($page, $limit)->select();

        return msg("ok", "success", ['total' => $total, 'items' => $items]);
    }

    public function get()
    {
        $params = Request::param();

        $validate = Validate::rule([
            'product_id' => 'require|integer',
        ]);
        if (!$validate->check($params)) {
            return msg('error', $validate->getError());
        }

        $item = Db::name('product')->where('product_id', $params['product_id'])->findOrEmpty();

        return msg('ok', 'success', $item);
    }

    public function add()
    {
        $params = Request::param();

        $validate = Validate::rule([
            'product_name|产品名称' => 'require',
            'product_category|产品分类' => 'require|in:服务器,域名,其他',
            'currency|币种' => 'require|in:CNY,USD,EUR',
            'duration|周期' => 'require|in:月付,季付,半年付,年付',
            'price|预算费用' => 'require|float',
            'status' => 'integer',
        ]);
        if (!$validate->check($params)) {
            return msg('error', $validate->getError());
        }

        Db::name('product')->insert([
            'product_name' => trim($params['product_name']),
            'product_category' => trim($params['product_category']),
            'purchase_url' => isset($params['purchase_url']) ? trim($params['purchase_url']) : null,
            'purchase_account' => isset($params['purchase_account']) ? trim($params['purchase_account']) : null,
            'currency' => trim($params['currency']),
            'duration' => trim($params['duration']),
            'price' => floatval($params['price']),
            'renew_method' => isset($params['renew_method']) ? trim($params['renew_method']) : null,
            'remark' => isset($params['remark']) ? trim($params['remark']) : null,
            'status' => isset($params['status']) ? intval($params['status']) : 1,
            'created_at' => date("Y-m-d H:i:s"),
            'updated_at' => date("Y-m-d H:i:s")
        ]);

        return msg();
    }

    public function edit()
    {
        $params = Request::param();

        $validate = Validate::rule([
            'product_id' => 'require|integer',
            'product_name|产品名称' => 'require',
            'product_category|产品分类' => 'require|in:服务器,域名,其他',
            'currency|币种' => 'require|in:CNY,USD,EUR',
            'duration|周期' => 'require|in:月付,季付,半年付,年付',
            'price|预算费用' => 'require|float',
            'status' => 'integer',
        ]);
        if (!$validate->check($params)) {
            return msg('error', $validate->getError());
        }

        Db::name('product')->where('product_id', $params['product_id'])->update([
            'product_name' => trim($params['product_name']),
            'product_category' => trim($params['product_category']),
            'purchase_url' => isset($params['purchase_url']) ? trim($params['purchase_url']) : null,
            'purchase_account' => isset($params['purchase_account']) ? trim($params['purchase_account']) : null,
            'currency' => trim($params['currency']),
            'duration' => trim($params['duration']),
            'price' => floatval($params['price']),
            'renew_method' => isset($params['renew_method']) ? trim($params['renew_method']) : null,
            'remark' => isset($params['remark']) ? trim($params['remark']) : null,
            'status' => isset($params['status']) ? intval($params['status']) : 1,
            'updated_at' => date("Y-m-d H:i:s")
        ]);

        return msg();
    }

    public function delete()
    {
        $params = Request::param();

        $validate = Validate::rule([
            'product_id' => 'require|integer',
        ]);
        if (!$validate->check($params)) {
            return msg('error', $validate->getError());
        }

        Db::name('product')->where('product_id', $params['product_id'])->delete();

        return msg('ok', 'success');
    }

    public function batchUpdate()
    {
        $params = Request::param();

        $validate = Validate::rule([
            'ids' => 'require|array',
            'action' => 'require|in:enable,disable,changeCategory',
        ]);
        if (!$validate->check($params)) {
            return msg('error', $validate->getError());
        }

        $ids = $params['ids'];
        $action = $params['action'];

        if ($action === 'enable') {
            Db::name('product')->where('product_id', 'in', $ids)->update([
                'status' => 1,
                'updated_at' => date("Y-m-d H:i:s")
            ]);
        } elseif ($action === 'disable') {
            Db::name('product')->where('product_id', 'in', $ids)->update([
                'status' => 0,
                'updated_at' => date("Y-m-d H:i:s")
            ]);
        } elseif ($action === 'changeCategory' && !empty($params['product_category'])) {
            Db::name('product')->where('product_id', 'in', $ids)->update([
                'product_category' => trim($params['product_category']),
                'updated_at' => date("Y-m-d H:i:s")
            ]);
        }

        return msg('ok', 'success');
    }
}
