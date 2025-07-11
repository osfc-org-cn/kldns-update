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
            case 'update':
                return $this->update($request);
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

    private function update(Request $request)
    {
        $result = ['status' => -1];
        $id = intval($request->post('id'));
        $data = [
            'name' => $request->post('name'),
            'type' => $request->post('type'),
            'line_id' => $request->post('line_id'),
            'value' => $request->post('value'),
            'line' => $request->post('line')
        ];
        
        if (!$id || !$record = DomainRecord::find($id)) {
            $result['message'] = '记录不存在';
        } elseif (!$data['value']) {
            $result['message'] = '请输入记录值';
        } elseif (!$domain = \App\Models\Domain::find($record->did)) {
            $result['message'] = '域名不存在';
        } elseif (!$dns = $domain->dnsConfig) {
            $result['message'] = '域名配置错误[No Config]';
        } elseif (!$_dns = \App\Klsf\Dns\Helper::getModel($dns->dns)) {
            $result['message'] = '域名配置错误[Unsupporte]';
        } else {
            $_dns->config($dns->config);
            
            // 更新DNS记录
            list($ret, $error) = $_dns->updateDomainRecord(
                $record->record_id, 
                $data['name'], 
                $data['type'], 
                $data['value'], 
                $data['line_id'], 
                $domain->domain_id, 
                $domain->domain
            );
            
            if ($ret) {
                // 更新数据库记录
                if (DomainRecord::where('id', $id)->update($data)) {
                    $result = ['status' => 0, 'message' => '更新成功'];
                } else {
                    $result['message'] = '更新数据库记录失败，请稍后再试！';
                }
            } else {
                $result['message'] = '更新DNS记录失败: ' . $error;
            }
        }
        
        return $result;
    }
}