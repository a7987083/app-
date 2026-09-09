@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('编辑软件') }}@endsection
@section('link')
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/dropzone/dropzone.min.css" rel="stylesheet" type="text/css">
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-shrink-5 me-2">
                            <img id="icon" class="rounded avatar-sm" src="/asset/appicon/{{ $arr->app_id }}.png?v={{ time() }}" alt="">
                        </div>
                        <div class="flex-grow">
                            <h5>{{ $arr->app_name }} · {{ $arr->app_version }}</h5>
                            <p style="font-size: 11px; color: gray;">创建日期：{{ $arr->created_at }}</p>
                            <p style="font-size: 11px; color: gray;">更新日期：{{ $arr->updated_at }}</p>
                        </div>
                        <div class="ms-auto align-self-center">
                            <button type="button" class="updateIcon-btn btn btn-primary waves-effect waves-light">
                                上传更新图标
                            </button>
                        </div>
                    </div>
                    <hr>
                    <form class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">软件名称</label>
                                    <input name="app_name" type="text" class="form-control" placeholder="软件名称" value="{{ $arr->app_name }}" required>
                                    <div class="invalid-feedback">请设置软件名称！</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">软件版本</label>
                                    <input name="app_version" type="text" class="form-control" placeholder="软件版本" value="{{ $arr->app_version }}" required>
                                    <div class="invalid-feedback">请设置软件版本！</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">软件标识</label>
                                    <input name="app_bid" type="text" class="form-control" placeholder="软件标识" value="{{ $arr->app_bid }}" required>
                                    <div class="invalid-feedback">请设置软件标识！</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">软件分类</label>
                                    <select name="class_id" class="form-control select2" required>
                                        @foreach ($class as $item)
                                            <option value="{{ $item->id }}" {{ ($arr->class_id === $item->id) ? 'selected' : '' }}>{{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">注入开关</label>
                                    <select name="injection_framework" class="form-control select2" required>
                                        <option value="on" {{ ($arr->injection_framework === 'on') ? 'selected' : '' }}>开启注入</option>
                                        <option value="off" {{ ($arr->injection_framework === 'off') ? 'selected' : '' }}>关闭注入</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">软件介绍</label>
                                <div>
                                    <textarea name="app_introduction" class="form-control" rows="5">{{ str_replace('\r\n', "\n", $arr->app_introduction) }}</textarea>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">网络验证弹框公告内容</label>
                                <div>
                                    <textarea name="notice" class="form-control" rows="5">{{ str_replace('\r\n', "\n", $arr->notice) }}</textarea>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">上传更新</label>
                                <div class="dropzone" id="speed-awesome-dropzone"></div>
                            </div>
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
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/dropzone/dropzone.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/newUI/dist/src/compressor.min.js"></script>
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
                                url: systemPath+'/app/edit/{{ $arr->app_id }}',
                                type: 'put',
                                data: $("form").serialize(),
                                successCallBack: function (response) {
                                    layer.prompt({
                                        formType: 2,
                                        title: '修改软件成功',
                                        value: `
软件名称：${response['data']['app_name']}\n
软件版本：${response['data']['app_version']}\n
软件标识：${response['data']['app_bid']}\n
软件分类：${response['data']['class_id']}\n
软件介绍：${response['data']['app_introduction']}\n
自动注入：${(response['data']['injection_framework'] === 'on')?'开启':'关闭'}“${response['data']['app_name']}”自动注入网络验证库
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
        Dropzone.options.speedAwesomeDropzone = {
            dictDefaultMessage: '将ipa文件拖到这里或点击上传',
            dictFallbackMessage: '您的浏览器不支持拖拽文件上传',
            dictInvalidFileType: '不允许上传此类型文件',
            dictFileTooBig: '上传的文件：@{{filesize}} MB，超出了@{{maxFilesize}} MB限制！',
            dictCancelUpload: '取消上传',
            dictCancelUploadConfirmation: '您确定要取消此上传此文件吗？',
            dictRemoveFile: '删除文件',
            url: location.href,
            method: 'post',
            paramName: 'ipa',
            addRemoveLinks: true,
            autoProcessQueue: true,
            chunking: true,
            chunkSize: {{ config('api.basics.upload_chunk', 5) }} * 1024 * 1024,
            retryChunks: true,
            retryChunksLimit: 3,
            parallelUploads: 1,
            maxFiles: 1,
            maxFilesize: {{ config('api.basics.upload_max', 500) }}, // MB
            acceptedFiles: '.ipa',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
        };
        $('.updateIcon-btn').on('click', function () {
            const body = $('body');
            const image = document.createElement('input');
            image.type = 'file';
            image.accept = 'image/*';
            image.style.display = 'none';
            body.append(image);
            image.onchange = function (event) {
                const file = event['target']['files'][0];
                if (file) {
                    new Promise((resolve, reject) => {
                        new Compressor(file, {
                            quality: '0',
                            maxWidth: 256,
                            maxHeight: 256,
                            mimeType: 'image/jpeg',
                            success(result) {
                                resolve(result);
                            },
                            error() {
                                reject(new Error('该图片资源无法进行解析压缩'));
                            },
                        });
                    })
                        .then((compressedFile) => {
                            const reader = new FileReader();
                            reader.onload = function (e) {
                                $('#icon').attr('src', e['target']['result']);
                                const hiddenInput = $('<input>', {
                                    type: 'hidden',
                                    name: 'icon',
                                    value: e['target']['result']
                                });
                                $('.needs-validation').append(hiddenInput);
                                layer.msg('上传成功，请提交修改', { icon: 1, time: 3000 });
                            };
                            reader.onerror = function () {
                                layer.msg('文件读取失败', { icon: 2, time: 3000 });
                            };
                            reader.readAsDataURL(compressedFile);
                        })
                        .catch((error) => {
                            layer.msg(error.message, { icon: 2, time: 3000 });
                        })
                        .finally(() => {
                            if (image.parentNode) {
                                image.parentNode.removeChild(image);
                            }
                        });
                }
            };
            image.click();
        });
    </script>
@endsection