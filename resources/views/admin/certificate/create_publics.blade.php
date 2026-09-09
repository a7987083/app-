@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('添加设备') }}@endsection
@section('link')
@endsection

@section('content')
<div class="alert alert-primary alert-dismissible">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    <strong>平台计费须知【未仔细阅读，全部认定您已知】！</strong><hr>
    公池点数剩余 <a id="public_credit">获取中</a> 点<hr>
    当前公池费用 <a id="public_price">获取中</a> 点/台<hr>
    公池点数说明：仅限用于购买开通公池设备扣费，证书价格根据市场行情波动！<br>
    公池证书保障：所有证书均为续费老账号，100%秒出证书，不卡设备！<br>
    公池证书优势：秒出不卡优势，接口优势：可随时禁用/启用/下载证书等！<hr>
    本次价格更新时间：<a class="text-dark" id="price_date">获取中</a><hr>
</div>
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">添加设备 - 公池模式</h4>
                <p class="card-title-desc"></p>
                <form id="usersCreatePublics" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">设备UDID</label>
                        <input id="udid" name="udid" type="text" class="form-control" required>
                        <div class="invalid-feedback">请输入苹果设备UDID！</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">备注信息</label>
                        <input id="mark" name="mark" type="text" class="form-control">
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
                layui['sessionData'](window['location']['hostname'], { key: 'code_buy_url', value: response['data']['code_buy_url'] });
            }
        }
    });
    $("[name='udid']").on("input", function() {
        const value = $(this).val();
        let new_udid = /[0-9]{8}-[0-9a-fA-F]{16}$/;
        let old_udid = /[0-9a-fA-F]{40}$/;
        let mac_uuid = /[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/;
        if (new_udid.test(value) || old_udid.test(value) || mac_uuid.test(value)) {
            layer.load(2);
            $('button[type="submit"]').prop('disabled', true).text('正在检测 UDID 有效性');
            SendAjax({
                'url': systemPath+'/certificate/v1/udid_query',
                'data': {
                    udid: value,
                    type: 'public'
                },
                'successCallBack': function(response) {
                    $('button[type="submit"]').prop('disabled', false).text('提交');
                    layer.msg(response['message']);
                },
                'errorCallBack': function(error) {
                    layer.confirm(error['responseJSON']['message'], {
                        btn: ['我已阅读以上内容，并且同意可能引发的后果！']
                    }, function(index){
                        layer.close(index);
                        $('button[type="submit"]').prop('disabled', false).text('提交');
                    });
                },
            });
        } else {
            $('button[type="submit"]').prop('disabled', true).text('请输入正确的 UDID');
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
                    SendAjax({
                        url: systemPath+'/certificate/basic/create_publics',
                        data: $("form").serialize(),
                        'successCallBack': function (response) {
                            layer.msg(response['message'], { icon: 1, time: 3000 });
                        }
                    });
                }
            }, false);
        });
    }, false);
</script>
@endsection