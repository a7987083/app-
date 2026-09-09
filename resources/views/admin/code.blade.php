@extends(config('api.admin.path', 'admin').'.layouts.master')
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
                    text: '清空全部用户所有卡密',
                    action: function () {
                        layer.confirm('您将清空全部用户所有的卡密，并且重置数据库递增？', {
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
                    text: '清空全部用户已用卡密',
                    action: function () {
                        layer.confirm('您将清空全部用户已用的卡密？', {
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
                    text: '清空全部用户未用卡密',
                    action: function () {
                        layer.confirm('您将清空全部用户未用的卡密？', {
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
                },
                {
                    text: '清空指定代理所有卡密',
                    action: function () {
                        layer.prompt({title: '请输入代理ID'}, function(value, index, elem){
                            if(value === '') return elem.focus();
                            layer.load(2);
                            SendAjax({
                                url: systemPath+'/code/delete',
                                data: {
                                    type: 'agent-all',
                                    id: value
                                },
                                type: 'delete',
                                'successCallBack': function (response) {
                                    DataTable.DataTable().row($(this)).remove().draw();
                                    layer.msg(response['message'], { icon: 1, time: 3000 });
                                }
                            });
                            layer.close(index);
                        });
                    }
                },
                {
                    text: '清空指定代理已用卡密',
                    action: function () {
                        layer.prompt({title: '请输入代理ID'}, function(value, index, elem){
                            if(value === '') return elem.focus();
                            layer.load(2);
                            SendAjax({
                                url: systemPath+'/code/delete',
                                data: {
                                    type: 'agent-used',
                                    id: value
                                },
                                type: 'delete',
                                'successCallBack': function (response) {
                                    DataTable.DataTable().row($(this)).remove().draw();
                                    layer.msg(response['message'], { icon: 1, time: 3000 });
                                }
                            });
                            layer.close(index);
                        });
                    }
                },
                {
                    text: '清空指定代理未用卡密',
                    action: function () {
                        layer.prompt({title: '请输入代理ID'}, function(value, index, elem){
                            if(value === '') return elem.focus();
                            layer.load(2);
                            SendAjax({
                                url: systemPath+'/code/delete',
                                data: {
                                    type: 'agent-not-used',
                                    id: value
                                },
                                type: 'delete',
                                'successCallBack': function (response) {
                                    DataTable.DataTable().row($(this)).remove().draw();
                                    layer.msg(response['message'], { icon: 1, time: 3000 });
                                }
                            });
                            layer.close(index);
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
            order: [[ 11, 'desc' ]],
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
                    title: '<small>代理 ID</small>',
                    data: 'agent_id',
                    render: function(data, type, row) {
                        if (row['agent_id']) {
                            return '<small>' + row['agent_id'] + '</small>';
                        } else {
                            return '<small></small>';
                        }
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
                    <div class="blacklist-btn badge bg-dark">拉黑操作</div>
                    <div class="upload-cert-btn badge bg-primary">上传证书</div>
                    ${ (row['udid']) ? '<div class="inspection-cert-btn badge bg-success">实时状态</div>' : '' }
                    ${ (row['udid']) ? '<div class="update-cert-btn badge bg-warning">删除本地证书</div>' : '' }
                    `;
                    }
                },
            ]
        });
        DataTable.on('click', '.update-cert-btn', function() {
            let table = DataTable.DataTable();
            let data = table.row($(this)).data();
            if (data === undefined) {
                data = table.row($(this).closest('tr')).data();
            }
            layer.confirm(`您确定要删除 “${data['udid']}” 的本地证书文件吗？<br>
            <br>本次操作仅删除本系统下的证书文件，让系统可以重新获取最新的证书相关文件。<br>
            <br>如果您想删除后想快速帮用户拉取最新证书相关文件，可以删除后点击“实时状态”。
            `, {
                btn: ['确定', '取消']
            }, function() {
                layer.load(2);
                SendAjax({
                    url: systemPath+'/code/update',
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
        DataTable.on('click', '.blacklist-btn', function() {
            let table = DataTable.DataTable();
            let data = table.row($(this)).data();
            if (data === undefined) {
                data = table.row($(this).closest('tr')).data();
            }
            layer.alert(`请选择要操作的类型与目标
        <br><br>当前兑换码：
        <br>${ data['code'] }
        <br><br>当前设备码：
        <br>${ (data['udid']) ? data['udid'] : '未绑定' }`, {
                btn: ['拉黑兑换码', '拉黑设备码', '取消操作'],
                btnAlign: 'c',
                btn1: function() {
                    layer.prompt({title: '请输入拉黑原因'}, function(text, index) {
                        layer.close(index);
                        layer.load(2);
                        SendAjax({
                            url: systemPath+'/blacklist/create',
                            data: {
                                type: 'CODE',
                                value: data['code'],
                                reason: text
                            },
                            'successCallBack': function (response) {
                                layer.confirm('添加或修改黑名单成功<br><br>本次操作拉黑兑换码：<br>'+response['data']['value']+'<br><br>本次操作拉黑的原因：<br>'+response['data']['reason']);
                            }
                        });
                    });
                },
                btn2: function() {
                    if (data['udid']) {
                        layer.prompt({title: '请输入拉黑原因'}, function(text, index) {
                            layer.close(index);
                            layer.load(2);
                            SendAjax({
                                url: systemPath+'/blacklist/create',
                                data: {
                                    type: 'UDID',
                                    value: data['udid'],
                                    reason: text
                                },
                                'successCallBack': function (response) {
                                    layer.confirm('添加或修改黑名单成功<br><br>本次操作拉黑设备码：<br>'+response['data']['value']+'<br><br>本次操作拉黑的原因：<br>'+response['data']['reason']);
                                }
                            });
                        });
                    } else {
                        layer.msg('当前设备码为空，禁止拉黑操作');
                    }
                },
                btn3: function() {
                    layer.msg('已取消操作');
                }
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
                },
                'errorCallBack': function (error) {
                    if (error.status === 400) {
                        switch (error['responseJSON']['data']['status']) {
                            case 'REVOKED':
                                layer.alert(`
                                    检测的卡密：<br>${data['code']}<br><br>
                                    绑定设备码：<br>${data['udid']}<br><br>
                                    无法检测原因：<br><a style="color: red">当前设备证书检测后结果判断为撤销状态</a><br>
                                `);
                                return false;
                            case 'DISABLED':
                                layer.alert(`
                                    检测的卡密：<br>${data['code']}<br><br>
                                    绑定设备码：<br>${data['udid']}<br><br>
                                    无法检测原因：<br><a style="color: red">当前设备证书已被管理员禁止使用，详情请咨询管理员</a><br>
                                `);
                                return false;
                            case 'PROCESSING':
                                layer.alert(`
                                    检测的卡密：<br>${data['code']}<br><br>
                                    绑定设备码：<br>${data['udid']}<br><br>
                                    无法检测原因：<br><a style="color: red">当前设备证书苹果官方识别为审核中，预计：24 至 72 小时内通过审核</a><br>
                                `);
                                return false;
                            case 'INELIGIBLE':
                                layer.alert(`
                                    检测的卡密：<br>${data['code']}<br><br>
                                    绑定设备码：<br>${data['udid']}<br><br>
                                    无法检测原因：<br><a style="color: red">当前设备证书苹果官方识别为不合格，预计：30 天内通过审核</a><br>
                                `);
                                return false;
                            case 'NO-EXISTENCE':
                                layer.alert(`
                                    检测的卡密：<br>${data['code']}<br><br>
                                    绑定设备码：<br>${data['udid']}<br><br>
                                    无法检测原因：<br><a style="color: red">当前设备在签名系统以及证书系统未能查询到此设备、请您手动添加或上传证书</a><br>
                                `);
                                return false;
                        }
                    }
                    layer.msg(error['responseJSON']['message'], {icon: 5, time: 3000});
                }
            });
        });
        DataTable.on('click', '.upload-cert-btn', function() {
            let table = DataTable.DataTable();
            let data = table.row($(this)).data();
            if (data === undefined) {
                data = table.row($(this).closest('tr')).data();
            }
            if (data['udid']) {
                layer.confirm('上传证书将覆盖当前证书，你是否还要继续操作？', {
                    btn: ['确定', '取消']
                }, function() {
                    layer.open({
                        type: 1,
                        anim: 'slideDown',
                        title: '上传开发者证书',
                        content: `
                    <div style="padding: 16px;">
                        <small>
                            当前卡密：${data['code']}<br>
                            当前设备码：${data['udid']}<br>
                            卡密售后天数：${data['after_sale_day']} 天<br>
                            卡密售后次数：${data['after_sale_num']} 次<br>
                            卡密已用售后：${data['use_after_sale']} 次<br><br>
                        </small>
                        <hr>
                        <form class="layui-form" id="uploadCert">
                            <input type="hidden" name="udid" value="${data['udid']}" lay-verify="required" class="layui-input">
                            <textarea name="p12" placeholder="点击上传 p12 证书文件" class="layui-textarea" lay-verify="p12" readonly></textarea>
                            <hr class="ws-space-16">
                            <textarea name="mobileprovision" placeholder="点击上传 mobileprovision 描述文件" class="layui-textarea" lay-verify="mobileprovision" readonly></textarea>
                            <hr class="ws-space-16">
                            <input type="text" name="password" placeholder="在此输入证书密码" lay-verify="password" class="layui-input">
                            <hr class="ws-space-16">
                            <button class="layui-btn layui-btn-fluid" lay-submit lay-filter="uploadCert">上传</button>
                        </form>
                    </div>
                    `,
                        success: function(){
                            layui.form.render();
                            $('textarea[name="p12"]').click(function() {
                                const p12 = document.createElement('input');
                                p12['type'] = 'file';
                                p12['accept'] = 'application/x-pkcs12';
                                $('body').append(p12);
                                let p12_data = '';
                                p12.onchange = (event) => {
                                    const p12File = event['target']['files'][0];
                                    if (p12File) {
                                        try {
                                            if (p12File.name.endsWith('.p12')) {
                                                let reader = new FileReader();
                                                reader.onload = function(e) {
                                                    p12_data = e['target']['result'].replace(/:(.*?);/, ':application/x-pkcs12;');
                                                    const Textarea = $('#uploadCert textarea[name="p12"]');
                                                    if (Textarea) {
                                                        Textarea.val(p12_data.split(',')[1]);
                                                    }
                                                };
                                                reader.readAsDataURL(p12File);
                                            } else {
                                                layer.msg(`${p12File['name']} 不是证书文件`);
                                            }
                                        } catch (error) {
                                            layer.msg('解析证书文件数据时发生错误');
                                        } finally {
                                            if (p12['parentNode']) {
                                                p12['parentNode'].removeChild(p12);
                                            }
                                        }
                                    }
                                };
                                p12.click();
                            });
                            $('textarea[name="mobileprovision"]').click(function() {
                                const mobileprovision = document.createElement('input');
                                mobileprovision.type = 'file';
                                mobileprovision.accept = 'application/x-apple-aspen-mobileprovision';
                                $('body').append(mobileprovision);
                                let mobileprovision_data = '';
                                mobileprovision.onchange = (event) => {
                                    const mobileprovisionFile = event['target']['files'][0];
                                    if (mobileprovisionFile) {
                                        try {
                                            if (mobileprovisionFile.name.endsWith('.mobileprovision')) {
                                                let reader = new FileReader();
                                                reader.onload = function(e) {
                                                    mobileprovision_data = e['target']['result'].replace(/:(.*?);/, ':application/x-apple-aspen-mobileprovision;');
                                                    const Textarea = $('#uploadCert textarea[name="mobileprovision"]');
                                                    if (Textarea) {
                                                        Textarea.val(mobileprovision_data.split(',')[1]);
                                                    }
                                                };
                                                reader.readAsDataURL(mobileprovisionFile);
                                            } else {
                                                layer.msg(`${mobileprovisionFile['name']} 不是证书文件`);
                                            }
                                        } catch (error) {
                                            layer.msg('解析证书文件数据时发生错误');
                                        } finally {
                                            if (mobileprovision['parentNode']) {
                                                mobileprovision['parentNode'].removeChild(mobileprovision);
                                            }
                                        }
                                    }
                                };
                                mobileprovision.click();
                            });
                            layui.form.verify({
                                p12: function(value) {
                                    if (!value) {
                                        return '请先上传 p12 证书文件';
                                    }
                                },
                                mobileprovision: function(value) {
                                    if (!value) {
                                        return '请先上传 mobileprovision 描述文件';
                                    }
                                },
                                password: function(value) {
                                    if (!value) {
                                        return '证书密码不能为空';
                                    }
                                },
                            });
                            layui.form.on('submit(uploadCert)', function(formData) {
                                layer.load(2);
                                SendAjax({
                                    'url': systemPath+'/developer/update',
                                    'data': {
                                        p12: formData['field']['p12'],
                                        mobileprovision: formData['field']['mobileprovision'],
                                        password: formData['field']['password'],
                                        udid: formData['field']['udid'],
                                    },
                                    'successCallBack': function () {
                                        layer.msg('上传新开发者证书成功', { icon: 1, time: 3000 });
                                    }
                                });
                                return false;
                            });
                        }
                    });
                }, function() {
                    layer.msg('已取消操作');
                });
            } else {
                layer.msg('当前设备码为空，禁止上传证书操作');
            }
        });
    </script>
@endsection