@extends('agent.layouts.master')
@section('title'){{ __('App Token 管理') }}@endsection
@section('link')
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-12">
            <div class="alert alert-warning alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <strong>安全说明</strong><hr>
                App Token 接口仅用于配套签名工具 App 2.1.0 以上版本<br>
                高级密钥在服务端以哈希存储，仅生成时展示一次，请离线保存。<br>
                重新生成 Token 将同时更新 Token 与高级密钥；「仅轮换高级密钥」不改变当前 Token。
            </div>
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">当前账户</h4>
                    <p class="mb-1">登录邮箱：<strong>{{ $agent['email'] }}</strong></p>
                    <p class="mb-1">App Token：<code>{{ $token_masked }}</code></p>
                    <p class="mb-3 text-muted">高级密钥：@if($has_api_secret) 已设置（不可查看明文） @else <span class="text-danger">未生成，请先点击下方按钮</span> @endif</p>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary" id="btn-generate-token">重新生成 Token + 高级密钥</button>
                        <button type="button" class="btn btn-outline-primary" id="btn-regenerate-key">仅轮换高级密钥</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        function showCredentialModal(title, data) {
            const layer = layui['layer'];
            const content = `
                <div style="padding:16px;font-size:14px;">
                    <p>请立即复制保存（高级密钥仅显示一次）：</p>
                    <hr>
                    <p><strong>email</strong></p>
                    <textarea class="form-control" rows="2" readonly onclick="this.select()">${data.email || ''}</textarea>
                    <p class="mt-2"><strong>token</strong></p>
                    <textarea class="form-control" rows="2" readonly onclick="this.select()">${data.token || ''}</textarea>
                    <p class="mt-2"><strong>key</strong></p>
                    <textarea class="form-control" rows="3" readonly onclick="this.select()">${data.key || ''}</textarea>
                </div>
            `;
            layer.open({
                type: 1,
                title: title,
                area: ['520px', '480px'],
                content: content,
                btn: ['关闭'],
                yes: function (index) {
                    layer.close(index);
                    location.reload();
                }
            });
        }

        $('#btn-generate-token').on('click', function () {
            const layer = layui['layer'];
            layer.confirm('将生成新的 Token 与高级密钥，旧凭证将立即失效。是否继续？', { btn: ['确定', '取消'] }, function (idx) {
                layer.close(idx);
                layer.load(2);
                SendAjax({
                    url: systemPath + '/app-token/generate',
                    successCallBack: function (response) {
                        layer.closeLast('loading');
                        if (response['data']) {
                            showCredentialModal('已生成', response['data']);
                        } else {
                            layer.msg(response['message'], { icon: 1, time: 3000 });
                        }
                    },
                    errorCallBack: function (error) {
                        layer.closeLast('loading');
                        if (error['responseJSON'] && error['responseJSON']['message']) {
                            layer.msg(error['responseJSON']['message'], { icon: 5, time: 4000 });
                        }
                    }
                });
            });
        });

        $('#btn-regenerate-key').on('click', function () {
            const layer = layui['layer'];
            layer.confirm('将仅轮换高级密钥，Token 不变。是否继续？', { btn: ['确定', '取消'] }, function (idx) {
                layer.close(idx);
                layer.load(2);
                SendAjax({
                    url: systemPath + '/app-token/regenerate-key',
                    successCallBack: function (response) {
                        layer.closeLast('loading');
                        if (response['data']) {
                            showCredentialModal('已轮换高级密钥', response['data']);
                        } else {
                            layer.msg(response['message'], { icon: 1, time: 3000 });
                        }
                    },
                    errorCallBack: function (error) {
                        layer.closeLast('loading');
                        if (error['responseJSON'] && error['responseJSON']['message']) {
                            layer.msg(error['responseJSON']['message'], { icon: 5, time: 4000 });
                        }
                    }
                });
            });
        });
    </script>
@endsection
