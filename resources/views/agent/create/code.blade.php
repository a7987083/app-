@extends('agent.layouts.master')
@section('title'){{ __('添加卡密') }}@endsection
@section('link')

@endsection

@section('content')
    <div class="alert alert-primary alert-dismissible">
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        <strong>代理卡密须知【未仔细阅读，全部认定您已知】！</strong><hr>
        生成卡密均不收取费用 可任意生成卡密类型、数量、售后次数等等<hr>
        您的账户当前剩余可用点数为：{{ $agent['credit'] }} 点<hr>
        您的账户默认类型卡密收费为：{{ $agent['price'] }} 点 / 台<br>
        您的账户秒出证书卡密收费为：{{ $agent['good_price'] }} 点 / 台<br>
        您的账户预约证书卡密收费为：{{ $agent['processing_price'] }} 点 / 台<br>
        您的账户iPad默认类型卡密收费为：{{ $agent['ipad_price'] }} 点 / 台<br>
        您的账户iPad秒出证书卡密收费为：{{ $agent['ipad_good_price'] }} 点 / 台<br>
        您的账户iPad预约证书卡密收费为：{{ $agent['ipad_processing_price'] }} 点 / 台<hr>
        关于收费：激活对应类型卡密需要有足够点数、用户才能激活使用（举例：用户使用秒出证书激活绑定 那么您的账户就需要拥有 {{ $agent['good_price'] }} 点 可用额度）<hr>
        收费详情：每当用户新激活（平台尚未拥有的证书）都会扣除点数 以及 售后证书（当用户原证书吊销后 您的卡密如果有设置售后次数的话）都会扣除点数【注：如果平台已经拥有过此证书、并且有效期足够 就不会进行扣费 权当赠送代理的福利】<br>
    </div>
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">生成卡密</h4>
                    <p class="card-title-desc">生成卡密不会进行收费</p>
                    <div class="tab-content p-3 text-muted">
                        <div class="tab-pane active" id="navpills2-home" role="tabpanel">
                            <form id="system" class="system" novalidate>
                                <input type="hidden" name="type" value="system" autocomplete="off">
                                <div class="mb-3">
                                    <label class="form-label">卡密数量</label>
                                    <input name="number" type="text" class="form-control" placeholder="留空则生成一个">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">售后天数</label>
                                    <input name="after_sale_day" type="text" class="form-control" placeholder="留空默认365天">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">售后次数</label>
                                    <input name="after_sale_num" type="text" class="form-control" placeholder="留空默认0次">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">备注信息</label>
                                    <input name="remark" type="text" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">卡密前缀</label>
                                    <input name="prefix" type="text" class="form-control" value="{{ config('api.basics.code_generate_prefix') }}" placeholder="留空默认使用：{{ config('api.basics.code_generate_prefix') }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">卡密类型</label>
                                    <select name="codeType" class="form-control select2">
                                        <option value="default">原始模式</option>
                                        <option value="processing">预约证书</option>
                                        <option value="good">秒出证书</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">支持机型</label>
                                    <select name="codeProduct" class="form-control select2">
                                        <option value="DEFAULT">全部机型</option>
                                        <option value="IPAD">仅限iPad</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <div class="alert alert-primary alert-dismissible">
                                        <strong>卡密类型说明</strong>
                                        <br>原始模式：不进行筛选、根据您平台证书进行添加、优先级：第三方平台 > 证书平台
                                        <br>预约证书：优先筛选证书平台私有池卡设备证书、排除第三方平台（必须有私有池证书）
                                        <br>秒出证书：优先筛选证书平台私有池秒出证书、排除第三方平台（当没有可用私有池证书时、自动切换公池证书）
                                    </div>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary waves-effect waves-light me-1">提交生成</button>
                                    <button type="reset" class="btn btn-secondary waves-effect">清除</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script type="text/javascript" src="{{ url('/theme/weui/js/file-saver.js') }}"></script>
    <script>
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                let system = document.getElementsByClassName('system');
                Array.prototype.filter.call(system, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                            form.classList.add('was-validated');
                        } else {
                            event.preventDefault();
                            layer.load(2);
                            SendAjax({
                                url: systemPath+'/code/create',
                                data: $("#system").serialize(),
                                'successCallBack': function (response) {
                                    layer.confirm(`
生成数量：${response['data']['number']}个卡密<br>
售后天数：${response['data']['after_sale_day']}天<br>
售后次数：${response['data']['after_sale_num']}次<br>
支持机型：${response['data']['product']}<br>
备注信息：${response['data']['remark']}
                                `, {
                                            btn: ['下载本次生成的卡密', '关闭']
                                        },
                                        function() {
                                            const decodedData = atob(response['data']['codes']);
                                            const uint8Array = new Uint8Array(decodedData.length);
                                            for (let i = 0; i < decodedData.length; i++) {
                                                uint8Array[i] = decodedData.charCodeAt(i);
                                            }
                                            const blob = new Blob([uint8Array], { type: 'text/plain;charset=utf-8' });
                                            saveAs(blob, response['data']['number']+'个卡密_售后'+response['data']['after_sale_day']+'天_次数'+response['data']['after_sale_num']+'次.txt');
                                        });
                                }
                            });
                            return false;
                        }
                    }, false);
                });
            }, false);
        })();
    </script>
@endsection