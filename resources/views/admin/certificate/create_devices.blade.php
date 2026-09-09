@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('创建设备') }}@endsection
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
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">添加设备 - 新接口模式「P8模式」</h4>
                <p class="card-title-desc"><code>您已经是开发者证书管理开发者，您可以在这里免费添加苹果个人开发者证书进行一键式销售。</code></p>
                <form id="usersCreateDdevices" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">设备UDID</label>
                        <input id="udid" name="udid" type="text" class="form-control" required>
                        <div class="invalid-feedback">请输入苹果设备UDID！</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">使用证书</label>
                        <select id="iss" name="iss" class="form-control select2"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">添加模式</label>
                        <select id="special" name="special" class="form-control select2">
                            <option value>官方默认</option>
                            <option value="mac">使用MAC额度添加</option>
                            <option value="vip">会员超开700设备</option>
                        </select>
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
    layer.load(2);
    SendAjax({
        'url': systemPath+'/certificate/v1/lists_certificate',
        'data': {
            start: 0,
            search: {
                value: '',
                regex: false
            },
            order: [
                {
                    dir: 'asc',
                    column: 1
                },
                {
                    dir: 'asc',
                    column: 2
                },
                {
                    dir: 'asc',
                    column: 3
                }
            ],
            length: 1000,
            draw: 0,
            columns: [
                {
                    searchable: true,
                    search: {
                        value: 'ENABLED',
                        regex: false
                    },
                    orderable: true,
                    name: '',
                    data: 'switch'
                },
                {
                    searchable: true,
                    search: {
                        value: '',
                        regex: false
                    },
                    orderable: true,
                    name: '',
                    data: 'IPHONE'
                },
                {
                    searchable: true,
                    search: {
                        value: '',
                        regex: false
                    },
                    orderable: true,
                    name: '',
                    data: 'MAC'
                },
                {
                    searchable: true,
                    search: {
                        value: '',
                        regex: false
                    },
                    orderable: true,
                    name: '',
                    data: 'IPAD'
                },
                {
                    searchable: true,
                    search: {
                        value: '',
                        regex: false
                    },
                    orderable: true,
                    name: '',
                    data: 'APPLE_VISION_PRO'
                },
                {
                    searchable: true,
                    search: {
                        value: '',
                        regex: false
                    },
                    orderable: true,
                    name: '',
                    data: 'APPLE_WATCH'
                },
                {
                    searchable: true,
                    search: {
                        value: '',
                        regex: false
                    },
                    orderable: true,
                    name: '',
                    data: 'IPOD'
                },
                {
                    searchable: true,
                    search: {
                        value: '',
                        regex: false
                    },
                    orderable: true,
                    name: '',
                    data: 'APPLE_TV'
                }
            ]
        },
        'successCallBack': function (response) {
            const options = response['data']['data'];
            const selectElement = document.querySelector('select[name="iss"]');
            options.forEach(option => {
                const optionElement = document.createElement('option');
                optionElement.value = option['iss'];
                optionElement.textContent = `${option['apple_id']}
                【iPhone剩余：${option['IPHONE']}】
                【iPad剩余：${option['IPAD']}】
                【Mac剩余：${option['MAC']}】
                【Watch剩余：${option['APPLE_WATCH']}】
                【iPod剩余：${option['IPOD']}】
                【TV剩余：${option['APPLE_TV']}】
                【VisionPro剩余：${option['APPLE_VISION_PRO']}】`;
                selectElement.appendChild(optionElement);
            });
            $(".select2").select2();
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
                    udid: value
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
        $(".select2").select2();
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
                        url: systemPath+'/certificate/basic/create_devices',
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