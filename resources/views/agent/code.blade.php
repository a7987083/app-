@extends('agent.layouts.master')
@section('title'){{ __('卡密列表') }}@endsection
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
        let classTableList = {
            extend: 'collection',
            text: '更多操作',
            buttons: [
                {
                    text: '清空所有卡密',
                    action: function () {
                        layer.confirm('您将清空所有的卡密？', {
                            btn: ['确定清空', '取消']
                        }, function(){
                            layer.load(2);
                            SendAjax({
                                url: systemPath+'/code/delete',
                                data: { type: 'all' },
                                type: 'delete',
                                'successCallBack': function (response) {
                                    DataTable.DataTable().row($(this)).remove().draw();
                                    layer.msg(response['message'], { icon: 1, time: 3000 });
                                }
                            });
                        }, function(){
                            layer.msg('已取消操作');
                        });
                    }
                },
                {
                    text: '清空已用卡密',
                    action: function () {
                        layer.confirm('您将清空已用的卡密？', {
                            btn: ['确定清空', '取消']
                        }, function(){
                            layer.load(2);
                            SendAjax({
                                url: systemPath+'/code/delete',
                                data: { type: 'used' },
                                type: 'delete',
                                'successCallBack': function (response) {
                                    DataTable.DataTable().row($(this)).remove().draw();
                                    layer.msg(response['message'], { icon: 1, time: 3000 });
                                }
                            });
                        }, function(){
                            layer.msg('已取消操作');
                        });
                    }
                },
                {
                    text: '清空未用卡密',
                    action: function () {
                        layer.confirm('您将清空未用的卡密？', {
                            btn: ['确定清空', '取消']
                        }, function(){
                            layer.load(2);
                            SendAjax({
                                url: systemPath+'/code/delete',
                                data: { type: 'not-used' },
                                type: 'delete',
                                'successCallBack': function (response) {
                                    DataTable.DataTable().row($(this)).remove().draw();
                                    layer.msg(response['message'], { icon: 1, time: 3000 });
                                }
                            });
                        }, function(){
                            layer.msg('已取消操作');
                        });
                    }
                }
            ],
            fade: 'button-left'
        };
        DataTablesConfig.buttons.push(classTableList);
        DataTable.DataTable({
            dom: DataTablesConfig.dom,
            language: DataTablesConfig.language,
            processing: DataTablesConfig.processing,
            serverSide: DataTablesConfig.serverSide,
            pagingType: DataTablesConfig.pagingType,
            buttons: DataTablesConfig.buttons,
            ajax: function(data, callback) {
                SendAjax({
                    url: systemPath+'/code',
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
                    title: '<small>卡密信息</small>',
                    data: 'code',
                    render: function(data, type, row) {
                        return '<small>' + row['code'] + '</small>';
                    }
                },
                {
                    title: '<small>绑定设备</small>',
                    data: 'udid',
                    render: function(data, type, row) {
                        if (row['udid']) {
                            return '<small>' + row['udid'] + '</small>';
                        } else {
                            return '<small></small>';
                        }
                    }
                },
                {
                    title: '<small>备注信息</small>',
                    data: 'remark',
                    render: function(data, type, row) {
                        if (row.remark) {
                            return '<small>' + row['remark'] + '</small>';
                        } else {
                            return '<small></small>';
                        }
                    }
                },
                {
                    title: '<small>卡密状态</small>',
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
                    title: '<small>卡密类型</small>',
                    data: 'type',
                    render: function(data, type, row) {
                        switch (row['type']) {
                            case 'default':
                                return '<div class="badge bg-primary">默认模式</div>';
                            case 'processing':
                                return '<div class="badge bg-warning">预约证书</div>';
                            case 'good':
                                return '<div class="badge bg-success">秒出证书</div>';
                        }
                    }
                },
                {
                    title: '<small>支持机型</small>',
                    data: 'product',
                    render: function(data, type, row) {
                        switch (row['product']) {
                            case 'DEFAULT':
                                return '<div class="badge bg-info">全部机型</div>';
                            case 'IPAD':
                                return '<div class="badge bg-warning">仅限iPad</div>';
                        }
                    }
                },
                {
                    title: '<small>售后天数</small>',
                    data: 'after_sale_day',
                    render: function(data, type, row) {
                        return '<small>' + row['after_sale_day'] + '</small>';
                    }
                },
                {
                    title: '<small>售后次数</small>',
                    data: 'after_sale_num',
                    render: function(data, type, row) {
                        return '<small>' + row['after_sale_num'] + '</small>';
                    }
                },
                {
                    title: '<small>已用售后</small>',
                    data: 'use_after_sale',
                    render: function(data, type, row) {
                        return '<small>' + row['use_after_sale'] + '</small>';
                    }
                },
                {
                    title: '<small>激活日期</small>',
                    data: 'verified_at',
                    render: function(data, type, row) {
                        return '<small>'+row['verified_at']+'</small>';
                    }
                },
                {
                    title: '<small>到期日期</small>',
                    data: 'maturity_at',
                    render: function(data, type, row) {
                        return '<small>'+row['maturity_at']+'</small>';
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
                    ${ (row['udid']) ? '<div class="inspection-cert-btn badge bg-success">实时状态</div>' : '' }
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
            RedirectTo(systemPath+'/code/edit/'+data['code']);
        });
        DataTable.on('click', '.delete-btn', function() {
            let table = DataTable.DataTable();
            let data = table.row($(this)).data();
            if (data === undefined) {
                data = table.row($(this).closest('tr')).data();
            }
            layer.confirm('您确定要删除卡密 “'+data['code']+'” 吗？', {
                btn: ['确定', '取消']
            }, function() {
                layer.load(2);
                SendAjax({
                    url: systemPath+'/code/delete',
                    data: {
                        code: data['code']
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
        DataTable.on('click', '.inspection-cert-btn', function() {
            let table = DataTable.DataTable();
            let data = table.row($(this)).data();
            if (data === undefined) {
                data = table.row($(this).closest('tr')).data();
            }
            layer.load(2);
            SendAjax({
                'url': '/api/inspection_certificate',
                'data': {
                    udid: data['udid'],
                },
                'successCallBack': function (response) {
                    let realTimeStatus;
                    if (response['data']['certificates']['is_revoked'] === false) {
                        realTimeStatus = '<a style="color: green">证书有效</a>';
                    } else {
                        realTimeStatus = '<a style="color: red">证书撤销</a>';
                    }
                    layer.alert(`
                检测的卡密：<br>${data['code']}<br><br>
                绑定设备码：<br>${data['udid']}<br><br>
                证书所有者：<br>${response['data']['certificates']['name']}<br><br>
                开发者证书当前实时状态：<br>${realTimeStatus}<br><br>
                开发者证书剩余实时天数：<br>${response['data']['mobileprovision']['cert_end_date']} 天<br>
                `);
                }
            });
        });
    </script>
@endsection