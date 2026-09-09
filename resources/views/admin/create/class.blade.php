@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('添加分类') }}@endsection
@section('link')

@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body">
                <form class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">分类名称</label>
                        <input name="name" type="text" class="form-control" required>
                        <div class="invalid-feedback">请设置分类名称！</div>
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
                            url: systemPath+'/class/create',
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