@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('添加黑名单') }}@endsection
@section('link')

@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">添加黑名单</h4>
                <p class="card-title-desc">选择您要添加的黑名单类型</p>
                <ul class="nav nav-pills nav-justified bg-light" role="tablist">
                    <li class="nav-item waves-effect waves-light">
                        <a class="nav-link active" data-bs-toggle="tab" href="#navpills2-udid" role="tab">
                            <span>设备黑名单</span>
                        </a>
                    </li>
                    <li class="nav-item waves-effect waves-light">
                        <a class="nav-link" data-bs-toggle="tab" href="#navpills2-code" role="tab">
                            <span>卡密黑名单</span>
                        </a>
                    </li>
                    <li class="nav-item waves-effect waves-light">
                        <a class="nav-link" data-bs-toggle="tab" href="#navpills2-source" role="tab">
                            <span>软件源黑名单</span>
                        </a>
                    </li>
                </ul>
                <div class="tab-content p-3 text-muted">
                    <div class="tab-pane active" id="navpills2-udid" role="tabpanel">
                        <form id="udid" class="udid" novalidate>
                            <input type="hidden" name="type" value="UDID" autocomplete="off"> 
                            <div class="mb-3">
                                <label class="form-label">设备</label>
                                <input name="value" type="text" class="form-control" placeholder="要拉黑的设备">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">拉黑原因</label>
                                <input name="reason" type="text" class="form-control" placeholder="拉黑原因">
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary waves-effect waves-light me-1">提交添加</button>
                                <button type="reset" class="btn btn-secondary waves-effect">清除</button>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane" id="navpills2-code" role="tabpanel">
                        <form id="code" class="code" novalidate>
                            <input type="hidden" name="type" value="CODE" autocomplete="off">
                            <div class="mb-3">
                                <label class="form-label">卡密</label>
                                <input name="value" type="text" class="form-control" placeholder="要拉黑的卡密">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">拉黑原因</label>
                                <input name="reason" type="text" class="form-control" placeholder="拉黑原因">
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary waves-effect waves-light me-1">提交添加</button>
                                <button type="reset" class="btn btn-secondary waves-effect">清除</button>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane" id="navpills2-source" role="tabpanel">
                        <form id="source" class="source" novalidate>
                            <input type="hidden" name="type" value="SOURCE" autocomplete="off">
                            <div class="mb-3">
                                <label class="form-label">软件源</label>
                                <input name="value" type="text" class="form-control" placeholder="要拉黑的软件源（支持模糊匹配）">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">拉黑原因</label>
                                <input name="reason" type="text" class="form-control" placeholder="拉黑原因">
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary waves-effect waves-light me-1">提交添加</button>
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
<script>
    (function() {
        'use strict';
        window.addEventListener('load', function() {
            let udid = document.getElementsByClassName('udid');
            let code = document.getElementsByClassName('code');
            let source = document.getElementsByClassName('source');
            Array.prototype.filter.call(udid, function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                        form.classList.add('was-validated');
                    } else {
                        event.preventDefault();
                        layer.load(2);
                        SendAjax({
                            url: systemPath+'/blacklist/create',
                            data: $("#udid").serialize(),
                            'successCallBack': function (response) {
                                layer.confirm(`
添加或修改黑名单成功<br><br>
本次操作拉黑设备：<br>
${response['data']['value']}<br><br>
本次操作拉黑原因：<br>
${response['data']['reason']}
                                `);
                            }
                        });
                        return false;
                    }
                }, false);
            });
            Array.prototype.filter.call(code, function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                        form.classList.add('was-validated');
                    } else {
                        event.preventDefault();
                        layer.load(2);
                        SendAjax({
                            url: systemPath+'/blacklist/create',
                            data: $("#code").serialize(),
                            'successCallBack': function (response) {
                                layer.confirm(`
添加或修改黑名单成功<br><br>
本次操作拉黑卡密：<br>
${response['data']['value']}<br><br>
本次操作拉黑原因：<br>
${response['data']['reason']}
                                `);
                            }
                        });
                        return false;
                    }
                }, false);
            });
            Array.prototype.filter.call(source, function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                        form.classList.add('was-validated');
                    } else {
                        event.preventDefault();
                        layer.load(2);
                        SendAjax({
                            url: systemPath+'/blacklist/create',
                            data: $("#source").serialize(),
                            'successCallBack': function (response) {
                                layer.confirm(`
添加或修改黑名单成功<br><br>
本次操作拉黑软件源：<br>
${response['data']['value']}<br><br>
本次操作拉黑的原因：<br>
${response['data']['reason']}
                                `);
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