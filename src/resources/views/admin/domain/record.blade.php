@extends('admin.layout.index')
@section('title', '记录列表')
@section('content')
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="card">
            <div class="card-header">
                记录列表
            </div>
            <div class="card-header">
                <div class="form-inline">
                    <input type="text" disabled="disabled" class="d-none">
                    <div class="form-group">
                        <select class="form-control" v-model="search.did">
                            <option value="0">所有</option>
                            @foreach(\App\Models\Domain::get() as $domain)
                                <option value="{{ $domain->did }}">{{ $domain->domain }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group ml-1">
                        <select class="form-control" v-model="search.type">
                            <option value="0">所有</option>
                            <option value="A">A记录</option>
                            <option value="AAAA">AAAA记录</option>
                            <option value="CNAME">CANME</option>
                            <option value="TXT">TXT</option>
                        </select>
                    </div>
                    <div class="form-group ml-1">
                        <input type="text" placeholder="UID" class="form-control" v-model="search.uid">
                    </div>
                    <div class="form-group ml-1">
                        <input type="text" placeholder="主机记录" class="form-control" v-model="search.name">
                    </div>
                    <div class="form-group ml-1">
                        <input type="text" placeholder="记录值" class="form-control" v-model="search.value">
                    </div>
                    <a class="btn btn-info ml-1" @click="getList(1)"><i class="fa fa-search"></i> 搜索</a></div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户</th>
                            <th>域名</th>
                            <th>记录类型</th>
                            <th>线路</th>
                            <th>记录值</th>
                            <th>添加时间</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody v-cloak="">
                        <tr v-for="(row,i) in data.data" :key="i">
                            <td>@{{ row.id }}</td>
                            <td>@{{ row.user?row.user.username:'' }}[UID:@{{ row.uid }}]</td>
                            <td>
                                <a :href="'http://'+row.name+'.'+(row.domain?row.domain.domain:'')" target="_blank">
                                    @{{ row.name }}.@{{ row.domain?row.domain.domain:'' }}
                                </a>
                            </td>
                            <td>@{{ row.type }}</td>
                            <td>@{{ row.line }}</td>
                            <td>@{{ row.value }}</td>
                            <td>@{{ row.created_at }}</td>
                            <td>
                                <a href="#modal-update" class="btn btn-sm btn-info" data-toggle="modal"
                                   @click="storeInfo=Object.assign({},row)">编辑</a>
                                <a class="btn btn-sm btn-danger" @click="del(row.id)">删除</a>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer pb-0 text-center">
                @include('admin.layout.pagination')
            </div>
        </div>
        
        <div class="modal fade" id="modal-update">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">修改解析记录</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="form-update">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" :value="storeInfo.id">
                            <input type="hidden" name="line_id" :value="storeInfo.line_id">
                            <input type="hidden" name="line" :value="storeInfo.line">
                            
                            <div class="form-group row">
                                <label class="col-sm-2 col-form-label">域名</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" :value="storeInfo.name+'.'+
                                    (storeInfo.domain?storeInfo.domain.domain:'')" disabled>
                                </div>
                            </div>
                            
                            <div class="form-group row">
                                <label class="col-sm-2 col-form-label">主机记录</label>
                                <div class="col-sm-10">
                                    <input type="text" name="name" class="form-control" :value="storeInfo.name">
                                </div>
                            </div>
                            
                            <div class="form-group row">
                                <label class="col-sm-2 col-form-label">记录类型</label>
                                <div class="col-sm-10">
                                    <select name="type" class="form-control" :value="storeInfo.type">
                                        <option value="A">A</option>
                                        <option value="AAAA">AAAA</option>
                                        <option value="CNAME">CNAME</option>
                                        <option value="TXT">TXT</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group row">
                                <label class="col-sm-2 col-form-label">记录值</label>
                                <div class="col-sm-10">
                                    <input type="text" name="value" class="form-control" :value="storeInfo.value">
                                </div>
                            </div>
                            
                            <div class="form-group row">
                                <label class="col-sm-2 col-form-label">线路</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" :value="storeInfo.line" disabled>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">关闭</button>
                        <button type="button" class="btn btn-primary" @click="form('update')">保存</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('foot')
    <script>
        new Vue({
            el: '#vue',
            data: {
                search: {
                    page: 1, did: 0, name: '', type: 0, value: '', uid: $_GET('uid')
                },
                data: {},
                storeInfo: {}
            },
            methods: {
                getList: function (page) {
                    var vm = this;
                    vm.search.page = typeof page === 'undefined' ? vm.search.page : page;
                    this.$post("/admin/domain/record", vm.search, {action: 'select'})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.data = data.data
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        })
                },
                del: function (id) {
                    if (!confirm('确认删除？')) return;
                    var vm = this;
                    this.$post("/admin/domain/record", {action: 'delete', id: id})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.getList();
                                vm.$message(data.message, 'success');
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        });
                },
                form: function (action) {
                    var vm = this;
                    var form = $('#form-update');
                    var data = form.serialize();
                    this.$post("/admin/domain/record", data)
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.getList();
                                $('#modal-update').modal('hide');
                                vm.$message(data.message, 'success');
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        });
                }
            },
            mounted: function () {
                this.getList();
            }
        });
    </script>
@endsection