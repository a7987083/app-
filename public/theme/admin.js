const systemPath = document.querySelector('meta[name="system-path"]').getAttribute('content');

function SendAjax(params) {
    var $ = layui.$;
    var layer = layui.layer;
    var url = params.url;
    var type = params.type || 'post';
    var data = params.data || '';
    var successCallBack;
    var errorCallBack;
    var completeCallBack;
    if (typeof params.successCallBack !== 'undefined') {
        successCallBack = params.successCallBack;
    } else {
        successCallBack = function (response) {
            if (response.data['speed-cert-v2-token']) {
                layui.sessionData(StoreKey(), { key: 'speed-cert-v2-token', value: response.data['speed-cert-v2-token'] });
            }
            layer.msg(response['message'], {icon: 1, time: 3000}, function(){
                location.reload();
            });
            return false;
        }
    }
    if (typeof params.errorCallBack !== 'undefined') {
        errorCallBack = params.errorCallBack;
    } else {
        errorCallBack = function (error, textStatus, errorThrown) {
             layer.msg(error['responseJSON']['message'], {icon: 5, time: 3000});
        };
    }
    if (typeof params.completeCallBack !== 'undefined') {
        completeCallBack = params.completeCallBack;
    } else {
        completeCallBack = function () {
            layer.closeLast('loading');
        };
    }
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        url: url,
        type: type,
        data: data,
        dataType: "json",
        success: successCallBack,
        error: errorCallBack,
        complete: completeCallBack,
    });
}

