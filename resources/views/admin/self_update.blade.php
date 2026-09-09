@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('整站更新') }}@endsection
@section('link')
@endsection

@section('content')
    <div class="modal fade" id="auth-info-modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="auth-info-name"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>
                <div class="modal-body text-center">
                    <p class="mb-2">授权状态：<span ID="auth-info-status"></span></p>
                    <p class="mb-2">授权时效：<span ID="auth-info-cycle"></span></p>
                    <p class="mb-2">激活日期：<span ID="auth-info-verified_at"></span></p>
                    <p class="mb-2">到期日期：<span ID="auth-info-maturity_at"></span></p>
                    <p class="mb-2">更新日期：<span ID="auth-info-updated_at"></span></p>
                    <div class="mb-3">
                        <label for="recipient-name" class="col-form-label">绑定域名</label>
                        <input type="text" class="form-control" id="auth-info-domain" value="">
                    </div>
                    <div class="mb-3">
                        <label for="recipient-name" class="col-form-label">授权代码【请勿泄露 否则后果自负】</label>
                        <div class="input-group">
                            <input name="code" type="text" class="form-control" id="auth-info-code" value="{{ config('api.app.code') }}" required="">
                            <span id="auth_btn" class="input-group-btn input-group-prepend">
                                <button class="btn btn-primary bootstrap-touchspin-down" type="button">更换授权</button>
                            </span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary waves-effect waves-light" id="buy_auth">点击前往购买官方授权码</button>
                </div>
            </div>
        </div>
    </div>
    <div class="d-lg-flex mb-4">
        <div class="chat-leftsidebar card">
            <div class="p-3 px-4">
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0 me-3 align-self-center">
                        <img src="https://q1.qlogo.cn/g?b=qq&nk=1276117137&s=640" class="avatar-xs rounded-circle" alt="logo">
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="font-size-16 mb-1"><a class="text-dark">{{ $getConfig['name'] }} <i class="mdi mdi-circle text-success align-middle font-size-10 ms-1"></i></a></h5>
                        <p class="text-muted mb-0">教程与文档：{{ $getConfig['doc_url'] }}</p>
                    </div>
                </div>
            </div>
            <div class="pb-3">
                <div class="chat-message-list" data-simplebar>
                    <div class="p-4 border-top">
                        <div>
                            <h5 class="font-size-16 mb-3"><i class="uil uil-processor me-1"></i> 源码与授权信息</h5>
                            <ul class="list-unstyled chat-list group-list">
                                <li>
                                    <a href="#" id="system-info">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="user-img online">
                                                    <img src="https://q1.qlogo.cn/g?b=qq&nk=1276117137&s=640" class="rounded-circle avatar-xs" alt="logo">
                                                    <span class="user-status"></span>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 overflow-hidden">
                                                <h5 class="font-size-14 mb-0">系统版本号</h5>
                                                <p class="text-truncate mb-0">{{ config('api.app.version') }}</p>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <a href="#" id="auth-info">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="user-img online">
                                                    <img src="https://q1.qlogo.cn/g?b=qq&nk=1276117137&s=640" class="rounded-circle avatar-xs" alt="logo">
                                                    <span class="user-status"></span>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 overflow-hidden">
                                                <h5 class="text-truncate font-size-14 mb-1">点击查看授权信息</h5>
                                                <p class="text-truncate mb-0">{{ request()->host() }}</p>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="p-4 border-top">
                        <div>
                            <h5 class="font-size-16 mb-3">
                                <i class="uil uil-processor me-1"></i> 站点 PHP 信息
                            </h5>
                            <ul class="list-unstyled chat-list group-list module-list">
                                <li>
                                    <a href="#">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="avatar-xs">
                                                    <span class="avatar-title rounded-circle bg-soft-primary text-primary">PHP</span>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h5 class="font-size-14 mb-0">版本号</h5>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <div class="font-size-11">{{ phpversion() }}</div>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <a href="#">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="avatar-xs">
                                                    <span class="avatar-title rounded-circle bg-soft-primary text-primary">PHP</span>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h5 class="font-size-14 mb-0">脚本最大内存</h5>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <div class="font-size-11">{{ ini_get('memory_limit') }}</div>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <a href="#">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="avatar-xs">
                                                    <span class="avatar-title rounded-circle bg-soft-primary text-primary">PHP</span>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h5 class="font-size-14 mb-0">脚本最大执行时间</h5>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <div class="font-size-11">{{ ini_get('max_execution_time') }} 秒</div>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <a href="#">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="avatar-xs">
                                                    <span class="avatar-title rounded-circle bg-soft-primary text-primary">PHP</span>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h5 class="font-size-14 mb-0">上传文件最大大小</h5>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <div class="font-size-11">{{ ini_get('upload_max_filesize') }}</div>
                                            </div>
                                        </div>
                                    </a>
                                </li>

                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="w-100 user-chat mt-4 mt-sm-0 ms-lg-1">
            <div class="card">
                <div class="p-3 px-lg-4 border-bottom">
                    <div class="row">
                        <div class="btn-toolbar p-3" role="toolbar">
                            <div id="update" class="btn-group me-2 mb-2 mb-sm-0">
                                <div class="btn btn-primary waves-light waves-effect">检测更新</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="card mb-0">
                        <div>
                            <div class="chat-conversation py-3">
                                <ul class="list-unstyled mb-0 chat-conversation-message px-3" data-simplebar="init">
                                    <div class="simplebar-wrapper" style="margin: 0 -16px;">
                                        <div class="simplebar-mask">
                                            <div class="simplebar-offset" style="right: -20px; bottom: 0;">
                                                <div class="simplebar-content-wrapper" style="height: 100%; padding-right: 20px; padding-bottom: 0; overflow: hidden scroll;">
                                                    <div class="simplebar-content" style="padding: 0 16px;" id="progression"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="simplebar-placeholder" style="width: auto; height: 803px;"></div>
                                    </div>
                                </ul>
                            </div>
                        </div>
                        <div class="tab-content p-4"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        let exclusion_update = '{{ config('api.basics.exclusion_update') }}';
        if (exclusion_update !== '') {
            const exclusion_list = exclusion_update.split('\r\n');
            exclusion_list.forEach((item) => {
                logs(`本次更新将排除包含 ${item} 的文件覆盖更新`);
            });
        }

        $('#update').click(function() {
            layer.load(2);
            SendAjax({
                url: systemPath+'/self_update/show',
                'successCallBack': function (response) {
                    layer.open({
                        anim: 'slideDown',
                        area: ['90%', '70%'],
                        title: [`${response['data']['title']}`, 'font-size: 18px;'],
                        closeBtn: 0,
                        content: `更新内容：<br>${response['data']['message']}`,
                        btn: ['立即更新', '稍后更新'],
                        btn1: function(index) {
                            layer.close(index);
                            logs(`正在准备更新版本：${response['data']['version']}`);
                            update(response['data']['hash']);
                        },
                        btn2: function(index) {
                            layer.close(index);
                        }
                    });
                }
            });
        });

        function update(hash) {
            SendAjax({
                url: systemPath+'/self_update/update',
                data: {
                    hash: hash
                },
                'successCallBack': function (response) {
                    switch (response['data']['status']) {
                        case 'pending':
                            logs(response['data']['message']);
                            update(hash);
                            break;
                        case 'completed':
                            logs(response['data']['message']);
                            logs('请您手动重启进程守护 避免系统执行的还是老版本代码');
                            break;
                        case 'failed':
                            logs(response['data']['message']);
                            break;
                        default:
                            logs(response['message']);
                            update(hash);
                            break;
                    }
                },
                'errorCallBack': function (error) {
                    switch (error['responseJSON']['data']['status']) {
                        case 'pending':
                            logs(error['responseJSON']['data']['message']);
                            update(hash);
                            break;
                        case 'completed':
                            logs(error['responseJSON']['data']['message']);
                            logs('请您手动重启进程守护 避免系统执行的还是老版本代码');
                            break;
                        case 'failed':
                            logs(error['responseJSON']['data']['message']);
                            break;
                        default:
                            logs(error['responseJSON']['message']);
                            update(hash);
                            break;
                    }
                }
            });
        }

        function logs(msg) {
            const progression = document.getElementById('progression');
            const newLi = document.createElement('li');
            newLi.className = 'chat-day-title';
            const newDiv = document.createElement('div');
            newDiv.className = 'title';
            newDiv.textContent = msg;
            newLi.appendChild(newDiv);
            progression.append(newLi);
        }

        $('#auth-info').click(function() {
            layer.load(2);
            SendAjax({
                url: 'https://accredit.v-team.cn/api/v1/query',
                data: {
                    hash: 'query',
                    domain: window['location']['hostname']
                },
                'successCallBack': function (response) {
                    $('#auth-info-name').text('{{ $getConfig['name'] }}');
                    $('#auth-info-status').html(response['data']['status']);
                    $('#auth-info-cycle').html(response['data']['cycle']);
                    $('#auth-info-verified_at').text(response['data']['verified_at']);
                    $('#auth-info-maturity_at').text(response['data']['maturity_at']);
                    $('#auth-info-updated_at').text(response['data']['updated_at']);
                    $('#auth-info-domain').val(response['data']['domain']);
                    let modal = new bootstrap.Modal(document.getElementById('auth-info-modal'));
                    modal.show();
                }
            });
        });
        $('#auth_btn').click(function() {
            layer.load(2);
            SendAjax({
                url: '/{{ config('api.admin.path', 'admin') }}/update_auth',
                data: {
                    code: $('#auth-info-code').val()
                },
                'successCallBack': function (response) {
                    layer.msg(response['message'], { icon: 1, time: 3000 });
                },
                'errorCallBack': function (error) {
                    layer.msg(error['responseJSON']['message'], { icon: 2, time: 3000 });
                }
            });
        });
        $('#buy_auth').click(function() {
            layer.load(2);
            SendAjax({
                url: '/{{ config('api.admin.path', 'admin') }}/get_config',
                'successCallBack': function (response) {
                    RedirectTo(response['data']['buy_auth'], true);
                },
                'errorCallBack': function (error) {
                    layer.msg(error['responseJSON']['message'], { icon: 2, time: 3000 });
                }
            });
        });
    </script>
@endsection
