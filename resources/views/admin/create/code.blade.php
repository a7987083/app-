@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('添加卡密') }}@endsection
@section('link')

@endsection

@section('content')
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">生成卡密</h4>
                    <p class="card-title-desc">选择您需要的生成模式进行生成卡密</p>
                    <ul class="nav nav-pills nav-justified bg-light" role="tablist">
                        <li class="nav-item waves-effect waves-light">
                            <a class="nav-link active" data-bs-toggle="tab" href="#navpills2-home" role="tab">
                                <span>系统自动生成</span>
                            </a>
                        </li>
                        <li class="nav-item waves-effect waves-light">
                            <a class="nav-link" data-bs-toggle="tab" href="#navpills2-profile" role="tab">
                                <span>输入模式生成</span>
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content p-3 text-muted">
                        <div class="tab-pane active" id="navpills2-home" role="tabpanel">
                            <form id="system" class="system" novalidate>
                                <div class="row">
                                    <input type="hidden" name="type" value="system" autocomplete="off">
                                    <div class="col-md-4">
                                        <div class="mb-3 position-relative">
                                            <div class="input-group">
                                                <div class="input-group-text">生成数量</div>
                                                <input type="text" class="form-control" name="number" placeholder="留空则生成一个">
                                                <div class="input-group-text">个</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3 position-relative">
                                            <div class="input-group">
                                                <div class="input-group-text">售后天数</div>
                                                <input type="text" class="form-control" name="after_sale_day" placeholder="留空默认365天">
                                                <div class="input-group-text">天</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3 position-relative">
                                            <div class="input-group">
                                                <div class="input-group-text">售后次数</div>
                                                <input type="text" class="form-control" name="after_sale_num" placeholder="留空默认0次">
                                                <div class="input-group-text">次</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <div class="input-group">
                                                <div class="input-group-text">备注信息</div>
                                                <input type="text" class="form-control" name="remark">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <div class="input-group">
                                                <div class="input-group-text">卡密前缀</div>
                                                <input type="text" class="form-control" name="prefix" value="{{ config('api.basics.code_generate_prefix') }}" placeholder="留空默认使用：{{ config('api.basics.code_generate_prefix') }}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <div class="input-group">
                                                <div class="input-group-text">支持机型</div>
                                                <select name="codeProduct" class="form-control">
                                                    <option value="DEFAULT">全部机型</option>
                                                    <option value="IPAD">仅限iPad</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <div class="input-group">
                                                <div class="input-group-text">卡密类型</div>
                                                <select name="codeType" class="form-control">
                                                    <option value="default">默认模式</option>
                                                    <option value="processing">预约证书</option>
                                                    <option value="good">秒出证书</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="transition">
                                        <div class="mb-3">
                                            <label class="form-label">强制让卡密使用过渡接口指定资源池创建</label>
                                            <select name="transition_type" class="form-control select2">
                                                <option value="default">根据签名系统的选择资源池</option>
                                                <option value="good">使用过滤接口的秒出资源池</option>
                                                <option value="processing">使用过滤接口的预约资源池</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">强制让卡密使用过渡接口指定的套餐创建</label>
                                            <input name="transition_create" type="text" class="form-control" placeholder="根据您过渡接口的配置，设置套餐ID 留空跟随过渡接口配置创建">
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="alert alert-primary alert-dismissible">
                                            <strong>卡密类型说明</strong>
                                            <br>默认模式：不进行筛选、根据您平台证书进行添加、优先级：第三方平台 > 证书平台
                                            <br>预约证书：优先筛选证书平台私有池卡设备证书、排除第三方平台（必须有私有池证书）
                                            <br>秒出证书：优先筛选证书平台私有池秒出证书、排除第三方平台（当没有可用私有池证书时、自动切换公池证书）
                                            <br>默认模式新增可指定过渡接口 资源池 与 套餐 创建配置（仅限默认模式）
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary waves-effect waves-light me-1">提交生成</button>
                                    <button type="reset" class="btn btn-secondary waves-effect">清除</button>
                                </div>
                            </form>
                        </div>

                        <div class="tab-pane" id="navpills2-profile" role="tabpanel">
                            <form id="textarea" class="textarea" novalidate>
                                <div class="row">
                                    <input type="hidden" name="type" value="textarea" autocomplete="off">
                                    <div class="col-md-12">
                                        <div class="mb-3 position-relative">
                                            <div class="input-group">
                                                <div class="input-group-text">输入卡密</div>
                                                <textarea name="textarea" class="form-control" rows="5" placeholder="一行一个卡密" required></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="mb-3 position-relative">
                                            <div class="input-group">
                                                <div class="input-group-text">售后天数</div>
                                                <input type="text" class="form-control" name="after_sale_day" placeholder="留空默认365天">
                                                <div class="input-group-text">天</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3 position-relative">
                                            <div class="input-group">
                                                <div class="input-group-text">售后次数</div>
                                                <input type="text" class="form-control" name="after_sale_num" placeholder="留空默认0次">
                                                <div class="input-group-text">次</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3 position-relative">
                                            <div class="input-group">
                                                <div class="input-group-text">备注信息</div>
                                                <input type="text" class="form-control" name="remark">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <div class="input-group">
                                                <div class="input-group-text">支持机型</div>
                                                <select name="codeProduct" class="form-control">
                                                    <option value="DEFAULT">全部机型</option>
                                                    <option value="IPAD">仅限iPad</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <div class="input-group">
                                                <div class="input-group-text">卡密类型</div>
                                                <select name="codeType" class="form-control">
                                                    <option value="default">默认模式</option>
                                                    <option value="processing">预约证书</option>
                                                    <option value="good">秒出证书</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="transition">
                                        <div class="mb-3">
                                            <label class="form-label">强制让卡密使用过渡接口指定资源池创建</label>
                                            <select name="transition_type" class="form-control select2">
                                                <option value="default">根据签名系统的选择资源池</option>
                                                <option value="good">使用过滤接口的秒出资源池</option>
                                                <option value="processing">使用过滤接口的预约资源池</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">强制让卡密使用过渡接口指定的套餐创建</label>
                                            <input name="transition_create" type="text" class="form-control" placeholder="根据您过渡接口的配置，设置套餐ID 留空跟随过渡接口配置创建">
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="alert alert-primary alert-dismissible">
                                            <strong>卡密类型说明</strong>
                                            <br>原始模式：不进行筛选、根据您平台证书进行添加、优先级：第三方平台 > 证书平台
                                            <br>预约证书：优先筛选证书平台私有池卡设备证书、排除第三方平台（必须有私有池证书）
                                            <br>秒出证书：优先筛选证书平台私有池秒出证书、排除第三方平台（当没有可用私有池证书时、自动切换公池证书）
                                            <br>默认模式新增可指定过渡接口 资源池 与 套餐 创建配置（仅限默认模式）
                                        </div>
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
    <script type="text/javascript" src="{{ config('api.basics.cdn') }}/theme/weui/js/file-saver.js"></script>
    <script>
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                $(".select2").select2();
                $('.nav-pills a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
                    $(".select2").select2();
                });
                let system = document.getElementsByClassName('system');
                let textarea = document.getElementsByClassName('textarea');
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
                Array.prototype.filter.call(textarea, function(form) {
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
                                data: $("#textarea").serialize(),
                                'successCallBack': function (response) {
                                    layer.confirm(`
导入数量：${response['data']['number']}个卡密<br>
售后天数：${response['data']['after_sale_day']}天<br>
售后次数：${response['data']['after_sale_num']}次<br>
支持机型：${response['data']['product']}<br>
备注信息：${response['data']['remark']}
                                `, {
                                            btn: ['下载本次导入的卡密', '关闭']
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

            $(document).on('change', 'select[name="codeType"]', function() {
                let $this = $(this);
                let value = $this.val();
                let text = $this.find('option:selected').text();
                // 触发自定义事件，方便其他地方监听
                $this.trigger('codeTypeChanged', [value, text]);
                // 根据不同的值执行不同逻辑
                handleCodeTypeChange(value, text);
            });

            // 单独的处理函数
            function handleCodeTypeChange(value, text) {
                switch(value) {
                    case 'default':
                        // 默认模式逻辑
                        lay('#transition').html(`
                        <div class="mb-3">
                            <label class="form-label">强制让卡密使用过渡接口指定资源池创建</label>
                            <select name="transition_type" class="form-control select2">
                                <option value="default">根据签名系统的选择资源池</option>
                                <option value="good">使用过滤接口的秒出资源池</option>
                                <option value="processing">使用过滤接口的预约资源池</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">强制让卡密使用过渡接口指定的套餐创建</label>
                            <input name="transition_create" type="text" class="form-control" placeholder="根据您过渡接口的配置，设置套餐ID 留空跟随过渡接口配置创建">
                        </div>
                        `);
                        console.log(text);
                        break;
                    case 'processing':
                        // 预约证书逻辑
                        lay('#transition').html('');
                        console.log(text);
                        break;
                    case 'good':
                        // 秒出证书逻辑
                        lay('#transition').html('');
                        console.log(text);
                        break;
                }
            }
        })();
    </script>
@endsection