@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('添加介绍') }}@endsection
@section('link')

@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body">
                <form class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">设置标题</label>
                        <input name="title" type="text" class="form-control" placeholder="显示标题，结尾建议加：" required>
                        <div class="invalid-feedback">请设置标题！</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">设置内容</label>
                        <div>
                            <textarea name="value" class="form-control" rows="8" placeholder="显示内容，点击会跳转“设置的链接”" required></textarea>
                            <div class="invalid-feedback">请设置内容！</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">设置链接</label>
                        <input name="url" type="text" class="form-control" placeholder="跳转链接地址" required>
                        <div class="invalid-feedback">请设置链接！</div>
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
                            url: systemPath+'/about_us/create',
                            data: $("form").serialize(),
                        });
                        return false;
                    }
                }, false);
            });
        }, false);
    })();
</script>
@endsection