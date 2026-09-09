if (localStorage.getItem('clause') != 'ConsentClause') {
    layer.open({
        anim: 'slideDown',
        area: ['90%', '70%'],
        title: ['服务条款与免责协议', 'font-size: 18px;'],
        closeBtn: 0,
        content: `
        <b>甲方（提供方）：极速网络（极速网络 Apple 签名系统 V2 开发者）</b>
        <br>
        <b>乙方（使用方）：当前站点所有者（${window.location.hostname}）</b>
        <br>
        <b>甲乙双方在平等、自愿、诚信的基础上，经友好协商，就甲方为乙方提供互联网技术账号服务的相关事宜，为确保服务的合法合规使用，明确双方权利与义务，特订立本协议，双方共同遵守：</b>
        <br><hr>
        <b>1. 服务描述与责任限制</b>
        <br><br>1.1 开发声明：极速网络 Apple 签名系统 V2 的开发目的仅限于公司内部应用、本地环境和测试分发。任何对外开放的使用需由乙方自行承担法律责任，并且甲方不对此负责。
        <br><br>1.2 服务描述：本协议适用于服务方（以下简称“甲方”）向乙方提供 极速网络 Apple 签名系统 V2 的使用权销售服务，包括软件许可、技术支持等。
        <br><br>1.3 使用者责任：乙方使用本系统上传应用程序时，应自行承担所有法律责任（确保您上传的应用得到了版权方的授权）。甲方不对乙方上传的应用内容承担任何法律责任。
        <br><br>1.4 使用权终止：甲方保留发现乙方使用本系统违反法律或侵犯第三方权益的权利。一经发现，甲方有权立即停止乙方的使用权，并不退还任何费用。
        <br><br>1.5 合法合规使用：乙方明确知晓并同意，本系统应用于合法合规的应用分发、测试与发布流程中，不得从事任何违反法律法规、侵害第三方权益的行为，包括但不限于开发含有恶意代码、侵犯版权或隐私的应用程序。
        <br><br>1.6 禁止行为：乙方不得利用甲方提供的服务进行任何违法活动，包括但不限于分发非法软件、进行网络攻击、侵犯用户隐私等。同时，乙方不得将系统使用权转租、转售给任何第三方，或以任何形式泄露给未经授权的个人或实体。
        <br><br>1.7 合规性承诺：乙方在使用服务过程中，应当遵守所有适用的国际、国内法律法规，以及苹果公司关于开发者账号使用的规定和指南，确保所有开发及分发活动的合规性。乙方应主动了解并适应相关法律法规的变化，确保自身的使用行为始终符合最新要求。
        <br><hr>
        <br><br><b>2. 费用与支付条款</b>
        <br><br>2.1 费用：乙方应按照约定支付 极速网络 Apple 签名系统 V2 的使用权费用，具体金额和支付方式由双方协商确定。
        <br><br>2.2 支付：乙方应按时支付费用。如未能按时支付，甲方有权暂停或终止服务，并保留法律追索权利。
        <br><hr>
        <br><br><b>3. 免责声明</b>
        <br><br>3.1 免责条款：除非另有明确约定，甲方不承担以下责任：
        <br><br>（a）因乙方使用本系统导致的任何直接、间接、特别或后果性损失；
        <br><br>（b）由于第三方软件或服务（包括但不限于 Apple 的政策变更、iOS 系统更新等）引起的系统不兼容或功能失效；
        <br><br>（c）乙方操作失误或未经授权使用签名系统而导致的损失或责任；
        <br><br>（d）因不可抗力或其他无法预见、避免并且不能克服的事件而造成的损失。
        <br><br>3.2 知识产权：极速网络 Apple 签名系统 V2 的知识产权归属于甲方所有，乙方仅获得有限的非独占使用许可。
        <br><hr>
        <br><br><b>4. 数据保护和隐私</b>
        <br><br>4.1 数据保护：双方应遵守中华人民共和国的相关数据保护法律法规，对用户数据进行合法、安全的处理和保护。
        <br><br>4.2 隐私：乙方应提供符合法律要求的隐私政策，并确保其合法性和适用性。
        <br><br>4.3 监管：乙方应对本系统的数据上传、分发、下载、存储等活动承担监管责任。甲方不对乙方行为承担任何法律责任。
        <br><hr>
        <br><br><b>5. 终止协议</b>
        <br><br>5.1 终止条件：双方有权在以下情形终止本协议：
        <br><br>（a）一方严重违反协议条款且在接到通知后未能在合理期限内纠正；
        <br><br>（b）乙方未能按时支付费用，且在甲方发出催告后仍未履行支付义务。
        <br><hr>
        <br><br><b>6. 法律管辖和争议解决</b>
        <br><br>6.1 法律管辖：本协议适用中华人民共和国法律。
        <br><br>6.2 争议解决：如发生争议，双方应通过友好协商解决。协商不成时，应提交至甲方所在地人民法院诉讼解决。
        <br><hr>
        <br><br><b>7. 其他条款</b>
        <br><br>7.1 修改条款：本协议条款如有变更，双方应书面确认后生效。
        <br><br>7.2 完整协议：本协议构成双方之间的完整协议，取代一切先前口头或书面约定。
        <br><br>7.3 如果乙方使用技术手段删除或隐藏本协议弹框、甲方一律视为同意以上所有内容、并且甲方一旦发现有权利停止该乙方的使用权。
        `,
        btn: ['我已阅读并同意遵守以上条款', '暂不同意使用本系统'],
        btn1: function(index, layero, that) {
            localStorage.setItem('clause', 'ConsentClause');
            layer.close(index);
        },
        btn2: function(index, layero, that) {
            event.preventDefault();
            document.getElementById('logout-form').submit();
        }
    });
}

function update() {
    layer.load(2);
    sendAjax({
        url: '/update',
        'successCallBack': function (response) {
            layer.msg(response['data']['data'].message, { icon: 1});
        }
    });
}

function StoreKey() {
    return window.location.hostname;
}

function processCreditCard(input) {
    let cleanedInput = input.replace(/\s+/g, '');
    let numberValue = parseFloat(cleanedInput);
    if (isNaN(numberValue)) {
        return 0.00;
    }
    let formattedValue = numberValue.toFixed(2);
    return formattedValue;
}

function change_password() {
    layer.prompt({ title: '设置新密码、修改成功会注销本次登录' }, function(pass, index) {
        layer.close(index);
        layer.load(2);
        sendAjax({
            url: '/password',
            type: 'put',
            data: {
                password: pass
            },
            'successCallBack': function (response) {
                window.location.href = '/login';
            }
        });
    });
}
var DataTablesConfig = {
    dom: '<"row"<"col-sm-12 col-md-6"B>><"row"<"col-sm-12"tr>>"<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
    language: {
        url: '/theme/bootstrap-v5.2.1/languages/' + document.documentElement.lang + '.json'
    },
    buttons: [
        {
            text: '更多功能',
            extend: 'collection',
            buttons: ['copy', 'excel', 'pdf', 'csv', 'print']
        },
        {
            text: '页面数',
            action: function(e, dt) {
                PromptAndAct('设置页面数量', layui.sessionData(StoreKey())['设置页面数量'], function(pageSize) {
                    dt.page.len(pageSize).draw();
                });
            }
        },
        {
            text: '搜索',
            action: function(e, dt) {
                PromptAndAct('搜索内容', layui.sessionData(StoreKey())['搜索内容'], function(search) {
                    dt.search(search).draw();
                });
            }
        },
        'colvis'
    ],
    processing: true,
    serverSide: true,
    pagingType: 'numbers',
};

