@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('软件列表') }}@endsection
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
        let appTableList = {
            extend: 'collection',
            text: '更多操作',
            buttons: [
                {
                    text: '清空所有', action: function () {
                        layer.confirm('您将清空所有软件，并且重置数据库递增？', {
                            btn: ['确定清空', '取消']
                        }, function() {
                            layer.load(2);
                            SendAjax({
                                url: systemPath+'/app/delete',
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
        DataTablesConfig.buttons.push(appTableList);
        DataTable.DataTable({
            dom: DataTablesConfig.dom,
            language: DataTablesConfig.language,
            processing: DataTablesConfig.processing,
            serverSide: DataTablesConfig.serverSide,
            pagingType: DataTablesConfig.pagingType,
            buttons: DataTablesConfig.buttons,
            ajax: function(data, callback) {
                SendAjax({
                    url: systemPath+'/app',
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
            order: [[ 7, 'desc' ]],
            columns: [
                {
                    title: '<small>软件名</small>',
                    data: 'app_name',
                    render: function(data, type, row) {
                        return '<small>'+row['app_name']+'</small>';
                    }
                },
                {
                    title: '<small>软件ID</small>',
                    data: 'app_id',
                    render: function(data, type, row) {
                        return '<small>'+row['app_id']+'</small>';
                    }
                },
                {
                    title: '<small>软件包ID</small>',
                    data: 'app_bid',
                    render: function(data, type, row) {
                        return '<small>'+row['app_bid']+'</small>';
                    }
                },
                {
                    title: '<small>软件版本</small>',
                    data: 'app_version',
                    render: function(data, type, row) {
                        return '<small>'+row['app_version']+'</small>';
                    }
                },
                {
                    title: '<small>软件介绍</small>',
                    data: 'app_introduction',
                    render: function(data, type, row) {
                        return '<small>'+row['app_introduction']+'</small>';
                    }
                },
                {
                    title: '<small>软件分类</small>',
                    data: 'class_id',
                    render: function(data, type, row) {
                        return '<small>'+row['class_id']+'</small>';
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
                    render: function(data, type, row) {
                        return `
                    <div class="edit-btn badge bg-info">编辑数据</div>
                    <div class="delete-btn badge bg-danger">删除数据</div>
                    ${(row['status'] === 'ENABLED') ? '<div class="disable-btn badge bg-dark">隐藏软件</div>' : '<div class="enabled-btn badge bg-primary">显示软件</div>'}
                    ${(row['updateIPA']) ? '<div class="updateIPA-btn badge bg-success">同步官方最新版</div>' : ''}
                    `;
                    }
                },
            ]
        });
        DataTable.on('click', '.edit-btn', function() {
            let table = DataTable.DataTable();
            let data = table.row($(this)).data();
            if (data === undefined) {
                data = table.row($(this).closest('tr')).data();
            }
            RedirectTo(systemPath+'/app/edit/'+data['app_id']);
        });
        DataTable.on('click', '.enabled-btn', function() {
            let table = DataTable.DataTable();
            let data = table.row($(this)).data();
            if (data === undefined) {
                data = table.row($(this).closest('tr')).data();
            }
            layer.load(2);
            SendAjax({
                url: systemPath+'/app/edit/'+data['app_id'],
                data: {
                    status: 'ENABLED'
                },
                type: 'put',
                'successCallBack': function (response) {
                    DataTable.DataTable().row($(this)).remove().draw();
                    layer.msg(response['message'], { icon: 1, time: 3000 });
                }
            });
        });
        DataTable.on('click', '.disable-btn', function() {
            let table = DataTable.DataTable();
            let data = table.row($(this)).data();
            if (data === undefined) {
                data = table.row($(this).closest('tr')).data();
            }
            layer.load(2);
            SendAjax({
                url: systemPath+'/app/edit/'+data['app_id'],
                data: {
                    status: 'DISABLED'
                },
                type: 'put',
                'successCallBack': function (response) {
                    DataTable.DataTable().row($(this)).remove().draw();
                    layer.msg(response['message'], { icon: 1, time: 3000 });
                }
            });
        });
        DataTable.on('click', '.delete-btn', function() {
            let table = DataTable.DataTable();
            let data = table.row($(this)).data();
            if (data === undefined) {
                data = table.row($(this).closest('tr')).data();
            }
            layer.confirm('您确定要删除软件 “'+data['app_name']+'” 吗？', {
                btn: ['确定', '取消']
            }, function() {
                layer.load(2);
                SendAjax({
                    url: systemPath+'/app/delete',
                    data: {
                        app_id: data['app_id']
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
        DataTable.on('click', '.updateIPA-btn', function() {
            let table = DataTable.DataTable();
            let data = table.row($(this)).data();
            if (data === undefined) {
                data = table.row($(this).closest('tr')).data();
            }
            layer.confirm('您确定要一键同步官方最新版 “'+data['app_name']+'” 吗？', {
                btn: ['确定', '取消']
            }, function() {
                layer.load(2);
                SendAjax({
                    url: systemPath+'/app/update',
                    data: {
                        app_id: data['app_id'],
                        app_bid: data['app_bid']
                    },
                    'successCallBack': function (response) {
                        layer.closeAll('loading');
                        layer.msg(response['message'], { icon: 1, time: 4000 });
                        let appId = data['app_id'];
                        let pollCount = 0;
                        const pollMax = 900;
                        const pollOnce = function () {
                            pollCount++;
                            if (pollCount > pollMax) {
                                layer.msg('等待更新结果超时，请稍后手动刷新列表', { icon: 0, time: 5000 });
                                return;
                            }
                            SendAjax({
                                url: systemPath + '/app/update-status',
                                type: 'post',
                                data: { app_id: appId },
                                successCallBack: function (res) {
                                    let st = (res['data'] && res['data']['status']) ? res['data']['status'] : 'idle';
                                    let msg = (res['data'] && res['data']['message']) ? res['data']['message'] : '';
                                    if (st === 'downloading') {
                                        layer.msg(msg || '正在下载更新中...', { icon: 3, time: 2000 });
                                    }
                                    if (st === 'done') {
                                        layer.msg(msg || '更新完成', { icon: 1, time: 4000 });
                                        DataTable.DataTable().ajax.reload(null, false);
                                        return;
                                    }
                                    if (st === 'failed') {
                                        layer.msg(msg || '更新失败', { icon: 2, time: 6000 });
                                        return;
                                    }
                                    if (st === 'idle') {
                                        if (pollCount < 8) {
                                            setTimeout(pollOnce, 2000);
                                        }
                                        return;
                                    }
                                    setTimeout(pollOnce, 2000);
                                }
                            });
                        };
                        setTimeout(pollOnce, 1500);
                    }
                });
            }, function() {
                layer.msg('已取消操作');
            });
        });
    </script>
@endsection