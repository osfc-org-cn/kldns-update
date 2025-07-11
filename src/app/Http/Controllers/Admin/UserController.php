<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 16:42
 */

namespace App\Http\Controllers\Admin;


use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserPointRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function post(Request $request)
    {
        $action = $request->post('action');
        switch ($action) {
            case 'pointRecord':
                return $this->pointRecord($request);
            case 'point':
                return $this->point($request);
            case 'update':
                return $this->update($request);
            case 'select':
                return $this->select($request);
            case 'delete':
                return $this->delete($request);
            case 'create':
                return $this->create($request);
            default:
                return ['status' => -1, 'message' => '对不起，此操作不存在！'];
        }
    }

    private function pointRecord(Request $request)
    {
        $data = UserPointRecord::search('admin')->orderBy('id', 'desc')->pageSelect();
        return ['status' => 0, 'message' => '', 'data' => $data];
    }

    private function point(Request $request)
    {
        $result = ['status' => -1];
        $uid = intval($request->post('uid'));
        $point = intval($request->post('point'));
        $remark = $request->post('remark');
        $act = $request->post('act') ? 1 : 0;
        if (!$uid || !$row = User::find($uid)) {
            $result['message'] = '用户不存在';
        } elseif ($point < 1) {
            $result['message'] = '请输入正确积分数';
        } elseif (User::point($uid, $act ? '扣除' : '增加', $act ? 0 - $point : $point, $remark)) {
            $result = ['status' => 0, 'message' => $act ? '扣除成功' : '增加成功'];
        } else {
            $result['message'] = '操作失败，请稍后再试！';
        }
        return $result;
    }

    private function update(Request $request)
    {
        $result = ['status' => -1];
        $uid = intval($request->post('uid'));
        $data = [
            'gid' => intval($request->post('gid')),
            'status' => intval($request->post('status')),
            'email' => $request->post('email')
        ];
        $password = $request->post('password');
        if (!$uid || !$row = User::find($uid)) {
            $result['message'] = '用户不存在';
        } elseif (!UserGroup::find($data['gid'])) {
            $result['message'] = '用户组不存在';
        } elseif ($password && strlen($password) < 5) {
            $result['message'] = '新密码太简单';
        } else {
            if ($password) {
                $data['password'] = Hash::make($password);
                $data['sid'] = md5(uniqid() . Str::random(15));
            }
            if ($row->update($data)) {
                $result = ['status' => 0, 'message' => '修改成功'];
            } else {
                $result['message'] = '修改失败或未做任何修改！';
            }
        }
        return $result;
    }

    private function select(Request $request)
    {
        $data = User::search()->where('gid', '!=', 99)->orderBy('uid', 'desc')->pageSelect();
        return ['status' => 0, 'message' => '', 'data' => $data];
    }

    private function delete(Request $request)
    {
        $result = ['status' => -1];
        $id = intval($request->post('id'));
        if (!$id || !$row = User::find($id)) {
            $result['message'] = '用户不存在';
        } elseif ($row->delete()) {
            $result = ['status' => 0, 'message' => '删除成功'];
        } else {
            $result['message'] = '删除失败，请稍后再试！';
        }
        return $result;
    }

    private function create(Request $request)
    {
        $result = ['status' => -1];
        $data = [
            'username' => $request->post('username'),
            'email' => $request->post('email'),
            'gid' => intval($request->post('gid')),
            'status' => intval($request->post('status')),
            'point' => intval($request->post('point', 0)),
            'password' => $request->post('password'),
            'sid' => md5(uniqid() . Str::random(15))
        ];
        
        // 验证用户名
        if (empty($data['username']) || strlen($data['username']) < 3) {
            $result['message'] = '用户名不能为空且长度至少为3个字符';
        }
        // 验证邮箱
        elseif (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $result['message'] = '请输入有效的邮箱地址';
        }
        // 验证用户组
        elseif (!UserGroup::find($data['gid'])) {
            $result['message'] = '用户组不存在';
        }
        // 验证密码
        elseif (empty($data['password']) || strlen($data['password']) < 5) {
            $result['message'] = '密码不能为空且长度至少为5个字符';
        }
        // 验证用户名唯一性
        elseif (User::where('username', $data['username'])->exists()) {
            $result['message'] = '用户名已存在';
        }
        // 验证邮箱唯一性
        elseif (User::where('email', $data['email'])->exists()) {
            $result['message'] = '邮箱已被使用';
        }
        else {
            // 加密密码
            $data['password'] = Hash::make($data['password']);
            
            // 创建用户
            if ($user = User::create($data)) {
                $result = ['status' => 0, 'message' => '创建用户成功'];
            } else {
                $result['message'] = '创建用户失败，请稍后再试！';
            }
        }
        
        return $result;
    }
}