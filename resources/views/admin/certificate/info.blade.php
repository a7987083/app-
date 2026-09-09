@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('个人资料') }}@endsection
@section('link')
@endsection

@section('content')
<div class="alert alert-primary alert-dismissible">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    <strong>平台计费须知【未仔细阅读，全部认定您已知】！</strong><hr>
    接口点数剩余 <a id="api_credit">获取中</a> 点<br>
    公池点数剩余 <a id="public_credit">获取中</a> 点<hr>
    当前接口费用 <a id="api_price">获取中</a> 点/台<br>
    当前公池费用 <a id="public_price">获取中</a> 点/台<hr>
    接口点数说明：仅限用于自有证书添加设备扣费、查询以及其他操作不会扣费！<br>
    公池点数说明：仅限用于购买开通公池设备扣费，证书价格根据市场行情波动！<br>
    公池证书保障：所有证书均为续费老账号，100%秒出证书，不卡设备！<br>
    公池证书优势：秒出不卡优势，接口优势：可随时禁用/启用/下载证书等！<hr>
    本次价格更新时间：<a class="text-dark" id="price_date">获取中</a><hr>
    <b>请妥善选择充值类型，一点充值，恕不更改！</b>
</div>
<div class="row">
    <div class="col-lg-12">
        <div class="card card-body text-center">
            <h4 class="card-title">我的Token令牌</h4>
            <p class="card-title-desc">Token令牌用于本平台各种接口对接，包含您的账户各种敏感信息与设置。请勿泄露 Token 令牌，并且不定期更新！</p>
            <button type="button" class="btn btn-primary waves-effect waves-light" id="copy_token">查看Token令牌</button>
            <br>
            <button type="button" class="btn btn-primary waves-effect waves-light" id="reset_token">重置Token令牌</button>
        </div>
    </div>
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body">
                <form class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">原证书管理平台密码</label>
                        <input id="password" name="password" type="password" class="form-control" required>
                        <div class="invalid-feedback">请输入原来的密码！</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">新证书管理平台密码</label>
                        <input id="new_password" name="new_password" type="text" class="form-control" required>
                        <div class="invalid-feedback">请输入要设置的新密码！</div>
                    </div>
                    <button type="submit" class="btn btn-primary waves-effect waves-light me-1">提交</button>
                    <button type="reset" class="btn btn-secondary waves-effect">清除</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    layer.load(2);
    SendAjax({
        url: systemPath+'/certificate/basic/public_cert_price',
        'successCallBack': function (response) {
            if (response['status'] === true) {
                lay('#api_price').html(response['data']['api_price']);
                lay('#api_credit').html(response['data']['api_credit']);
                lay('#public_price').html(response['data']['public_price']);
                lay('#public_credit').html(response['data']['public_credit']);
                lay('#public_rate').html(response['data']['public_rate']);
                lay('#price_date').html(response['data']['date']);
            }
        }
    });
    window.addEventListener('load', function() {
        const forms = document.getElementsByClassName('needs-validation');
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
                        url: systemPath+'/certificate/basic/change_password',
                        data: $("form").serialize(),
                        'successCallBack': function (response) {
                            layer.msg(response['message'], { icon: 1, time: 3000 });
                        }
                    });
                }
            }, false);
        });
    }, false);
    $('#copy_token').click(function () {
        layer.prompt({title: '验证证书管理平台密码'}, function(password, index){
            layer.close(index);
            if (!password || password.length < 6) {
                layer.msg('证书管理平台密码密码长度至少为6位', { icon: 2, time: 5000 });
                return false;
            }
            layer.load(2);
            SendAjax({
                'url': systemPath+'/certificate/v1/token',
                'data': {
                    password: password
                },
                'successCallBack': function (response) {
                    layer.prompt({
                        formType: 2,
                        title: '您的 Token 令牌',
                        value: `${response['data']['token']}\n\n注意：\nToken 令牌为您的账户免登录凭证、请勿：泄露、转让、借用等操作，否则出现任何问题自行承担后果！`,
                        area: ['500px', '150px']
                    }, function(value, index) {
                        layer.close(index);
                    });
                }
            });
        });
    });
    $('#reset_token').click(function () {
        layer.prompt({title: '验证证书管理平台密码'}, function(text, index){
            layer.close(index);
            if (!text || text.length < 6) {
                layer.msg('证书管理平台密码密码长度至少为6位', { icon: 2, time: 5000 });
                return false;
            }
            layer.load(2);
            SendAjax({
                url: systemPath+'/certificate/basic/reset_token',
                data: {
                    password: text
                },
                'successCallBack': function (response) {
                    layer.prompt({
                        title: '您的新 Token 令牌',
                        value: response['data']['token']
                    }, function(value, index) {
                        layer.close(index);
                    });
                }
            });
        });
    });
</script>
@endsection