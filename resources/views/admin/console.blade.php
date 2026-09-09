@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('数据统计') }}@endsection
@section('link')
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/datatables/datatables.min.css" rel="stylesheet" type="text/css">
@endsection

@section('content')
<div class="row">
    @if (PHP_OS === 'Linux')
    <div class="col-md-3 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="text-center">
                    <h5 class="font-size-14 mb-3">服务器CPU使用率</h5>
                    <input id="cpu_used" class="knob" data-width="150" data-height="130" data-step="0.01" value="0.00" data-angleOffset="-125" data-angleArc="250" data-readOnly="true">
                    <h5 class="font-size-14 mt-3">当前服务器为 <a id="cpu">获取中</a> 核心处理器</h5>
                    <h5 class="font-size-10 mt-3"><a id="cpu_info">获取中</a></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="text-center">
                    <h5 class="font-size-14 mb-3">服务器内存使用率</h5>
                    <input id="mem_used" class="knob" data-width="150" data-height="130" data-step="0.01" value="0.00" data-angleOffset="-125" data-angleArc="250" data-readOnly="true">
                    <h5 class="font-size-14 mt-3">当前服务器为 <a id="mem">获取中</a> 运行内存</h5>
                    <h5 class="font-size-10 mt-3"><a id="mem_info">获取中</a></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="text-center">
                    <h5 class="font-size-14 mb-3">机器系统盘使用率</h5>
                    <input id="disk_used" class="knob" data-width="150" data-height="130" data-step="0.01" value="0.00" data-angleOffset="-125" data-angleArc="250" data-readOnly="true">
                    <h5 class="font-size-14 mt-3">当前服务器为 <a id="disk">获取中</a> 系统盘</h5>
                    <h5 class="font-size-10 mt-3"><a id="disk_info">获取中</a></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="text-center">
                    <h5 class="font-size-14 mb-3">机器挂载盘使用率</h5>
                    <input id="disk_mount_used" class="knob" data-width="150" data-height="130" data-step="0.01" value="0.00" data-angleOffset="-125" data-angleArc="250" data-readOnly="true">
                    <h5 class="font-size-14 mt-3">当前服务器为 <a id="disk_mount">获取中</a> 挂载盘</h5>
                    <h5 class="font-size-10 mt-3"><a id="disk_mount_info">获取中</a></h5>
                </div>
            </div>
        </div>
    </div>
    @endif
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span id="sign_total">0</span> 个</h4>
                    <p class="text-muted mb-0">签名软件总计次数</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span id="sign_pending_total">0</span> 个</h4>
                    <p class="text-muted mb-0">正在签名任务次数</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span id="sign_completed_total">0</span> 个</h4>
                    <p class="text-muted mb-0">签名软件任务次数</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span id="sign_failed_total">0</span> 个</h4>
                    <p class="text-muted mb-0">签名任务失败次数</p>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4">签名任务列表</h4>
                <div class="table-responsive">
                    <table id="DataTable" class="table table-striped table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;"></table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('script')
