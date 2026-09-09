@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('公池列表') }}@endsection
@section('link')
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
@endsection

@section('content')
<div class="alert alert-primary alert-dismissible">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    <strong>平台计费须知【未仔细阅读，全部认定您已知】！</strong><hr>
    公池点数剩余 <a id="public_credit">获取中</a> 点<hr>
    当前公池费用 <a id="public_price">获取中</a> 点/台<hr>
    公池点数说明：仅限用于购买开通公池设备扣费，证书价格根据市场行情波动！<br>
    公池证书保障：所有证书均为续费老账号，100%秒出证书，不卡设备！<br>
    公池证书优势：秒出不卡优势，接口优势：可随时禁用/启用/下载证书等！<hr>
    本次价格更新时间：<a class="text-dark" id="price_date">获取中</a><hr>
</div>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="DataTables" class="table table-striped table-bordered dt-responsive nowrap"></table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    layer.load(2);
    SendAjax({
        url: systemPath+'/certificate/basic/public_cert_price',
        'successCallBack': function (response) {
            if (response['status'] === true) {
                lay('#api_price').html(response['data']['api_price']);
                lay('#api_credit').html(response['data']['api_credit']);
                lay('#public_price').html(response['data']['public_price']);
                lay('#public_credit').html(response['data']['public_credit']);
                lay('#public_rate').html(response['data']['public_rate']);
                lay('#price_date').html(response['data']['date']);
                layui['sessionData'](window['location']['hostname'], { key: 'code_buy_url', value: response['data']['code_buy_url'] });
            }
        }
    });
    let DataTables = $('#DataTables');
    let usersPublicsList = DataTables.DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        buttons: DataTablesConfig.buttons,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        ajax: function(data, callback) {
            SendAjax({
                'url': systemPath+'/certificate/v1/lists_publics',
                'data': data,
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
        order: [[ 9, 'desc' ]],
        columns: [
            {
                title: '<small>设备码</small>',
                data: 'udid',
                render: function(data, type, row) {
                    return '<small>'+row['udid']+'</small>';
                }
            },
            {
                title: '<small>设备ID</small>',
                data: 'devices_id',
                render: function(data, type, row) {
                    return '<small>'+row['devices_id']+'</small>';
                }
            },
            {
                title: '<small>描述文件ID</small>',
                data: 'profiles_id',
                render: function(data, type, row) {
                    return '<small>'+row['profiles_id']+'</small>';
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
                title: '<small>设备状态</small>',
                data: 'status',
                render: function(data, type, row) {
                    let custom;
                    switch(row['status']) {
                        case 'ENABLED':
                            custom = '<div class="badge bg-success">设备可用</div>';
                            break;
                        case 'DISABLED':
                            custom = '<div class="badge bg-secondary">设备禁用</div>';
                            break;
                        case 'PROCESSING':
                            custom = '<div class="badge bg-secondary"">苹果审核</div>';
                            break;
                        case 'INELIGIBLE':
                            custom = '<div class="badge bg-danger">不合格的</div>';
                            break;
                        default:
                            custom = '<div class="badge bg-secondary">未知状态</div>';
                            break;
                    }
                    return custom;
                }
            },
            {
                title: '<small>实时状态</small>',
                data: 'status',
                render: function() {
                    return '<div class="checkcert-btn badge bg-info">点击检测</div>';
                }
            },
            {
                title: '<small>设备机型</small>',
                data: 'model',
                render: function(data, type, row) {
                    return '<small>'+row['model']+'</small>';
                }
            },
            {
                title: '<small>设备系统</small>',
                data: 'platform',
                render: function(data, type, row) {
                    return '<small>'+row['platform']+'</small>';
                }
            },
            {
                title: '<small>设备类型</small>',
                data: 'deviceClass',
                render: function(data, type, row) {
                    return '<small>'+row['deviceClass']+'</small>';
                }
            },
            {
                title: '<small>添加日期</small>',
                data: 'adddate_at',
                render: function(data, type, row) {
                    return '<small>'+row['adddate_at']+'</small>';
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
                    let custom = '<div class="remark-btn badge bg-warning">修改备注</div>&nbsp;';
                    switch(row.status) {
                        case 'ENABLED':
                            custom += '<div class="disabled-btn badge bg-danger">禁用设备</div>&nbsp;<div class="download-btn badge bg-success">下载证书</div>';
                            break;
                        case 'DISABLED':
                            custom += '<div class="enabled-btn badge bg-danger">启用设备</div>';
                            break;
                        case 'PROCESSING':
                            custom += '<div class="badge bg-secondary">设备苹果审核中</div>';
                            break;
                        case 'INELIGIBLE':
                            custom += '<div class="badge bg-danger">不合格的设备</div>';
                            break;
                        default:
                            custom += '<div class="badge bg-secondary">未知状态</div>';
                            break;
                    }
                    return custom;
                }
            },
        ]
    });
    usersPublicsList.on('click', '.checkcert-btn', function() {
        let data = usersPublicsList.row($(this)).data();
        if (data === undefined) {
            data = usersPublicsList.row($(this).closest('tr')).data();
        }
        layer.load(2);
        SendAjax({
            'url': systemPath+'/certificate/basic/query_publics',
            'data': {
                devices_id: data['devices_id'],
                udid: data['udid']
            },
            'successCallBack': function (response) {
                let DeviceStatus = '';
                switch (response['data']['status']) {
                    case 'ENABLED':
                        DeviceStatus = '<a style="color: green">设备可用</a>';
                        break;
                    case 'DISABLED':
                        DeviceStatus = '<a style="color: red">设备禁用</a>';
                        break;
                    case 'PROCESSING':
                        realTimeStatus = '<a style="color: red">苹果审核</a>';
                        break;
                    case 'INELIGIBLE':
                        realTimeStatus = '<a style="color: red">不合格的</a>';
                        break;
                }
                let realTimeStatus;
                switch (response['data']['real_time_status']) {
                    case 'good':
                        realTimeStatus = '<a style="color: green">证书有效</a>';
                        break;
                    case 'revoked':
                        realTimeStatus = '<a style="color: red">证书撤销</a>';
                        break;
                    case 'unknown':
                        realTimeStatus = '<a style="color: black">未知状态</a>';
                        break;
                    default:
                        realTimeStatus = '<a style="color: black">错误码：'+response['data']['real_time_status']+'</a>';
                }
                layer.alert(`
                    本次检测的【设备码】：${response['data']['udid']}<br>
                    证书所有者【开发者】：${response['data']['cert_subject_info']['O']}<br>
                    证书所属类型【苹果】：${response['data']['cert_type']}<br>
                    证书到期时间【精准】：${response['data']['cert_maturity_at']}<br>
                    设备当前状态【苹果】：${DeviceStatus}<br>
                    证书实时状态【结果】：${realTimeStatus}<br>
                    `);
            }
        });
    });
    usersPublicsList.on('click', '.remark-btn', function() {
        let data = usersPublicsList.row($(this)).data();
        if (data === undefined) {
            data = usersPublicsList.row($(this).closest('tr')).data();
        }
        layer.prompt({title: '修改备注', value: data['remark']}, function(text, index){
            layer.close(index);
            layer.load(2);
            SendAjax({
                'url': systemPath+'/certificate/basic/remark_devices',
                'data': {
                    devices_id: data['devices_id'],
                    udid: data['udid'],
                    remark: text
                },
                'successCallBack': function (response) {
                    usersPublicsList.draw();
                    layer.msg(response.message, { icon: 1, time: 3000 });
                }
            });
        });
    });
    usersPublicsList.on('click', '.download-btn', function() {
        let data = usersPublicsList.row($(this)).data();
        if (data === undefined) {
            data = usersPublicsList.row($(this).closest('tr')).data();
        }
        layer.load(2);
        SendAjax({
            'url': systemPath+'/certificate/basic/query_publics',
            'data': {
                devices_id: data['devices_id'],
                udid: data['udid']
            },
            'successCallBack': function (response) {
                let p12_pass = response['data']['p12_pass'];
                let p12_data = response['data']['p12_data'];
                let profile_data = response['data']['profile_data'];
                if (p12_data === '' || p12_data === null) {
                    layer.msg('P12证书数据为空，请检查证书', { icon: 2, time: 5000 });
                    return false;
                } else
                if (profile_data === '' || profile_data === null) {
                    layer.msg('描述文件未创建，请先创建描述文件', { icon: 2, time: 5000 });
                    return false;
                }
                let p12_pass_file = new File([p12_pass], '证书密码.txt', { type: 'text/plain' });
                let p12_data_binary = atob(response['data']['p12_data']);
                let p12_data_array = new Uint8Array(p12_data_binary.length);
                for (let i = 0; i < p12_data_binary.length; i++) {
                    p12_data_array[i] = p12_data_binary.charCodeAt(i);
                }
                let p12_data_blob = new Blob([p12_data_array], { type: 'application/x-pkcs12' });
                let p12_data_file = new File([p12_data_blob], data['udid']+'.p12');
                let profile_data_binary = atob(response['data']['profile_data']);
                let profile_data_array = new Uint8Array(profile_data_binary.length);
                for (let i = 0; i < profile_data_binary.length; i++) {
                    profile_data_array[i] = profile_data_binary.charCodeAt(i);
                }
                let profile_data_blob = new Blob([profile_data_array], { type: 'application/octet-stream' });
                let profile_data_file = new File([profile_data_blob], data['udid']+'.mobileprovision');
                let zip = new JSZip();
                zip.file(p12_pass_file['name'], p12_pass_file);
                zip.file(p12_data_file['name'], p12_data_file);
                zip.file(profile_data_file['name'], profile_data_file);
                zip.generateAsync({ type: 'blob' })
                    .then(function (content) {
                        saveAs(content, '苹果开发者证书_'+data['udid']+'.zip');
                    });
            }
        });
    });
    usersPublicsList.on('click', '.disabled-btn', function() {
        let data = usersPublicsList.row($(this)).data();
        if (data === undefined) {
            data = usersPublicsList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要禁用“'+data['udid']+'”设备吗？', {
            btn: ['确定禁用', '取消操作']
        }, function(){
            layer.load(2);
            SendAjax({
                'url': systemPath+'/certificate/basic/delete_publics',
                'data': {
                    devices_id: data['devices_id'],
                    udid: data['udid']
                },
                'successCallBack': function (response) {
                    usersPublicsList.draw();
                    layer.msg(response['message'], { icon: 1, time: 3000 });
                }
            });
        });
    });
    usersPublicsList.on('click', '.enabled-btn', function() {
        let data = usersPublicsList.row($(this)).data();
        if (data === undefined) {
            data = usersPublicsList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要启用“'+data.udid+'”设备吗？', {
            btn: ['确定启用', '取消操作']
        }, function(){
            layer.load(2);
            SendAjax({
                'url': systemPath+'/certificate/basic/recovery_publics',
                'data': {
                    devices_id: data['devices_id'],
                    udid: data['udid']
                },
                'successCallBack': function (response) {
                    usersPublicsList.draw();
                    layer.msg(response['message'], { icon: 1, time: 3000 });
                }
            });
        });
    });
</script>
@endsection