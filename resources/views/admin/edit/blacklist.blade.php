@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('黑名单') }}@endsection
@section('link')

@endsection

@section('content')
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">
                    <form class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label class="form-label"> 拉黑 目标</label>
                            <input name="value" type="text" class="form-control" placeholder="拉黑目标" value="{{ $arr->value }}" required>
                            <div class="invalid-feedback">请设置拉黑目标！</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">拉黑原因</label>
                            <input name="reason" type="text" class="form-control" placeholder="拉黑原因" value="{{ $arr->reason }}" required>
                            <div class="invalid-feedback">请设置拉黑原因！</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">拉黑类型</label>
                            <select name="type" class="form-control select2" required>
                                <option value="UDID" {{ ($arr->type === 'UDID') ? 'selected' : '' }}>设备黑名单</option>
                                <option value="CODE" {{ ($arr->type === 'CODE') ? 'selected' : '' }}>卡密黑名单</option>
                                <option value="SOURCE" {{ ($arr->type === 'SOURCE') ? 'selected' : '' }}>软件源黑名单</option>
                            </select>
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
                                url: systemPath+'/blacklist/edit/{{ $arr->id }}',
                                data: $("form").serialize(),
                                successCallBack: function (response) {
                                    layer.prompt({
                                        formType: 2,
                                        title: '修改黑名单成功',
                                        value: `
拉黑类型：${response['data']['type']}\n
拉黑目标：${response['data']['value']}\n
拉黑原因：${response['data']['reason']}
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