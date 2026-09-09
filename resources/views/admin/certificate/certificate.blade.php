@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('证书列表') }}@endsection
@section('link')
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
@endsection

@section('content')
<div class="alert alert-primary alert-dismissible">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    <strong>证书审核状态自动更新声明！</strong><hr>
    系统会自动在第一个“设备类型”的第 11 台设备起开始检测证书卡设备状态并且更新！（无需手动设置）<hr>
    详情可参考苹果官方文档：<a href="https://developer.apple.com/cn/help/account/reference/device-registration-updates" class="alert-link" target="_blank">【参考.设备注册更新.临时处理与不符合资格的测试设备状态】</a><hr>
    苹果官方文档已声明：已注册的测试设备计数 (每个平台)，第 1 至 10 不卡设备，第 11 至 100 有几率会卡 24 至 72 小时内。<hr>
    苹果官方文档已声明：如果预置设备标识符之前与因违反 《Apple Developer Program 许可协议》而被终止的会员资格相关联，则设备可能会被移到不符合资格状态，最长可达 30 天。
</div>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="DataTables" class="table table-bordered dt-responsive nowrap"></table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
    <script>
        let DataTables = $('#DataTables');
        let usersCertificateList = DataTables.DataTable({
            dom: DataTablesConfig.dom,
            language: DataTablesConfig.language,
            buttons: DataTablesConfig.buttons,
            processing: DataTablesConfig.processing,
            serverSide: DataTablesConfig.serverSide,
            pagingType: DataTablesConfig.pagingType,
            ajax: function(data, callback) {
                SendAjax({
                    'url': systemPath+'/certificate/v1/lists_certificate',
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
            order: [[ 14, 'desc' ]],
            columns: [
                {
                    title: '<small>Apple ID</small>',
                    data: 'apple_id',
                    render: function(data, type, row) {
                        return '<small>'+row['apple_id']+'</small>';
                    }
                },
                {
                    title: '<small>备注信息</div>',
                    data: 'remark',
                    render: function(data, type, row) {
                        return '<div class="badge bg-secondary">'+row['remark']+'</div>';
                    }
                },
                {
                    title: '<small>实时状态</small>',
                    data: 'status',
                    render: function(data, type, row) {
                        let custom;
                        switch(row['status']) {
                            case 'good':
                                custom = '<div class="badge bg-success">证书有效</div>';
                                break;
                            case 'revoked':
                                custom = '<div class="badge bg-danger">证书吊销</div>';
                                break;
                            case 'unknown':
                                custom = '<div class="badge bg-secondary">未知状态</div>';
                                break;
                            case 'delete':
                                custom = '<div class="badge bg-danger">需要重构</div>';
                                break;
                            default:
                                custom = '<div class="badge bg-secondary">'+row['status']+'</div>';
                                break;
                        }
                        return custom;
                    }
                },
                {
                    title: '<small>证书开关</small>',
                    data: 'switch',
                    render: function(data, type, row) {
                        if (row['switch'] === 'ENABLED') {
                            return '<div class="badge bg-success">证书启用</div>';
                        } else {
                            return '<div class="badge bg-warning">证书停用</div>';
                        }
                    }
                },
                {
                    title: '<small>秒出状态</small>',
                    data: 'processing',
                    render: function(data, type, row) {
                        let custom;
                        switch(row['processing']) {
                            case 'good':
                                custom = '<div class="badge bg-success">不卡设备</div>';
                                break;
                            case 'processing':
                                custom = '<div class="badge bg-danger">已卡设备</div>';
                                break;
                            case 'unknown':
                                custom = '<div class="badge bg-warning">等待检测</div>';
                                break;
                            default:
                                custom = '<div class="badge bg-secondary">未知状态</div>';
                                break;
                        }
                        return custom;
                    }
                },
                {
                    title: '<small>参与销售</small>',
                    data: 'sales',
                    render: function(data, type, row) {
                        switch (row['sales']) {
                            case 'ENABLED':
                                return '<div class="badge bg-success">正在参与</div>';
                            case 'DISABLED':
                                return '<div class="badge bg-danger">暂不支持</div>';
                            case 'SUPPORT':
                                return '<div class="sales-btn badge bg-info">点击申请</div>';
                            case 'PROCESS':
                                return '<div class="badge bg-warning">正在审核</div>';
                            case 'NOT_AUTH':
                                return '<div class="get-auth-btn badge bg-warning">获取权限</div>';
                        }
                    }
                },
                {
                    title: '<small>iPhone 权限｜额度</small>',
                    data: 'IPHONE',
                    render: function(data, type, row) {
                        let custom = '';
                        custom += (row['iphone_camouflage'] === '伪装权限未开通') ? '<div class="badge bg-danger">'+row['iphone_camouflage']+'</div>' : '<div class="badge bg-success">'+row['iphone_camouflage']+'</div>';
                        custom += (row['IPHONE'] > 1) ? '&nbsp;<div class="badge bg-info">剩余：'+row['IPHONE']+'</div>' : '&nbsp;<div class="badge bg-danger">剩余：'+row['IPHONE']+'</div>';
                        return custom;
                    }
                },
                {
                    title: '<small>Mac 权限｜额度</small>',
                    data: 'MAC',
                    render: function(data, type, row) {
                        let custom = '';
                        custom += (row['mac_camouflage'] === '伪装权限未开通') ? '<div class="badge bg-danger">'+row['mac_camouflage']+'</div>' : '<div class="badge bg-success">'+row['mac_camouflage']+'</div>';
                        custom += (row['MAC'] > 1) ? '&nbsp;<div class="badge bg-info">剩余：'+row['MAC']+'</div>' : '&nbsp;<div class="badge bg-danger">剩余：'+row['MAC']+'</div>';
                        return custom;
                    }
                },
                {
                    title: '<small>iPad 权限｜额度</small>',
                    data: 'IPAD',
                    render: function(data, type, row) {
                        let custom = '';
                        custom += (row['ipad_camouflage'] === '伪装权限未开通') ? '<div class="badge bg-danger">'+row['ipad_camouflage']+'</div>' : '<div class="badge bg-success">'+row['ipad_camouflage']+'</div>';
                        custom += (row['IPAD'] > 1) ? '&nbsp;<div class="badge bg-info">剩余：'+row['IPAD']+'</div>' : '&nbsp;<div class="badge bg-danger">剩余：'+row['IPAD']+'</div>';
                        return custom;
                    }
                },
                {
                    title: '<small>Vision 权限｜额度</small>',
                    data: 'APPLE_VISION_PRO',
                    render: function(data, type, row) {
                        let custom = '';
                        custom += (row['vision_pro_camouflage'] === '伪装权限未开通') ? '<div class="badge bg-danger">'+row['vision_pro_camouflage']+'</div>' : '<div class="badge bg-success">'+row['vision_pro_camouflage']+'</div>';
                        custom += (row['APPLE_VISION_PRO'] > 1) ? '&nbsp;<div class="badge bg-info">剩余：'+row['APPLE_VISION_PRO']+'</div>' : '&nbsp;<div class="badge bg-danger">剩余：'+row['APPLE_VISION_PRO']+'</div>';
                        return custom;
                    }
                },
                {
                    title: '<small>Watch 权限｜额度</small>',
                    data: 'APPLE_WATCH',
                    render: function(data, type, row) {
                        let custom = '';
                        custom += (row['watch_camouflage'] === '伪装权限未开通') ? '<div class="badge bg-danger">'+row['watch_camouflage']+'</div>' : '<div class="badge bg-success">'+row['watch_camouflage']+'</div>';
                        custom += (row['APPLE_WATCH'] > 1) ? '&nbsp;<div class="badge bg-info">剩余：'+row['APPLE_WATCH']+'</div>' : '&nbsp;<div class="badge bg-danger">剩余：'+row['APPLE_WATCH']+'</div>';
                        return custom;
                    }
                },
                {
                    title: '<small>iPod 权限｜额度</small>',
                    data: 'IPOD',
                    render: function(data, type, row) {
                        let custom = '';
                        custom += (row['ipod_camouflage'] === '伪装权限未开通') ? '<div class="badge bg-danger">'+row['ipod_camouflage']+'</div>' : '<div class="badge bg-success">'+row['ipod_camouflage']+'</div>';
                        custom += (row['IPOD'] > 1) ? '&nbsp;<div class="badge bg-info">剩余：'+row['IPOD']+'</div>' : '&nbsp;<div class="badge bg-danger">剩余：'+row['IPOD']+'</div>';
                        return custom;
                    }
                },
                {
                    title: '<small>TV 权限｜额度</small>',
                    data: 'APPLE_TV',
                    render: function(data, type, row) {
                        let custom = '';
                        custom += (row['tv_camouflage'] === '伪装权限未开通') ? '<div class="badge bg-danger">'+row['tv_camouflage']+'</div>' : '<div class="badge bg-success">'+row['tv_camouflage']+'</div>';
                        custom += (row['APPLE_TV'] > 1) ? '&nbsp;<div class="badge bg-info">剩余：'+row['APPLE_TV']+'</div>' : '&nbsp;<div class="badge bg-danger">剩余：'+row['APPLE_TV']+'</div>';
                        return custom;
                    }
                },
                {
                    title: '<small>Issuer ID</small>',
                    data: 'iss',
                    render: function(data, type, row) {
                        return '<div class="badge bg-warning">'+row['iss']+'</div>';
                    }
                },
                {
                    title: '<small>添加日期</small>',
                    data: 'created_at',
                    render: function(data, type, row) {
                        return '<div class="badge bg-info">'+row['created_at']+'</div>';
                    }
                },
                {
                    title: '<small>更新日期</small>',
                    data: 'updated_at',
                    render: function(data, type, row) {
                        return '<div class="badge bg-success">'+row['updated_at']+'</div>';
                    }
                },
                {
                    title: '<small>功能操作</small>',
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        let custom = `
                        <div class="remark-btn badge bg-warning">修改备注</div>&nbsp;
                        <div class="sync-btn badge bg-info">同步证书</div>&nbsp;`;
                        if (row['sales'] !== 'ENABLED' && row['sales'] !== 'PROCESS') {
                            custom += `
                            <div class="reestablish-profiles-btn badge bg-dark">重建描述</div>&nbsp;
                            <div class="recovery-btn badge bg-primary">重构证书</div>&nbsp;
                            <div class="reset-password-btn badge bg-success">重设密码</div>&nbsp;
                            <div class="delete-btn badge bg-danger">删除证书</div>&nbsp;
                            <div class="permissions-btn badge bg-dark">修改权限</div>&nbsp;
                            <div class="cert_secondary_reset-btn badge bg-primary">二次超开</div>&nbsp;
                            `;
                            if (row['switch'] === 'ENABLED') {
                                custom += '<div class="disabled-btn badge bg-warning">停用证书</div>';
                            } else {
                                custom += '<div class="enabled-btn badge bg-info">启用证书</div>';
                            }
                        }
                        return custom;
                    }
                },
                {
                    title: '<small>进阶功能</small>',
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        let autoSelfCheckBadge = (row.auto_status === 'ENABLED')
                            ? '<div class="auto-self-check-btn badge bg-info">自动维护已经启用</div>&nbsp;'
                            : '<div class="auto-self-check-btn badge bg-secondary">自动维护尚未启用</div>&nbsp;';
                        return `
                        ${autoSelfCheckBadge}
                        <div class="auto-agreements-btn badge bg-success">自动签署新的协议</div>&nbsp;
                        <div class="del-keys-btn badge bg-warning">清理非本站的密钥</div>&nbsp;
                        <div class="del-profiles-btn badge bg-info">清理过期描述文件</div>&nbsp;
                        <div class="information-btn badge bg-primary">账户当前实时信息</div>&nbsp;
                        <div class="add-devices-btn badge bg-dark">批量提交添加设备</div>&nbsp;
                        <div class="del-devices-btn badge bg-danger">清空重置设备列表</div>&nbsp;
                        <div class="recommended-permissions-btn badge bg-success">配置官方推荐权限</div>&nbsp;
                        `;
                    }
                },
            ]
        });
        usersCertificateList.on('click', '.get-auth-btn', function() {
            RedirectTo('/participate_in_sales.html');
        });
        usersCertificateList.on('click', '.sales-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            layer.confirm(`
            当前证书Apple ID：${data['apple_id']}<br>
            当前证书Issuer ID：${data['iss']}<br><br>
            此证书已经符合参与公共池销售的“不卡设备”资质，您是否要继续提交申请，为本证书获取参与公共池销售权限？<br>
            参与公池销售、并不会影响您此本证书的添加新设备、查询等必要操作、本平台只是帮助您消耗您可能消耗不完的证书额度，避免过度浪费！<br><br>
            参与公共池销售须知条款：<br>
            一、申请参与公共池证书系统会自动进行首轮资质验证、验证失败会驳回申请，具体原因可在“证书公池->参与销售”里查看具体原因。<br>
            二、如您的证书经过系统首轮资质验证、工作人员一般会在当天进行审核，通过后您的证书将参与公共池销售，所获得利润可在“证书公池->销售记录”里查看。<br>
            三、一旦您提交申请，在审核结束前与成功参与后、您的证书会进入“禁止操作状态”！禁止操作状态下将不能进行：“重构证书、重设密码、删除证书、禁用证书、启用证书”等操作。但不影响您的证书添加新设备等必要操作！<br>
            四、更多条款请看“证书公池->参与销售”的参与销售须知<br>
            `, {
                btn: ['我已阅读条款，并提交申请', '取消操作']
            }, function(index){
                layer.close(index);
                layer.load(2);
                SendAjax({
                    'url': systemPath+'/certificate/v1/apply_participation_sales',
                    'data': {
                        iss: data['iss']
                    },
                    'successCallBack': function (response) {
                        layer.msg(response.message, { icon: 1, time: 3000 });
                    }
                });
            });
        });
        usersCertificateList.on('click', '.permissions-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            layer.load(2);
            SendAjax({
                'url': systemPath + '/certificate/basic/certificate_permissions',
                'successCallBack': function (response) {
                    let tips;
                    if (data['type'] === 'DISTRIBUTION' || data['type'] === 'IOS_DISTRIBUTION') {
                        tips = `当前证书类型为：${data['type']}，支持开关证书权限！【不会影响您的证书有效性】`;
                    } else {
                        tips = `<code>当前证书类型为：${data['type']}，不支持修改权限。如果您仍需要修改权限，请先阅读以下条款！<br>
                        一、由于此证书类型不符合条件！一旦提交请求，系统将自动为您强制重构证书为DISTRIBUTION类型！<br>
                        二、强制重构证书如果已有对应证书、系统将执行删除操作，这可能导致您之前的用户需要重新下载重构后的证书！
                        </code>`;
                    }
                    let allPermissions = [];
                    // 动态生成权限选项
                    let permissionOptions = '<option value="">请选择或搜索要操作的权限</option>';
                    if (response['data'] && typeof response['data'] === 'object') {
                        for (let key in response.data) {
                            if (response['data'].hasOwnProperty(key)) {
                                permissionOptions += `<option value="${key}">${response['data'][key]}</option>`;
                                allPermissions.push({
                                    key: key,
                                    displayText: response['data'][key]
                                });
                            }
                        }
                    }
                    // 创建状态监控弹窗
                    let stateLayerIndex = null;
                    function openStateMonitor() {
                        stateLayerIndex = layer.open({
                            type: 1,
                            title: '权限操作实时状态',
                            area: ['80%', '80%'],
                            content: `
                            <style>
                            .status-log {
                                height: 100%;
                                border: 1px solid #e6e6e6;
                                padding: 10px;
                                overflow-y: auto;
                                background-color: #f8f8f8;
                                white-space: pre-wrap;
                                font-family: monospace;
                            }
                            .status-success {
                                color: #009688;
                            }
                            .status-error {
                                color: #FF5722;
                            }
                            .status-info {
                                color: #333;
                            }
                            .status-progress {
                                color: #1E9FFF;
                            }
                            .status-summary {
                                color: #333;
                                font-weight: bold;
                                margin-top: 10px;
                                border-top: 1px solid #eee;
                                padding-top: 10px;
                            }
                            </style>
                            <div class="v-team-box v-team-body layui-form">
                                <div id="permissions-real-time-state" class="status-log"></div>
                            </div>
                            `
                        });
                    }
                    // 添加状态消息
                    function appendStatusMessage(message, type = 'info') {
                        let logContainer = $('#permissions-real-time-state');
                        let messageElement = $('<div>').addClass('status-' + type).text(message);
                        logContainer.append(messageElement);
                        logContainer.scrollTop(logContainer[0].scrollHeight);
                    }
                    // 执行批量权限操作
                    let isOperationCancelled = false;
                    function executeBatchOperation(permissions, action) {
                        layer.closeLast('dialog');
                        let successCount = 0;
                        let failCount = 0;
                        let failedPermissions = [];
                        openStateMonitor();
                        appendStatusMessage(`开始${action === 'ENABLED' ? '开启' : '禁用'}所有权限...`, 'info');
                        appendStatusMessage(`共 ${permissions['length']} 个权限需要处理`, 'info');
                        let currentIndex = 0;
                        let processNextPermission = function() {
                            if (currentIndex >= permissions['length'] || isOperationCancelled) {
                                // 显示最终统计结果
                                appendStatusMessage(`\n操作完成统计结果:`, 'summary');
                                appendStatusMessage(`成功: ${successCount} 个`, 'success');
                                appendStatusMessage(`失败: ${failCount} 个`, failCount > 0 ? 'error' : 'info');
                                if (failCount > 0) {
                                    appendStatusMessage(`\n失败列表【请手动开启以下权限】:`, 'summary');
                                    failedPermissions.forEach(function(item) {
                                        appendStatusMessage(`- ${item['displayText']}: ${item['error']}`, 'error');
                                    });
                                }
                                return;
                            }
                            let permission = permissions[currentIndex];
                            appendStatusMessage(`正在${action === 'ENABLED' ? '开启' : '禁用'}权限: ${permission['displayText']} (${currentIndex+1}/${permissions['length']})`, 'progress');
                            SendAjax({
                                'url': systemPath + '/certificate/v1/reset_certificate_permissions',
                                'data': {
                                    iss: data['iss'],
                                    permission: permission['key'],
                                    switch: action
                                },
                                'successCallBack': function (response) {
                                    successCount++;
                                    appendStatusMessage(`${response['message']}`, 'success');
                                    currentIndex++;
                                    processNextPermission();
                                },
                                'errorCallBack': function (error) {
                                    failCount++;
                                    let errorMsg = error['responseJSON']['message'] || '未知错误';
                                    failedPermissions.push({
                                        displayText: permission['displayText'],
                                        error: errorMsg
                                    });
                                    appendStatusMessage(`权限: ${permission['displayText']} 操作失败: ${errorMsg}`, 'error');
                                    currentIndex++;
                                    processNextPermission();
                                }
                            });
                        };
                        processNextPermission();
                    }
                    layer.open({
                        type: 1,
                        anim: 'slideDown',
                        area: ['90%', '70%'],
                        title: '开发者证书权限设置',
                        content: `
                        <div style="padding: 16px;">
                        <small>
                        当前证书Apple ID：${data['apple_id']}<br>
                        当前证书Issuer ID：${data['iss']}<br><br>
                        ${tips}<br><br>
                        修改证书权限后须知条款：<br>
                        一、修改证书权限后并不会使所以用户立即生效，而需要您手动为每个在此之前添加的设备进行重新"创建描述文件"后下载才能生效！<br>
                        二、条款一仅限之前添加的设备、之后通过平台【包含API接口】添加的设备会根据您本次修改的权限赋予对应权限！
                        </small>
                        <hr>
                        <form class="layui-form">
                        <select name="permission" lay-search="" lay-verify="required">
                        ${permissionOptions}
                        </select>
                        <hr>
                        <input type="radio" name="switch" value="ENABLED" title="开启权限" checked>
                        <input type="radio" name="switch" value="DISABLED" title="禁用权限">
                        <hr>
                        <button class="layui-btn layui-btn-fluid" lay-submit lay-filter="permissions">提交</button>
                        </form>
                        <br>
                        <hr>
                        <br>
                        <button id="enabled-all-permissions" class="layui-btn layui-btn-fluid layui-bg-blue">一键开启所有权限</button>
                        <br>
                        <hr>
                        <br>
                        <button id="disabled-all-permissions" class="layui-btn layui-btn-fluid layui-bg-red">一键关闭所有权限</button>
                        </div>
                        `,
                        success: function(){
                            layui['form'].render();
                            // 单个权限提交
                            layui['form'].on('submit(permissions)', function(formData){
                                layer.load(2);
                                SendAjax({
                                    'url': systemPath + '/certificate/v1/reset_certificate_permissions',
                                    'data': {
                                        iss: data['iss'],
                                        permission: formData['field']['permission'],
                                        switch: formData['field']['switch']
                                    },
                                    'successCallBack': function (response) {
                                        layer.msg(response['message'], { icon: 1, time: 3000 });
                                    }
                                });
                                return false;
                            });
                            // 一键开启所有权限
                            $('#enabled-all-permissions').on('click', function() {
                                layer.confirm('确定要一键开启所有权限吗？此操作可能需要较长时间，请勿关闭页面或刷新浏览器！', {
                                    btn: ['确定','取消'],
                                    title: '确认操作'
                                }, function(){
                                    executeBatchOperation(allPermissions, 'ENABLED');
                                });
                            });
                            // 一键关闭所有权限
                            $('#disabled-all-permissions').on('click', function() {
                                layer.confirm('确定要一键关闭所有权限吗？此操作可能需要较长时间，请勿关闭页面或刷新浏览器！', {
                                    btn: ['确定','取消'],
                                    title: '确认操作'
                                }, function(){
                                    executeBatchOperation(allPermissions, 'DISABLED');
                                });
                            });
                        }
                    });
                }
            });
        });
        usersCertificateList.on('click', '.reset-password-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            layer.confirm(`
            默认P12证书密码为：1，您确定要重新设置自定义证书P12密码吗？<br><br>
            重置密码不会影响之前下载的证书，只会影响重设之后的下载、查询、创建等！【仅限此本证书】`, {
                btn: ['我已阅读以上内容', '取消操作']
            }, function(index){
                layer.close(index);
                layer.prompt({title: '请输入新的证书P12密码'}, function(value, index, elem){
                    if(value === '') return elem.focus();
                    layer.close(index);
                    layer.load(2);
                    SendAjax({
                        'url': systemPath+'/certificate/v1/reset_certificate_password',
                        'data': {
                            iss: data['iss'],
                            password: value
                        },
                        'successCallBack': function (response) {
                            layer.msg(response['message'], { icon: 1, time: 3000 });
                        }
                    });
                });
            });
        });
        usersCertificateList.on('click', '.sync-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            layer.load(2);
            SendAjax({
                'url': systemPath+'/certificate/basic/sync_cert_udid',
                'data': {
                    iss: data['iss']
                },
                'successCallBack': function (response) {
                    layer.msg(response.message, { icon: 1, time: 3000 });
                }
            });
        });
        usersCertificateList.on('click', '.reestablish-profiles-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            layer.confirm(`
            您确定要重建此证书的所有描述文件吗？<br>
            本任务需在线等待，如果过程强制中断，请直接刷新页面
            `, {
                btn: ['开始重建', '取消操作']
            }, function(){
                layer.msg('正在加载证书可重建设备列表', {
                    icon: 16,
                    shade: 0.01,
                    time: false
                });
                SendAjax({
                    'url': systemPath+'/certificate/v1/lists_devices',
                    'data': {
                        start: 0,
                        search: {
                            value: data['iss'],
                            regex: false
                        },
                        order: [
                            {
                                column: 10,
                                dir: 'desc'
                            }
                        ],
                        length: 1400,
                        draw: 1,
                        columns: [
                            { data: 'udid', searchable: 'true', orderable: 'true' },
                            { data: 'status', searchable: 'true', orderable: 'true' },
                            { data: 'status', searchable: 'true', orderable: 'true' },
                            { data: 'devices_id', searchable: 'true', orderable: 'true' },
                            { data: 'profiles_id', searchable: 'true', orderable: 'true' },
                            { data: 'iss', searchable: 'true', orderable: 'true' },
                            { data: 'model', searchable: 'true', orderable: 'true' },
                            { data: 'platform', searchable: 'true', orderable: 'true' },
                            { data: 'deviceClass', searchable: 'true', orderable: 'true' },
                            { data: 'remark', searchable: 'true', orderable: 'true' },
                            { data: 'adddate_at', searchable: 'true', orderable: 'true' },
                            { data: 'updated_at', searchable: 'true', orderable: 'true' }
                        ]
                    },
                    'successCallBack': function (response) {
                        const lists_devices_data = response['data']['data'];
                        let PROCESSINGCount = 0;
                        let INELIGIBLECount = 0;
                        let NormalCount = 0;
                        let issOne = '';
                        let devices_idOne = '';
                        lists_devices_data.forEach(item => {
                            if (['PROCESSING'].includes(item['status'])) {
                                PROCESSINGCount++;
                            }
                            if (['INELIGIBLE'].includes(item['status'])) {
                                INELIGIBLECount++;
                            }
                            if (!['DISABLED', 'PROCESSING', 'INELIGIBLE'].includes(item['status'])) {
                                NormalCount++;
                                issOne = item['iss'];
                                devices_idOne = item['devices_id'];
                            }
                        });
                        if (NormalCount > 0) {
                            layer.msg(`
                            卡设备设备有 ${PROCESSINGCount} 台<br>
                            不合格设备有 ${INELIGIBLECount} 台<br>
                            可重建设备有 ${NormalCount} 台<br>
                            三秒后将自动重建
                            `, {
                                shade: 0.01,
                                time: 3000
                            }, function() {
                                layer.msg('正在重建描述文件中', {
                                    icon: 16,
                                    shade: 0.01,
                                    time: false
                                });
                                SendAjax({
                                    'url': systemPath+'/certificate/basic/create_profiles',
                                    'data': {
                                        iss: issOne,
                                        devices_id: devices_idOne
                                    },
                                    'successCallBack': function () {
                                        layer.msg(`重建设备ID：${devices_idOne} 重建成功`, {
                                            shade: 0.01,
                                            time: 3000
                                        });
                                        lists_devices_data.forEach(item => {
                                            if (devices_idOne === item['devices_id']) return;
                                            if (!['DISABLED', 'PROCESSING', 'INELIGIBLE'].includes(item['status'])) {
                                                SendAjax({
                                                    'url': systemPath+'/certificate/basic/create_profiles',
                                                    'data': {
                                                        iss: item['iss'],
                                                        devices_id: item['devices_id']
                                                    },
                                                    'successCallBack': function () {
                                                        layer.msg(`重建设备ID：${item['devices_id']} 重建成功`, {
                                                            shade: 0.01,
                                                            time: 3000
                                                        });
                                                    },
                                                    'errorCallBack': function(error) {
                                                        layer.msg(`重建设备ID：${error['devices_id']} 重建失败`, {
                                                            shade: 0.01,
                                                            time: 3000
                                                        });
                                                    }
                                                });
                                            }
                                        });
                                    },
                                    'errorCallBack': function(error) {
                                        layer.msg(error['responseJSON']['message'], {icon: 5, time: 3000});
                                    }
                                });
                            });
                        } else {
                            layer.msg(`
                            卡设备设备有 ${PROCESSINGCount} 台<br>
                            不合格设备有 ${INELIGIBLECount} 台<br>
                            可重建设备有 ${NormalCount} 台<br>
                            此证书不需要重建
                            `, {
                                shade: 0.01,
                                time: 3000
                            });
                        }
                    }
                });
            });
        });
        usersCertificateList.on('click', '.disabled-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            layer.confirm('您确定要停用“'+data['apple_id']+'”证书吗？', {
                btn: ['确定停用', '取消操作']
            }, function(){
                layer.load(2);
                SendAjax({
                    'url': systemPath+'/certificate/basic/certificate_switch',
                    'data': {
                        iss: data['iss'],
                        switch: 'DISABLED'
                    },
                    'successCallBack': function (response) {
                        usersCertificateList.draw();
                        layer.msg(response['message'], { icon: 1, time: 3000 });
                    }
                });
            });
        });
        usersCertificateList.on('click', '.enabled-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            layer.confirm('您确定要启用“'+data['apple_id']+'”证书吗？', {
                btn: ['确定启用', '取消操作']
            }, function(){
                layer.load(2);
                SendAjax({
                    'url': systemPath+'/certificate/basic/certificate_switch',
                    'data': {
                        iss: data['iss'],
                        switch: 'ENABLED'
                    },
                    'successCallBack': function (response) {
                        usersCertificateList.draw();
                        layer.msg(response['message'], { icon: 1, time: 3000 });
                    }
                });
            });
        });
        usersCertificateList.on('click', '.delete-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            layer.confirm('您确定要删除“'+data['apple_id']+'”开发者证书吗？', {
                btn: ['确定删除', '取消操作']
            }, function(){
                layer.load(2);
                SendAjax({
                    'url': systemPath+'/certificate/basic/delete_certificate',
                    'data': {
                        iss: data['iss']
                    },
                    'successCallBack': function (response) {
                        usersCertificateList.row($(this)).remove().draw();
                        layer.msg(response['message'], { icon: 1, time: 3000 });
                    }
                });
            });
        });
        usersCertificateList.on('click', '.cert_secondary_reset-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            layer.confirm('此功能为二次超开证书<br><br>二次超开理论上说明：<br>普通会员一本开发者证书最高支持开通：200*2=400 台设备<br>高级会员一本开发者证书最高支持开通：700*2=1400 台设备<br><br>如果您不懂得本操作的前提条件请您取消本次操作。', {
                btn: ['确定操作', '取消操作']
            }, function(){
                layer.load(2);
                SendAjax({
                    'url': systemPath+'/certificate/v1/cert_secondary_reset',
                    'data': {
                        iss: data.iss
                    },
                    'successCallBack': function (response) {
                        usersCertificateList.draw();
                        layer.msg(response['message'], { icon: 1, time: 3000 });
                    }
                });
            });
        });
        usersCertificateList.on('click', '.remark-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            layer.prompt({title: '修改备注', value: data['remark']}, function(text, index){
                layer.close(index);
                layer.load(2);
                SendAjax({
                    'url': systemPath+'/certificate/basic/remark_certificate',
                    'data': {
                        iss: data['iss'],
                        remark: text
                    },
                    'successCallBack': function (response) {
                        usersCertificateList.draw();
                        layer.msg(response['message'], { icon: 1, time: 3000 });
                    }
                });
            });
        });
        usersCertificateList.on('click', '.recovery-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            layer.open({
                type: 1,
                anim: 'slideDown',
                title: '开发者证书重构',
                content: `
                <div style="padding: 16px;">
                <small>
                当前证书Apple ID：${data['apple_id']}<br>
                当前证书Issuer ID：${data['iss']}<br>
                当前证书的备注信息：${data['remark']}<br>
                当前证书二次超开情况：${ (data['secondaryUse'] === 'ENABLED') ? '<b class="layui-font-red">已二次超开</b>' : '<b>未二次超开</b>' }<br>
                <br>
                <b class="layui-font-red">请根据您自己的需求进行自定义搭配操作，操作前请先阅读各选项说明，以免造成不必要的损失。</b>
                <hr>
                </small>
                <form class="layui-form layui-form-pane" action="">
                    <br>
                    <small>
                        选择删除后如果苹果官方存在此类型或原有证书类型将<b class="layui-font-red">自动删除原有证书</b>
                    </small>
                    <br><br>
                    <div class="layui-form-item" pane>
                        <label class="layui-form-label">原有的证书</label>
                        <div class="layui-input-block">
                            <input type="radio" name="renew" value="off" title="保留原有开发者证书" checked>
                            <input type="radio" name="renew" value="on" title="删除原有开发者证书">
                        </div>
                    </div>
                    <br>
                    <small>
                        选择清空后将清空本证书所含所有设备<b class="layui-font-red">【仅限清空本站记录 已二次超开慎用】</b>
                    </small>
                    <br><br>
                    <div class="layui-form-item" pane>
                        <label class="layui-form-label">设备的记录</label>
                        <div class="layui-input-block">
                            <input type="radio" name="deleteAll" value="off" title="保留本站 UDID 设备记录" checked>
                            <input type="radio" name="deleteAll" value="on" title="清空本站 UDID 设备记录">
                        </div>
                    </div>
                    <br>
                    <small>
                        如果您《接口配置->基础配置->开发者账户续费提前通知开关》已经开启。<br>
                        并且您的开发者账户为刚续订的账户，可以选择勾选 “刚续订的开发者账户”。<br>
                        勾选后系统会在此账户下次续订前的 7/15/30 天内发送邮件通知您续费账户。<br>
                        如果<b class="layui-font-red">不是刚续订的账户请保持勾选“不是刚续订的开发者账户”</b>避免系统误判通知。
                    </small>
                    <br><br>
                    <div class="layui-form-item" pane>
                        <label class="layui-form-label">是否为续费</label>
                        <div class="layui-input-block">
                            <input type="radio" name="dev_renew_reset" value="off" title="不是刚续订的开发者账户" checked>
                            <input type="radio" name="dev_renew_reset" value="on" title="刚续订的开发者账户">
                        </div>
                    </div>
                    <br>
                    <small>
                        不勾选默认为使用原有证书的证书类型，如有勾选项则为新增勾选项证书类型<b class="layui-font-red">【当前证书类型：${data.type}】</b><br>

                    </small>
                    <br><br>
                    <div class="layui-form-item" pane>
                        <label class="layui-form-label">证书的类型</label>
                        <div class="layui-input-block">
                            <input type="radio" name="type" value="NO_OPERATION" title="不新增证书">
                            <input type="radio" name="type" value="DISTRIBUTION" title="DISTRIBUTION">
                            <input type="radio" name="type" value="DEVELOPMENT" title="DEVELOPMENT">
                            <input type="radio" name="type" value="IOS_DEVELOPMENT" title="IOS_DEVELOPMENT">
                            <input type="radio" name="type" value="IOS_DISTRIBUTION" title="IOS_DISTRIBUTION">
                        </div>
                    </div>
                    <br><br>
                    <div class="layui-form-item">
                        <button class="layui-btn" lay-submit lay-filter="recovery_certificate">提交操作</button>
                        <button type="reset" class="layui-btn layui-btn-primary">重置输入</button>
                    </div>
                    <br><br><br><br>
                </form>
                </div>
                `,
                success: function(){
                    layui.form.render();
                    layui.form.on('submit(recovery_certificate)', function(formData){
                        layer.confirm(`
                        ${(formData['field']['deleteAll'] === 'off' && formData['field']['renew'] === 'off' && formData['field']['dev_renew_reset'] === 'on' && formData['field']['type'] !== 'NO_OPERATION' && formData['field']['type'] !== null && formData['field']['type'] !== undefined && formData['field']['type'] !== '') ? `
                        本次表单提交后将执行：<br>
                        为您保留原有证书以及所有设备相关数据【虚构数据】<br>
                        并且在原有证书真实到期后<b class="layui-font-red">自动删除【虚构数据】</b>（详情规则留意操作后的提示）<br>
                        您是否确定没有问题。
                        ` : `
                        本次表单提交后将执行：<br>
                        ${(formData['field']['renew'] === 'on') ? `${(formData['field']['type'] === 'NO_OPERATION') ? '保留原有开发者证书' : '<b class="layui-font-red">删除原有开发者证书</b>'}` : '保留原有开发者证书'}<br>
                        ${(formData['field']['deleteAll'] === 'on') ? '<b class="layui-font-red">清空本站 UDID 设备记录</b>' : '保留本站 UDID 设备记录'}<br>
                        ${(formData['field']['dev_renew_reset'] === 'on') ? '<b class="layui-font-red">重置开发者账户的续订时间</b>' : '使用原有开发者账户的续订时间'}<br>
                        ${(formData['field']['type'] === null || formData['field']['type'] === undefined || formData['field']['type'] === '') ? '使用原有开发者证书的证书类型' : `${(formData['field']['type'] === 'NO_OPERATION') ? '不新增证书' : `<b class="layui-font-red">新增新的证书类型：${formData['field']['type']}</b>`}`}
                        <br><br>
                        您是否确定没有问题。
                        `}
                        `, {
                            btn: ['确定', '取消']
                        }, function(){
                            layer.load(2);
                            SendAjax({
                                'url': systemPath+'/certificate/basic/recovery_certificate',
                                'data': {
                                    iss: data['iss'],
                                    type: formData['field']['type'],
                                    renew: formData['field']['renew'],
                                    deleteAll: formData['field']['deleteAll'],
                                    dev_renew_reset: formData['field']['dev_renew_reset'],
                                },
                                'successCallBack': function (response) {
                                    usersCertificateList.draw();
                                    layer.msg(response.message, { icon: 1, time: 3000 });
                                }
                            });
                        }, function(){
                            layer.msg('取消操作');
                        });
                        return false;
                    });
                }
            });
        });

        var AppleLoginPollInterval = 1500;
        var AppleLoginVerificationInitialPrompt = '请输入 6 位验证码 或选择接收短信验证码进行验证';

        function appleLoginApiData(response) {
            return (response && response.data) ? response.data : (response || {});
        }

        function appleLoginInputTypeLabel(type) {
            var map = {
                password: 'Apple ID 密码',
                verification_initial: '6 位验证码',
                verification_sms: '短信验证码',
                verification: '6 位验证码',
                phone_selection: '手机号序号',
                choice: '选项'
            };
            return map[type] || '输入内容';
        }

        function appleLoginInputPlaceholder(inputRequired) {
            if (!inputRequired) return '';
            if (inputRequired.type === 'verification_initial') {
                return AppleLoginVerificationInitialPrompt;
            }
            if (inputRequired.type === 'verification_sms') {
                return inputRequired.prompt || '请输入短信 6 位验证码';
            }
            if (inputRequired.type === 'phone_selection') {
                return '';
            }
            return inputRequired.prompt || appleLoginInputTypeLabel(inputRequired.type);
        }

        function appleLoginCloseLoading() {
            if (typeof layer !== 'undefined' && layer.closeLast) {
                layer.closeLast('loading');
            }
        }

        function appleLoginBuildModalHtml(data) {
            return [
                '<div id="apple-login-box" style="padding:16px;">',
                '<blockquote class="layui-elem-quote">',
                'AppleID：', data.apple_id, '<br>',
                'IssuerID：', data.iss, '<br>',
                '备注信息：', data.remark || '-', '<br>',
                '</blockquote>',
                '<div id="apple-login-status" class="layui-font-blue" style="margin:12px 0;min-height:20px;"></div>',
                '<form class="layui-form layui-form-pane" id="apple-login-form" action="">',
                '<input type="hidden" name="iss" value="', data.iss, '">',
                '<div class="layui-form-item" id="apple-password-item">',
                '<label class="layui-form-label">账户密码</label>',
                '<div class="layui-input-block">',
                '<input type="password" name="password" autocomplete="off" class="layui-input" placeholder="请您输入 Apple ID 登录密码">',
                '</div></div>',
                '<div class="layui-form-item layui-hide" id="apple-interactive-item">',
                '<label class="layui-form-label" id="apple-interactive-label">验证码</label>',
                '<div class="layui-input-block">',
                '<input type="text" name="interactive_input" autocomplete="off" class="layui-input" maxlength="20" placeholder="">',
                '</div></div>',
                '<div class="layui-form-item layui-hide" id="apple-sms-item">',
                '<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" id="apple-sms-btn">接收短信验证码</button>',
                '</div>',
                '<div id="apple-phone-list" class="layui-hide"></div>',
                '<div class="layui-form-item">',
                '<button class="layui-btn" lay-submit lay-filter="apple_login_submit" id="apple-login-submit">',
                '提交登录',
                '</button>',
                '<button type="button" class="layui-btn layui-btn-primary" id="apple-login-reset">重置</button>',
                '<button type="button" class="layui-btn layui-btn-danger layui-hide" id="apple-login-cancel">取消登录</button>',
                '</div></form></div>'
            ].join('');
        }

        function AppleLoginDialog(certData, certificateTable) {
            this.certData = certData;
            this.certificateTable = certificateTable;
            this.layerIndex = null;
            this.sessionId = null;
            this.pollTimer = null;
            this.polling = false;
            this.statusPolling = false;
            this.terminalHandled = false;
            this.submitting = false;
            this.currentInputType = null;
            this.pendingSmsPhoneDisplay = null;
        }

        AppleLoginDialog.prototype.destroy = function () {
            this.terminalHandled = true;
            this.stopPoll();
            this.pendingSmsPhoneDisplay = null;
            appleLoginCloseLoading();
            if (this.sessionId) {
                this.request({ action: 'cancel', login_session_id: this.sessionId }, function () {});
                this.sessionId = null;
            }
        };

        AppleLoginDialog.prototype.stopPoll = function () {
            this.polling = false;
            this.statusPolling = false;
            if (this.pollTimer) {
                clearTimeout(this.pollTimer);
                this.pollTimer = null;
            }
        };

        AppleLoginDialog.prototype.schedulePoll = function (delay) {
            var self = this;
            if (!self.polling || !self.sessionId || self.terminalHandled) {
                return;
            }
            if (self.pollTimer) {
                clearTimeout(self.pollTimer);
            }
            self.pollTimer = setTimeout(function () {
                self.pollTimer = null;
                self.pollOnce();
            }, typeof delay === 'number' ? delay : AppleLoginPollInterval);
        };

        AppleLoginDialog.prototype.pollOnce = function () {
            var self = this;
            if (!self.polling || !self.sessionId || self.terminalHandled) {
                return;
            }
            if (self.submitting) {
                self.schedulePoll();
                return;
            }
            if (self.statusPolling) {
                return;
            }

            self.statusPolling = true;
            SendAjax({
                url: systemPath+'/certificate/v3/apple_login',
                data: { action: 'status', login_session_id: self.sessionId },
                successCallBack: function (response) {
                    self.statusPolling = false;
                    if (!self.polling || self.terminalHandled) {
                        return;
                    }
                    self.handleSession(response);
                    if (self.polling && self.sessionId && !self.terminalHandled) {
                        self.schedulePoll();
                    }
                },
                errorCallBack: function (xhr) {
                    self.statusPolling = false;
                    appleLoginCloseLoading();
                    if (!self.polling || self.terminalHandled) {
                        return;
                    }
                    if (xhr.responseJSON) {
                        self.handleSession(xhr.responseJSON);
                    } else {
                        layer.msg((xhr.responseJSON && xhr.responseJSON.message) || '状态查询失败', { icon: 2, time: 3000 });
                    }
                    if (self.polling && self.sessionId && !self.terminalHandled) {
                        self.schedulePoll();
                    }
                }
            });
        };

        AppleLoginDialog.prototype.setStatusText = function (text, isError) {
            var el = document.getElementById('apple-login-status');
            if (!el) return;
            el.className = isError ? 'layui-font-red' : 'layui-font-blue';
            el.innerHTML = text || '';
        };

        AppleLoginDialog.prototype.request = function (payload, done) {
            SendAjax({
                url: systemPath+'/certificate/v3/apple_login',
                data: payload,
                successCallBack: function (response) {
                    if (typeof done === 'function') done(response);
                },
                errorCallBack: function (xhr) {
                    appleLoginCloseLoading();
                    if (xhr.responseJSON && typeof done === 'function') {
                        done(xhr.responseJSON);
                    } else {
                        layer.msg((xhr.responseJSON && xhr.responseJSON.message) || '请求失败', { icon: 2, time: 3000 });
                    }
                }
            });
        };

        AppleLoginDialog.prototype.buildSmsVerificationRequired = function (display) {
            var phoneDisplay = display || '';
            return {
                type: 'verification_sms',
                prompt: phoneDisplay !== ''
                    ? ('请输入手机号码' + phoneDisplay + '的 6 位短信验证码')
                    : '请输入短信 6 位验证码',
                phone_number: phoneDisplay,
                sensitive: false
            };
        };

        AppleLoginDialog.prototype.showPendingSmsInput = function (display) {
            this.showInteractive(this.buildSmsVerificationRequired(display));
            this.setStatusText('正在发送短信验证码...', false);
        };

        AppleLoginDialog.prototype.renderPhoneList = function (phoneNumbers) {
            var container = document.getElementById('apple-phone-list');
            if (!container) return;

            container.innerHTML = '';
            if (!phoneNumbers || !phoneNumbers.length) {
                container.classList.add('layui-hide');
                return;
            }

            container.classList.remove('layui-hide');
            var self = this;
            phoneNumbers.forEach(function (phone) {
                var item = document.createElement('div');
                item.className = 'layui-form-item';

                var label = document.createElement('label');
                label.className = 'layui-form-label';
                label.innerText = phone.label || ('手机号' + phone.index);

                var block = document.createElement('div');
                block.className = 'layui-input-block';

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'layui-btn layui-btn-primary';
                btn.style.width = '100%';
                btn.style.margin = '0';
                btn.style.textAlign = 'left';
                btn.style.paddingLeft = '15px';
                btn.innerText = phone.display || phone.number || '';
                btn.onclick = function () {
                    self.pendingSmsPhoneDisplay = phone.display || phone.number || '';
                    self.renderPhoneList([]);
                    self.showPendingSmsInput(self.pendingSmsPhoneDisplay);
                    self.submitInteractive(String(phone.index));
                };

                block.appendChild(btn);
                item.appendChild(label);
                item.appendChild(block);
                container.appendChild(item);
            });
        };

        AppleLoginDialog.prototype.showInteractive = function (inputRequired) {
            var passwordItem = document.getElementById('apple-password-item');
            var interactiveItem = document.getElementById('apple-interactive-item');
            var smsItem = document.getElementById('apple-sms-item');
            var label = document.getElementById('apple-interactive-label');
            var input = document.querySelector('#apple-interactive-item input[name="interactive_input"]');
            var cancelBtn = document.getElementById('apple-login-cancel');
            var submitBtn = document.getElementById('apple-login-submit');
            var hasPhoneOptions = inputRequired
                && inputRequired.type === 'phone_selection'
                && inputRequired.phone_numbers
                && inputRequired.phone_numbers.length > 0;

            if (!inputRequired) {
                if (interactiveItem) interactiveItem.classList.add('layui-hide');
                if (smsItem) smsItem.classList.add('layui-hide');
                if (submitBtn) submitBtn.classList.remove('layui-hide');
                this.renderPhoneList([]);
                return;
            }

            this.currentInputType = inputRequired.type;

            if (passwordItem) {
                passwordItem.classList.add('layui-hide');
            }

            if (inputRequired.type === 'phone_selection') {
                if (interactiveItem) interactiveItem.classList.add('layui-hide');
                if (smsItem) smsItem.classList.add('layui-hide');
                this.renderPhoneList(inputRequired.phone_numbers || []);
                if (submitBtn) {
                    submitBtn.classList.toggle('layui-hide', hasPhoneOptions);
                    submitBtn.innerText = '提交序号';
                }
            } else {
                if (interactiveItem) interactiveItem.classList.remove('layui-hide');
                if (label) label.innerText = appleLoginInputTypeLabel(inputRequired.type);
                if (input) {
                    input.value = '';
                    input.placeholder = appleLoginInputPlaceholder(inputRequired);
                    input.type = inputRequired.sensitive ? 'password' : 'text';
                    input.maxLength = inputRequired.type === 'verification_initial'
                    || inputRequired.type === 'verification_sms'
                    || inputRequired.type === 'verification' ? 6 : 20;
                }
                this.renderPhoneList([]);

                if (smsItem) {
                    if (inputRequired.type === 'verification_initial') {
                        smsItem.classList.remove('layui-hide');
                    } else {
                        smsItem.classList.add('layui-hide');
                    }
                }

                if (submitBtn) {
                    submitBtn.classList.remove('layui-hide');
                    submitBtn.innerText = '提交验证';
                }
            }

            if (cancelBtn) cancelBtn.classList.remove('layui-hide');

            if (inputRequired.type === 'verification_sms') {
                this.pendingSmsPhoneDisplay = inputRequired.phone_number || this.pendingSmsPhoneDisplay;
                this.setStatusText(inputRequired.prompt || '请输入短信 6 位验证码', false);
            } else if (inputRequired.type === 'verification_initial') {
                this.setStatusText(AppleLoginVerificationInitialPrompt, false);
            } else if (inputRequired.type === 'phone_selection') {
                if (hasPhoneOptions) {
                    this.setStatusText(inputRequired.prompt || '请选择接收短信验证码的手机号', false);
                } else {
                    this.setStatusText('正在加载手机号列表...', false);
                }
            }
        };

        AppleLoginDialog.prototype.handleSession = function (response) {
            if (this.terminalHandled) {
                return;
            }

            var data = appleLoginApiData(response);
            var message = response.message || data.message || '';

            if (data.login_session_id) {
                this.sessionId = data.login_session_id;
            }

            if (data.status === 'success' || data.verified === true) {
                this.terminalHandled = true;
                this.stopPoll();
                this.pendingSmsPhoneDisplay = null;
                appleLoginCloseLoading();
                this.setStatusText(message || '登录成功', false);
                layer.msg(message || '登录成功', { icon: 1, time: 3000 });
                layer.close(this.layerIndex);
                if (this.certificateTable) {
                    this.certificateTable.draw(false);
                }
                return;
            }

            if (data.status === 'failed') {
                this.terminalHandled = true;
                this.stopPoll();
                this.pendingSmsPhoneDisplay = null;
                appleLoginCloseLoading();
                this.setStatusText(message || '登录失败', true);
                layer.msg(message || '登录失败', { icon: 2, time: 4000 });

                if (data.requires_password_reentry || data.error_type === 'wrong_password') {
                    this.terminalHandled = false;
                    this.sessionId = null;
                    this.currentInputType = null;
                    this.showInteractive(null);
                    document.getElementById('apple-password-item').classList.remove('layui-hide');
                    document.getElementById('apple-login-submit').innerText = '重新提交';
                    document.querySelector('#apple-login-form input[name="password"]').focus();
                }
                return;
            }

            if (data.status === 'need_input' && data.input_required) {
                if (data.input_required.type === 'verification_sms') {
                    this.pendingSmsPhoneDisplay = data.input_required.phone_number || this.pendingSmsPhoneDisplay;
                }
                this.showInteractive(data.input_required);

                if (data.input_required.type === 'phone_selection'
                    && (!data.input_required.phone_numbers || !data.input_required.phone_numbers.length)) {
                    this.setStatusText('正在加载手机号列表...', false);
                    this.startPoll();
                    return;
                }

                appleLoginCloseLoading();
                this.stopPoll();

                if (data.input_required.type === 'verification_initial') {
                    return;
                }
                if (data.input_required.type !== 'phone_selection') {
                    this.setStatusText(data.input_required.prompt || message, false);
                }
                return;
            }

            if (data.status === 'asc_login' || data.status === 'extracting' || data.status === 'running' || data.status === 'starting') {
                appleLoginCloseLoading();

                if (this.pendingSmsPhoneDisplay
                    && (data.message || '').indexOf('加载手机号') === -1
                    && data.status !== 'asc_login'
                    && data.status !== 'extracting') {
                    this.showInteractive(this.buildSmsVerificationRequired(this.pendingSmsPhoneDisplay));
                    if (this.currentInputType === 'verification_sms') {
                        this.setStatusText(message || '正在验证短信验证码...', false);
                    } else {
                        this.setStatusText(message || '正在发送短信验证码...', false);
                    }
                } else if ((data.message || '').indexOf('加载手机号') !== -1) {
                    this.showInteractive({ type: 'phone_selection', prompt: data.message, phone_numbers: [] });
                    this.setStatusText(data.message, false);
                } else if (data.status === 'asc_login' || data.status === 'extracting') {
                    this.showInteractive(null);
                    this.setStatusText(message || '正在生成会话...', false);
                } else if (this.currentInputType === 'verification_initial') {
                    this.setStatusText(message || '正在验证...', false);
                } else {
                    this.showInteractive(null);
                    this.setStatusText(message || '正在登录...', false);
                }
                this.startPoll();
                return;
            }

            if (message) {
                this.setStatusText(message, false);
            }
        };

        AppleLoginDialog.prototype.startPoll = function () {
            if (!this.sessionId || this.terminalHandled) {
                return;
            }
            this.polling = true;
            if (!this.statusPolling && !this.pollTimer) {
                this.pollOnce();
            }
        };

        AppleLoginDialog.prototype.startLogin = function (fields) {
            var self = this;
            if (!fields.password) {
                layer.msg('登录密码不能为空', { icon: 2, time: 3000 });
                return;
            }

            self.submitting = true;
            self.pendingSmsPhoneDisplay = null;
            layer.load(2);
            self.request(
                { action: 'start', iss: fields.iss, password: fields.password },
                function (response) {
                    self.submitting = false;
                    appleLoginCloseLoading();
                    self.handleSession(response);
                }
            );
        };

        AppleLoginDialog.prototype.submitInteractive = function (value) {
            var self = this;
            if (!self.sessionId) {
                layer.msg('登录会话不存在，请重新开始', { icon: 2, time: 3000 });
                return;
            }
            if (!value) {
                layer.msg('输入不能为空', { icon: 2, time: 3000 });
                return;
            }

            self.submitting = true;
            layer.load(2);
            self.request(
                { action: 'input', login_session_id: self.sessionId, input: value },
                function (response) {
                    self.submitting = false;
                    appleLoginCloseLoading();
                    var inputEl = document.querySelector('#apple-interactive-item input[name="interactive_input"]');
                    if (inputEl) inputEl.value = '';
                    self.handleSession(response);
                }
            );
        };

        AppleLoginDialog.prototype.bindEvents = function () {
            var self = this;
            window.__activeAppleLoginDialog = self;

            if (!window.__appleLoginFormBound) {
                layui.form.on('submit(apple_login_submit)', function (formData) {
                    var dialog = window.__activeAppleLoginDialog;
                    if (!dialog) return false;

                    var fields = formData.field;
                    if (dialog.sessionId && dialog.currentInputType && dialog.currentInputType !== 'password') {
                        dialog.submitInteractive(fields.interactive_input);
                    } else {
                        dialog.startLogin(fields);
                    }
                    return false;
                });
                window.__appleLoginFormBound = true;
            }

            var resetBtn = document.getElementById('apple-login-reset');
            if (resetBtn) {
                resetBtn.onclick = function () {
                    document.getElementById('apple-login-form').reset();
                    self.setStatusText('', false);
                };
            }

            var cancelBtn = document.getElementById('apple-login-cancel');
            if (cancelBtn) {
                cancelBtn.onclick = function () {
                    self.destroy();
                    self.sessionId = null;
                    self.setStatusText('已取消登录', true);
                    layer.msg('已取消登录', { icon: 0, time: 2000 });
                };
            }

            var smsBtn = document.getElementById('apple-sms-btn');
            if (smsBtn) {
                smsBtn.onclick = function () {
                    self.submitInteractive('sms');
                };
            }
        };

        AppleLoginDialog.prototype.open = function () {
            var self = this;
            self.layerIndex = layer.open({
                type: 1,
                anim: 'slideDown',
                title: '进阶功能需登录苹果账户',
                area: ['520px', 'auto'],
                content: appleLoginBuildModalHtml(self.certData),
                success: function () {
                    layui.form.render();
                    self.bindEvents();
                },
                end: function () {
                    if (window.__activeAppleLoginDialog === self) {
                        window.__activeAppleLoginDialog = null;
                    }
                    self.destroy();
                }
            });
        };

        function openAppleLoginDialog(certRowData, certificateTable) {
            var dialog = new AppleLoginDialog(certRowData, certificateTable);
            dialog.open();
            return dialog;
        }

        function normalizeDeviceUdid(raw) {
            return String(raw || '').trim().toLowerCase();
        }

        function isValidDeviceUdid(value) {
            var udid = normalizeDeviceUdid(value);
            if (!udid) {
                return false;
            }
            var newUdid = /^\d{8}-[0-9a-f]{16}$/i;
            var oldUdid = /^[0-9a-f]{40}$/i;
            return newUdid.test(udid) || oldUdid.test(udid);
        }

        function parseBatchDeviceInput(text) {
            var lines = String(text || '').split(/\r?\n/);
            var devices = [];
            var invalid = [];
            var seen = {};

            lines.forEach(function (line, index) {
                line = line.trim();
                if (!line) {
                    return;
                }

                var udidPart = line;
                var name = '';
                var parts = line.split(/\s+/).filter(function (part) {
                    return part !== '';
                });

                if (parts.length > 0) {
                    udidPart = parts[0];
                    name = parts.slice(1).join(' ').trim();
                }

                var udid = normalizeDeviceUdid(udidPart);

                if (!udid) {
                    return;
                }

                if (!isValidDeviceUdid(udid)) {
                    invalid.push({
                        line: index + 1,
                        value: String(udidPart || '').trim()
                    });
                    return;
                }

                if (seen[udid]) {
                    return;
                }

                seen[udid] = true;
                devices.push({
                    udid: udid,
                    name: name
                });
            });

            return {
                devices: devices,
                invalid: invalid
            };
        }

        function batchAddDevicesReasonLabel(reason) {
            var map = {
                already_registered: '已在 Apple 开发者账户注册',
                duplicate: '重复提交',
                invalid_udid: '无效 UDID',
                invalid: '无效设备',
                quota_exceeded: '设备额度不足',
                device_limit: '已达设备上限',
                processing: '设备审核中',
                ineligible: '设备不合格',
                api_error: 'Apple 接口错误',
                unknown: '未知原因'
            };
            return map[reason] || reason || '未知原因';
        }

        function buildBatchAddDevicesResultCopyText(result) {
            var lines = ['UDID\t结果\t原因'];
            (result.skipped || []).forEach(function (item) {
                lines.push([
                    item.udid || '-',
                    '跳过',
                    batchAddDevicesReasonLabel(item.reason)
                ].join('\t'));
            });
            (result.failed || []).forEach(function (item) {
                lines.push([
                    item.udid || '-',
                    '失败',
                    batchAddDevicesReasonLabel(item.reason || item.message)
                ].join('\t'));
            });
            return lines.join('\n');
        }

        function copyTextToClipboard(text, done) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    if (typeof done === 'function') done(true);
                }).catch(function () {
                    copyTextToClipboardFallback(text, done);
                });
                return;
            }
            copyTextToClipboardFallback(text, done);
        }

        function copyTextToClipboardFallback(text, done) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            var ok = false;
            try {
                ok = document.execCommand('copy');
            } catch (e) {
                ok = false;
            }
            document.body.removeChild(textarea);
            if (typeof done === 'function') done(ok);
        }

        function openBatchAddDevicesResultDialog(response) {
            var result = response.data || {};
            var skipped = result.skipped || [];
            var failed = result.failed || [];
            var detailCount = skipped.length + failed.length;
            var registeredCount = result.registered_count != null ? result.registered_count : 0;
            var skippedCount = result.skipped_count != null ? result.skipped_count : skipped.length;
            var failedCount = result.failed_count != null ? result.failed_count : failed.length;
            var copyText = buildBatchAddDevicesResultCopyText(result);

            var summaryHtml = [
                '<div style="padding:16px;">',
                '<p style="margin:0 0 14px;line-height:1.7;">', escapeHtmlText(response.message || '提交完成'), '</p>',
                '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;">',
                '<span style="padding:4px 12px;border-radius:999px;background:#ecfdf5;color:#15803d;font-size:13px;">成功 ', registeredCount, ' 台</span>',
                '<span style="padding:4px 12px;border-radius:999px;background:#fff7ed;color:#c2410c;font-size:13px;">跳过 ', skippedCount, ' 台</span>',
                '<span style="padding:4px 12px;border-radius:999px;background:#fef2f2;color:#dc2626;font-size:13px;">失败 ', failedCount, ' 台</span>',
                '</div>'
            ].join('');

            if (detailCount > 0) {
                summaryHtml += [
                    '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px;">',
                    '<strong style="font-size:13px;color:#374151;">跳过 / 失败明细（共 ', detailCount, ' 条）</strong>',
                    '<button type="button" class="layui-btn layui-btn-sm layui-btn-normal" id="batch-add-devices-copy-btn">一键复制</button>',
                    '</div>',
                    '<textarea id="batch-add-devices-result-text" readonly class="layui-textarea" style="min-height:220px;max-height:320px;resize:vertical;font-family:monospace;font-size:12px;line-height:1.6;">',
                    escapeHtmlText(copyText),
                    '</textarea>',
                    '<p style="margin:10px 0 0;font-size:12px;color:#9ca3af;">可手动选择复制，或点击「一键复制」复制全部明细。</p>'
                ].join('');
            }

            summaryHtml += '</div>';

            layer.open({
                type: 1,
                anim: 'slideDown',
                title: '批量添加设备结果',
                area: detailCount > 0 ? ['720px', 'auto'] : ['480px', 'auto'],
                shadeClose: true,
                content: summaryHtml,
                btn: ['知道了'],
                success: function (layero) {
                    if (detailCount <= 0) {
                        return;
                    }
                    layero.find('#batch-add-devices-copy-btn').on('click', function () {
                        var text = layero.find('#batch-add-devices-result-text').val() || copyText;
                        copyTextToClipboard(text, function (ok) {
                            layer.msg(ok ? '明细已复制到剪贴板' : '复制失败，请手动选择复制', { icon: ok ? 1 : 2, time: 2500 });
                        });
                    });
                }
            });
        }

        function formatAccountYesNo(value) {
            if (value === true || value === 1 || value === '1' || value === 'true' || value === 'yes') {
                return '是';
            }
            if (value === false || value === 0 || value === '0' || value === 'false' || value === 'no') {
                return '否';
            }
            return (value === null || value === undefined || value === '') ? '-' : String(value);
        }

        function escapeHtmlText(value) {
            return String(value == null ? '-' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function buildAccountRealTimeInformationHtml(data, meta) {
            meta = meta || {};
            var autoRenew = formatAccountYesNo(data.auto_renew);
            var resetRequired = formatAccountYesNo(data.device_reset_required);
            var amount = data.amount;
            if (amount !== '-' && amount != null && amount !== '') {
                amount = amount;
            } else {
                amount = '-';
            }

            return `
    <div style="padding:16px;">
        <small>
            Apple ID：${escapeHtmlText(meta.apple_id)}<br>
            Issuer ID：${escapeHtmlText(meta.iss)}<br>
            数据更新时间：${escapeHtmlText(new Date().toLocaleString())}
        </small>
        <hr>
        <table class="layui-table" lay-size="sm">
            <colgroup>
                <col width="180">
                <col>
            </colgroup>
            <tbody>
                <tr><td>团队 ID</td><td>${escapeHtmlText(data.team_id)}</td></tr>
                <tr><td>团队名称</td><td>${escapeHtmlText(data.team_name)}</td></tr>
                <tr><td>管理邮箱</td><td>${escapeHtmlText(data.email)}</td></tr>
                <tr><td>会员到期</td><td>${escapeHtmlText(data.expires_at)}</td></tr>
                <tr><td>自动续费</td><td>${autoRenew === '是' ? '<b class="layui-font-green">是</b>' : '<b class="layui-font-red">否</b>'}</td></tr>
                <tr><td>续费金额</td><td>${escapeHtmlText(amount)}</td></tr>
                <tr><td>设备重置日期</td><td>${escapeHtmlText(data.next_device_reset_date)}</td></tr>
                <tr><td>支持设备重置</td><td>${resetRequired === '是' ? '<b class="layui-font-green">是</b>' : '<b class="layui-font-red">否</b>'}</td></tr>
                <tr><td>会员当前状态</td><td>${data.status === 'active' ? '<b class="layui-font-green">正常</b>' : escapeHtmlText(data.status)}</td></tr>
                <tr><td>会话有效期到</td><td>${escapeHtmlText(meta.re_login_date)}</td></tr>
                re_login_date
            </tbody>
        </table>
    </div>
    `;
        }

        function buildAutoSelfCheckFeatureItem(num, title, desc, accentColor, bgColor) {
            return [
                '<div style="display:flex;gap:12px;padding:12px 14px;border-radius:10px;background:', bgColor, ';border:1px solid ', accentColor, '33;">',
                '<div style="flex-shrink:0;width:28px;height:28px;line-height:28px;text-align:center;border-radius:50%;background:', accentColor, ';color:#fff;font-weight:700;font-size:13px;">', num, '</div>',
                '<div><div style="font-weight:600;color:#111827;margin-bottom:2px;">', title, '</div>',
                '<div style="font-size:12px;color:#6b7280;">', desc, '</div></div>',
                '</div>'
            ].join('');
        }

        function buildRecommendedPermissionsGroupRow(group, checked) {
            var isChecked = checked !== false;
            var groupId = group.applicationGroup || '';
            return [
                '<tr class="rp-group-row" data-id="', escapeHtmlText(groupId), '">',
                '<td style="padding:8px;text-align:center;width:42px;border-bottom:1px solid #f3f4f6;">',
                '<input type="checkbox" class="rp-group-checkbox" data-id="', escapeHtmlText(groupId), '"', isChecked ? ' checked' : '', '>',
                '</td>',
                '<td style="padding:8px;border-bottom:1px solid #f3f4f6;">', escapeHtmlText(group.name || '-'), '</td>',
                '<td style="padding:8px;border-bottom:1px solid #f3f4f6;"><code style="font-size:12px;">', escapeHtmlText(group.identifier || '-'), '</code></td>',
                '<td style="padding:8px;text-align:center;border-bottom:1px solid #f3f4f6;">',
                groupId
                    ? '<button type="button" class="layui-btn layui-btn-xs layui-btn-danger rp-delete-group-btn" data-id="'
                    + escapeHtmlText(groupId) + '" data-identifier="' + escapeHtmlText(group.identifier || '') + '">删除</button>'
                    : '-',
                '</td>',
                '</tr>'
            ].join('');
        }

        function bindRecommendedPermissionsGroupTable(layero) {
            var $ = layui.$;
            var selectAll = layero.find('#rp-select-all');
            var checkboxes = layero.find('.rp-group-checkbox');

            selectAll.off('change.rpGroups').on('change.rpGroups', function () {
                checkboxes.prop('checked', selectAll.prop('checked'));
                selectAll.prop('indeterminate', false);
            });

            checkboxes.off('change.rpGroups').on('change.rpGroups', function () {
                var total = checkboxes.length;
                var checked = layero.find('.rp-group-checkbox:checked').length;
                selectAll.prop('checked', total > 0 && checked === total);
                selectAll.prop('indeterminate', checked > 0 && checked < total);
            });

            var total = checkboxes.length;
            var checked = layero.find('.rp-group-checkbox:checked').length;
            selectAll.prop('checked', total > 0 && checked === total);
            selectAll.prop('indeterminate', checked > 0 && checked < total);
        }

        function appendRecommendedPermissionsGroupRow(layero, group, checked) {
            var tbody = layero.find('#rp-groups-tbody');
            if (!tbody.length) {
                return;
            }
            tbody.append(buildRecommendedPermissionsGroupRow(group, checked));
            bindRecommendedPermissionsGroupTable(layero);
        }

        function removeRecommendedPermissionsGroupRow(layero, applicationGroupId) {
            var $ = layui.$;
            layero.find('#rp-groups-tbody tr.rp-group-row').each(function () {
                if ($(this).attr('data-id') === applicationGroupId) {
                    $(this).remove();
                }
            });
            bindRecommendedPermissionsGroupTable(layero);
        }

        function openRecommendedPermissionsDialog(data) {
            layer.load(2);
            SendAjax({
                url: systemPath+'/certificate/v3/get_groups',
                data: {
                    iss: data.iss
                },
                successCallBack: function (response) {
                    layer.closeAll('loading');
                    var groups = response.data || [];
                    var rows = groups.map(function (group) {
                        return buildRecommendedPermissionsGroupRow(group, true);
                    }).join('');

                    var content = [
                        '<div style="padding:12px 16px 0;line-height:1.7;color:#374151;">',
                        '<div style="margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">',
                        '<div style="font-size:12px;color:#6b7280;">',
                        'App Groups 进阶配置 - 您可以选择要关联的ID（可多选或不选）',
                        '</div>',
                        '<button type="button" class="layui-btn layui-btn-sm layui-btn-normal" id="rp-create-group-btn">创建新的 App Groups</button>',
                        '</div>',
                        '<div style="max-height:360px;overflow:auto;border:1px solid #e5e7eb;border-radius:8px;">',
                        '<table style="width:100%;border-collapse:collapse;font-size:13px;">',
                        '<thead>',
                        '<tr style="background:#f9fafb;">',
                        '<th style="padding:8px;text-align:center;width:42px;">',
                        '<input type="checkbox" id="rp-select-all"', groups.length ? ' checked' : '', '>',
                        '</th>',
                        '<th style="padding:8px;text-align:left;">名称</th>',
                        '<th style="padding:8px;text-align:left;">Identifier</th>',
                        '<th style="padding:8px;text-align:center;width:72px;">操作</th>',
                        '</tr>',
                        '</thead>',
                        '<tbody id="rp-groups-tbody">', rows, '</tbody>',
                        '</table>',
                        '</div>',
                        '<div id="rp-create-group-form" class="layui-hide" style="margin-top:12px;padding:12px;border:1px dashed #d1d5db;border-radius:8px;background:#f9fafb;">',
                        '<div style="font-size:13px;font-weight:600;margin-bottom:10px;">新建 App Group</div>',
                        '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">',
                        '<input type="text" id="rp-new-group-name" class="layui-input" placeholder="名称 name" style="width:140px;">',
                        '<input type="text" id="rp-new-group-identifier" class="layui-input" placeholder="Identifier，如 group.xxx.app" style="flex:1;min-width:220px;">',
                        '<button type="button" class="layui-btn layui-btn-sm" id="rp-submit-create-group">提交创建</button>',
                        '<button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="rp-cancel-create-group">取消</button>',
                        '</div>',
                        '</div>',
                        '</div>'
                    ].join('');

                    layer.open({
                        type: 1,
                        anim: 'slideDown',
                        title: '进阶功能 - 官方推荐权限【修改的Identifiers为：nsk-'+data.nsk_id+'.v-team.cn】',
                        area: ['680px', 'auto'],
                        shadeClose: false,
                        content: content,
                        btn: ['确认并设置官方推荐权限', '取消'],
                        btnAlign: 'c',
                        success: function (layero) {
                            var $ = layui.$;
                            bindRecommendedPermissionsGroupTable(layero);

                            layero.find('#rp-create-group-btn').on('click', function () {
                                layero.find('#rp-create-group-form').removeClass('layui-hide');
                                layero.find('#rp-new-group-name').focus();
                            });

                            layero.find('#rp-cancel-create-group').on('click', function () {
                                layero.find('#rp-create-group-form').addClass('layui-hide');
                            });

                            layero.find('#rp-groups-tbody').on('click', '.rp-delete-group-btn', function () {
                                var btn = $(this);
                                var applicationGroupId = btn.attr('data-id');
                                var identifier = btn.attr('data-identifier') || '';
                                var groupName = btn.closest('tr').find('td').eq(1).text().trim() || applicationGroupId;

                                if (!applicationGroupId) {
                                    return;
                                }

                                layer.confirm(
                                    '确定要删除 App Group「' + groupName + '」吗？<br>此操作不可恢复。',
                                    { btn: ['确定删除', '取消'] },
                                    function (confirmIndex) {
                                        layer.close(confirmIndex);
                                        layer.load(2);
                                        SendAjax({
                                            url: systemPath+'/certificate/v3/del_groups',
                                            data: {
                                                iss: data.iss,
                                                applicationGroup: applicationGroupId,
                                                identifier: identifier
                                            },
                                            successCallBack: function (deleteResponse) {
                                                layer.closeAll('loading');
                                                removeRecommendedPermissionsGroupRow(layero, applicationGroupId);
                                                layer.msg(deleteResponse.message || 'App Group 删除成功', { icon: 1, time: 2500 });
                                            },
                                            errorCallBack: function (error) {
                                                layer.closeAll('loading');
                                                var message = (error && error.responseJSON && error.responseJSON.message)
                                                    ? error.responseJSON.message
                                                    : '删除 App Group 失败';
                                                layer.msg(message, { icon: 2, time: 5000 });
                                            }
                                        });
                                    }
                                );
                            });

                            layero.find('#rp-submit-create-group').on('click', function () {
                                var name = layero.find('#rp-new-group-name').val().trim();
                                var identifier = layero.find('#rp-new-group-identifier').val().trim();

                                if (!name) {
                                    layer.msg('请填写名称', { icon: 2, time: 3000 });
                                    return;
                                }

                                if (!identifier) {
                                    layer.msg('请填写 Identifier', { icon: 2, time: 3000 });
                                    return;
                                }

                                layer.load(2);
                                SendAjax({
                                    url: systemPath+'/certificate/v3/add_groups',
                                    data: {
                                        iss: data.iss,
                                        name: name,
                                        identifier: identifier
                                    },
                                    successCallBack: function (createResponse) {
                                        layer.closeAll('loading');
                                        var newGroup = createResponse.data || {};
                                        if (!newGroup.applicationGroup) {
                                            layer.msg('创建成功，但未返回 App Group ID', { icon: 2, time: 4000 });
                                            return;
                                        }
                                        appendRecommendedPermissionsGroupRow(layero, newGroup, true);
                                        layero.find('#rp-new-group-name').val('');
                                        layero.find('#rp-new-group-identifier').val('');
                                        layero.find('#rp-create-group-form').addClass('layui-hide');
                                        layer.msg(createResponse.message || 'App Group 创建成功', { icon: 1, time: 2500 });
                                    },
                                    errorCallBack: function (error) {
                                        layer.closeAll('loading');
                                        var message = (error && error.responseJSON && error.responseJSON.message)
                                            ? error.responseJSON.message
                                            : '创建 App Group 失败';
                                        layer.msg(message, { icon: 2, time: 5000 });
                                    }
                                });
                            });
                        },
                        yes: function (confirmIndex, layero) {
                            var $ = layui.$;
                            var selected = [];
                            layero.find('.rp-group-checkbox:checked').each(function () {
                                var id = $(this).attr('data-id');
                                if (id) {
                                    selected.push(id);
                                }
                            });

                            layer.close(confirmIndex);
                            layer.load(2);
                            SendAjax({
                                url: systemPath+'/certificate/v3/recommended_permissions',
                                data: {
                                    iss: data.iss,
                                    application_groups: selected
                                },
                                successCallBack: function (submitResponse) {
                                    layer.closeAll('loading');
                                    layer.msg(submitResponse.message, { icon: 1, time: 3000 });
                                },
                                errorCallBack: function (error) {
                                    layer.closeAll('loading');
                                    var message = (error && error.responseJSON && error.responseJSON.message)
                                        ? error.responseJSON.message
                                        : '设置官方推荐权限失败';
                                    layer.msg(message, { icon: 2, time: 5000 });
                                }
                            });
                        }
                    });
                },
                errorCallBack: function (error) {
                    layer.closeAll('loading');
                    var message = (error && error.responseJSON && error.responseJSON.message)
                        ? error.responseJSON.message
                        : '获取 App Groups 列表失败';
                    layer.msg(message, { icon: 2, time: 5000 });
                }
            });
        }

        function openAutoSelfCheckConfirm(data, certificateTable) {
            var isEnabled = data.auto_status === 'ENABLED';
            var actionBtnText = isEnabled ? '禁用自动维护' : '启用自动维护';
            var statusBadge = isEnabled
                ? '<span style="display:inline-block;padding:2px 10px;border-radius:999px;background:#dcfce7;color:#15803d;font-size:12px;font-weight:600;">● 运行中</span>'
                : '<span style="display:inline-block;padding:2px 10px;border-radius:999px;background:#f3f4f6;color:#6b7280;font-size:12px;font-weight:600;">○ 未启用</span>';

            var content = [
                '<div style="padding:2px 4px 0;line-height:1.7;color:#374151;">',
                '<div style="margin-bottom:14px;padding:12px 14px;background:linear-gradient(135deg,#f8fafc 0%,#eef2ff 100%);border-radius:10px;border:1px solid #e5e7eb;">',
                '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px;">',
                '<strong style="font-size:15px;color:#111827;">账户自动维护</strong>',
                statusBadge,
                '</div>',
                '<div style="font-size:12px;color:#6b7280;">Apple ID：', escapeHtmlText(data.apple_id || '-'), '</div>',
                '<div style="font-size:12px;color:#6b7280;">预计自动维护到：', escapeHtmlText(data.re_login_date || '-'), '</div>',
                '</div>',
                '<p style="margin:0 0 10px;font-size:13px;color:#4b5563;">本功能目前仅自动维护以下项目：</p>',
                '<div style="display:flex;flex-direction:column;gap:10px;">',
                buildAutoSelfCheckFeatureItem('1', '定时签署新协议', '避免新协议导致接口无法正常使用', '#16a34a', '#ecfdf5'),
                buildAutoSelfCheckFeatureItem('2', '定时清理无效描述文件', '避免描述文件过多导致重复名无法添加', '#2563eb', '#eff6ff'),
                '</div>',
                '<p style="margin:14px 0 0;font-size:12px;color:#9ca3af;">启用后系统将按定时任务自动执行，无需手动操作。</p>',
                '</div>'
            ].join('');

            layer.confirm(content, {
                title: '自动维护设置',
                area: ['500px', 'auto'],
                btnAlign: 'c',
                btn: [actionBtnText, '取消本次操作'],
                success: function (layero) {
                    var btn0 = layero.find('.layui-layer-btn0');
                    var btn1 = layero.find('.layui-layer-btn1');
                    if (isEnabled) {
                        btn0.css({ backgroundColor: '#dc2626', borderColor: '#dc2626', color: '#fff' });
                    } else {
                        btn0.css({ backgroundColor: '#16a34a', borderColor: '#16a34a', color: '#fff' });
                    }
                    btn1.css({ backgroundColor: '#f3f4f6', borderColor: '#d1d5db', color: '#374151' });
                }
            }, function (confirmIndex) {
                layer.close(confirmIndex);
                layer.load(2);
                SendAjax({
                    url: systemPath+'/certificate/v3/auto_self_check',
                    data: {
                        iss: data.iss
                    },
                    successCallBack: function (response) {
                        layer.closeAll('loading');
                        if (certificateTable) {
                            certificateTable.draw();
                        }
                        layer.msg(response.message, { icon: 1, time: 3000 });
                    },
                    errorCallBack: function (error) {
                        layer.closeAll('loading');
                        var message = (error && error.responseJSON && error.responseJSON.message)
                            ? error.responseJSON.message
                            : '操作失败';
                        layer.msg(message, { icon: 2, time: 5000 });
                    }
                });
            });
        }

        function openAccountRealTimeInformationDialog(certData) {
            layer.load(2);
            SendAjax({
                url: systemPath+'/certificate/v3/information',
                data: {
                    iss: certData.iss
                },
                successCallBack: function (response) {
                    layer.closeAll('loading');
                    var payload = response.data || {};
                    layer.open({
                        type: 1,
                        anim: 'slideDown',
                        title: '账户当前实时信息',
                        area: ['680px', 'auto'],
                        shadeClose: true,
                        content: buildAccountRealTimeInformationHtml(payload, {
                            iss: certData.iss,
                            apple_id: certData.apple_id,
                            re_login_date: certData.re_login_date
                        }),
                        btn: ['关闭']
                    });
                },
                errorCallBack: function (error) {
                    layer.closeAll('loading');
                    var message = (error && error.responseJSON && error.responseJSON.message)
                        ? error.responseJSON.message
                        : '获取账户实时信息失败';
                    layer.msg(message, { icon: 2, time: 5000 });
                }
            });
        }

        function openBatchAddDevicesDialog(certData) {
            layer.open({
                type: 1,
                anim: 'slideDown',
                title: '批量提交添加设备',
                area: ['760px', '85%'],
                content: `
        <div style="padding:16px;">
            <small>
                Apple ID：${escapeHtmlText(certData.apple_id)}<br>
                Issuer ID：${escapeHtmlText(certData.iss)}<br>
                <br>
                每行填写一台设备，支持以下格式：<br>
                1. 仅 UDID：<code>00008030-001E1CE40286402E</code><br>
                2. UDID + 备注：<code>00008030-001E1CE40286402E 测试机</code><br>
                3. 使用空格来分隔 UDID 与名称<br>
                <br>
                支持 iOS 40 位老型 UDID、以及带连字符的新型 UDID。<br>
                单次最多提交 <b>100</b> 台设备。
            </small>
            <hr>
            <form class="layui-form layui-form-pane" lay-filter="batchAddDevicesForm">
                <div class="layui-form-item layui-form-text">
                    <label class="layui-form-label">设备列表</label>
                    <div class="layui-input-block">
                        <textarea name="devices_text" placeholder="每行一个 UDID，或 UDID 备注" class="layui-textarea" style="min-height:260px;"></textarea>
                    </div>
                </div>
                <div class="layui-form-item">
                    <blockquote class="layui-elem-quote" id="batch-add-devices-summary">有效设备：0 台 | 无效行：0 行</blockquote>
                </div>
                <div class="layui-form-item">
                    <button class="layui-btn" lay-submit lay-filter="batch_add_devices">提交验证添加</button>
                    <button type="reset" class="layui-btn layui-btn-primary">清空输入</button>
                </div>
            </form>
        </div>
        `,
                success: function (layero) {
                    layui.form.render(null, 'batchAddDevicesForm');

                    var $textarea = layero.find('textarea[name="devices_text"]');
                    var $summary = layero.find('#batch-add-devices-summary');

                    function refreshBatchDeviceSummary() {
                        var parsed = parseBatchDeviceInput($textarea.val());
                        var invalidText = parsed.invalid.length
                            ? parsed.invalid.slice(0, 5).map(function (item) {
                            return '第' + item.line + '行';
                        }).join('、') + (parsed.invalid.length > 5 ? ' 等' : '')
                            : '无';
                        $summary.html(
                            '有效设备：<b>' + parsed.devices.length + '</b> 台 | 无效行：<b class="layui-font-red">' + parsed.invalid.length + '</b> 行'
                            + (parsed.invalid.length ? ('（' + invalidText + '）') : '')
                        );
                    }

                    $textarea.on('input', refreshBatchDeviceSummary);
                    layero.find('button[type="reset"]').on('click', function () {
                        setTimeout(refreshBatchDeviceSummary, 0);
                    });
                    refreshBatchDeviceSummary();

                    layui.form.on('submit(batch_add_devices)', function (formData) {
                        var parsed = parseBatchDeviceInput(formData.field.devices_text);

                        if (parsed.invalid.length > 0) {
                            layer.msg('存在无效 UDID，请修正后再提交', { icon: 2, time: 4000 });
                            return false;
                        }

                        if (parsed.devices.length === 0) {
                            layer.msg('请至少输入一个有效 UDID', { icon: 2, time: 4000 });
                            return false;
                        }

                        if (parsed.devices.length > 100) {
                            layer.msg('单次最多提交 100 台设备', { icon: 2, time: 4000 });
                            return false;
                        }

                        layer.confirm(
                            '即将向 Apple 开发者账户提交 <b class="layui-font-red">' + parsed.devices.length + '</b> 台设备，是否继续？',
                            { btn: ['确定提交', '取消'] },
                            function (confirmIndex) {
                                layer.close(confirmIndex);
                                layer.load(2);
                                SendAjax({
                                    url: systemPath+'/certificate/v3/add_devices',
                                    data: {
                                        iss: certData.iss,
                                        devices: parsed.devices
                                    },
                                    successCallBack: function (response) {
                                        layer.closeAll('loading');
                                        openBatchAddDevicesResultDialog(response);
                                    },
                                    errorCallBack: function (error) {
                                        layer.closeAll('loading');
                                        var message = (error && error.responseJSON && error.responseJSON.message)
                                            ? error.responseJSON.message
                                            : '批量添加设备失败';
                                        layer.msg(message, { icon: 2, time: 5000 });
                                    }
                                });
                            }
                        );

                        return false;
                    });
                }
            });
        }

        usersCertificateList.on('click', '.auto-self-check-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            if (data.apple !== true) {
                layer.msg('您的账户暂无此功能操作权限', { icon: 2, time: 5000 });
                return;
            }
            if (!data || data.apple_id === undefined || data.apple_id === null || data.apple_id === '') {
                layer.msg('请同步证书后再操作', { icon: 2, time: 5000 });
                return;
            }
            if (data.login !== true) {
                openAppleLoginDialog(data, usersCertificateList);
                return;
            }
            openAutoSelfCheckConfirm(data, usersCertificateList);
        });

        usersCertificateList.on('click', '.auto-agreements-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            if (data.apple !== true) {
                layer.msg('您的账户暂无此功能操作权限', { icon: 2, time: 5000 });
                return;
            }
            if (!data || data.apple_id === undefined || data.apple_id === null || data.apple_id === '') {
                layer.msg('请同步证书后再操作', { icon: 2, time: 5000 });
                return;
            }
            if (data.login !== true) {
                openAppleLoginDialog(data, usersCertificateList);
                return;
            }
            layer.load(2);
            SendAjax({
                'url': systemPath+'/certificate/v3/auto_agreements',
                'data': {
                    iss: data.iss
                },
                'successCallBack': function (response) {
                    layer.msg(response.message, { icon: 1, time: 3000 });
                }
            });
        });

        usersCertificateList.on('click', '.del-keys-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            if (data.apple !== true) {
                layer.msg('您的账户暂无此功能操作权限', { icon: 2, time: 5000 });
                return;
            }
            if (!data || data.apple_id === undefined || data.apple_id === null || data.apple_id === '') {
                layer.msg('请同步证书后再操作', { icon: 2, time: 5000 });
                return;
            }
            if (data.login !== true) {
                openAppleLoginDialog(data, usersCertificateList);
                return;
            }
            layer.load(2);
            SendAjax({
                'url': systemPath+'/certificate/v3/del_keys',
                'data': {
                    iss: data.iss
                },
                'successCallBack': function (response) {
                    layer.msg(response.message, { icon: 1, time: 3000 });
                }
            });
        });

        usersCertificateList.on('click', '.del-profiles-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            if (data.apple !== true) {
                layer.msg('您的账户暂无此功能操作权限', { icon: 2, time: 5000 });
                return;
            }
            if (!data || data.apple_id === undefined || data.apple_id === null || data.apple_id === '') {
                layer.msg('请同步证书后再操作', { icon: 2, time: 5000 });
                return;
            }
            if (data.login !== true) {
                openAppleLoginDialog(data, usersCertificateList);
                return;
            }
            layer.load(2);
            SendAjax({
                'url': systemPath+'/certificate/v3/del_profiles',
                'data': {
                    iss: data.iss
                },
                'successCallBack': function (response) {
                    layer.msg(response.message, { icon: 1, time: 3000 });
                }
            });
        });

        usersCertificateList.on('click', '.information-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            if (data.apple !== true) {
                layer.msg('您的账户暂无此功能操作权限', { icon: 2, time: 5000 });
                return;
            }
            if (!data || data.apple_id === undefined || data.apple_id === null || data.apple_id === '') {
                layer.msg('请同步证书后再操作', { icon: 2, time: 5000 });
                return;
            }
            if (data.login !== true) {
                openAppleLoginDialog(data, usersCertificateList);
                return;
            }
            openAccountRealTimeInformationDialog(data);
        });

        usersCertificateList.on('click', '.add-devices-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            if (data.apple !== true) {
                layer.msg('您的账户暂无此功能操作权限', { icon: 2, time: 5000 });
                return;
            }
            if (!data || data.apple_id === undefined || data.apple_id === null || data.apple_id === '') {
                layer.msg('请同步证书后再操作', { icon: 2, time: 5000 });
                return;
            }
            if (data.login !== true) {
                openAppleLoginDialog(data, usersCertificateList);
                return;
            }
            openBatchAddDevicesDialog(data);
        });

        usersCertificateList.on('click', '.del-devices-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            if (data.apple !== true) {
                layer.msg('您的账户暂无此功能操作权限', { icon: 2, time: 5000 });
                return;
            }
            if (!data || data.apple_id === undefined || data.apple_id === null || data.apple_id === '') {
                layer.msg('请同步证书后再操作', { icon: 2, time: 5000 });
                return;
            }
            if (data.login !== true) {
                openAppleLoginDialog(data, usersCertificateList);
                return;
            }
            layer.confirm(
                `本次操作将移除并重置设备列表所有设备。<br>
                本功能仅重置设备列表，其他操作需按平台原有习惯操作！<br>
                <b class="layui-font-red">如果您选择继续则代表您已经同意本次操作所出现的任何结果都由本人承担！</b><br>
                <br>
                您是否还要继续？
                `,
                { btn: ['同意并继续', '取消'] },
                function (confirmIndex) {
                    layer.close(confirmIndex);
                    layer.load(2);
                    SendAjax({
                        'url': systemPath+'/certificate/v3/del_devices',
                        'data': {
                            iss: data.iss
                        },
                        'successCallBack': function (response) {
                            layer.msg(response.message, { icon: 1, time: 3000 });
                        }
                    });
                }
            );
        });

        usersCertificateList.on('click', '.recommended-permissions-btn', function() {
            let data = usersCertificateList.row($(this)).data();
            if (data === undefined) {
                data = usersCertificateList.row($(this).closest('tr')).data();
            }
            if (data.apple !== true) {
                layer.msg('您的账户暂无此功能操作权限', { icon: 2, time: 5000 });
                return;
            }
            if (!data || data.apple_id === undefined || data.apple_id === null || data.apple_id === '') {
                layer.msg('请同步证书后再操作', { icon: 2, time: 5000 });
                return;
            }
            if (data.login !== true) {
                openAppleLoginDialog(data, usersCertificateList);
                return;
            }
            openRecommendedPermissionsDialog(data);
        });
    </script>
@endsection