<script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/jquery-knob/jquery-knob.min.js"></script>
<script>
    function Monitoring() {
        SendAjax({
            url: systemPath+'/detection',
            type: 'post',
            'successCallBack': function (response) {
                @if (PHP_OS === 'Linux')
                $('#cpu').html(response['data']['cpu']);
                $('#mem').html(response['data']['mem']);
                $('#disk').html(response['data']['disk']);
                $('#disk_mount').html(response['data']['disk_mount']);
                $('#cpu_info').html(response['data']['cpu_info']);
                $('#mem_info').html(response['data']['mem_info']);
                $('#disk_info').html(response['data']['disk_info']);
                $('#disk_mount_info').html(response['data']['disk_mount_info']);
                $('#cpu_used').val(response['data']['cpu_used']).trigger('change');
                $('#mem_used').val(response['data']['mem_used']).trigger('change');
                $('#disk_used').val(response['data']['disk_used']).trigger('change');
                $('#disk_mount_used').val(response['data']['disk_mount_used']).trigger('change');
                $('#sign_total').html(response['data']['sign_total']);
                $('#sign_pending_total').html(response['data']['sign_pending_total']);
                $('#sign_completed_total').html(response['data']['sign_completed_total']);
                $('#sign_failed_total').html(response['data']['sign_failed_total']);
                @endif
                setTimeout(Monitoring, 2000);
                return false;
            }
        });
    }
    Monitoring();
    let DataTable = $('#DataTable');
    let signTableList = {
        extend: 'collection',
        text: '更多操作',
        buttons: [
            { text: '清空所有', action: function () {
                    layer.confirm('您将清空所有签名记录，并且重置数据库递增？此操作会导致正在签名的任务异常停止，并所有已签名的安装包都将被清理！', {
                        btn: ['确定清空', '取消']
                    }, function() {
                        layer.load(2);
                        SendAjax({
                            url: systemPath+'/console/delete',
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
    DataTablesConfig.buttons.push(signTableList);
    DataTable.DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        buttons: DataTablesConfig.buttons,
        ajax: function(data, callback) {
            SendAjax({
                url: systemPath+'/console',
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
        order: [[ 11, 'desc' ]],
        columns: [
            {
                title: '<small>ID</small>',
                data: 'id',
                render: function(data, type, row) {
                    return '<small>'+row['id']+'</small>';
                }
            },
            {
                title: '<small>状态</small>',
                data: 'status',
                render: function(data, type, row) {
                    switch (row['status']) {
                        case 'pending':
                            return '<div class="badge bg-warning">pending</div>';
                        case 'failed':
                            return '<div class="badge bg-danger">failed</div>';
                        case 'completed':
                            return '<div class="badge bg-success">completed</div>';
                    }
                }
            },
            {
                title: '<small>签名进展</small>',
                data: 'progressing',
                render: function(data, type, row) {
                    return '<small>'+row['progressing']+'</small>';
                }
            },
            {
                title: '<small>UDID</small>',
                data: 'udid',
                render: function(data, type, row) {
                    return '<small>'+row['udid']+'</small>';
                }
            },
            {
                title: '<small>证书ID</small>',
                data: 'cert_id',
                render: function(data, type, row) {
                    return '<small>'+row['cert_id']+'</small>';
                }
            },
            {
                title: '<small>应用ID</small>',
                data: 'app_id',
                render: function(data, type, row) {
                    return '<small>'+row['app_id']+'</small>';
                }
            },
            {
                title: '<small>重签名</small>',
                data: 'app_name',
                render: function(data, type, row) {
                    return '<small>'+row['app_name']+'</small>';
                }
            },
            {
                title: '<small>重签版本</small>',
                data: 'app_version',
                render: function(data, type, row) {
                    return '<small>'+row['app_version']+'</small>';
                }
            },
            {
                title: '<small>重签包ID</small>',
                data: 'app_bid',
                render: function(data, type, row) {
                    return '<small>'+row['app_bid']+'</small>';
                }
            },
            {
                title: '<small>多开数量</small>',
                data: 'multiple_num',
                render: function(data, type, row) {
                    return '<small>'+row['multiple_num']+'</small>';
                }
            },
            {
                title: '<small>多开初始值</small>',
                data: 'multiple_init',
                render: function(data, type, row) {
                    return '<small>'+row['multiple_init']+'</small>';
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
                    let op = '';
                    switch (row['status']) {
                        case 'pending':
                            op = '<div class="resign-btn badge bg-success">重试签名任务</div>';
                            break;
                        case 'failed':
                            op = '<div class="resign-btn badge bg-success">重试签名任务</div>';
                            break;
                    }
                    return `
                    <div class="delete-btn badge bg-danger">删除数据</div>
                    <div class="query-btn badge bg-info">获取最新状态</div>
                    ${op}
                    `;
                }
            },
        ]
    });
    DataTable.on('click', '.query-btn', function() {
        let table = DataTable.DataTable();
        let data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.load(2);
        SendAjax({
            url: '/api/query_sign',
            data: {
                sign_id: data['id']
            },
            'successCallBack': function (response) {
                layer.msg(`
                签名 ${response['data']['app_name']} 成功<br><br>
                共计签名 ${response['data']['multiple_num']} 个<br><br>
                安装有效期：${response['data']['sign'][0]['expiration_date']}
                `, {icon: 1, time: 3000});
            }
        });
    });
    DataTable.on('click', '.delete-btn', function() {
        let table = DataTable.DataTable();
        let data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要删除签名任务 “'+data['id']+'” 吗？', {
            btn: ['确定', '取消']
        }, function() {
            layer.load(2);
            SendAjax({
                url: systemPath+'/console/delete',
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
    DataTable.on('click', '.resign-btn', function() {
        let table = DataTable.DataTable();
        let data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.confirm(`您确定要重新尝试此任务吗？<br>
        任务 ID：${data['id']}<br>
        软件 ID：${data['app_id']}<br>
        签名软件：${data['app_name']}<br>
        软件版本：${data['app_version']}<br>
        多开数量：${data['multiple_num']}<br>
        初始数值：${data['multiple_init']}<br>
        设备标识：${data['udid']}<br>
        证书 ID：${data['cert_id']}<br>
        当前进度：${data['progressing']}<code>【 请确保此原因已解决】</code><br>
        注意事项：请您在确定此任务为失败的情况下再去操作，否则有几率触发签名异常结果！
        `, {
            btn: ['确定', '取消']
        }, function() {
            layer.load(2);
            SendAjax({
                url: systemPath+'/console/resign',
                data: {
                    id: data['id']
                },
                type: 'post',
                'successCallBack': function (response) {
                    DataTable.DataTable().row($(this)).remove().draw();
                    layer.msg(response['message'], { icon: 1, time: 3000 });
                }
            });
        }, function() {
            layer.msg('已取消操作');
        });
    });
    $(".knob").knob({
        max: 100.00,
        min: 0.00,
        fgColor: '#16b777',
        format: function(value) {
            if (value === undefined || isNaN(value)) {
                value = 0;
                return value;
            } else {
                if (value > 100) {
                    value = 100;
                    return value;
                } else {
                    return value;
                }
            }
        }
    });
    $('.knob').on('change', function() {
        const value = parseFloat($(this).val()).toFixed(2);
        if (value >= 90.00) {
            $(this).trigger('configure', { fgColor: '#ff5722' });
            $(this).css('color', '#ff5722');
        } else if (value >= 70.00) {
            $(this).trigger('configure', { fgColor: '#ffb800' });
            $(this).css('color', '#ffb800');
        } else {
            $(this).trigger('configure', { fgColor: '#16b777' });
            $(this).css('color', '#16b777');
        }
    });
</script>
@endsection