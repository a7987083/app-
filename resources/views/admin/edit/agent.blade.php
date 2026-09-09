@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('编辑代理') }}@endsection
@section('link')

@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body">
                <form class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">代理邮箱</label>
                                <input name="email" type="text" class="form-control" placeholder="代理邮箱" value="{{ $arr->email }}" required>
                                <div class="invalid-feedback">请设置代理邮箱！</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">代理备注</label>
                                <input name="remark" type="text" class="form-control" placeholder="备注信息" value="{{ $arr->remark }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">代理名称</label>
                                <input name="name" type="text" class="form-control" placeholder="代理名称" value="{{ $arr->name }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">代理密码</label>
                                <input name="password" type="text" class="form-control" placeholder="留空则不进行密码修改">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">可用点数</label>
                                <input name="credit" type="text" class="form-control" value="{{ $arr->credit }}" placeholder="可用点数">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">默认模式设备价格</label>
                                <input name="price" type="text" class="form-control" value="{{ $arr->price }}" placeholder="默认模式卡密新增设备扣除的点数">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">秒出证书设备价格</label>
                                <input name="good_price" type="text" class="form-control" value="{{ $arr->good_price }}" placeholder="秒出证书卡密新增设备扣除的点数">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">预约证书设备价格</label>
                                <input name="processing_price" type="text" class="form-control" value="{{ $arr->processing_price }}" placeholder="预约证书卡密新增设备扣除的点数">
                            </div>
                        </div>


                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">iPad默认模式设备价格</label>
                                <input name="ipad_price" type="text" class="form-control" value="{{ $arr->ipad_price }}" placeholder="iPad默认模式卡密新增设备扣除的点数">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">iPad秒出证书设备价格</label>
                                <input name="ipad_good_price" type="text" class="form-control" value="{{ $arr->ipad_good_price }}" placeholder="iPad秒出证书卡密新增设备扣除的点数">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">iPad预约证书设备价格</label>
                                <input name="ipad_processing_price" type="text" class="form-control" value="{{ $arr->ipad_processing_price }}" placeholder="iPad预约证书卡密新增设备扣除的点数">
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
                            url: systemPath+'/agent/edit/{{ $arr->id }}',
                            data: $("form").serialize(),
                            successCallBack: function (response) {
                                layer.prompt({
                                    formType: 2,
                                    title: '修改代理成功',
                                    value: `
代理名称：${response['data']['name']}\n
备注信息：${response['data']['remark']}\n
邮箱地址：${response['data']['email']}\n
登录密码：${response['data']['password']}\n
可用点数：${response['data']['credit']}\n
对接令牌：${response['data']['token']}\n
默认模式开通价格：${response['data']['price']}\n
秒出证书开通价格：${response['data']['good_price']}\n
预约证书开通价格：${response['data']['processing_price']}\n
iPad默认模式开通价格：${response['data']['ipad_price']}\n
iPad秒出证书开通价格：${response['data']['ipad_good_price']}\n
iPad预约证书开通价格：${response['data']['ipad_processing_price']}
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