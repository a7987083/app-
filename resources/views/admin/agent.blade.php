@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('代理列表') }}@endsection
@section('link')
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/datatables/datatables.min.css" rel="stylesheet" type="text/css">
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="DataTable" class="table table-striped table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;"></table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
    <script>
        let DataTable = $('#DataTable');
        let agentTableList = {
            extend: 'collection',
            text: '更多操作',
            buttons: [
                {
                    text: '清空所有', action: function () {
                        layer.confirm('您将清空所有代理，并且重置数据库递增？', {
                            btn: ['确定清空', '取消']
                        }, function() {
                            layer.load(2);
                            SendAjax({
                                url: systemPath+'/agent/delete',
                                data: {
                                    type: 'all'
                                },
                                type: 'delete',
                                'successCallBack': function (response) {
                                    DataTable.DataTable().row($(this)).remove().draw();
                                    layer.msg(response['message'], { icon: 1, time: 3000 });
                                }
                            });
                        }, function() {
                            layer.msg('已取消操作');
                        });
                    }
                }
            ],
            fade: 'button-left'
        };
        DataTablesConfig.buttons.push(agentTableList);
        DataTable.DataTable({
            dom: DataTablesConfig.dom,
            language: DataTablesConfig.language,
            processing: DataTablesConfig.processing,
            serverSide: DataTablesConfig.serverSide,
            pagingType: DataTablesConfig.pagingType,
            buttons: DataTablesConfig.buttons,
            ajax: function(data, callback) {
                SendAjax({
                    url: systemPath+'/agent',
                    data: data,
                    successCallBack: function(response) {
                        const responseJSON = {};
                        responseJSON.draw = response['data']['draw'];
                        responseJSON.recordsTotal = response['data']['recordsTotal'];
                        responseJSON.recordsFiltered = response['data']['recordsFiltered'];
                        responseJSON.data = response['data']['data'];
                        callback(responseJSON);
                    },
                });
            },
            order: [[ 10, 'desc' ]],
            columns: [
                {
                    title: '<small>ID</small>',
                    data: 'id',
                    render: function(data, type, row) {
                        return '<small>'+row['id']+'</small>';
                    }
                },
                {
                    title: '<small>代理名称</small>',
                    data: 'name',
                    render: function(data, type, row) {
                        return '<small>'+row['name']+'</small>';
                    }
                },
                {
                    title: '<small>备注信息</small>',
                    data: 'remark',
                    render: function(data, type, row) {
                        return '<small>'+row['remark']+'</small>';
                    }
                },
                {
                    title: '<small>绑定邮箱</small>',
                    data: 'email',
                    render: function(data, type, row) {
                        return '<small>'+row['email']+'</small>';
                    }
                },
                {
                    title: '<small>可用余额</small>',
                    data: 'credit',
                    render: function(data, type, row) {
                        return '<small>'+row['credit']+' 点</small>';
                    }
                },
                {
                    title: '<small>默认模式设备价格</small>',
                    data: 'price',
                    render: function(data, type, row) {
                        return '<small>'+row['price']+' 点/台</small>';
                    }
                },
                {
                    title: '<small>秒出证书设备价格</small>',
                    data: 'good_price',
                    render: function(data, type, row) {
                        return '<small>'+row['good_price']+' 点/台</small>';
                    }
                },
                {
                    title: '<small>预约证书设备价格</small>',
                    data: 'processing_price',
                    render: function(data, type, row) {
                        return '<small>'+row['processing_price']+' 点/台</small>';
                    }
                },
                {
                    title: '<small>Token</small>',
                    data: 'token',
                    render: function(data, type, row) {
                        return '<small>'+row['token']+'</small>';
                    }
                },
                {
                    title: '<small>iPad默认模式设备价格</small>',
                    data: 'ipad_price',
                    render: function(data, type, row) {
                        return '<small>'+row['ipad_price']+' 点/台</small>';
                    }
                },
                {
                    title: '<small>iPad秒出证书设备价格</small>',
                    data: 'ipad_good_price',
                    render: function(data, type, row) {
                        return '<small>'+row['ipad_good_price']+' 点/台</small>';
                    }
                },
                {
                    title: '<small>iPad预约证书设备价格</small>',
                    data: 'ipad_processing_price',
                    render: function(data, type, row) {
                        return '<small>'+row['ipad_processing_price']+' 点/台</small>';
                    }
                },
                {
                    title: '<small>代理状态</small>',
                    data: 'status',
                    render: function(data, type, row) {
                        switch (row['status']) {
                            case 'DISABLED':
                                return '<div class="badge bg-danger">DISABLED</div>';
                            case 'ENABLED':
                                return '<div class="badge bg-success">ENABLED</div>';
                        }
                    }
                },
                {
                    title: '<small>创建日期</small>',
                    data: 'created_at',
                    render: function(data, type, row) {
                        return '<small>'+row['created_at']+'</small>';
                    }
                },
                {
                    title: '<small>更新日期</small>',
                    data: 'updated_at',
                    render: function(data, type, row) {
                        return '<small>'+row['updated_at']+'</small>';
                    }
                },
                {
                    title: '<small>功能操作</small>',
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function() {
                        return `
                    <div class="edit-btn badge bg-info">编辑数据</div>
                    <div class="delete-btn badge bg-danger">删除数据</div>
                    <div class="login-btn badge bg-success">登录代理</div>
                    `;
                    }
                },
            ]
        });
        DataTable.on('click', '.login-btn', function() {
            let table = DataTable.DataTable();
            let data = table.row($(this)).data();
            if (data === undefined) {
                data = table.row($(this).closest('tr')).data();
            }
            layer.confirm('您确定要登录代理账户 “'+data['name']+'” 吗？', {
                btn: ['确定', '取消']
            }, function() {
                layer.load(2);
                SendAjax({
                    url: '/agent/login',
                    data: {
                        id: data['id']
                    },
                    'successCallBack': function (response) {
                        layer['closeLast']('loading');
                        layer.msg(response['message'], { icon: 1, time: 3000 }, function (){
                            RedirectTo('/agent/console', true);
                        });
                    }
                });
            }, function() {
                layer.msg('已取消操作');
            });
        });
        DataTable.on('click', '.edit-btn', function() {
            let table = DataTable.DataTable();
            let data = table.row($(this)).data();
            if (data === undefined) {
                data = table.row($(this).closest('tr')).data();
            }
            RedirectTo(systemPath+'/agent/edit/'+data['id']);
        });
        DataTable.on('click', '.delete-btn', function() {
            let table = DataTable.DataTable();
            let data = table.row($(this)).data();
            if (data === undefined) {
                data = table.row($(this).closest('tr')).data();
            }
            layer.confirm('您确定要删除代理 “'+data['name']+'” 吗？', {
                btn: ['确定', '取消']
            }, function() {
                layer.load(2);
                SendAjax({
                    url: systemPath+'/agent/delete',
                    data: {
                        id: data['id']
                    },
                    type: 'delete',
                    'successCallBack': function (response) {
                        DataTable.DataTable().row($(this)).remove().draw();
                        layer.msg(response['message'], { icon: 1, time: 3000 });
                    }
                });
            }, function() {
                layer.msg('已取消操作');
            });
        });
    </script>
@endsection