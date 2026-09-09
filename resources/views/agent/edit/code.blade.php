@extends('agent.layouts.master')
@section('title'){{ __('编辑卡密') }}@endsection
@section('link')

@endsection

@section('content')
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">
                    <form class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">绑定设备</label>
                                    <input name="udid" type="text" class="form-control" placeholder="绑定设备" value="{{ $arr['udid'] }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">备注信息</label>
                                    <input name="remark" type="text" class="form-control" placeholder="备注信息" value="{{ $arr['remark'] }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">售后天数</label>
                                    <input name="after_sale_day" type="text" class="form-control" placeholder="售后天数" value="{{ $arr['after_sale_day'] }}" required>
                                    <div class="invalid-feedback">请设置售后天数！</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">售后次数</label>
                                    <input name="after_sale_num" type="text" class="form-control" placeholder="售后次数" value="{{ $arr['after_sale_num'] }}" required>
                                    <div class="invalid-feedback">请设置售后次数！</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">已用售后</label>
                                    <input name="use_after_sale" type="text" class="form-control" placeholder="售后天数" value="{{ $arr['use_after_sale'] }}" required>
                                    <div class="invalid-feedback">请设置已用售后！</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">卡密状态</label>
                                    <select name="status" class="form-control select2" required>
                                        <option value="DISABLED" {{ ($arr['status'] === 'DISABLED') ? 'selected' : '' }}>已用</option>
                                        <option value="ENABLED" {{ ($arr['status'] === 'ENABLED') ? 'selected' : '' }}>未用</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">卡密类型</label>
                                    <select name="type" class="form-control select2" required disabled>
                                        <option value="default" {{ ($arr['type'] === 'default') ? 'selected' : '' }}>默认类型</option>
                                        <option value="good" {{ ($arr['type'] === 'good') ? 'selected' : '' }}>秒出证书</option>
                                        <option value="processing" {{ ($arr['type'] === 'processing') ? 'selected' : '' }}>预约证书</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">支持机型</label>
                                    <select name="product" class="form-control select2" required disabled>
                                        <option value="DEFAULT" {{ ($arr->product === 'DEFAULT') ? 'selected' : '' }}>全部机型</option>
                                        <option value="IPAD" {{ ($arr->product === 'IPAD') ? 'selected' : '' }}>仅限iPad</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">激活日期</label>
                                    <input name="verified_at" type="text" class="form-control" placeholder="激活日期" value="{{ $arr['verified_at'] }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">到期日期</label>
                                    <input name="maturity_at" type="text" class="form-control" placeholder="到期日期" value="{{ $arr['maturity_at'] }}">
                                </div>
                            </div>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-1">提交修改</button>
                            <button type="reset" class="btn btn-secondary waves-effect">清除</button>
                        </div>
                    </form>
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
                $(".select2").select2();
                let forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                            form.classList.add('was-validated');
                        } else {
                            event.preventDefault();
                            layer.load(2);
                            SendAjax({
                                url: systemPath+'/code/edit/{{ $arr['code'] }}',
                                data: $("form").serialize(),
                                successCallBack: function (response) {
                                    layer.prompt({
                                        formType: 2,
                                        title: '修改卡密成功',
                                        value: `
卡密信息：${response['data']['code']}\n
绑定设备：${response['data']['udid']}\n
备注信息：${response['data']['remark']}\n
售后天数：${response['data']['after_sale_day']}\n
售后次数：${response['data']['after_sale_num']}\n
已用售后：${response['data']['use_after_sale']}\n
卡密状态：${response['data']['status']}\n
卡密类型：${response['data']['type']}\n
支持机型：${response['data']['product']}\n
激活日期：${response['data']['verified_at']}\n
到期日期：${response['data']['maturity_at']}
`,
                                        area: ['500px', '300px']
                                    }, function(value, index) {
                                        layer.close(index);
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