function PromptAndAct(title, value, action) {
    layer.prompt({ title: title, value: value }, function(data, index) {
        layer.close(index);
        layui.sessionData(window.location.hostname, { key: title, value: data });
        action(data);
    });
}

function GetChartColorsArray(chartId) {
    if (document.getElementById(chartId) !== null) {
        var colors = document.getElementById(chartId).getAttribute('data-colors');
        if (colors) {
            colors = JSON.parse(colors);
            return colors.map(function (value) {
                var newValue = value.replace(' ', '');
                if (newValue.indexOf(',') === -1) {
                    var color = getComputedStyle(document.documentElement).getPropertyValue(newValue);
                    if (color) return color;else return newValue;
                } else {
                    var val = value.split(',');
                    if (val.length == 2) {
                        var rgbaColor = getComputedStyle(document.documentElement).getPropertyValue(val[0]);
                        rgbaColor = 'rgba(' + rgbaColor + ',' + val[1] + ')';
                        return rgbaColor;
                    } else {
                        return newValue;
                    }
                }
            });
        }
    }
}

function RedirectTo(href, newWindow = false) {
    if (newWindow == true) {
        window.open(href, '_blank');
    } else {
        window.location.href = href;
    }
}
function repairFunc() {
    layer.open({
        anim: 'slideDown',
        area: ['90%', '70%'],
        title: ['修复异常', 'font-size: 18px;'],
        closeBtn: 0,
        content: `
        <b>请根据出现的问题选择修复</b>
        <br><hr>
        <b>签名程序异常</b>
        <br><br>当签名任务异常时可通过此项进行自动修复或检测引导修复
        <br><hr>
        <b>前端图标异常</b>
        <br><br>当前端图标404丢失时可通过此项进行自动修复或检测引导修复
        <br><hr>
        <b>缓存数据异常</b>
        <br><br>当配置文件没能及时生效时可通过此项进行自动修复或检测引导修复
        <br><hr>
        <b>跨版本更新数据库</b>
        <br><br>当您手动替换更新或者跨版本更新出现数据库异常时可通过此项进行自动修复或检测引导修复
        <br><hr>
        `,
        btn: ['签名程序异常', '前端图标异常', '缓存数据异常', '跨版本更新数据库'],
        btn1: function() {
            layer.load(2);
            sendAjax({
                url: '/repair/sign',
                'successCallBack': function (response) {
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        },
        btn2: function() {
            layer.load(2);
            sendAjax({
                url: '/repair/link',
                'successCallBack': function (response) {
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        },
        btn3: function() {
            layer.load(2);
            sendAjax({
                url: '/repair/cache',
                'successCallBack': function (response) {
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        },
        btn4: function() {
            layer.load(2);
            sendAjax({
                url: '/repair/datatable',
                'successCallBack': function (response) {
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        }
    });
}

function Monitoring() {
    sendAjax({
        url: '/detection',
        type: 'post',
        'successCallBack': function (response) {
            $('#cpu').html(response['data']['data'].cpu);
            $('#mem').html(response['data']['data'].mem);
            $('#disk').html(response['data']['data'].disk);
            $('#disk_mount').html(response['data']['data'].disk_mount);
            $('#cpu_info').html(response['data']['data'].cpu_info);
            $('#mem_info').html(response['data']['data'].mem_info);
            $('#disk_info').html(response['data']['data'].disk_info);
            $('#disk_mount_info').html(response['data']['data'].disk_mount_info);
            $('#cpu_used').val(response['data']['data'].cpu_used).trigger('change');
            $('#mem_used').val(response['data']['data'].mem_used).trigger('change');
            $('#disk_used').val(response['data']['data'].disk_used).trigger('change');
            $('#disk_mount_used').val(response['data']['data'].disk_mount_used).trigger('change');
            $('#sign_total').html(response['data']['data'].sign_total);
            $('#sign_pending_total').html(response['data']['data'].sign_pending_total);
            $('#sign_completed_total').html(response['data']['data'].sign_completed_total);
            $('#sign_failed_total').html(response['data']['data'].sign_failed_total);
            setTimeout(Monitoring, 2000);
            return false;
        }
    });
}

if (document.getElementById('signTableList')) {
    Monitoring();
    var signTableList = {
        extend: 'collection',
        text: '更多操作',
        buttons: [
            { text: '清空所有', action: function (e, dt, node, config) {
                layer.confirm('您将清空所有签名，并且重置数据库递增？此操作会导致正在签名的任务异常停止，并所有已签名的安装包都将被清理！', {
                    btn: ['确定清空', '取消']
                }, function() {
                    layer.load(2);
                    sendAjax({
                        url: location.href,
                        data: {
                            type: 'all'
                        },
                        type: 'delete',
                        'successCallBack': function (response) {
                            $('#signTableList').DataTable().row($(this)).remove().draw();
                                layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
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
    $('#signTableList').DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        buttons: DataTablesConfig.buttons,
        ajax: function(data, callback, settings) {
            sendAjax({
                url: location.href,
                data: data,
                successCallBack: function(response) {
                    const responseJSON = {};
                    responseJSON.draw = response['data']['data'].draw;
                    responseJSON.recordsTotal = response['data']['data'].recordsTotal;
                    responseJSON.recordsFiltered = response['data']['data'].recordsFiltered;
                    responseJSON.data = response['data']['data'].data;
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
                    return '<small>'+row.id+'</small>';
                }
            },
            {
                title: '<small>状态</small>',
                data: 'status',
                render: function(data, type, row) {
                    switch (row.status) {
                        case 'pending':
                            return '<div class="badge bg-warning">pending</div>';
                        break;
                        case 'failed':
                            return '<div class="badge bg-danger">failed</div>';
                        break;
                        case 'completed':
                            return '<div class="badge bg-success">completed</div>';
                        break;
                    }
                }
            },
            {
                title: '<small>UDID</small>',
                data: 'udid',
                render: function(data, type, row) {
                    return '<small>'+row.udid+'</small>';
                }
            },
            {
                title: '<small>证书ID</small>',
                data: 'cert_id',
                render: function(data, type, row) {
                    return '<small>'+row.cert_id+'</small>';
                }
            },
            {
                title: '<small>应用ID</small>',
                data: 'app_id',
                render: function(data, type, row) {
                    return '<small>'+row.app_id+'</small>';
                }
            },
            {
                title: '<small>重签名</small>',
                data: 'app_name',
                render: function(data, type, row) {
                    return '<small>'+row.app_name+'</small>';
                }
            },
            {
                title: '<small>重签版本</small>',
                data: 'app_version',
                render: function(data, type, row) {
                    return '<small>'+row.app_version+'</small>';
                }
            },
            {
                title: '<small>重签包ID</small>',
                data: 'app_bid',
                render: function(data, type, row) {
                    return '<small>'+row.app_bid+'</small>';
                }
            },
            {
                title: '<small>多开数量</small>',
                data: 'multiple_num',
                render: function(data, type, row) {
                    return '<small>'+row.multiple_num+'</small>';
                }
            },
            {
                title: '<small>多开初始值</small>',
                data: 'multiple_init',
                render: function(data, type, row) {
                    return '<small>'+row.multiple_init+'</small>';
                }
            },
            {
                title: '<small>创建日期</small>',
                data: 'created_at',
                render: function(data, type, row) {
                    return '<small>'+row.created_at+'</small>';
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
                    return `
                    <div class="delete-btn badge bg-danger">删除</div>
                    `;
                }
            },
        ]
    });
    $('#signTableList').on('click', '.delete-btn', function() {
        var table = $('#signTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要删除签名任务：'+data.id+'？', {
            btn: ['确定', '取消']
        }, function() {
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    id: data.id
                },
                type: 'delete',
                'successCallBack': function (response) {
                    $('#signTableList').DataTable().row($(this)).remove().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        }, function() {
            layer.msg('已取消操作');
        });
    });
}

if (document.getElementById('appTableList')) {
    var appTableList = {
        extend: 'collection',
        text: '更多操作',
        buttons: [
            {
                text: '清空所有', action: function (e, dt, node, config) {
                    layer.confirm('您将清空所有软件，并且重置数据库递增？', {
                        btn: ['确定清空', '取消']
                    }, function() {
                        layer.load(2);
                        sendAjax({
                            url: location.href,
                            data: {
                                type: 'all'
                            },
                            type: 'delete',
                            'successCallBack': function (response) {
                                $('#appTableList').DataTable().row($(this)).remove().draw();
                                layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
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
    $('#appTableList').DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        buttons: DataTablesConfig.buttons,
        ajax: function(data, callback, settings) {
            sendAjax({
                url: location.href,
                data: data,
                successCallBack: function(response) {
                    const responseJSON = {};
                    responseJSON.draw = response['data']['data'].draw;
                    responseJSON.recordsTotal = response['data']['data'].recordsTotal;
                    responseJSON.recordsFiltered = response['data']['data'].recordsFiltered;
                    responseJSON.data = response['data']['data'].data;
                    callback(responseJSON);
                },
            });
        },
        order: [[ 6, 'desc' ]],
        columns: [
            {
                title: '<small>软件名</small>',
                data: 'app_name',
                render: function(data, type, row) {
                    return '<small>'+row.app_name+'</small>';
                }
            },
            {
                title: '<small>软件ID</small>',
                data: 'app_id',
                render: function(data, type, row) {
                    return '<small>'+row.app_id+'</small>';
                }
            },
            {
                title: '<small>软件包ID</small>',
                data: 'app_bid',
                render: function(data, type, row) {
                    return '<small>'+row.app_bid+'</small>';
                }
            },
            {
                title: '<small>软件版本</small>',
                data: 'app_version',
                render: function(data, type, row) {
                    return '<small>'+row.app_version+'</small>';
                }
            },
            {
                title: '<small>软件介绍</small>',
                data: 'app_introduction',
                render: function(data, type, row) {
                    return '<small>'+row.app_introduction+'</small>';
                }
            },
            {
                title: '<small>软件分类</small>',
                data: 'class_id',
                render: function(data, type, row) {
                    return '<small>'+row.class_id+'</small>';
                }
            },
            {
                title: '<small>创建日期</small>',
                data: 'created_at',
                render: function(data, type, row) {
                    return '<small>'+row.created_at+'</small>';
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
                    return `
                    <div class="class_id-btn badge bg-warning">编辑分类</div>
                    <div class="app_introduction-btn badge bg-info">编辑介绍</div>
                    <div class="delete-btn badge bg-danger">删除数据</div>
                    `;
                }
            },
        ]
    });
    $('#appTableList').on('click', '.class_id-btn', function() {
        var table = $('#appTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.prompt({ title: '修改分类', value: data.class_id }, function(text, index) {
            layer.close(index);
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    id: data.id,
                    put: 'class_id',
                    text: text
                },
                type: 'put',
                'successCallBack': function (response) {
                    $('#appTableList').DataTable().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        });
    });
    $('#appTableList').on('click', '.app_introduction-btn', function() {
        var table = $('#appTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.prompt({ title: '修改介绍', formType: 2, value: data.app_introduction }, function(text, index) {
            layer.close(index);
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    id: data.id,
                    put: 'app_introduction',
                    text: text
                },
                type: 'put',
                'successCallBack': function (response) {
                    $('#appTableList').DataTable().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        });
    });
    $('#appTableList').on('click', '.delete-btn', function() {
        var table = $('#appTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要删除软件：'+data.app_name+'？', {
            btn: ['确定', '取消']
        }, function() {
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    app_id: data.app_id
                },
                type: 'delete',
                'successCallBack': function (response) {
                    $('#appTableList').DataTable().row($(this)).remove().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        }, function() {
            layer.msg('已取消操作');
        });
    });
}

if (document.getElementById('classTableList')) {
    var classTableList = {
        extend: 'collection',
        text: '更多操作',
        buttons: [
            {
                text: '清空所有', action: function (e, dt, node, config) {
                    layer.confirm('您将清空所有分类，并且重置数据库递增？', {
                        btn: ['确定清空', '取消']
                    }, function() {
                        layer.load(2);
                        sendAjax({
                            url: location.href,
                            data: {
                                type: 'all'
                            },
                            type: 'delete',
                            'successCallBack': function (response) {
                                $('#datatable').DataTable().row($(this)).remove().draw();
                                layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
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
    DataTablesConfig.buttons.push(classTableList);
    $('#classTableList').DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        buttons: DataTablesConfig.buttons,
        ajax: function(data, callback, settings) {
            sendAjax({
                url: location.href,
                data: data,
                successCallBack: function(response) {
                    const responseJSON = {};
                    responseJSON.draw = response['data']['data'].draw;
                    responseJSON.recordsTotal = response['data']['data'].recordsTotal;
                    responseJSON.recordsFiltered = response['data']['data'].recordsFiltered;
                    responseJSON.data = response['data']['data'].data;
                    callback(responseJSON);
                },
            });
        },
        order: [[ 2, 'desc' ]],
        columns: [
            {
                title: '<small>分类ID</small>',
                data: 'id',
                render: function(data, type, row) {
                    return '<small>'+row.id+'</small>';
                }
            },
            {
                title: '<small>分类名称</small>',
                data: 'name',
                render: function(data, type, row) {
                    return '<small>'+row.name+'</small>';
                }
            },
            {
                title: '<small>创建日期</small>',
                data: 'created_at',
                render: function(data, type, row) {
                    return '<small>'+row.created_at+'</small>';
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
                    return `
                    <div class="name-btn badge bg-warning">编辑名称</div>
                    <div class="delete-btn badge bg-danger">删除数据</div>
                    `;
                }
            },
        ]
    });
    $('#classTableList').on('click', '.name-btn', function() {
        var table = $('#classTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.prompt({ title: '修改名称', value: data.name }, function(text, index) {
            layer.close(index);
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    id: data.id,
                    put: 'name',
                    text: text
                },
                type: 'put',
                'successCallBack': function (response) {
                    $('#classTableList').DataTable().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        });
    });
    $('#classTableList').on('click', '.delete-btn', function() {
        var table = $('#classTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要删除软件分类：'+data.name+'？', {
            btn: ['确定', '取消']
        }, function() {
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    id: data.id
                },
                type: 'delete',
                'successCallBack': function (response) {
                    $('#classTableList').DataTable().row($(this)).remove().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        }, function() {
            layer.msg('已取消操作');
        });
    });
}

if (document.getElementById('codeTableList')) {
    var classTableList = {
        extend: 'collection',
        text: '更多操作',
        buttons: [
            {
                text: '清空全部用户所有卡密', 
                action: function (e, dt, node, config) {
                    layer.confirm('您将清空全部用户所有的卡密，并且重置数据库递增？', {
                        btn: ['确定清空', '取消']
                    }, function(){
                        layer.load(2);
                        sendAjax({
                            url: location.href,
                            data: { type: 'all' },
                            type: 'delete',
                            'successCallBack': function (response) {
                                $('#codeTableList').DataTable().row($(this)).remove().draw();
                                layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                            }
                        });
                    }, function(){
                        layer.msg('已取消操作');
                    });
                }
            },
            {
                text: '清空全部用户已用卡密',
                action: function (e, dt, node, config) {
                    layer.confirm('您将清空全部用户已用的卡密？', {
                        btn: ['确定清空', '取消']
                    }, function(){
                        layer.load(2);
                        sendAjax({
                            url: location.href,
                            data: { type: 'used' },
                            type: 'delete',
                            'successCallBack': function (response) {
                                $('#codeTableList').DataTable().row($(this)).remove().draw();
                                layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                            }
                        });
                    }, function(){
                        layer.msg('已取消操作');
                    });
                }
            },
            {
                text: '清空全部用户未用卡密',
                action: function (e, dt, node, config) {
                    layer.confirm('您将清空全部用户未用的卡密？', {
                        btn: ['确定清空', '取消']
                    }, function(){
                        layer.load(2);
                        sendAjax({
                            url: location.href,
                            data: { type: 'not-used' },
                            type: 'delete',
                            'successCallBack': function (response) {
                                $('#codeTableList').DataTable().row($(this)).remove().draw();
                                layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                            }
                        });
                    }, function(){
                        layer.msg('已取消操作');
                    });
                }
            },
            {
                text: '清空指定代理所有卡密', 
                action: function (e, dt, node, config) {
                    layer.prompt({title: '请输入代理ID'}, function(value, index, elem){
                        if(value === '') return elem.focus();
                        layer.load(2);
                        sendAjax({
                            url: location.href,
                            data: {
                                type: 'agent-all',
                                id: value
                            },
                            type: 'delete',
                            'successCallBack': function (response) {
                                $('#codeTableList').DataTable().row($(this)).remove().draw();
                                layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                            }
                        });
                        layer.close(index);
                    });
                }
            },
            {
                text: '清空指定代理已用卡密',
                action: function (e, dt, node, config) {
                    layer.prompt({title: '请输入代理ID'}, function(value, index, elem){
                        if(value === '') return elem.focus();
                        layer.load(2);
                        sendAjax({
                            url: location.href,
                            data: {
                                type: 'agent-used',
                                id: value
                            },
                            type: 'delete',
                            'successCallBack': function (response) {
                                $('#codeTableList').DataTable().row($(this)).remove().draw();
                                layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                            }
                        });
                        layer.close(index);
                    });
                }
            },
            {
                text: '清空指定代理未用卡密',
                action: function (e, dt, node, config) {
                    layer.prompt({title: '请输入代理ID'}, function(value, index, elem){
                        if(value === '') return elem.focus();
                        layer.load(2);
                        sendAjax({
                            url: location.href,
                            data: {
                                type: 'agent-not-used',
                                id: value
                            },
                            type: 'delete',
                            'successCallBack': function (response) {
                                $('#codeTableList').DataTable().row($(this)).remove().draw();
                                layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
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
    $('#codeTableList').DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        buttons: DataTablesConfig.buttons,
        ajax: function(data, callback, settings) {
            sendAjax({
                url: location.href,
                data: data,
                successCallBack: function(response) {
                    const responseJSON = {};
                    responseJSON.draw = response['data']['data'].draw;
                    responseJSON.recordsTotal = response['data']['data'].recordsTotal;
                    responseJSON.recordsFiltered = response['data']['data'].recordsFiltered;
                    responseJSON.data = response['data']['data'].data;
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
                    return '<small>' + row.code + '</small>';
                }
            },
            {
                title: '<small>绑定设备</small>',
                data: 'udid',
                render: function(data, type, row) {
                    if (row.udid) {
                        return '<small>' + row.udid + '</small>';
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
                        return '<small>' + row.remark + '</small>';
                    } else {
                        return '<small></small>';
                    }
                }
            },
            {
                title: '<small>卡密状态</small>',
                data: 'status',
                render: function(data, type, row) {
                    switch (row.status) {
                        case 'DISABLED':
                            return '<div class="badge bg-danger">DISABLED</div>';
                        break;
                        case 'ENABLED':
                            return '<div class="badge bg-success">ENABLED</div>';
                        break;
                    }
                }
            },
            {
                title: '<small>卡密类型</small>',
                data: 'type',
                render: function(data, type, row) {
                    switch (row.type) {
                        case 'default':
                            return '<div class="badge bg-primary">默认模式</div>';
                        break;
                        case 'processing':
                            return '<div class="badge bg-warning">预约证书</div>';
                        break;
                        case 'good':
                            return '<div class="badge bg-success">秒出证书</div>';
                        break;
                    }
                }
            },
            {
                title: '<small>售后天数</small>',
                data: 'after_sale_day',
                render: function(data, type, row) {
                    return '<small>' + row.after_sale_day + '</small>';
                }
            },
            {
                title: '<small>售后次数</small>',
                data: 'after_sale_num',
                render: function(data, type, row) {
                    return '<small>' + row.after_sale_num + '</small>';
                }
            },
            {
                title: '<small>已用售后</small>',
                data: 'use_after_sale',
                render: function(data, type, row) {
                    return '<small>' + row.use_after_sale + '</small>';
                }
            },
            {
                title: '<small>代理 ID</small>',
                data: 'agent_id',
                render: function(data, type, row) {
                    if (row.agent_id) {
                        return '<small>' + row.agent_id + '</small>';
                    } else {
                        return '<small></small>';
                    }
                }
            },
            {
                title: '<small>激活日期</small>',
                data: 'verified_at',
                render: function(data, type, row) {
                    return '<small>'+row.verified_at+'</small>';
                }
            },
            {
                title: '<small>到期日期</small>',
                data: 'maturity_at',
                render: function(data, type, row) {
                    return '<small>'+row.maturity_at+'</small>';
                }
            },
            {
                title: '<small>创建日期</small>',
                data: 'created_at',
                render: function(data, type, row) {
                    return '<small>'+row.created_at+'</small>';
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
                    return '<button class="remark-btn btn btn-outline-warning btn-sm">修改备注</button> <button class="delete-btn btn btn-outline-danger btn-sm">删除数据</button> <button class="blacklist-btn btn btn-outline-dark btn-sm">拉黑操作</button>';
                }
            },
        ]
    });
    $('#codeTableList').on('click', '.blacklist-btn', function() {
        var table = $('#codeTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.alert(`请选择要操作的类型与目标
        <br><br>当前兑换码：
        <br>${ data.code }
        <br><br>当前设备码：
        <br>${ (data.udid) ? data.udid : '未绑定' }`, {
            btn: ['拉黑兑换码', '拉黑设备码', '取消操作'],
            btnAlign: 'c',
            btn1: function() {
                layer.prompt({title: '请输入拉黑原因'}, function(text, index) {
                    layer.close(index);
                    layer.load(2);
                    sendAjax({
                        url: '/add/blacklist',
                        data: {
                            type: 'CODE',
                            value: data.code,
                            reason: text
                        },
                        'successCallBack': function (response) {
                            layer.confirm('添加或修改黑名单成功<br><br>本次操作拉黑兑换码：<br>'+response['data']['data'].value+'<br><br>本次操作拉黑的原因：<br>'+response['data']['data'].reason);
                        }
                    });
                });
            },
            btn2: function() {
                if (data.udid) {
                    layer.prompt({title: '请输入拉黑原因'}, function(text, index) {
                        layer.close(index);
                        layer.load(2);
                        sendAjax({
                            url: '/add/blacklist',
                            data: {
                                type: 'UDID',
                                value: data.udid,
                                reason: text
                            },
                            'successCallBack': function (response) {
                                layer.confirm('添加或修改黑名单成功<br><br>本次操作拉黑设备码：<br>'+response['data']['data'].value+'<br><br>本次操作拉黑的原因：<br>'+response['data']['data'].reason);
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
    $('#codeTableList').on('click', '.remark-btn', function() {
        var table = $('#codeTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.prompt({title: '修改备注', value: data.remark}, function(text, index){
            layer.close(index);
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    id: data.id,
                    put: 'remark',
                    text: text
                },
                type: 'put',
                'successCallBack': function (response) {
                    $('#codeTableList').DataTable().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        });
    });
    $('#codeTableList').on('click', '.delete-btn', function() {
        var table = $('#codeTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要删除卡密：'+data.code+'？', {
            btn: ['确定', '取消']
        }, function() {
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    code: data.code
                },
                type: 'delete',
                'successCallBack': function (response) {
                    $('#codeTableList').DataTable().row($(this)).remove().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        }, function() {
            layer.msg('已取消操作');
        });
    });
}

if (document.getElementById('helpTableList')) {
    var helpTableList = {
        extend: 'collection',
        text: '更多操作',
        buttons: [
            {
                text: '清空所有', action: function (e, dt, node, config) {
                    layer.confirm('您将清空所有帮助，并且重置数据库递增？', {
                        btn: ['确定清空', '取消']
                    }, function() {
                        layer.load(2);
                        sendAjax({
                            url: location.href,
                            data: {
                                type: 'all'
                            },
                            type: 'delete',
                            'successCallBack': function (response) {
                                $('#helpTableList').DataTable().row($(this)).remove().draw();
                                layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
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
    DataTablesConfig.buttons.push(helpTableList);
    $('#helpTableList').DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        buttons: DataTablesConfig.buttons,
        ajax: function(data, callback, settings) {
            sendAjax({
                url: location.href,
                data: data,
                successCallBack: function(response) {
                    const responseJSON = {};
                    responseJSON.draw = response['data']['data'].draw;
                    responseJSON.recordsTotal = response['data']['data'].recordsTotal;
                    responseJSON.recordsFiltered = response['data']['data'].recordsFiltered;
                    responseJSON.data = response['data']['data'].data;
                    callback(responseJSON);
                },
            });
        },
        order: [[ 3, 'desc' ]],
        columns: [
            {
                title: '<small>ID</small>',
                data: 'id',
                render: function(data, type, row) {
                    return '<small>'+row.id+'</small>';
                }
            },
            {
                title: '<small>标题</small>',
                data: 'title',
                render: function(data, type, row) {
                    return '<small>'+row.title+'</small>';
                }
            },
            {
                title: '<small>内容</small>',
                data: 'content',
                render: function(data, type, row) {
                    return '<small>'+row.content+'</small>';
                }
            },
            {
                title: '<small>创建日期</small>',
                data: 'created_at',
                render: function(data, type, row) {
                    return '<small>'+row.created_at+'</small>';
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
                    return `
                    <div class="title-btn badge bg-warning">编辑标题</div>
                    <div class="content-btn badge bg-info">编辑内容</div>
                    <div class="delete-btn badge bg-danger">删除数据</div>
                    `;
                }
            },
        ]
    });
    $('#helpTableList').on('click', '.title-btn', function() {
        var table = $('#helpTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.prompt({ title: '修改标题', value: data.title }, function(text, index) {
            layer.close(index);
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    id: data.id,
                    put: 'title',
                    text: text
                },
                type: 'put',
                'successCallBack': function (response) {
                    $('#datatable').DataTable().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        });
    });
    $('#helpTableList').on('click', '.content-btn', function() {
        var table = $('#helpTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.prompt({ title: '修改内容', formType: 2, value: data.content }, function(text, index) {
            layer.close(index);
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    id: data.id,
                    put: 'content',
                    text: text
                },
                type: 'put',
                'successCallBack': function (response) {
                    $('#datatable').DataTable().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        });
    });
    $('#helpTableList').on('click', '.delete-btn', function() {
        var table = $('#helpTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要删除帮助：'+data.title+'？', {
            btn: ['确定', '取消']
        }, function() {
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    id: data.id
                },
                type: 'delete',
                'successCallBack': function (response) {
                    $('#helpTableList').DataTable().row($(this)).remove().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        }, function() {
            layer.msg('已取消操作');
        });
    });
}

if (document.getElementById('aboutUSTableList')) {
    var aboutUSTableList = {
        extend: 'collection',
        text: '更多操作',
        buttons: [
            {
                text: '清空所有', action: function (e, dt, node, config) {
                    layer.confirm('您将清空所有分类，并且重置数据库递增？', {
                        btn: ['确定清空', '取消']
                    }, function() {
                        layer.load(2);
                        sendAjax({
                            url: location.href,
                            data: {
                                type: 'all'
                            },
                            type: 'delete',
                            'successCallBack': function (response) {
                                $('#datatable').DataTable().row($(this)).remove().draw();
                                layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
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
    DataTablesConfig.buttons.push(aboutUSTableList);
    $('#aboutUSTableList').DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        buttons: DataTablesConfig.buttons,
        ajax: function(data, callback, settings) {
            sendAjax({
                url: location.href,
                data: data,
                successCallBack: function(response) {
                    const responseJSON = {};
                    responseJSON.draw = response['data']['data'].draw;
                    responseJSON.recordsTotal = response['data']['data'].recordsTotal;
                    responseJSON.recordsFiltered = response['data']['data'].recordsFiltered;
                    responseJSON.data = response['data']['data'].data;
                    callback(responseJSON);
                },
            });
        },
        order: [[ 4, 'desc' ]],
        columns: [
            {
                title: '<small>ID</small>',
                data: 'id',
                render: function(data, type, row) {
                    return '<small>'+row.id+'</small>';
                }
            },
            {
                title: '<small>标题</small>',
                data: 'title',
                render: function(data, type, row) {
                    return '<small>'+row.title+'</small>';
                }
            },
            {
                title: '<small>内容</small>',
                data: 'value',
                render: function(data, type, row) {
                    return '<small>'+row.value+'</small>';
                }
            },
            {
                title: '<small>链接</small>',
                data: 'url',
                render: function(data, type, row) {
                    return '<small>'+row.url+'</small>';
                }
            },
            {
                title: '<small>创建日期</small>',
                data: 'created_at',
                render: function(data, type, row) {
                    return '<small>'+row.created_at+'</small>';
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
                    return `
                    <div class="title-btn badge bg-warning">修改标题</div>
                    <div class="value-btn badge bg-info">修改内容</div>
                    <div class="url-btn badge bg-success">修改链接</div>
                    <div class="delete-btn badge bg-danger">删除</div>
                    `;
                }
            },
        ]
    });
    $('#aboutUSTableList').on('click', '.title-btn', function() {
        var table = $('#aboutUSTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.prompt({ title: '修改标题', value: data.title }, function(text, index) {
            layer.close(index);
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    id: data.id,
                    put: 'title',
                    text: text
                },
                type: 'put',
                'successCallBack': function (response) {
                    $('#aboutUSTableList').DataTable().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        });
    });
    $('#aboutUSTableList').on('click', '.value-btn', function() {
        var table = $('#aboutUSTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.prompt({ title: '修改内容', formType: 2, value: data.value }, function(text, index) {
            layer.close(index);
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    id: data.id,
                    put: 'value',
                    text: text
                },
                type: 'put',
                'successCallBack': function (response) {
                    $('#aboutUSTableList').DataTable().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        });
    });
    $('#aboutUSTableList').on('click', '.url-btn', function() {
        var table = $('#aboutUSTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.prompt({ title: '修改链接', value: data.url }, function(text, index) {
            layer.close(index);
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    id: data.id,
                    put: 'url',
                    text: text
                },
                type: 'put',
                'successCallBack': function (response) {
                    $('#aboutUSTableList').DataTable().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        });
    });
    $('#aboutUSTableList').on('click', '.delete-btn', function() {
        var table = $('#aboutUSTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要删除介绍：'+data.title+'？', {
            btn: ['确定', '取消']
        }, function() {
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    id: data.id
                },
                type: 'delete',
                'successCallBack': function (response) {
                    $('#aboutUSTableList').DataTable().row($(this)).remove().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        }, function() {
            layer.msg('已取消操作');
        });
    });
}

if (document.getElementById('agentTableList')) {
    var agentTableList = {
        extend: 'collection',
        text: '更多操作',
        buttons: [
            {
                text: '清空所有', action: function (e, dt, node, config) {
                    layer.confirm('您将清空所有代理，并且重置数据库递增？', {
                        btn: ['确定清空', '取消']
                    }, function() {
                        layer.load(2);
                        sendAjax({
                            url: location.href,
                            data: {
                                type: 'all'
                            },
                            type: 'delete',
                            'successCallBack': function (response) {
                                $('#datatable').DataTable().row($(this)).remove().draw();
                                layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
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
    $('#agentTableList').DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        buttons: DataTablesConfig.buttons,
        ajax: function(data, callback, settings) {
            sendAjax({
                url: location.href,
                data: data,
                successCallBack: function(response) {
                    const responseJSON = {};
                    responseJSON.draw = response['data']['data'].draw;
                    responseJSON.recordsTotal = response['data']['data'].recordsTotal;
                    responseJSON.recordsFiltered = response['data']['data'].recordsFiltered;
                    responseJSON.data = response['data']['data'].data;
                    callback(responseJSON);
                },
            });
        },
        order: [[ 4, 'desc' ]],
        columns: [
            {
                title: '<small>ID</small>',
                data: 'id',
                render: function(data, type, row) {
                    return '<small>'+row.id+'</small>';
                }
            },
            {
                title: '<small>代理名称</small>',
                data: 'name',
                render: function(data, type, row) {
                    return '<small>'+row.name+'</small>';
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
                title: '<small>绑定邮箱</small>',
                data: 'email',
                render: function(data, type, row) {
                    return '<small>'+row.email+'</small>';
                }
            },
            {
                title: '<small>可用余额</small>',
                data: 'credit',
                render: function(data, type, row) {
                    return '<small>'+row.credit+' 点</small>';
                }
            },
            {
                title: '<small>默认模式设备价格</small>',
                data: 'price',
                render: function(data, type, row) {
                    return '<small>'+row.price+' 点/台</small>';
                }
            },
            {
                title: '<small>秒出证书设备价格</small>',
                data: 'good_price',
                render: function(data, type, row) {
                    return '<small>'+row.good_price+' 点/台</small>';
                }
            },
            {
                title: '<small>预约证书设备价格</small>',
                data: 'processing_price',
                render: function(data, type, row) {
                    return '<small>'+row.processing_price+' 点/台</small>';
                }
            },
            {
                title: '<small>Token</small>',
                data: 'token',
                render: function(data, type, row) {
                    return '<small>'+row.token+'</small>';
                }
            },
            {
                title: '<small>代理状态</small>',
                data: 'status',
                render: function(data, type, row) {
                    switch (row.status) {
                        case 'DISABLED':
                            return '<div class="badge bg-danger">DISABLED</div>';
                        break;
                        case 'ENABLED':
                            return '<div class="badge bg-success">ENABLED</div>';
                        break;
                    }
                }
            },
            {
                title: '<small>创建日期</small>',
                data: 'created_at',
                render: function(data, type, row) {
                    return '<small>'+row.created_at+'</small>';
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
                    return `
                    <div class="edit-btn badge bg-info">修改资料</div>
                    <div class="delete-btn badge bg-danger">删除代理</div>
                    `;
                }
            },
        ]
    });
    $('#agentTableList').on('click', '.edit-btn', function() {
        var table = $('#agentTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.open({
            type: 1,
            anim: 'slideDown',
            title: '修改代理 - '+data.email,
            content: `
            <div style="padding: 16px;">
                <form class="layui-form">
                    <div class="layui-form-item">
                        <div class="layui-input-group">
                            <div class="layui-input-split layui-input-prefix">代理账户用户名称</div>
                            <input type="text" name="name" placeholder="代理账户用户名称" value="${data.name}" class="layui-input">
                            <div class="layui-input-split layui-input-suffix">
                                <i class="layui-icon layui-icon-username"></i>
                            </div>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <div class="layui-input-group">
                            <div class="layui-input-split layui-input-prefix">代理账户备注信息</div>
                            <input type="text" name="remark" placeholder="代理账户备注信息" value="${data.remark}" class="layui-input">
                            <div class="layui-input-split layui-input-suffix">
                                <i class="layui-icon layui-icon-note"></i>
                            </div>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <div class="layui-input-group">
                            <div class="layui-input-split layui-input-prefix">代理账户绑定邮箱</div>
                            <input type="email" name="email" placeholder="代理账户绑定邮箱" value="${data.email}" class="layui-input">
                            <div class="layui-input-split layui-input-suffix">
                                <i class="layui-icon layui-icon-email"></i>
                            </div>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <div class="layui-input-group">
                            <div class="layui-input-split layui-input-prefix">代理账户可用余额</div>
                            <input type="text" name="credit" placeholder="代理账户可用余额" value="${data.credit}" class="layui-input">
                            <div class="layui-input-split layui-input-suffix">
                                <i class="layui-icon layui-icon-rmb"></i>
                            </div>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <div class="layui-input-group">
                            <div class="layui-input-split layui-input-prefix">代理账户对接令牌</div>
                            <input type="text" placeholder="代理账户对接令牌" value="${data.token}" class="layui-input" disabled>
                            <div class="layui-input-split layui-input-suffix">
                                <i class="layui-icon layui-icon-vercode"></i>
                            </div>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <div class="layui-input-group">
                            <div class="layui-input-split layui-input-prefix">默认模式设备价格</div>
                            <input type="text" name="price" placeholder="默认模式设备价格" value="${data.price}" class="layui-input">
                            <div class="layui-input-split layui-input-suffix">
                                <i class="layui-icon layui-icon-rmb"></i>
                            </div>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <div class="layui-input-group">
                            <div class="layui-input-split layui-input-prefix">秒出证书设备价格</div>
                            <input type="text" name="good_price" placeholder="秒出证书设备价格" value="${data.good_price}" class="layui-input">
                            <div class="layui-input-split layui-input-suffix">
                                <i class="layui-icon layui-icon-rmb"></i>
                            </div>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <div class="layui-input-group">
                            <div class="layui-input-split layui-input-prefix">预约证书设备价格</div>
                            <input type="text" name="processing_price" placeholder="预约证书设备价格" value="${data.processing_price}" class="layui-input">
                            <div class="layui-input-split layui-input-suffix">
                                <i class="layui-icon layui-icon-rmb"></i>
                            </div>
                        </div>
                    </div>
                    <button class="layui-btn layui-btn-fluid" lay-submit lay-filter="edit_agent">提交</button>
                </form>
            </div>
            `,
            success: function(){
                layui.form.render();
                layui.form.on('submit(edit_agent)', function(formData){
                    layer.load(2);
                    sendAjax({
                        url: location.href,
                        data: {
                            id: data.id,
                            name: formData.field.name,
                            remark: formData.field.remark,
                            email: formData.field.email,
                            credit: processCreditCard(formData.field.credit),
                            price: processCreditCard(formData.field.price),
                            good_price: processCreditCard(formData.field.good_price),
                            processing_price: processCreditCard(formData.field.processing_price)
                        },
                        type: 'put',
                        'successCallBack': function (response) {
                            $('#agentTableList').DataTable().draw();
                            layer.msg(response['data']['data'].message, { icon: 1, time: 3000 }, function(){
                                layer.closeAll();
                            });
                        }
                    });
                    return false;
                });
            }
        });
    });
    $('#agentTableList').on('click', '.delete-btn', function() {
        var table = $('#agentTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要删除代理：'+data.name+'？', {
            btn: ['确定', '取消']
        }, function() {
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    id: data.id
                },
                type: 'delete',
                'successCallBack': function (response) {
                    $('#agentTableList').DataTable().row($(this)).remove().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        }, function() {
            layer.msg('已取消操作');
        });
    });
}

if (document.getElementById('blacklistTableList')) {
    var blacklistTableList = {
        extend: 'collection',
        text: '更多操作',
        buttons: [
            {
                text: '清空所有', action: function (e, dt, node, config) {
                    layer.confirm('您将清空所有代理，并且重置数据库递增？', {
                        btn: ['确定清空', '取消']
                    }, function() {
                        layer.load(2);
                        sendAjax({
                            url: location.href,
                            data: {
                                type: 'all'
                            },
                            type: 'delete',
                            'successCallBack': function (response) {
                                $('#datatable').DataTable().row($(this)).remove().draw();
                                layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
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
    DataTablesConfig.buttons.push(blacklistTableList);
    $('#blacklistTableList').DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        buttons: DataTablesConfig.buttons,
        ajax: function(data, callback, settings) {
            sendAjax({
                url: location.href,
                data: data,
                successCallBack: function(response) {
                    const responseJSON = {};
                    responseJSON.draw = response['data']['data'].draw;
                    responseJSON.recordsTotal = response['data']['data'].recordsTotal;
                    responseJSON.recordsFiltered = response['data']['data'].recordsFiltered;
                    responseJSON.data = response['data']['data'].data;
                    callback(responseJSON);
                },
            });
        },
        order: [[ 4, 'desc' ]],
        columns: [
            {
                title: '<small>ID</small>',
                data: 'id',
                render: function(data, type, row) {
                    return '<small>'+row.id+'</small>';
                }
            },
            {
                title: '<small>设备/卡密</small>',
                data: 'value',
                render: function(data, type, row) {
                    return '<small>'+row.value+'</small>';
                }
            },
            {
                title: '<small>拉黑原因</small>',
                data: 'reason',
                render: function(data, type, row) {
                    return '<small>'+row.reason+'</small>';
                }
            },
            {
                title: '<small>拉黑类型</small>',
                data: 'type',
                render: function(data, type, row) {
                    switch (row.type) {
                        case 'CODE':
                            return '<small>卡密</small>';
                        break;
                        case 'UDID':
                            return '<small>设备</small>';
                        break;
                    }
                }
            },
            {
                title: '<small>添加日期</small>',
                data: 'created_at',
                render: function(data, type, row) {
                    return '<small>'+row.created_at+'</small>';
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
                    return `
                    <div class="delete-btn badge bg-danger">删除数据</div>
                    `;
                }
            },
        ]
    });
    $('#blacklistTableList').on('click', '.delete-btn', function() {
        var table = $('#blacklistTableList').DataTable();
        var data = table.row($(this)).data();
        if (data === undefined) {
            data = table.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要删除数据：'+data.value+'？', {
            btn: ['确定', '取消']
        }, function() {
            layer.load(2);
            sendAjax({
                url: location.href,
                data: {
                    id: data.id
                },
                type: 'delete',
                'successCallBack': function (response) {
                    $('#blacklistTableList').DataTable().row($(this)).remove().draw();
                    layer.msg(response['data']['data'].message, { icon: 1, time: 3000 });
                }
            });
        }, function() {
            layer.msg('已取消操作');
        });
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
            sendAjax({
                'url': '/cert/v1/lists_certificate',
                'data': data,
                successCallBack: function(response) {
                    const responseJSON = {};
                    responseJSON.draw = response['data']['data'].draw;
                    responseJSON.recordsTotal = response['data']['data'].recordsTotal;
                    responseJSON.recordsFiltered = response['data']['data'].recordsFiltered;
                    responseJSON.data = response['data']['data'].data;
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
                    if (row.switch == 'ENABLED') {
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
                        if (row.switch == 'ENABLED') {
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
        if (data == undefined) {
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
            sendAjax({
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
        if (data == undefined) {
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
                    sendAjax({
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
        if (data == undefined) {
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
                sendAjax({
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
        if (data == undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.load(2);
        sendAjax({
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
        if (data == undefined) {
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
            sendAjax({
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
                    const lists_devices_data = response['data']['data'].data;
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
                            sendAjax({
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
                                            sendAjax({
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
        if (data == undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要停用“'+data.apple_id+'”证书吗？', {
            btn: ['确定停用', '取消操作']
        }, function(){
            layer.load(2);
            sendAjax({
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
        if (data == undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要启用“'+data.apple_id+'”证书吗？', {
            btn: ['确定启用', '取消操作']
        }, function(){
            layer.load(2);
            sendAjax({
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
        if (data == undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要删除“'+data.apple_id+'”开发者证书吗？', {
            btn: ['确定删除', '取消操作']
        }, function(){
            layer.load(2);
            sendAjax({
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
        if (data == undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.confirm('此功能为二次超开证书<br><br>二次超开理论上说明：<br>普通会员一本开发者证书最高支持开通：200*2=400 台设备<br>高级会员一本开发者证书最高支持开通：700*2=1400 台设备<br><br>如果您不懂得本操作的前提条件请您取消本次操作。', {
            btn: ['确定操作', '取消操作']
        }, function(){
            layer.load(2);
            sendAjax({
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
        if (data == undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.prompt({title: '修改备注', value: data.remark}, function(text, index){
            layer.close(index);
            layer.load(2);
            sendAjax({
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
        if (data == undefined) {
            data = usersCertificateList.row($(this).closest('tr')).data();
        }
        layer.alert('请选择重构证书模式，以及了解重构证书的用途。<br><br>证书异常重构：<br>当证书状态异常且证书无法正常使用时选择此项重新生成证书<br><br>强制重构证书：<br>续费证书或您的证书需要强制重构时，此选项会删除原有证书<br><br>取消证书重构：<br>如果证书状态正常且正常使用的情况下，请选择此项取消重构', {
            btn: ['证书异常重构', '强制重构证书', '取消证书重构'],
            btnAlign: 'c',
            btn1: function() {
                layer.load(2);
                sendAjax({
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
                sendAjax({
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
            sendAjax({
                'url': '/cert/v1/lists_publics',
                'data': data,
                successCallBack: function(response) {
                    const responseJSON = {};
                    responseJSON.draw = response['data']['data'].draw;
                    responseJSON.recordsTotal = response['data']['data'].recordsTotal;
                    responseJSON.recordsFiltered = response['data']['data'].recordsFiltered;
                    responseJSON.data = response['data']['data'].data;
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
        if (data == undefined) {
            data = usersPublicsList.row($(this).closest('tr')).data();
        }
        layer.load(2);
        sendAjax({
            'url': '/cert/basic/query_publics',
            'data': {
                devices_id: data.devices_id,
                udid: data.udid
            },
            'successCallBack': function (response) {
                var DeviceStatus = '';
                switch (response['data']['data'].status) {
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
                switch (response['data']['data'].real_time_status) {
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
                    realTimeStatus = '<a style="color: black">错误码：'+response['data']['data'].real_time_status+'</a>';
                }
                layer.alert(`
                本次检测的【设备码】：${response['data']['data'].udid}<br>
                证书所有者【开发者】：${response['data']['data'].cert_subject_info.O}<br>
                证书所属类型【苹果】：${response['data']['data'].cert_type}<br>
                证书到期时间【精准】：${response['data']['data'].cert_maturity_at}<br>
                设备当前状态【苹果】：${DeviceStatus}<br>
                证书实时状态【结果】：${realTimeStatus}<br>
                `);
            }
        });
    });
    usersPublicsList.on('click', '.remark-btn', function() {
        var data = usersPublicsList.row($(this)).data();
        if (data == undefined) {
            data = usersPublicsList.row($(this).closest('tr')).data();
        }
        layer.prompt({title: '修改备注', value: data.remark}, function(text, index){
            layer.close(index);
            layer.load(2);
            sendAjax({
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
        if (data == undefined) {
            data = usersPublicsList.row($(this).closest('tr')).data();
        }
        layer.load(2);
        sendAjax({
            'url': '/cert/basic/query_publics',
            'data': {
                devices_id: data.devices_id,
                udid: data.udid
            },
            'successCallBack': function (response) {
                var p12_pass = response['data']['data'].p12_pass;
                var p12_data = response['data']['data'].p12_data;
                var profile_data = response['data']['data'].profile_data;
                if (p12_data === '' || p12_data === null) {
                    layer.msg('P12证书数据为空，请检查证书', { icon: 2, time: 5000 });
                    return false;
                } else
                if (profile_data === '' || profile_data === null) {
                    layer.msg('描述文件未创建，请先创建描述文件', { icon: 2, time: 5000 });
                    return false;
                }
                var p12_pass_file = new File([p12_pass], '证书密码.txt', { type: 'text/plain' });
                var p12_data_binary = atob(response['data']['data'].p12_data);
                var p12_data_array = new Uint8Array(p12_data_binary.length);
                for (var i = 0; i < p12_data_binary.length; i++) {
                    p12_data_array[i] = p12_data_binary.charCodeAt(i);
                }
                var p12_data_blob = new Blob([p12_data_array], { type: 'application/x-pkcs12' });
                var p12_data_file = new File([p12_data_blob], data.udid+'.p12');
                var profile_data_binary = atob(response['data']['data'].profile_data);
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
        if (data == undefined) {
            data = usersPublicsList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要禁用“'+data.udid+'”设备吗？', {
            btn: ['确定禁用', '取消操作']
        }, function(){
            layer.load(2);
            sendAjax({
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
        if (data == undefined) {
            data = usersPublicsList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要启用“'+data.udid+'”设备吗？', {
            btn: ['确定启用', '取消操作']
        }, function(){
            layer.load(2);
            sendAjax({
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
            sendAjax({
                'url': '/cert/v1/lists_devices',
                'data': data,
                successCallBack: function(response) {
                    const responseJSON = {};
                    responseJSON.draw = response['data']['data'].draw;
                    responseJSON.recordsTotal = response['data']['data'].recordsTotal;
                    responseJSON.recordsFiltered = response['data']['data'].recordsFiltered;
                    responseJSON.data = response['data']['data'].data;
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
        if (data == undefined) {
            data = usersDevicesList.row($(this).closest('tr')).data();
        }
        layer.load(2);
        sendAjax({
            'url': '/cert/basic/query_devices',
            'data': {
                iss: data.iss,
                udid: data.udid
            },
            'successCallBack': function (response) {
                var DeviceStatus = '';
                switch (response['data']['data'].status) {
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
                switch (response['data']['data'].real_time_status) {
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
                    realTimeStatus = '<a style="color: black">错误码：'+response['data']['data'].real_time_status+'</a>';
                }
                layer.alert(`
                本次检测的【设备码】：${response['data']['data'].udid}<br>
                证书所有者【开发者】：${response['data']['data'].cert_subject_info.O}<br>
                证书所属类型【苹果】：${response['data']['data'].cert_type}<br>
                证书到期时间【精准】：${response['data']['data'].cert_maturity_at}<br>
                设备当前状态【苹果】：${DeviceStatus}<br>
                证书实时状态【结果】：${realTimeStatus}<br>
                `);
            }
        });
    });
    usersDevicesList.on('click', '.remark-btn', function() {
        var data = usersDevicesList.row($(this)).data();
        if (data == undefined) {
            data = usersDevicesList.row($(this).closest('tr')).data();
        }
        layer.prompt({title: '修改备注', value: data.remark}, function(text, index){
            layer.close(index);
            layer.load(2);
            sendAjax({
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
        if (data == undefined) {
            data = usersDevicesList.row($(this).closest('tr')).data();
        }
        layer.load(2);
        sendAjax({
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
        if (data == undefined) {
            data = usersDevicesList.row($(this).closest('tr')).data();
        }
        layer.load(2);
        sendAjax({
            'url': '/cert/basic/query_devices',
            'data': {
                iss: data.iss,
                udid: data.udid
            },
            'successCallBack': function (response) {
                var p12_pass = response['data']['data'].p12_pass;
                var p12_data = response['data']['data'].p12_data;
                var profile_data = response['data']['data'].profile_data;
                if (p12_data === '' || p12_data === null) {
                    layer.msg('P12证书数据为空，请检查证书', { icon: 2, time: 5000 });
                    return false;
                } else
                if (profile_data === '' || profile_data === null) {
                    layer.msg('描述文件未创建，请先创建描述文件', { icon: 2, time: 5000 });
                    return false;
                }
                var p12_pass_file = new File([p12_pass], '证书密码.txt', { type: 'text/plain' });
                var p12_data_binary = atob(response['data']['data'].p12_data);
                var p12_data_array = new Uint8Array(p12_data_binary.length);
                for (var i = 0; i < p12_data_binary.length; i++) {
                    p12_data_array[i] = p12_data_binary.charCodeAt(i);
                }
                var p12_data_blob = new Blob([p12_data_array], { type: 'application/x-pkcs12' });
                var p12_data_file = new File([p12_data_blob], data.udid+'.p12');
                var profile_data_binary = atob(response['data']['data'].profile_data);
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
        if (data == undefined) {
            data = usersDevicesList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要禁用“'+data.udid+'”设备吗？', {
            btn: ['确定禁用', '取消操作']
        }, function(){
            layer.load(2);
            sendAjax({
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
        if (data == undefined) {
            data = usersDevicesList.row($(this).closest('tr')).data();
        }
        layer.confirm('您确定要启用“'+data.udid+'”设备吗？', {
            btn: ['确定启用', '取消操作']
        }, function(){
            layer.load(2);
            sendAjax({
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