if (document.getElementById('usersCertificateList')) {
    var usersCertificateList = $('#usersCertificateList').DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        buttons: DataTablesConfig.buttons,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        ajax: function(data, callback, settings) {
            SendAjax({
                'url': '/cert/v1/lists_certificate',
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
                    return '<small>'+row.apple_id+'</small>';
                }
            },
            {
                title: '<small>备注信息</div>',
                data: 'remark',
                render: function(data, type, row) {
                    return '<div class="badge bg-secondary">'+row.remark+'</div>';
                }
            },
            {
                title: '<small>实时状态</small>',
                data: 'status',
                render: function(data, type, row) {
                    var custom = '';
                    switch(row.status) {
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
                            custom = '<div class="badge bg-secondary">'+row.status+'</div>';
                        break;
                    }
                    return custom;
                }
            },
            {
                title: '<small>证书开关</small>',
                data: 'switch',
                render: function(data, type, row) {
                    if (row.switch === 'ENABLED') {
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
                    var custom = '';
                    switch(row.processing) {
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
                    switch (row.sales) {
                        case 'ENABLED':
                            return '<div class="badge bg-success">正在参与</div>';
                        break;
                        case 'DISABLED':
                            return '<div class="badge bg-danger">暂不支持</div>';
                        break;
                        case 'SUPPORT':
                            return '<div class="sales-btn badge bg-info">点击申请</div>';
                        break;
                        case 'PROCESS':
                            return '<div class="badge bg-warning">正在审核</div>';
                        break;
                        case 'NOT_AUTH':
                            return '<div class="get-auth-btn badge bg-warning">获取权限</div>';
                        break;
                    }
                }
            },
            {
                title: '<small>iPhone 权限｜额度</small>',
                data: 'IPHONE',
                render: function(data, type, row) {
                    var custom = '';
                    custom += (row.iphone_camouflage === '伪装权限未开通') ? '<div class="badge bg-danger">'+row.iphone_camouflage+'</div>' : '<div class="badge bg-success">'+row.iphone_camouflage+'</div>';
                    custom += (row.IPHONE > 1) ? '&nbsp;<div class="badge bg-info">剩余：'+row.IPHONE+'</div>' : '&nbsp;<div class="badge bg-danger">剩余：'+row.IPHONE+'</div>';
                    return custom;
                }
            },
            {
                title: '<small>Mac 权限｜额度</small>',
                data: 'MAC',
                render: function(data, type, row) {
                    var custom = '';
                    custom += (row.mac_camouflage === '伪装权限未开通') ? '<div class="badge bg-danger">'+row.mac_camouflage+'</div>' : '<div class="badge bg-success">'+row.mac_camouflage+'</div>';
                    custom += (row.MAC > 1) ? '&nbsp;<div class="badge bg-info">剩余：'+row.MAC+'</div>' : '&nbsp;<div class="badge bg-danger">剩余：'+row.MAC+'</div>';
                    return custom;
                }
            },
            {
                title: '<small>iPad 权限｜额度</small>',
                data: 'IPAD',
                render: function(data, type, row) {
                    var custom = '';
                    custom += (row.ipad_camouflage === '伪装权限未开通') ? '<div class="badge bg-danger">'+row.ipad_camouflage+'</div>' : '<div class="badge bg-success">'+row.ipad_camouflage+'</div>';
                    custom += (row.IPAD > 1) ? '&nbsp;<div class="badge bg-info">剩余：'+row.IPAD+'</div>' : '&nbsp;<div class="badge bg-danger">剩余：'+row.IPAD+'</div>';
                    return custom;
                }
            },
            {
                title: '<small>Vision 权限｜额度</small>',
                data: 'APPLE_VISION_PRO',
                render: function(data, type, row) {
                    var custom = '';
                    custom += (row.vision_pro_camouflage === '伪装权限未开通') ? '<div class="badge bg-danger">'+row.vision_pro_camouflage+'</div>' : '<div class="badge bg-success">'+row.vision_pro_camouflage+'</div>';
                    custom += (row.APPLE_VISION_PRO > 1) ? '&nbsp;<div class="badge bg-info">剩余：'+row.APPLE_VISION_PRO+'</div>' : '&nbsp;<div class="badge bg-danger">剩余：'+row.APPLE_VISION_PRO+'</div>';
                    return custom;
                }
            },
            {
                title: '<small>Watch 权限｜额度</small>',
                data: 'APPLE_WATCH',
                render: function(data, type, row) {
                    var custom = '';
                    custom += (row.watch_camouflage === '伪装权限未开通') ? '<div class="badge bg-danger">'+row.watch_camouflage+'</div>' : '<div class="badge bg-success">'+row.watch_camouflage+'</div>';
                    custom += (row.APPLE_WATCH > 1) ? '&nbsp;<div class="badge bg-info">剩余：'+row.APPLE_WATCH+'</div>' : '&nbsp;<div class="badge bg-danger">剩余：'+row.APPLE_WATCH+'</div>';
                    return custom;
                }
            },
            {
                title: '<small>iPod 权限｜额度</small>',
                data: 'IPOD',
                render: function(data, type, row) {
                    var custom = '';
                    custom += (row.ipod_camouflage === '伪装权限未开通') ? '<div class="badge bg-danger">'+row.ipod_camouflage+'</div>' : '<div class="badge bg-success">'+row.ipod_camouflage+'</div>';
                    custom += (row.IPOD > 1) ? '&nbsp;<div class="badge bg-info">剩余：'+row.IPOD+'</div>' : '&nbsp;<div class="badge bg-danger">剩余：'+row.IPOD+'</div>';
                    return custom;
                }
            },
            {
                title: '<small>TV 权限｜额度</small>',
                data: 'APPLE_TV',
                render: function(data, type, row) {
                    var custom = '';
                    custom += (row.tv_camouflage === '伪装权限未开通') ? '<div class="badge bg-danger">'+row.tv_camouflage+'</div>' : '<div class="badge bg-success">'+row.tv_camouflage+'</div>';
                    custom += (row.APPLE_TV > 1) ? '&nbsp;<div class="badge bg-info">剩余：'+row.APPLE_TV+'</div>' : '&nbsp;<div class="badge bg-danger">剩余：'+row.APPLE_TV+'</div>';
                    return custom;
                }
            },
            {
                title: '<small>Issuer ID</small>',
                data: 'iss',
                render: function(data, type, row) {
                    return '<div class="badge bg-warning">'+row.iss+'</div>';
                }
            },
            {
                title: '<small>添加日期</small>',
                data: 'created_at',
                render: function(data, type, row) {
                    return '<div class="badge bg-info">'+row.created_at+'</div>';
                }
            },
            {
                title: '<small>更新日期</small>',
                data: 'updated_at',
                render: function(data, type, row) {
                    return '<div class="badge bg-success">'+row.updated_at+'</div>';
                }
            },
            {
                title: '<small>功能操作</small>',
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                var custom = `
                    <div class="remark-btn badge bg-warning">修改备注</div>&nbsp;
                    <div class="sync-btn badge bg-info">同步证书</div>&nbsp;`;
                    if (row.sales !== 'ENABLED' && row.sales !== 'PROCESS') {
                        custom += `
                        <div class="reestablish-profiles-btn badge bg-dark">重建描述</div>&nbsp;
                        <div class="recovery-btn badge bg-primary">重构证书</div>&nbsp;
                        <div class="reset-password-btn badge bg-success">重设密码</div>&nbsp;
                        <div class="delete-btn badge bg-danger">删除证书</div>&nbsp;
                        <div class="permissions-btn badge bg-dark">修改权限</div>&nbsp;
                        <div class="cert_secondary_reset-btn badge bg-primary">二次超开</div>&nbsp;
                        `;
                        if (row.switch === 'ENABLED') {
                            custom += '<div class="disabled-btn badge bg-warning">停用证书</div>';
                        } else {
                            custom += '<div class="enabled-btn badge bg-info">启用证书</div>';
                        }
                    }
                    return custom;
                }
            },
        ]
    });
    usersCertificateList.on('click', '.get-auth-btn', function() {
        RedirectTo('/participate_in_sales.html');
    });
    usersCertificateList.on('click', '.sales-btn', function() {
        var data = usersCertificateList.row($(this)).data();
        if (data === undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.confirm(`
        当前证书Apple ID：${data.apple_id}<br>
        当前证书Issuer ID：${data.iss}<br><br>
        此证书已经符合参与公共池销售的“不卡设备”资质，您是否要继续提交申请，为本证书获取参与公共池销售权限？<br>
        参与公池销售、并不会影响您此本证书的添加新设备、查询等必要操作、本平台只是帮助您消耗您可能消耗不完的证书额度，避免过度浪费！<br><br>
        参与公共池销售须知条款：<br>
        一、申请参与公共池证书系统会自动进行首轮资质验证、验证失败会驳回申请，具体原因可在“证书公池->参与销售”里查看具体原因。<br>
        二、如您的证书经过系统首轮资质验证、工作人员一般会在当天进行审核，通过后您的证书将参与公共池销售，所获得利润可在“证书公池->销售记录”里查看。<br>
        三、一旦您提交申请，在审核结束前与成功参与后、您的证书会进入“禁止操作状态”！禁止操作状态下将不能进行：“重构证书、重设密码、删除证书、禁用证书、启用证书”等操作。但不影响您的证书添加新设备等必要操作！<br>
        四、更多条款请看“证书公池->参与销售”的参与销售须知<br>
        `, {
            btn: ['我已阅读条款，并提交申请', '取消操作']
        }, function(index, elem){
            layer.close(index);
            layer.load(2);
            SendAjax({
                'url': '/cert/v1/apply_participation_sales',
                'data': {
                    iss: data.iss
                },
                'successCallBack': function (response) {
                    layer.msg(response['data']['message'], { icon: 1, time: 3000 });
                }
            });
        });
    });
    usersCertificateList.on('click', '.permissions-btn', function() {
        var data = usersCertificateList.row($(this)).data();
        if (data === undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        if (data.type === 'DISTRIBUTION' || data.type === 'IOS_DISTRIBUTION') {
            var tips = `当前证书类型为：${data.type}，支持开关证书权限！【不会影响您的证书有效性】`;
        } else {
            var tips = `<code>当前证书类型为：${data.type}，不支持修改权限。如果您仍需要修改权限，请先阅读以下条款！<br>
            一、由于此证书类型不符合条件！一旦提交请求，系统将自动为您强制重构证书为DISTRIBUTION类型！<br>
            二、强制重构证书如果已有对应证书、系统将执行删除操作，这可能导致您之前的用户需要重新下载重构后的证书！
            </code>`;
        }
        layer.open({
            type: 1,
            anim: 'slideDown',
            title: '开发者证书权限设置',
            content: `
            <div style="padding: 16px;">
            <small>
            当前证书Apple ID：${data.apple_id}<br>
            当前证书Issuer ID：${data.iss}<br><br>
            ${tips}<br><br>
            修改证书权限后须知条款：<br>
            一、修改证书权限后并不会使所以用户立即生效，而需要您手动为每个在此之前添加的设备进行重新“创建描述文件”后下载才能生效！<br>
            二、条款一仅限之前添加的设备、之后通过平台【包含API接口】添加的设备会根据您本次修改的权限赋予对应权限！
            </small>
            <hr>
            <form class="layui-form">
            <select name="permission" lay-search="" lay-verify="required">
            <option value="">请选择或搜索要操作的权限</option>
            <option value="SIRIKIT">Siri【Siri】</option>
            <option value="MAPS">地图【Maps】</option>
            <option value="WALLET">钱包【Wallet】</option>
            <option value="ICLOUD">iCloud【iCloud】</option>
            <option value="CLASSKIT">教育【ClassKit】</option>
            <option value="HOMEKIT">智能家居【HomeKit】</option>
            <option value="HOT_SPOT">个人热点【Hotspot】</option>
            <option value="HEALTHKIT">健康数据【HealthKit】</option>
            <option value="APP_GROUPS">数据共享【App Groups】</option>
            <option value="MULTIPATH">多路径访问【Multipath】</option>
            <option value="IN_APP_PURCHASE">内购【In-App Purchase】</option>
            <option value="GAME_CENTER">游戏中心【Game Center】</option>
            <option value="DATA_PROTECTION">数据保护【Data Protection】</option>
            <option value="USER_MANAGEMENT">用户管理【User Management】</option>
            <option value="COREMEDIA_HLS_LOW_LATENCY">低延迟HLS【Low Latency HLS】</option>
            <option value="SYSTEM_EXTENSION_INSTALL">系统扩展【System Extension】</option>
            <option value="INTER_APP_AUDIO">跨应用音频【Inter-App Audio】</option>
            <option value="PUSH_NOTIFICATIONS">推送通知【Push Notifications】</option>
            <option value="ASSOCIATED_DOMAINS">关联域名【Associated Domains】</option>
            <option value="NETWORK_EXTENSIONS">网络扩展【Network Extensions】</option>
            <option value="NFC_TAG_READING">NFC标签读取【NFC Tag Reading】</option>
            <option value="APPLE_ID_AUTH">使用Apple登录【Sign In with Apple】</option>
            <option value="APPLE_PAY">Apple Pay【Apple Pay Payment Processing】</option>
            <option value="NETWORK_CUSTOM_PROTOCOL">自定义网络协议【Custom Network Protocol】</option>
            <option value="ACCESS_WIFI_INFORMATION">获取 Wi-Fi 信息【Access Wi-Fi Information】</option>
            <option value="AUTOFILL_CREDENTIAL_PROVIDER">密码自动填充【AutoFill Credential Provider】</option>
            <option value="WIRELESS_ACCESSORY_CONFIGURATION">无线附件配置【Wireless Accessory Configuration】</option>
            <option value="PERSONAL_VPN">个人虚拟专用网络【Personal Virtual Private Network】</option>
            </select>
            <hr>
            <input type="radio" name="switch" value="ENABLED" title="开启权限" checked>
            <input type="radio" name="switch" value="DISABLED" title="禁用权限">
            <hr>
            <button class="layui-btn layui-btn-fluid" lay-submit lay-filter="permissions">提交</button>
            </form>
            </div>
            `,
            success: function(){
                layui.form.render();
                layui.form.on('submit(permissions)', function(formData){
                    layer.load(2);
                    SendAjax({
                        'url': '/cert/v1/reset_certificate_permissions',
                        'data': {
                            iss: data.iss,
                            permission: formData.field.permission,
                            switch: formData.field.switch
                        },
                        'successCallBack': function (response) {
                            layer.msg(response['data']['message'], { icon: 1, time: 3000 });
                        }
                    });
                    return false;
                });
            }
        });
    });
    usersCertificateList.on('click', '.reset-password-btn', function() {
        var data = usersCertificateList.row($(this)).data();
        if (data === undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.confirm(`
        默认P12证书密码为：1，您确定要重新设置自定义证书P12密码吗？<br><br>
        重置密码不会影响之前下载的证书，只会影响重设之后的下载、查询、创建等！【仅限此本证书】`, {
            btn: ['我已阅读以上内容', '取消操作']
        }, function(index, elem){
            layer.close(index);
            layer.prompt({title: '请输入新的证书P12密码'}, function(value, index, elem){
                if(value === '') return elem.focus();
                layer.close(index);
                layer.load(2);
                SendAjax({
                    'url': '/cert/v1/reset_certificate_password',
                    'data': {
                        iss: data.iss,
                        password: value
                    },
                    'successCallBack': function (response) {
                        layer.msg(response['data']['message'], { icon: 1, time: 3000 });
                    }
                });
            });
        });
    });
    usersCertificateList.on('click', '.sync-btn', function() {
        var data = usersCertificateList.row($(this)).data();
        if (data === undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.load(2);
        SendAjax({
            'url': '/cert/basic/sync_cert_udid',
            'data': {
                iss: data.iss
            },
            'successCallBack': function (response) {
                layer.msg(response['data']['message'], { icon: 1, time: 3000 });
            }
        });
    });
    usersCertificateList.on('click', '.reestablish-profiles-btn', function() {
        var data = usersCertificateList.row($(this)).data();
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
                'url': '/cert/v1/lists_devices',
                'data': {
                    start: 0,
                    search: {
                        value: data.iss,
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
                        if (['PROCESSING'].includes(item.status)) {
                            PROCESSINGCount++;
                        }
                        if (['INELIGIBLE'].includes(item.status)) {
                            INELIGIBLECount++;
                        }
                        if (!['DISABLED', 'PROCESSING', 'INELIGIBLE'].includes(item.status)) {
                            NormalCount++;
                            issOne = item.iss;
                            devices_idOne = item.devices_id;
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
                                'url': '/cert/basic/create_profiles',
                                'data': {
                                    iss: issOne,
                                    devices_id: devices_idOne
                                },
                                'successCallBack': function (response) {
                                    layer.msg(`重建设备ID：${devices_idOne} 重建成功`, {
                                        shade: 0.01,
                                        time: 3000
                                    });
                                    lists_devices_data.forEach(item => {
                                        if (devices_idOne === item.devices_id) return;
                                        if (!['DISABLED', 'PROCESSING', 'INELIGIBLE'].includes(item.status)) {
                                            SendAjax({
                                                'url': '/cert/basic/create_profiles',
                                                'data': {
                                                    iss: item.iss,
                                                    devices_id: item.devices_id
                                                },
                                                'successCallBack': function (response) {
                                                    layer.msg(`重建设备ID：${item.devices_id} 重建成功`, {
                                                        shade: 0.01,
                                                        time: 3000
                                                    });
                                                },
                                                'errorCallBack': function(error) {
                                                    layer.msg(`重建设备ID：${error.response.data.devices_id} 重建失败`, {
                                                        shade: 0.01,
                                                        time: 3000
                                                    });
                                                }
                                            });
                                        }
                                    });
                                },
                                'errorCallBack': function(error) {
                                    layer.msg(error['response']['data']['message'], {icon: 5, time: 3000});
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
        var data = usersCertificateList.row($(this)).data();
        if (data === undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要停用“'+data.apple_id+'”证书吗？', {
            btn: ['确定停用', '取消操作']
        }, function(){
            layer.load(2);
            SendAjax({
                'url': '/cert/basic/certificate_switch',
                'data': {
                    iss: data.iss,
                    switch: 'DISABLED'
                },
                'successCallBack': function (response) {
                    usersCertificateList.draw();
                    layer.msg(response['data']['message'], { icon: 1, time: 3000 });
                }
            });
        });
    });
    usersCertificateList.on('click', '.enabled-btn', function() {
        var data = usersCertificateList.row($(this)).data();
        if (data === undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要启用“'+data.apple_id+'”证书吗？', {
            btn: ['确定启用', '取消操作']
        }, function(){
            layer.load(2);
            SendAjax({
                'url': '/cert/basic/certificate_switch',
                'data': {
                    iss: data.iss,
                    switch: 'ENABLED'
                },
                'successCallBack': function (response) {
                    usersCertificateList.draw();
                    layer.msg(response['data']['message'], { icon: 1, time: 3000 });
                }
            });
        });
    });
    usersCertificateList.on('click', '.delete-btn', function() {
        var data = usersCertificateList.row($(this)).data();
        if (data === undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要删除“'+data.apple_id+'”开发者证书吗？', {
            btn: ['确定删除', '取消操作']
        }, function(){
            layer.load(2);
            SendAjax({
                'url': '/cert/basic/delete_certificate',
                'data': {
                    iss: data.iss
                },
                'successCallBack': function (response) {
                    usersCertificateList.row($(this)).remove().draw();
                    layer.msg(response['data']['message'], { icon: 1, time: 3000 });
                }
            });
        });
    });
    usersCertificateList.on('click', '.cert_secondary_reset-btn', function() {
        var data = usersCertificateList.row($(this)).data();
        if (data === undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.confirm('此功能为二次超开证书<br><br>二次超开理论上说明：<br>普通会员一本开发者证书最高支持开通：200*2=400 台设备<br>高级会员一本开发者证书最高支持开通：700*2=1400 台设备<br><br>如果您不懂得本操作的前提条件请您取消本次操作。', {
            btn: ['确定操作', '取消操作']
        }, function(){
            layer.load(2);
            SendAjax({
                'url': '/cert/v1/cert_secondary_reset',
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
        var data = usersCertificateList.row($(this)).data();
        if (data === undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.prompt({title: '修改备注', value: data.remark}, function(text, index){
            layer.close(index);
            layer.load(2);
            SendAjax({
                'url': '/cert/basic/remark_certificate',
                'data': {
                    iss: data.iss,
                    remark: text
                },
                'successCallBack': function (response) {
                    usersCertificateList.draw();
                    layer.msg(response['data']['message'], { icon: 1, time: 3000 });
                }
            });
        });
    });
    usersCertificateList.on('click', '.recovery-btn', function() {
        var data = usersCertificateList.row($(this)).data();
        if (data === undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.alert('请选择重构证书模式，以及了解重构证书的用途。<br><br>证书异常重构：<br>当证书状态异常且证书无法正常使用时选择此项重新生成证书<br><br>强制重构证书：<br>续费证书或您的证书需要强制重构时，此选项会删除原有证书<br><br>取消证书重构：<br>如果证书状态正常且正常使用的情况下，请选择此项取消重构', {
            btn: ['证书异常重构', '强制重构证书', '取消证书重构'],
            btnAlign: 'c',
            btn1: function() {
                layer.load(2);
                SendAjax({
                    'url': '/cert/basic/recovery_certificate',
                    'data': {
                        iss: data.iss,
                    },
                    'successCallBack': function (response) {
                        usersCertificateList.draw();
                        layer.msg(response['data']['message'], { icon: 1, time: 3000 });
                    }
                });
            },
            btn2: function() {
                layer.load(2);
                SendAjax({
                    'url': '/cert/basic/recovery_certificate',
                    'data': {
                        iss: data.iss,
                        renew: 'on'
                    },
                    'successCallBack': function (response) {
                        usersCertificateList.draw();
                        layer.msg(response['data']['message'], { icon: 1, time: 3000 });
                    }
                });
            },
            btn3: function() {
                layer.msg('已取消本次证书重构操作');
            }
        });
    });
}

if (document.getElementById('usersPublicsList')) {
        var usersPublicsList = $('#usersPublicsList').DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        buttons: DataTablesConfig.buttons,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        ajax: function(data, callback, settings) {
            SendAjax({
                'url': '/cert/v1/lists_publics',
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
                    return '<small>'+row.udid+'</small>';
                }
            },
            {
                title: '<small>设备ID</small>',
                data: 'devices_id',
                render: function(data, type, row) {
                    return '<small>'+row.devices_id+'</small>';
                }
            },
            {
                title: '<small>描述文件ID</small>',
                data: 'profiles_id',
                render: function(data, type, row) {
                    return '<small>'+row.profiles_id+'</small>';
                }
            },
            {
                title: '<small>备注信息</small>',
                data: 'remark',
                render: function(data, type, row) {
                    return '<small>'+row.remark+'</small>';
                }
            },
            {
                title: '<small>设备状态</small>',
                data: 'status',
                render: function(data, type, row) {
                    var custom = '';
                    switch(row.status) {
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
                render: function(data, type, row) {
                    return '<div class="checkcert-btn badge bg-info">点击检测</div>';
                }
            },
            {
                title: '<small>设备机型</small>',
                data: 'model',
                render: function(data, type, row) {
                    return '<small>'+row.model+'</small>';
                }
            },
            {
                title: '<small>设备系统</small>',
                data: 'platform',
                render: function(data, type, row) {
                    return '<small>'+row.platform+'</small>';
                }
            },
            {
                title: '<small>设备类型</small>',
                data: 'deviceClass',
                render: function(data, type, row) {
                    return '<small>'+row.deviceClass+'</small>';
                }
            },
            {
                title: '<small>添加日期</small>',
                data: 'adddate_at',
                render: function(data, type, row) {
                    return '<small>'+row.adddate_at+'</small>';
                }
            },
            {
                title: '<small>更新日期</small>',
                data: 'updated_at',
                render: function(data, type, row) {
                    return '<small>'+row.updated_at+'</small>';
                }
            },
            {
                title: '<small>功能操作</small>',
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    var custom = '<div class="remark-btn badge bg-warning">修改备注</div>&nbsp;';
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
        var data = usersPublicsList.row($(this)).data();
        if (data === undefined) {
            data = usersPublicsList.row($(this).closest('tr')).data();
        }
        layer.load(2);
        SendAjax({
            'url': '/cert/basic/query_publics',
            'data': {
                devices_id: data.devices_id,
                udid: data.udid
            },
            'successCallBack': function (response) {
                var DeviceStatus = '';
                switch (response.data.status) {
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
                var realTimeStatus = '';
                switch (response.data.real_time_status) {
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
                    realTimeStatus = '<a style="color: black">错误码：'+response.data.real_time_status+'</a>';
                }
                layer.alert(`
                本次检测的【设备码】：${response.data.udid}<br>
                证书所有者【开发者】：${response.data.cert_subject_info.O}<br>
                证书所属类型【苹果】：${response.data.cert_type}<br>
                证书到期时间【精准】：${response.data.cert_maturity_at}<br>
                设备当前状态【苹果】：${DeviceStatus}<br>
                证书实时状态【结果】：${realTimeStatus}<br>
                `);
            }
        });
    });
    usersPublicsList.on('click', '.remark-btn', function() {
        var data = usersPublicsList.row($(this)).data();
        if (data === undefined) {
            data = usersPublicsList.row($(this).closest('tr')).data();
        }
        layer.prompt({title: '修改备注', value: data.remark}, function(text, index){
            layer.close(index);
            layer.load(2);
            SendAjax({
                'url': '/cert/basic/remark_devices',
                'data': {
                    devices_id: data.devices_id,
                    udid: data.udid,
                    remark: text
                },
                'successCallBack': function (response) {
                    usersPublicsList.draw();
                    layer.msg(response['data']['message'], { icon: 1, time: 3000 });
                }
            });
        });
    });
    usersPublicsList.on('click', '.download-btn', function() {
        var data = usersPublicsList.row($(this)).data();
        if (data === undefined) {
            data = usersPublicsList.row($(this).closest('tr')).data();
        }
        layer.load(2);
        SendAjax({
            'url': '/cert/basic/query_publics',
            'data': {
                devices_id: data.devices_id,
                udid: data.udid
            },
            'successCallBack': function (response) {
                var p12_pass = response.data.p12_pass;
                var p12_data = response.data.p12_data;
                var profile_data = response.data.profile_data;
                if (p12_data === '' || p12_data === null) {
                    layer.msg('P12证书数据为空，请检查证书', { icon: 2, time: 5000 });
                    return false;
                } else
                if (profile_data === '' || profile_data === null) {
                    layer.msg('描述文件未创建，请先创建描述文件', { icon: 2, time: 5000 });
                    return false;
                }
                var p12_pass_file = new File([p12_pass], '证书密码.txt', { type: 'text/plain' });
                var p12_data_binary = atob(response.data.p12_data);
                var p12_data_array = new Uint8Array(p12_data_binary.length);
                for (var i = 0; i < p12_data_binary.length; i++) {
                    p12_data_array[i] = p12_data_binary.charCodeAt(i);
                }
                var p12_data_blob = new Blob([p12_data_array], { type: 'application/x-pkcs12' });
                var p12_data_file = new File([p12_data_blob], data.udid+'.p12');
                var profile_data_binary = atob(response.data.profile_data);
                var profile_data_array = new Uint8Array(profile_data_binary.length);
                for (var i = 0; i < profile_data_binary.length; i++) {
                    profile_data_array[i] = profile_data_binary.charCodeAt(i);
                }
                var profile_data_blob = new Blob([profile_data_array], { type: 'application/octet-stream' });
                var profile_data_file = new File([profile_data_blob], data.udid+'.mobileprovision');
                var zip = new JSZip();
                zip.file(p12_pass_file.name, p12_pass_file);
                zip.file(p12_data_file.name, p12_data_file);
                zip.file(profile_data_file.name, profile_data_file);
                zip.generateAsync({ type: 'blob' })
                .then(function (content) {
                    saveAs(content, '苹果开发者证书_'+data.udid+'.zip');
                });
            }
        });
    });
    usersPublicsList.on('click', '.disabled-btn', function() {
        var data = usersPublicsList.row($(this)).data();
        if (data === undefined) {
            data = usersPublicsList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要禁用“'+data.udid+'”设备吗？', {
            btn: ['确定禁用', '取消操作']
        }, function(){
            layer.load(2);
            SendAjax({
                'url': '/cert/basic/delete_publics',
                'data': {
                    devices_id: data.devices_id,
                    udid: data.udid
                },
                'successCallBack': function (response) {
                    usersPublicsList.draw();
                    layer.msg(response['data']['message'], { icon: 1, time: 3000 });
                }
            });
        });
    });
    usersPublicsList.on('click', '.enabled-btn', function() {
        var data = usersPublicsList.row($(this)).data();
        if (data === undefined) {
            data = usersPublicsList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要启用“'+data.udid+'”设备吗？', {
            btn: ['确定启用', '取消操作']
        }, function(){
            layer.load(2);
            SendAjax({
                'url': '/cert/basic/recovery_publics',
                'data': {
                    devices_id: data.devices_id,
                    udid: data.udid
                },
                'successCallBack': function (response) {
                    usersPublicsList.draw();
                    layer.msg(response['data']['message'], { icon: 1, time: 3000 });
                }
            });
        });
    });
}

if (document.getElementById('usersDevicesList')) {
    var usersDevicesList = $('#usersDevicesList').DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        buttons: DataTablesConfig.buttons,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        ajax: function(data, callback, settings) {
            SendAjax({
                'url': '/cert/v1/lists_devices',
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
        order: [[ 10, 'desc' ]],
        columns: [
            {
                title: '<small>设备码</small>',
                data: 'udid',
                render: function(data, type, row) {
                    return '<small>'+row.udid+'</small>';
                }
            },
            {
                title: '<small>实时状态</small>',
                data: 'status',
                render: function(data, type, row) {
                    return '<div class="checkcert-btn badge bg-info">点击检测</div>';
                }
            },
            {
                title: '<small>设备状态</small>',
                data: 'status',
                render: function(data, type, row) {
                    var custom = '';
                    switch(row.status) {
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
                title: '<small>设备ID</small>',
                data: 'devices_id',
                render: function(data, type, row) {
                    return '<small>'+row.devices_id+'</small>';
                }
            },
            {
                title: '<small>描述文件ID</small>',
                data: 'profiles_id',
                render: function(data, type, row) {
                    return '<small>'+row.profiles_id+'</small>';
                }
            },
            {
                title: '<small>Issuer ID</small>',
                data: 'iss',
                render: function(data, type, row) {
                    return '<small>'+row.iss+'</small>';
                }
            },
            {
                title: '<small>设备机型</small>',
                data: 'model',
                render: function(data, type, row) {
                    return '<small>'+row.model+'</small>';
                }
            },
            {
                title: '<small>设备系统</small>',
                data: 'platform',
                render: function(data, type, row) {
                    return '<small>'+row.platform+'</small>';
                }
            },
            {
                title: '<small>设备类型</small>',
                data: 'deviceClass',
                render: function(data, type, row) {
                    return '<small>'+row.deviceClass+'</small>';
                }
            },
            {
                title: '<small>备注信息</small>',
                data: 'remark',
                render: function(data, type, row) {
                    return '<small>'+row.remark+'</small>';
                }
            },
            {
                title: '<small>添加日期</small>',
                data: 'adddate_at',
                render: function(data, type, row) {
                    return '<small>'+row.adddate_at+'</small>';
                }
            },
            {
                title: '<small>更新日期</small>',
                data: 'updated_at',
                render: function(data, type, row) {
                    return '<small>'+row.updated_at+'</small>';
                }
            },
            {
                title: '<small>功能操作</small>',
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    var custom = '<div class="remark-btn badge bg-warning">修改备注</div>&nbsp;';
                    switch(row.status) {
                        case 'ENABLED':
                            custom += '<div class="create-btn badge bg-primary">创建描述文件</div>&nbsp;<div class="disabled-btn badge bg-danger">禁用设备</div>&nbsp;<div class="download-btn badge bg-success">下载证书</div>';
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
    usersDevicesList.on('click', '.checkcert-btn', function() {
        var data = usersDevicesList.row($(this)).data();
        if (data === undefined) {
            data = usersDevicesList.row($(this).closest('tr')).data();
        }
        layer.load(2);
        SendAjax({
            'url': '/cert/basic/query_devices',
            'data': {
                iss: data.iss,
                udid: data.udid
            },
            'successCallBack': function (response) {
                var DeviceStatus = '';
                switch (response.data.status) {
                    case 'ENABLED':
                        DeviceStatus = '<a style="color: green">设备可用</a>';
                    break;
                    case 'DISABLED':
                        DeviceStatus = '<a style="color: red">设备禁用</a>';
                    break;
                    case 'PROCESSING':
                        DeviceStatus = '<a style="color: red">苹果审核</a>';
                    break;
                    case 'INELIGIBLE':
                        DeviceStatus = '<a style="color: red">不合格的</a>';
                    break;
                }
                var realTimeStatus = '';
                switch (response.data.real_time_status) {
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
                    realTimeStatus = '<a style="color: black">错误码：'+response.data.real_time_status+'</a>';
                }
                layer.alert(`
                本次检测的【设备码】：${response.data.udid}<br>
                证书所有者【开发者】：${response.data.cert_subject_info.O}<br>
                证书所属类型【苹果】：${response.data.cert_type}<br>
                证书到期时间【精准】：${response.data.cert_maturity_at}<br>
                设备当前状态【苹果】：${DeviceStatus}<br>
                证书实时状态【结果】：${realTimeStatus}<br>
                `);
            }
        });
    });
    usersDevicesList.on('click', '.remark-btn', function() {
        var data = usersDevicesList.row($(this)).data();
        if (data === undefined) {
            data = usersDevicesList.row($(this).closest('tr')).data();
        }
        layer.prompt({title: '修改备注', value: data.remark}, function(text, index){
            layer.close(index);
            layer.load(2);
            SendAjax({
                'url': '/cert/basic/remark_devices',
                'data': {
                    iss: data.iss,
                    udid: data.udid,
                    remark: text
                },
                'successCallBack': function (response) {
                    usersDevicesList.draw();
                    layer.msg(response['data']['message'], { icon: 1, time: 3000 });
                }
            });
        });
    });
    usersDevicesList.on('click', '.create-btn', function() {
        var data = usersDevicesList.row($(this)).data();
        if (data === undefined) {
            data = usersDevicesList.row($(this).closest('tr')).data();
        }
        layer.load(2);
        SendAjax({
            'url': '/cert/basic/create_profiles',
            'data': {
                iss: data.iss,
                udid: data.udid
            },
            'successCallBack': function (response) {
                layer.msg(response['data']['message'], { icon: 1, time: 3000 });
            }
        });
    });
    usersDevicesList.on('click', '.download-btn', function() {
    var data = usersDevicesList.row($(this)).data();
        if (data === undefined) {
            data = usersDevicesList.row($(this).closest('tr')).data();
        }
        layer.load(2);
        SendAjax({
            'url': '/cert/basic/query_devices',
            'data': {
                iss: data.iss,
                udid: data.udid
            },
            'successCallBack': function (response) {
                var p12_pass = response.data.p12_pass;
                var p12_data = response.data.p12_data;
                var profile_data = response.data.profile_data;
                if (p12_data === '' || p12_data === null) {
                    layer.msg('P12证书数据为空，请检查证书', { icon: 2, time: 5000 });
                    return false;
                } else
                if (profile_data === '' || profile_data === null) {
                    layer.msg('描述文件未创建，请先创建描述文件', { icon: 2, time: 5000 });
                    return false;
                }
                var p12_pass_file = new File([p12_pass], '证书密码.txt', { type: 'text/plain' });
                var p12_data_binary = atob(response.data.p12_data);
                var p12_data_array = new Uint8Array(p12_data_binary.length);
                for (var i = 0; i < p12_data_binary.length; i++) {
                    p12_data_array[i] = p12_data_binary.charCodeAt(i);
                }
                var p12_data_blob = new Blob([p12_data_array], { type: 'application/x-pkcs12' });
                var p12_data_file = new File([p12_data_blob], data.udid+'.p12');
                var profile_data_binary = atob(response.data.profile_data);
                var profile_data_array = new Uint8Array(profile_data_binary.length);
                for (var i = 0; i < profile_data_binary.length; i++) {
                    profile_data_array[i] = profile_data_binary.charCodeAt(i);
                }
                var profile_data_blob = new Blob([profile_data_array], { type: 'application/octet-stream' });
                var profile_data_file = new File([profile_data_blob], data.udid+'.mobileprovision');
                var zip = new JSZip();
                zip.file(p12_pass_file.name, p12_pass_file);
                zip.file(p12_data_file.name, p12_data_file);
                zip.file(profile_data_file.name, profile_data_file);
                zip.generateAsync({ type: 'blob' })
                .then(function (content) {
                    saveAs(content, '苹果开发者证书_'+data.udid+'.zip');
                });
            }
        });
    });
    usersDevicesList.on('click', '.disabled-btn', function() {
        var data = usersDevicesList.row($(this)).data();
        if (data === undefined) {
            data = usersDevicesList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要禁用“'+data.udid+'”设备吗？', {
            btn: ['确定禁用', '取消操作']
        }, function(){
            layer.load(2);
            SendAjax({
                'url': '/cert/basic/delete_devices',
                'data': {
                    iss: data.iss,
                    udid: data.udid
                },
                'successCallBack': function (response) {
                    usersDevicesList.draw();
                    layer.msg(response['data']['message'], { icon: 1, time: 3000 });
                }
            });
        });
    });
    usersDevicesList.on('click', '.enabled-btn', function() {
        var data = usersDevicesList.row($(this)).data();
        if (data === undefined) {
            data = usersDevicesList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要启用“'+data.udid+'”设备吗？', {
            btn: ['确定启用', '取消操作']
        }, function(){
            layer.load(2);
            SendAjax({
                'url': '/cert/basic/recovery_devices',
                'data': {
                    iss: data.iss,
                    udid: data.udid
                },
                'successCallBack': function (response) {
                    usersDevicesList.draw();
                    layer.msg(response['data']['message'], { icon: 1, time: 3000 });
                }
            });
        });
    });
}