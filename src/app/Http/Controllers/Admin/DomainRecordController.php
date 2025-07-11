<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 16:42
 */

namespace App\Http\Controllers\Admin;


use App\Helper;
use App\Models\DomainRecord;
use Illuminate\Http\Request;

class DomainRecordController extends Controller
{
    public function post(Request $request)
    {
        $action = $request->post('action');
        switch ($action) {
            case 'select':
                return $this->select($request);
            case 'delete':
                return $this->delete($request);
            default:
                return ['status' => -1, 'message' => '对不起，此操作不存在！'];
        }
    }

    private function select(Request $request)
    {
        $data = DomainRecord::search('admin')->orderBy('id', 'desc')->pageSelect();
        return ['status' => 0, 'message' => '', 'data' => $data];
    }

    private function delete(Request $request)
    {
        $result = ['status' => -1];
        $id = intval($request->post('id'));
        if (!$id || !$row = DomainRecord::find($id)) {
            $result['message'] = '记录不存在';
        } else {
            // 获取域名信息和用户ID，用于返还积分
            $domain = \App\Models\Domain::find($row->did);
            $uid = $row->uid;
            
            // 删除DNS记录
            Helper::deleteRecord($row);
            
            if ($row->delete()) {
                // 如果域名存在且有积分设置，返还积分给用户
                if ($domain && $domain->point > 0) {
                    \App\Models\User::point($uid, '返还', $domain->point, "管理员删除记录返还积分[{$row->name}.{$domain->domain}]({$row->line})");
                }
                
                $result = ['status' => 0, 'message' => '删除成功'];
            } else {
                $result['message'] = '删除失败，请稍后再试！';
            }
        }
        return $result;
    }

}