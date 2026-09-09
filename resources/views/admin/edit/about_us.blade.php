@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('编辑介绍') }}@endsection
@section('link')

@endsection

@section('content')
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">
                    <form class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label class="form-label">标题</label>
                            <input name="title" type="text" class="form-control" placeholder="标题" value="{{ $arr->title }}" required>
                            <div class="invalid-feedback">请设置标题！</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">内容</label>
                            <input name="value" type="text" class="form-control" placeholder="内容" value="{{ $arr->value }}" required>
                            <div class="invalid-feedback">请设置内容！</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">链接</label>
                            <input name="url" type="text" class="form-control" placeholder="链接" value="{{ $arr->url }}" required>
                            <div class="invalid-feedback">请设置链接！</div>
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
                                url: systemPath+'/about_us/edit/{{ $arr->id }}',
                                data: $("form").serialize(),
                                successCallBack: function (response) {
                                    layer.prompt({
                                        formType: 2,
                                        title: '修改介绍成功',
                                        value: `
标题：${response['data']['title']}\n
内容：${response['data']['value']}\n
链接：${response['data']['url']}
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