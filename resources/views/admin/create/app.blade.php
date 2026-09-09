@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('上传软件') }}@endsection
@section('link')
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/dropzone/dropzone.min.css" rel="stylesheet" type="text/css">
@endsection

@section('content')
    <form class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">上传软件</h4>
                    <p class="card-title-desc">支持多安装包同时上传</p>
                    <div class="mb-3">
                        <label class="form-label">选择上传软件分类</label>
                        <select name="ClassID" class="form-control select2"></select>
                    </div>
                    <div class="dropzone" id="speed-awesome-dropzone"></div>
                </div>
            </div>
        </div>
    </form>
@endsection

@section('script')
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/dropzone/dropzone.min.js"></script>
    <script>
        layer.load(2);
        $.ajax({
            url: '/api/config',
            type: 'POST',
            async: false,
            success: function(response) {
                let app_class = response['data']['app_class'].map(function(item) {
                    return `<option value="${item['value']}">${item['label']}</option>`;
                });
                if (app_class.length === 0) {
                    $('select[name="ClassID"]').html(`<option value="1">系统默认分类</option>`);
                } else {
                    $('select[name="ClassID"]').html(app_class.join(''));
                }
            },
            error: function(xhr, status, error) {
                if (error && error['response']) {
                    const { data } = error['response'];
                    const message = data['message'];
                    layer.msg(message, { icon: 2, time: 5000 });
                } else {
                    layer.msg(error, { icon: 2, time: 5000 });
                }
            },
            complete: function() {
                layer['closeLast']('loading');
            }
        });

        function ClassID() {
            return $('select[name="ClassID"]').val();
        }

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
            parallelUploads: 5,
            maxFilesize: {{ config('api.basics.upload_max', 500) }}, // MB
            acceptedFiles: '.ipa',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'ClassID': ClassID(),
            },
        };
    </script>
@endsection