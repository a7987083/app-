<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title')丨{{ config('app.name', 'Laravel') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="system-path" content="/agent">
    @yield('link')
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/css/bootstrap.css" rel="stylesheet" type="text/css">
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/sweetalert2/sweetalert2.min.css"  rel="stylesheet" type="text/css">
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/select2/select2.min.css"  rel="stylesheet" type="text/css">
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/css/icons.css" rel="stylesheet" type="text/css">
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/css/app.css" rel="stylesheet" type="text/css">
    <link href="{{ config('api.basics.cdn') }}/theme/layui/css/layui.css"  rel="stylesheet" type="text/css">
</head>
@section('body')
    <body data-layout="horizontal" data-topbar="dark" data-layout-size="boxed">
    @show
    <header id="page-topbar">
        <div class="navbar-header">
            <div class="d-flex">
                <button type="button" class="btn btn-sm px-3 font-size-16 d-lg-none header-item waves-effect waves-light" data-bs-toggle="collapse" data-bs-target="#topnav-menu-content">
                    <i class="fa fa-fw fa-bars"></i>
                </button>
            </div>
            <div class="d-flex">
                <div class="dropdown d-inline-block">
                    <button type="button" class="btn header-item waves-effect" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <img class="rounded-circle header-profile-user" src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/images/users/none.png" alt="Header Avatar">
                        <span class="d-none d-xl-inline-block ms-1 fw-medium font-size-15">{{ Str::ucfirst($agent['name']) }}</span>
                        <i class="uil-angle-down d-none d-xl-inline-block font-size-15"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" id="change-password">
                            <i class="uil uil-user-circle font-size-18 align-middle text-muted me-1"></i>
                            <span class="align-middle">{{ __('修改密码') }}</span>
                        </a>
                        <a class="dropdown-item" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="uil uil-sign-out-alt font-size-18 align-middle me-1 text-muted"></i>
                            <span class="align-middle">{{ __('注销登录') }}</span>
                        </a>
                        <form id="logout-form" action="/agent/logout" method="POST" style="display: none;">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="container-fluid">
            <div class="topnav">
                <nav class="navbar navbar-light navbar-expand-lg topnav-menu">
                    <div class="collapse navbar-collapse" id="topnav-menu-content">
                        <ul class="navbar-nav">
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('agent.console') }}">
                                    <i class="uil-home-alt me-2"></i> {{ __('数据统计') }}
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('agent.code') }}">
                                    <i class="uil-card-atm me-2"></i> {{ __('卡密列表') }}
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('agent.code.create') }}">
                                    <i class="uil-cloud-upload me-2"></i> {{ __('添加卡密') }}
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('agent.app-token') }}">
                                    <i class="uil-shield-check me-2"></i> {{ __('App Token 管理') }}
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="download-source-code">
                                    <i class="uil-arrow-circle-down me-2"></i> {{ __('下载源码') }}
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('agent.faq') }}">
                                    <i class="uil-question-circle me-2"></i> {{ __('常见问题') }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </div>
    </header>
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </div>
        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <script>document.write(new Date().getFullYear())</script> © {{ config('app.name', 'Laravel') }}.
                    </div>
                    <div class="col-sm-6">
                        <div class="text-sm-end d-none d-sm-block">
                            Crafted with <i class="mdi mdi-heart text-danger"></i> by <a href="https://wpa.qq.com/msgrd?v=1&uin=1276117137&site=&menu=yes" target="_blank" class="text-reset">Liuless</a>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    <div class="rightbar-overlay"></div>
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/jquery/jquery.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/bootstrap/bootstrap.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/metismenu/metismenu.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/node-waves/node-waves.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/datatables/datatables.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/simplebar/simplebar.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/apexcharts/apexcharts.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/sweetalert2/sweetalert2.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/pdfmake/pdfmake.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/select2/select2.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/app.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/layui/js/layui.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/weui/js/file-saver.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/weui/js/jszip.js"></script>
    <script>
        const systemPath = document.querySelector('meta[name="system-path"]').getAttribute('content');
        function SendAjax(params) {
            params['completeCallBack'] = undefined;
            const $ = layui.$;
            const layer = layui['layer'];
            const url = params['url'];
            const type = params['type'] || 'post';
            const data = params['data'] || '';
            let successCallBack;
            let errorCallBack;
            let completeCallBack;
            if (typeof params['successCallBack'] !== 'undefined') {
                successCallBack = params['successCallBack'];
            } else {
                successCallBack = function (response) {
                    layer.msg(response['message'], {icon: 1, time: 3000}, function(){
                        location.reload();
                    });
                    return false;
                }
            }
            if (typeof params['errorCallBack'] !== 'undefined') {
                errorCallBack = params['errorCallBack'];
            } else {
                errorCallBack = function (error) {
                    layer.msg(error['responseJSON']['message'], {icon: 5, time: 3000});
                };
            }
            if (typeof params['completeCallBack'] !== 'undefined') {
                completeCallBack = params['completeCallBack'];
            } else {
                completeCallBack = function () {
                    layer['closeLast']('loading');
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
        function StoreKey() {
            return window['location']['hostname'];
        }
        function processCreditCard(input) {
            let cleanedInput = input.replace(/\s+/g, '');
            let numberValue = parseFloat(cleanedInput);
            if (isNaN(numberValue)) {
                return 0.00;
            }
            return numberValue.toFixed(2);
        }
        $('#change-password').on('click', function() {
            layer.open({
                type: 1,
                anim: 'slideDown',
                title: '修改密码',
                content: `
                <div style="padding: 16px;">
                <small>注意：修改密码成功后将自动注销本次登录【需要重新登录】<br></small>
                <hr>
                <form class="layui-form">
                <input type="text" name="password" placeholder="输入原密码" class="layui-input" lay-verify="required">
                <hr>
                <input type="text" name="new_password" placeholder="输入新密码" class="layui-input" lay-verify="required">
                <hr>
                <input type="text" name="new_password_confirmation" placeholder="重复新密码" class="layui-input" lay-verify="required">
                <hr>
                <button class="layui-btn layui-btn-fluid" lay-submit lay-filter="change-password">提交修改</button>
                </form>
                </div>
                `,
                success: function() {
                    layui['form'].render();
                    layui['form'].on('submit(change-password)', function(formData) {
                        layer.load(2);
                        SendAjax({
                            'url': '/agent/change-password',
                            'data': {
                                password: formData['field']['password'],
                                new_password: formData['field']['new_password'],
                                new_password_confirmation: formData['field']['new_password_confirmation'],
                            },
                            'successCallBack': function () {
                                layer.msg('密码修改成功，即将跳转登录页', {icon: 1, time: 3000}, function() {
                                    RedirectTo('/agent/login');
                                });
                            }
                        });
                        return false;
                    });
                }
            });
        });
        $('#download-source-code').on('click', function() {
            layer.load(2);
            SendAjax({
                'url': '/agent/theme-list',
                'successCallBack': function (response) {
                    if (Array.isArray(response['data']) && response['data'].length) {
                        let optionsHtml = '<option value="">请选择或搜索要下载的主题源码</option>';
                        response['data'].forEach(item => {
                            optionsHtml += `<option value="${item.key}">${item.name}-${item.version}</option>`;
                        });
                        const themesData = response['data'];
                        layer.open({
                            type: 1,
                            anim: 'slideDown',
                            title: '请选择要下载的主题源码',
                            content: `
                            <div style="padding: 16px;">
                            <small>
                            主题介绍：<br>
                            请选择主题后查看介绍，介绍包含【需授权】字样的主题需要额外授权。
                            </small>
                            <hr>
                            <form class="layui-form" id="downloadSourceCode">
                            <select name="theme" lay-search="" lay-filter="theme" lay-verify="required">
                            ${optionsHtml}
                            </select>
                            <hr>
                            <input type="text" name="domain" placeholder="输入您要搭建的签名站点域名" class="layui-input" lay-verify="domainCheck">
                            <hr>
                            <input type="text" name="name" placeholder="输入您要设置的站点名称" class="layui-input" lay-verify="required">
                            <hr>
                            <blockquote class="layui-elem-quote">
                                <p>⬇️ 上传证书自动签名描述文件 留空不自动签名 ⬇️</p>
                            </blockquote>
                            <textarea name="p12" placeholder="点击上传 p12 证书文件" class="layui-textarea" readonly></textarea>
                            <hr class="ws-space-16">
                            <textarea name="mobileprovision" placeholder="点击上传 mobileprovision 描述文件" class="layui-textarea" readonly></textarea>
                            <hr class="ws-space-16">
                            <input type="text" name="password" placeholder="在此输入证书密码" class="layui-input">
                            <hr class="ws-space-16">
                            <button class="layui-btn layui-btn-fluid" lay-submit lay-filter="download-source-code">提交下载</button>
                            </form>
                            </div>
                            `,
                            success: function() {
                                layui['form'].render();
                                layui['form'].on('select(theme)', function(data){
                                    const selectedTheme = themesData.find(theme => theme['key'] === data['value']);
                                    layer.msg(selectedTheme['introduction']);
                                });
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
                                                        const Textarea = $('#downloadSourceCode textarea[name="p12"]');
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
                                                        const Textarea = $('#downloadSourceCode textarea[name="mobileprovision"]');
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
                                layui['form'].verify({
                                    domainCheck: function(value) {
                                        let domainPattern = /^https:\/\/([a-zA-Z0-9-]{1,63}\.)+[a-zA-Z]{2,}$/;
                                        if (!domainPattern.test(value)) {
                                            return '请输入以 https:// 开头的有效域名';
                                        }
                                    }
                                });
                                layui['form'].on('submit(download-source-code)', function(formData) {
                                    layer.load(2);
                                    SendAjax({
                                        'url': '/agent/source-code',
                                        'data': {
                                            name: formData['field']['name'],
                                            theme: formData['field']['theme'],
                                            domain: formData['field']['domain'],
                                            p12: formData['field']['p12'],
                                            mobileconfig: formData['field']['mobileprovision'],
                                            password: formData['field']['password'],
                                        },
                                        'successCallBack': function (response) {
                                            if (response['data']['is_signed'] === true) {
                                                layer.alert('<p style="color: #16b777;">本次生成的源码已提供证书文件<br>系统已自动签名获取设备码描述文件<br>您可以等待自动下载或者点击确定立即下载源码</p>', {
                                                    time: 5 * 1000,
                                                    success: function(layero, index) {
                                                        let timeNum = this.time/1000, setText = function(start) {
                                                            layer.title('<span class="layui-font-red">'+ (start ? timeNum : --timeNum) + '</span> 秒后自动下载', index);
                                                        };
                                                        setText(!0);
                                                        this.timer = setInterval(setText, 1000);
                                                        if(timeNum <= 0) clearInterval(this.timer);
                                                    },
                                                    end: function() {
                                                        clearInterval(this.timer);
                                                        RedirectTo(response['data']['url']);
                                                    }
                                                });
                                            } else {
                                                layer.alert('<p style="color: #ffb800;">本次生成的源码没有提供证书文件<br>系统不能自动签名获取设备码描述文件<br>您可以下载源码后自行签名或者直接使用<br>您可以等待自动下载或者点击确定立即下载源码</p>', {
                                                    time: 5 * 1000,
                                                    success: function(layero, index) {
                                                        let timeNum = this.time/1000, setText = function(start) {
                                                            layer.title('<span class="layui-font-red">'+ (start ? timeNum : --timeNum) + '</span> 秒后自动下载', index);
                                                        };
                                                        setText(!0);
                                                        this.timer = setInterval(setText, 1000);
                                                        if(timeNum <= 0) clearInterval(this.timer);
                                                    },
                                                    end: function() {
                                                        clearInterval(this.timer);
                                                        RedirectTo(response['data']['url']);
                                                    }
                                                });
                                            }
                                        }
                                    });
                                    return false;
                                });
                            }
                        });
                    } else {
                        layer.msg('没有获取到可用主题，请重试或联系站长。');
                    }
                }
            });
        });
        let DataTablesConfig = {
            dom: '<"row"<"col-sm-12 col-md-6"B>><"row"<"col-sm-12"tr>>"<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            language: {
                url: '/theme/bootstrap-v5.2.1/languages/' + document['documentElement']['lang'] + '.json'
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
                        PromptAndAct('设置页面数量', layui['sessionData'](StoreKey())['设置页面数量'], function(pageSize) {
                            dt.page.len(pageSize).draw();
                        });
                    }
                },
                {
                    text: '搜索',
                    action: function(e, dt) {
                        PromptAndAct('搜索内容', layui['sessionData'](StoreKey())['搜索内容'], function(search) {
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
                layui['sessionData'](window['location']['hostname'], { key: title, value: data });
                action(data);
            });
        }

        function RedirectTo(href, newWindow = false) {
            if (newWindow === true) {
                window.open(href, '_blank');
            } else {
                window['location']['href'] = href;
            }
        }
    </script>
    @yield('script')
    </body>
</html>