@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('点数充值') }}@endsection
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
</div>
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">在线充值</h4>
                <p class="card-title-desc"></p>
                <form id="usersOnlinePay" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">充值金额</label>
                        <div class="input-group">
                            <input name="money" type="text" class="form-control" pattern="^(0\.0[1-9]|[1-9]\d{0,4}(\.\d{2})?|100000(\.00)?)$" placeholder="充值金额" required>
                            <div class="invalid-feedback">请输入要充值的金额数值！</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">充值类型</label>
                        <select id="type" name="type" class="form-control select2" required>
                            <option value>请选择充值类型</option>
                            <option value="api">接口点数【用于添加私有池设备】</option>
                            <option value="public">公池点数【用于添加公共池设备】</option>
                        </select>
                        <div class="invalid-feedback">请选择本次要充值的类型！</div>
                    </div>
                    <button type="submit" class="btn btn-primary waves-effect waves-light me-1">提交充值</button>
                    <button type="reset" class="btn btn-secondary waves-effect">清除</button>
                </form>
            </div>
        </div>
    </div>
    <div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">卡密充值</h4>
                <p class="card-title-desc"></p>
                <form id="usersPay" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">充值卡密</label>
                        <div class="input-group">
                            <input name="code" type="text" class="form-control" pattern="[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$" placeholder="输入充值卡密" required>
                            <span id="buy_btn" class="input-group-btn input-group-prepend">
                                <button class="btn btn-primary bootstrap-touchspin-down" type="button"><i class="bx bxs-cart-alt"></i> 购买卡密</button>
                            </span>
                            <div class="invalid-feedback">请输入36位充值UUID 4格式卡密！</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary waves-effect waves-light me-1">提交充值</button>
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
                layui['sessionData'](window.location.hostname, { key: 'code_buy_url', value: response['data']['code_buy_url'] });
            }
        }
    });
    $(".select2").select2();
    $(document).on('submit', 'form', function(e) {
        e.preventDefault();
        let formId = $(this).attr('id');
        let formData = $("form").serialize();
        let params = new URLSearchParams(formData);
        switch (formId) {
            case 'usersPay':
                let code = params.get('code');
                if (!code?.trim()) {
                    layer.msg('请输入充值卡密', { icon: 5, time: 3000 });
                    return false;
                }
                layer.load(2);
                SendAjax({
                    'url': systemPath+'/certificate/basic/code_pay',
                    'data': formData,
                    'errorCallBack': function (error) {
                        layer.confirm(error['responseJSON']['message'] + '，是否前往购买卡密？', {
                            btn: ['前往购买', '暂不购买']
                        }, function() {
                            RedirectTo(layui['sessionData'](StoreKey())['code_buy_url'], true);
                        });
                        return false;
                    }
                });
            break;
            case 'usersOnlinePay':
                let money = params.get('money');
                if (!money?.trim()) {
                    layer.msg('请输入充值金额', { icon: 5, time: 3000 });
                    return false;
                }
                let type = params.get('type');
                if (!type?.trim()) {
                    layer.msg('请输入充值类型', { icon: 5, time: 3000 });
                    return false;
                }
                layer.load(2);
                SendAjax({
                    'url': systemPath+'/certificate/basic/payment_purchase',
                    'data': formData,
                    'successCallBack': function (response) {
                        layer.closeAll();
                        let htmlContent = response['data']['html'];
                        let tempDiv = document.createElement('div');
                        tempDiv.innerHTML = htmlContent;
                        let form = tempDiv.querySelector('form#alipaysubmit');
                        if (form) {
                            document.body.appendChild(form);
                            form.submit();
                        } else {
                            console.error('未找到支付表单');
                        }
                    },
                    'errorCallBack': function (error) {
                        layer.msg(error['responseJSON']['message'], { icon: 5, time: 3000 });
                        return false;
                    }
                });
            break;
        }
    });
    $('#buy_btn').click(function() {
        RedirectTo(layui['sessionData'](window['location']['hostname'])['code_buy_url'], true);
    });
</script>
@endsection