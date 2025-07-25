<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 16:41
 */

namespace App\Http\Controllers\Home;


use App\Helper;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\DomainRecord;
use App\Models\User;
use App\Models\UserPointRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    public function post(Request $request)
    {
        $action = $request->post('action');
        switch ($action) {
            case 'verify':
                return $this->verify($request);
            case 'profile':
                return $this->profile($request);
            case 'recordList':
                return $this->recordList($request);
            case 'domainList':
                return $this->domainList($request);
            case 'pointRecord':
                return $this->pointRecord($request);
        }
        if (Auth::user()->status != 2) {
            return ['status' => -1, 'message' => "对不起，请先完成认证！<a href='/home/profile'>点击认证</a>"];
        }

        switch ($action) {
            case 'recordStore':
                return $this->recordStore($request);
            case 'recordDelete':
                return $this->recordDelete($request);
            default:
                return ['status' => -1, 'message' => '对不起，此操作不存在！'];
        }
    }

    private function verify(Request $request)
    {
        $result = ['status' => -1];
        $user = Auth::user();
        if ($user->status != 1) {
            $result['message'] = '当前状态不需要认证';
        } else {
            list($ret, $error) = Helper::sendVerifyEmail($user);
            if ($ret) {
                $result = ['status' => 0, 'message' => "已将认证邮件发送到{$user->email}，请注意查收！"];
            } else {
                $result['message'] = "发送邮件失败：" . $error;
            }
        }
        return $result;
    }

    private function profile(Request $request)
    {
        $result = ['status' => -1];
        $old_password = $request->post('old_password');
        $new_password = $request->post('new_password');
        if (strlen($old_password) < 5) {
            $result['message'] = '旧密码验证失败';
        } elseif (!Hash::check($old_password, Auth::user()->password)) {
            $result['message'] = '旧密码验证失败';
        } elseif (strlen($new_password) < 5) {
            $result['message'] = '新密码太简单';
        } else {
            if (User::where('uid', Auth::id())->update([
                'password' => Hash::make($new_password),
                'sid' => md5(uniqid() . Str::random())
            ])) {
                $result = ['status' => 0, 'message' => '修改成功'];
            } else {
                $result['message'] = '修改失败，请稍后再试！';
            }
        }
        return $result;
    }

    private function pointRecord(Request $request)
    {
        $data = UserPointRecord::search()->where('uid', Auth::id())->orderBy('id', 'desc')->pageSelect();
        return ['status' => 0, 'message' => '', 'data' => $data];
    }

    private function recordStore(Request $request)
    {
        $result = ['status' => -1];
        $id = intval($request->post('id'));
        $data = [
            'uid' => Auth::id(),
            'did' => intval($request->post('did')),
            'name' => $request->post('name'),
            'type' => $request->post('type'),
            'line_id' => $request->post('line_id'),
            'value' => $request->post('value'),
            'line' => '默认'
        ];
        
        // 检查用户是否被隐式禁用
        if (Auth::user()->status === 3) {
            $errorCode = config('sys.user.implicit_ban_code', 'ERROR');
            $result['message'] = "错误，未知错误（code:{$errorCode},uid:" . Auth::id() . "）";
            return $result;
        }
        
        list($check, $error) = Helper::checkDomainName($data['name']);
        if (!$check) {
            $result['message'] = $error;
        } elseif ($id && !$record = DomainRecord::where('uid', Auth::id())->where('id', $id)->first()) {
            $result['message'] = '记录不存在';
        } elseif (!$data['value']) {
            $result['message'] = '请输入记录值';
        } elseif (!$id && DomainRecord::where('did', $data['did'])->where('name', $data['name'])->where('uid', '!=', Auth::id())->where('line_id', $data['line_id'])->first()) {
            $result['message'] = '此主机记录已被使用';
        } elseif (!$domain = Domain::available()->where('did', $data['did'])->first()) {
            $result['message'] = '域名不存在，或无此权限';
        } elseif (!$dns = $domain->dnsConfig) {
            $result['message'] = '域名配置错误[No Config]';
        } elseif (!$_dns = \App\Klsf\Dns\Helper::getModel($dns->dns)) {
            $result['message'] = '域名配置错误[Unsupporte]';
        } else {
            // 检查服务商是否已有该记录（防止与服务商记录冲突）
            if (!$id) {
                try {
                    $_dns->config($dns->config);
                    
                    // 直接从服务商API获取记录列表
                    list($records, $error) = $_dns->getDomainRecords($domain->domain_id, $domain->domain);
                    
                    if (is_array($records)) {
                        // 标记是否找到匹配记录
                        $foundMatchingRecord = false;
                        
                        foreach ($records as $remoteRecord) {
                            // 获取记录名称（兼容不同服务商的返回格式）
                            $recordName = isset($remoteRecord['Name']) ? $remoteRecord['Name'] : 
                                         (isset($remoteRecord['name']) ? $remoteRecord['name'] : null);
                                         
                            // 获取记录ID（兼容不同服务商的返回格式）
                            $recordId = isset($remoteRecord['RecordId']) ? $remoteRecord['RecordId'] : 
                                       (isset($remoteRecord['id']) ? $remoteRecord['id'] : null);
                            
                            // 检查记录名称是否匹配（不区分大小写）
                            if ($recordName && strtolower($recordName) === strtolower($data['name'])) {
                                $foundMatchingRecord = true;
                                
                                // 检查数据库中是否有此记录
                                $existingRecord = DomainRecord::where('record_id', $recordId)->first();
                                
                                if ($existingRecord) {
                                    // 记录存在于数据库，检查是否属于当前用户
                                    if ($existingRecord->uid != Auth::id()) {
                                        $result['message'] = '此主机记录已被其他用户使用';
                                        return $result;
                                    }
                                    // 如果是当前用户的记录，允许继续（可能是更新操作）
                                } else {
                                    // 记录存在于服务商，但不在数据库中，说明是服务商手动添加的
                                    $result['message'] = '此主机记录已在服务商存在，请使用其他名称';
                                    return $result;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('检查服务商记录失败: ' . $e->getMessage());
                    // 如果检查失败，继续处理
                }
            }

            $_dns->config($dns->config);
            $lines = $_dns->getRecordLine($domain->domain_id, $domain->domain);
            foreach ($lines as $line) {
                if ($line['Id'] == $data['line_id']) {
                    $data['line'] = $line['Name'];
                }
            }
            if ($id) {
                //更新
                list($ret, $error) = $_dns->updateDomainRecord($record->record_id, $data['name'], $data['type'], $data['value'], $data['line_id'], $domain->domain_id, $domain->domain);
                if ($ret) {
                    if (DomainRecord::where('id', $id)->update($data)) {
                        $result = ['status' => 0, 'message' => '更新成功'];
                    } else {
                        $result['message'] = '更新失败，请稍后再试！';
                    }
                } else {
                    $result['message'] = '更新记录失败:' . $error;
                }
            } else {
                //添加
                if ($domain->point > 0 && Auth::user()->point < $domain->point) {
                    $result['message'] = '账户剩余积分不足！';
                } else {
                    list($ret, $error) = $_dns->addDomainRecord($data['name'], $data['type'], $data['value'], $data['line_id'], $domain->domain_id, $domain->domain);
                    if ($ret) {
                        if ($domain->point > 0 && !User::point(Auth::id(), '消费', 0 - $domain->point, "添加记录[{$data['name']}.{$domain->domain}]({$data['line']})")) {
                            $result['message'] = '账户剩余积分不足！';
                            $_dns->deleteDomainRecord($ret['RecordId'], $domain->domain_id, $domain->domain);
                        } else {
                            $data['record_id'] = $ret['RecordId'];
                            if (DomainRecord::create($data)) {
                                $result = ['status' => 0, 'message' => '添加成功'];
                            } else {
                                list($ret, $error) = $_dns->deleteDomainRecord($ret['RecordId'], $domain->domain_id, $domain->domain);
                                $result['message'] = '添加失败，请稍后再试！';
                            }
                        }
                    } else {
                        $result['message'] = '添加记录失败:' . $error;
                    }
                }
            }
        }
        return $result;
    }

    private function recordList(Request $request)
    {
        $data = DomainRecord::search()
            ->where('uid', auth()->id())
            ->orderBy('id', 'desc')
            ->pageSelect();
        return ['status' => 0, 'message' => '', 'data' => $data];
    }

    private function domainList(Request $request)
    {
        $data = Domain::with('dnsConfig')->available()->get();
        $list = [];
        foreach ($data as $domain) {
            if ($dns = $domain->dnsConfig) {
                if ($_dns = \App\Klsf\Dns\Helper::getModel($dns->dns)) {
                    $_dns->config($dns->config);
                    $list[] = [
                        'did' => $domain->did,
                        'domain' => $domain->domain,
                        'point' => $domain->point,
                        'desc' => $domain->desc,
                        'line' => $_dns->getRecordLine()
                    ];
                }
            }
        }
        return ['status' => 0, 'message' => '', 'data' => $list];
    }

    private function recordDelete(Request $request)
    {
        $result = ['status' => -1];
        $id = intval($request->post('id'));
        if (!$id || !$row = DomainRecord::where('id', $id)->where('uid', Auth::id())->first()) {
            $result['message'] = '记录不存在';
        } else {
            // 获取域名信息，用于返还积分
            $domain = Domain::find($row->did);
            
            // 删除DNS记录
            Helper::deleteRecord($row);
            
            if ($row->delete()) {
                // 如果域名存在且有积分设置，返还积分
                if ($domain && $domain->point > 0) {
                    User::point(Auth::id(), '返还', $domain->point, "删除记录返还积分[{$row->name}.{$domain->domain}]({$row->line})");
                }
                
                $result = ['status' => 0, 'message' => '删除成功'];
            } else {
                $result['message'] = '删除失败，请稍后再试！';
            }
        }
        return $result;
    }

}