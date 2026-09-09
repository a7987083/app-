@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('添加证书') }}@endsection
@section('link')
@endsection

@section('content')
<div class="alert alert-primary alert-dismissible">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    <strong>添加自有证书须知！</strong>
    <hr>
    <b>
    您可以在这里免费添加苹果个人开发者证书进行一键式销售，平台不收取托管费用！<hr>
    但是您每通过本平台私有池【包含API接口】添加一个新的设备，将扣除 <a id="api_price">获取中</a> 点接口手续费！<hr>
    如果您自己没有开发者证书，可通过充值公池点数，前往证书公池【或者使用公共池API接口】进行添加新设备！
    </b>
    <hr>
    <b>本平台支持在线开关证书【推送通知、健康数据...】等权限：</b><hr>
    添加证书后可在“证书管理->证书列表”对应证书的功能操作选项点击“修改权限”按钮进行操作。【操作前请先认真阅读须知条款】
    <hr>
    <b>使用声明：在本平台添加证书，一定要把其他P8文件权限撤销，否则出现任何问题平台概不负责！！！</b>
</div>
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">添加证书 - 新接口模式「P8模式」</h4>
                <p class="card-title-desc"></p>
                <form class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">密钥 ID</label>
                        <input name="kid" type="text" class="form-control" placeholder="例子：3FJ3***79Z" required>
                        <div class="invalid-feedback">请输入10位密钥ID！</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Issuer ID</label>
                        <input name="iss" type="text" class="form-control" placeholder="例子：001b****-****-****-****-********4942" required>
                        <div class="invalid-feedback">请输入36位Issuer ID！</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">P8文件内容</label>
                        <textarea name="key" class="form-control" rows="8" placeholder="例子
：-----BEGIN PRIVATE KEY-----
MIGT************************************************************
****************************************************************
****************************************************************
****wBJZ
-----END PRIVATE KEY-----" required></textarea>
                        <div class="invalid-feedback">请输入P8文件内容！</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">备注信息</label>
                        <input name="mark" type="text" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">默认密码</label>
                        <input name="password" type="text" class="form-control" placeholder="默认 P12 证书密码，留空为：1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">默认类型</label>
                        <select name="type" class="form-control select2">
                            <option value="DEVELOPMENT">默认使用 DEVELOPMENT 类型证书</option>
                            <option value="DISTRIBUTION">选择使用 DISTRIBUTION 类型证书</option>
                            <option value="IOS_DEVELOPMENT">选择使用 IOS_DEVELOPMENT 类型证书</option>
                            <option value="IOS_DISTRIBUTION">选择使用 IOS_DISTRIBUTION 类型证书</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">必要时自动删除证书</label>
                        <div class="form-check form-switch form-switch-lg">
                            <input name="del" type="checkbox" class="form-check-input">
                            <label class="form-check-label" for="customSwitchsizelg">开启后，添加证书时如果发现开发者已存在证书，则自动删除已存在的证书。</label>
                        </div>
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
    $("[name='iss']").on("input", function() {
        const value = $(this).val();
        let uuid4 = /[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/;
        if (uuid4.test(value)) {
            layer.load(2);
            $('button[type="submit"]').prop('disabled', true).text('正在检测 Issuer ID 有效性');
            SendAjax({
                'url': systemPath+'/certificate/v1/issuer_query',
                'data': {
                    iss: value
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
            $('button[type="submit"]').prop('disabled', true).text('请输入正确的 Issuer ID');
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
                        url: systemPath+'/certificate/basic/create_certificate',
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