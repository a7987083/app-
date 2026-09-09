<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title')丨{{ config('app.name', 'Laravel') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="system-path" content="/{{ config('api.admin.path', 'admin') }}">
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
                        <span class="d-none d-xl-inline-block ms-1 fw-medium font-size-15">{{ Str::ucfirst(Auth::user()->name) }}</span>
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
                        <form id="logout-form" action="{{ route(config('api.admin.path', 'admin').'.logout') }}" method="POST" style="display: none;">
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
                                <a class="nav-link" href="{{ route(config('api.admin.path', 'admin').'.console') }}">
                                    <i class="uil-home-alt me-2"></i> {{ __('数据统计') }}
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none" href="#" id="topnav-sign" role="button">
                                    <i class="uil-apps me-2"></i> {{ __('签名相关') }} <div class="arrow-down"></div>
                                </a>
                                <div class="dropdown-menu" aria-labelledby="topnav-sign">
                                    <div class="dropdown">
                                        <a class="dropdown-item dropdown-toggle arrow-none" href="#" id="topnav-sign-app" role="button">
                                            <i class="uil-apple-alt me-2"></i> {{ __('软件管理') }} <div class="arrow-down"></div>
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="topnav-sign-app">
                                            <a href="{{ route(config('api.admin.path', 'admin').'.app') }}" class="dropdown-item">
                                                <i class="uil-apple-alt me-2"></i> {{ __('软件列表') }}
                                            </a>
                                            <a href="{{ route(config('api.admin.path', 'admin').'.app.create') }}" class="dropdown-item">
                                                <i class="uil-cloud-upload me-2"></i> {{ __('上传软件') }}
                                            </a>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a class="dropdown-item dropdown-toggle arrow-none" href="#" id="topnav-sign-class" role="button">
                                            <i class="uil-data-sharing me-2"></i> {{ __('分类管理') }} <div class="arrow-down"></div>
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="topnav-sign-class">
                                            <a href="{{ route(config('api.admin.path', 'admin').'.class') }}" class="dropdown-item">
                                                <i class="uil-data-sharing me-2"></i> {{ __('分类列表') }}
                                            </a>
                                            <a href="{{ route(config('api.admin.path', 'admin').'.class.create') }}" class="dropdown-item">
                                                <i class="uil-cloud-upload me-2"></i> {{ __('添加分类') }}
                                            </a>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a class="dropdown-item dropdown-toggle arrow-none" href="#" id="topnav-sign-code" role="button">
                                            <i class="uil-card-atm me-2"></i> {{ __('卡密管理') }} <div class="arrow-down"></div>
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="topnav-sign-code">
                                            <a href="{{ route(config('api.admin.path', 'admin').'.code') }}" class="dropdown-item">
                                                <i class="uil-card-atm me-2"></i> {{ __('卡密列表') }}
                                            </a>
                                            <a href="{{ route(config('api.admin.path', 'admin').'.code.create') }}" class="dropdown-item">
                                                <i class="uil-cloud-upload me-2"></i> {{ __('添加卡密') }}
                                            </a>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a class="dropdown-item dropdown-toggle arrow-none" href="#" id="topnav-sign-help" role="button">
                                            <i class="uil-comment-info me-2"></i> {{ __('帮助管理') }} <div class="arrow-down"></div>
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="topnav-sign-help">
                                            <a href="{{ route(config('api.admin.path', 'admin').'.help') }}" class="dropdown-item">
                                                <i class="uil-comment-info me-2"></i> {{ __('帮助列表') }}
                                            </a>
                                            <a href="{{ route(config('api.admin.path', 'admin').'.help.create') }}" class="dropdown-item">
                                                <i class="uil-cloud-upload me-2"></i> {{ __('添加帮助') }}
                                            </a>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a class="dropdown-item dropdown-toggle arrow-none" href="#" id="topnav-sign-about_us" role="button">
                                            <i class="uil-comment-lines me-2"></i> {{ __('介绍管理') }} <div class="arrow-down"></div>
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="topnav-sign-about_us">
                                            <a href="{{ route(config('api.admin.path', 'admin').'.about_us') }}" class="dropdown-item">
                                                <i class="uil-comment-lines me-2"></i> {{ __('介绍列表') }}
                                            </a>
                                            <a href="{{ route(config('api.admin.path', 'admin').'.about_us.create') }}" class="dropdown-item">
                                                <i class="uil-cloud-upload me-2"></i> {{ __('添加介绍') }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none" href="#" id="topnav-cert" role="button">
                                    <i class="uil-apps me-2"></i> {{ __('证书相关') }} <div class="arrow-down"></div>
                                </a>
                                <div class="dropdown-menu" aria-labelledby="topnav-cert">
                                    <a href="{{ url('/'.config('api.admin.path', 'admin').'/certificate/console') }}" class="dropdown-item">
                                        <i class="uil-apple-alt me-2"></i> {{ __('数据统计') }}
                                    </a>
                                    <a href="{{ url('/'.config('api.admin.path', 'admin').'/certificate/info') }}" class="dropdown-item">
                                        <i class="uil-user-circle me-2"></i> {{ __('个人资料') }}
                                    </a>
                                    <a href="{{ url('/'.config('api.admin.path', 'admin').'/certificate/pay') }}" class="dropdown-item">
                                        <i class="uil-data-sharing me-2"></i> {{ __('点数充值') }}
                                    </a>
                                    <div class="dropdown">
                                        <a class="dropdown-item dropdown-toggle arrow-none" href="#" id="topnav-cert-cert" role="button">
                                            <i class="uil-card-atm me-2"></i> {{ __('证书管理') }} <div class="arrow-down"></div>
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="topnav-cert-cert">
                                            <a href="{{ url('/'.config('api.admin.path', 'admin').'/certificate/certificate') }}" class="dropdown-item">
                                                <i class="uil-card-atm me-2"></i> {{ __('证书列表') }}
                                            </a>
                                            <a href="{{ url('/'.config('api.admin.path', 'admin').'/certificate/create_certificate') }}" class="dropdown-item">
                                                <i class="uil-cloud-upload me-2"></i> {{ __('添加证书') }}
                                            </a>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a class="dropdown-item dropdown-toggle arrow-none" href="#" id="topnav-cert-udids" role="button">
                                            <i class="uil-desktop me-2"></i> {{ __('设备管理') }} <div class="arrow-down"></div>
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="topnav-cert-udids">
                                            <a href="{{ url('/'.config('api.admin.path', 'admin').'/certificate/devices') }}" class="dropdown-item">
                                                <i class="uil-desktop me-2"></i> {{ __('设备列表') }}
                                            </a>
                                            <a href="{{ url('/'.config('api.admin.path', 'admin').'/certificate/create_devices') }}" class="dropdown-item">
                                                <i class="uil-cloud-upload me-2"></i> {{ __('创建设备') }}
                                            </a>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a class="dropdown-item dropdown-toggle arrow-none" href="#" id="topnav-cert-publics" role="button">
                                            <i class="uil-comment-lines me-2"></i> {{ __('证书公池') }} <div class="arrow-down"></div>
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="topnav-cert-publics">
                                            <a href="{{ url('/'.config('api.admin.path', 'admin').'/certificate/publics') }}" class="dropdown-item">
                                                <i class="uil-comment-lines me-2"></i> {{ __('公池列表') }}
                                            </a>
                                            <a href="{{ url('/'.config('api.admin.path', 'admin').'/certificate/create_publics') }}" class="dropdown-item">
                                                <i class="uil-cloud-upload me-2"></i> {{ __('添加设备') }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none" href="#" id="topnav-agent" role="button">
                                    <i class="uil-users-alt me-2"></i> {{ __('代理相关') }} <div class="arrow-down"></div>
                                </a>
                                <div class="dropdown-menu" aria-labelledby="topnav-agent">
                                    <a href="{{ route(config('api.admin.path', 'admin').'.agent') }}" class="dropdown-item">
                                        <i class="uil-users-alt me-2"></i> {{ __('代理列表') }}
                                    </a>
                                    <a href="{{ route(config('api.admin.path', 'admin').'.agent.create') }}" class="dropdown-item">
                                        <i class="uil-user-plus me-2"></i> {{ __('添加代理') }}
                                    </a>
                                </div>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none" href="#" id="topnav-blacklist" role="button">
                                    <i class="uil-users-alt me-2"></i> {{ __('黑名单相关') }} <div class="arrow-down"></div>
                                </a>
                                <div class="dropdown-menu" aria-labelledby="topnav-blacklist">
                                    <a href="{{ route(config('api.admin.path', 'admin').'.blacklist') }}" class="dropdown-item">
                                        <i class="uil-users-alt me-2"></i> {{ __('黑名单列表') }}
                                    </a>
                                    <a href="{{ route(config('api.admin.path', 'admin').'.blacklist.create') }}" class="dropdown-item">
                                        <i class="uil-user-plus me-2"></i> {{ __('添加黑名单') }}
                                    </a>
                                </div>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route(config('api.admin.path', 'admin').'.settings') }}">
                                    <i class="uil-cog me-2"></i> {{ __('系统设置') }}
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route(config('api.admin.path', 'admin').'.self_update') }}">
                                    <i class="uil-processor me-2"></i> {{ __('整站更新') }}
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
                    if (response['data']['speed-cert-v2-token']) {
                        layui['sessionData'](StoreKey(), { key: 'speed-cert-v2-token', value: response['data']['speed-cert-v2-token'] });
                    }
                    layer.msg(response['message'], {icon: 1, time: 3000}, function(){
                        location.reload();
                    });
                    return false;
                }
            }
            if (typeof params.errorCallBack !== 'undefined') {
                errorCallBack = params['errorCallBack'];
            } else {
                errorCallBack = function (error) {
                    if (error['responseJSON']['message']) {
                        if (error['responseJSON']['message'] == 'Server Error') {
                            layer.msg('未知响应，请刷新查看结果', {icon: 3, time: 3000});
                        } else {
                            layer.msg(error['responseJSON']['message'], {icon: 5, time: 3000});
                        }
                    } else {
                        layer.msg('接口出现未知错误', {icon: 5, time: 3000});
                    }
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
        if (localStorage.getItem('clause') !== 'ConsentClause') {
            layer.load(2);
            SendAjax({
                url: '/{{ config('api.admin.path', 'admin') }}/get_config',
                'successCallBack': function (response) {
                    layer.open({
                        anim: 'slideDown',
                        area: ['90%', '70%'],
                        title: ['服务条款与免责协议', 'font-size: 18px;'],
                        closeBtn: 0,
                        content: `
        <b>甲方（提供方）：${response['data']['name']}</b>
        <br>
        <b>乙方（使用方）：当前站点所有者（${window['location']['hostname']}）</b>
        <br>
        <b>甲乙双方在平等、自愿、诚信的基础上，经友好协商，就甲方为乙方提供互联网技术账号服务的相关事宜，为确保服务的合法合规使用，明确双方权利与义务，特订立本协议，双方共同遵守：</b>
        <br><hr>
        <b>1. 服务描述与责任限制</b>
        <br><br>1.1 开发声明：${response['data']['name']} 的开发目的仅限于公司内部应用、本地环境和测试分发。任何对外开放的使用需由乙方自行承担法律责任，并且甲方不对此负责。
        <br><br>1.2 服务描述：本协议适用于服务方（以下简称“甲方”）向乙方提供 ${response['data']['name']} 的使用权销售服务，包括软件许可、技术支持等。
        <br><br>1.3 使用者责任：乙方使用本系统上传应用程序时，应自行承担所有法律责任（确保您上传的应用得到了版权方的授权）。甲方不对乙方上传的应用内容承担任何法律责任。
        <br><br>1.4 使用权终止：甲方保留发现乙方使用本系统违反法律或侵犯第三方权益的权利。一经发现，甲方有权立即停止乙方的使用权，并不退还任何费用。
        <br><br>1.5 合法合规使用：乙方明确知晓并同意，本系统应用于合法合规的应用分发、测试与发布流程中，不得从事任何违反法律法规、侵害第三方权益的行为，包括但不限于开发含有恶意代码、侵犯版权或隐私的应用程序。
        <br><br>1.6 禁止行为：乙方不得利用甲方提供的服务进行任何违法活动，包括但不限于分发非法软件、进行网络攻击、侵犯用户隐私等。同时，乙方不得将系统使用权转租、转售给任何第三方，或以任何形式泄露给未经授权的个人或实体。
        <br><br>1.7 合规性承诺：乙方在使用服务过程中，应当遵守所有适用的国际、国内法律法规，以及苹果公司关于开发者账号使用的规定和指南，确保所有开发及分发活动的合规性。乙方应主动了解并适应相关法律法规的变化，确保自身的使用行为始终符合最新要求。
        <br><hr>
        <br><br><b>2. 费用与支付条款</b>
        <br><br>2.1 费用：乙方应按照约定支付 ${response['data']['name']} 的使用权费用，具体金额和支付方式由双方协商确定。
        <br><br>2.2 支付：乙方应按时支付费用。如未能按时支付，甲方有权暂停或终止服务，并保留法律追索权利。
        <br><hr>
        <br><br><b>3. 免责声明</b>
        <br><br>3.1 免责条款：除非另有明确约定，甲方不承担以下责任：
        <br><br>（a）因乙方使用本系统导致的任何直接、间接、特别或后果性损失；
        <br><br>（b）由于第三方软件或服务（包括但不限于 Apple 的政策变更、iOS 系统更新等）引起的系统不兼容或功能失效；
        <br><br>（c）乙方操作失误或未经授权使用签名系统而导致的损失或责任；
        <br><br>（d）因不可抗力或其他无法预见、避免并且不能克服的事件而造成的损失。
        <br><br>3.2 知识产权：${response['data']['name']} 的知识产权归属于甲方所有，乙方仅获得有限的非独占使用许可。
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
                        btn1: function(index) {
                            localStorage.setItem('clause', 'ConsentClause');
                            layer.close(index);
                        },
                        btn2: function() {
                            event.preventDefault();
                            document.getElementById('logout-form').submit();
                        }
                    });
                },
                'errorCallBack': function (error) {
                    layer.msg(error['responseJSON']['message'], { icon: 2, time: 3000 });
                    layer.load(2);
                }
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
                            'url': systemPath+'/change-password',
                            'data': {
                                password: formData['field']['password'],
                                new_password: formData['field']['new_password'],
                                new_password_confirmation: formData['field']['new_password_confirmation'],
                            },
                            'successCallBack': function () {
                                layer.msg('密码修改成功，即将跳转登录页', {icon: 1, time: 3000}, function() {
                                    RedirectTo(systemPath+'/login');
                                });
                            }
                        });
                        return false;
                    });
                }
            });
        });

        let DataTablesConfig = {
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

        function GetChartColorsArray(chartId) {
            if (document.getElementById(chartId) !== null) {
                let colors = document.getElementById(chartId).getAttribute('data-colors');
                if (colors) {
                    colors = JSON.parse(colors);
                    return colors.map(function (value) {
                        const newValue = value.replace(' ', '');
                        if (newValue.indexOf(',') === -1) {
                            const color = getComputedStyle(document.documentElement).getPropertyValue(newValue);
                            if (color) return color;else return newValue;
                        } else {
                            const val = value.split(',');
                            if (val.length === 2) {
                                let rgbaColor = getComputedStyle(document.documentElement).getPropertyValue(val[0]);
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