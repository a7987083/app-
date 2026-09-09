@extends('agent.layouts.master')
@section('title'){{ __('常见问题') }}@endsection
@section('link')
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/datatables/datatables.min.css" rel="stylesheet" type="text/css">
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mt-5">
                        <div class="col-xl-3 col-sm-5 mx-auto">
                            <div>
                                <img src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/images/faqs-img.png" alt="常见问题" class="img-fluid mx-auto d-block">
                            </div>
                        </div>
                        <div class="col-xl-8">
                            <div id="faqs-accordion" class="custom-accordion mt-5 mt-xl-0">
                                <div class="card border shadow-none">
                                    <a href="#faqs-web-site-collapse" class="text-dark" data-bs-toggle="collapse" aria-haspopup="true" aria-expanded="false" aria-controls="faqs-web-site-collapse">
                                        <div class="bg-light p-3">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar-xs">
                                                        <div class="avatar-title rounded-circle font-size-22">
                                                            <i class="uil uil-question-circle"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1 overflow-hidden">
                                                    <h5 class="font-size-16 mb-1">服务器开了小差，请您刷新。</h5>
                                                    <p class="text-muted text-truncate mb-0">代理站点部署后出现：服务器开了小差，请您刷新。</p>
                                                </div>
                                                <div class="flex-shrink-0">
                                                    <i class="mdi mdi-chevron-up accor-down-icon font-size-16"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    <div id="faqs-web-site-collapse" class="collapse" data-bs-parent="#faqs-accordion">
                                        <div class="p-4">
                                            <div class="row">
                                                <p class="text-muted">一、检查是否没有配置 SSL 证书 即 HTTPS://</p>
                                                <div class="mb-1"></div>
                                                <p class="text-muted">二、检查您的站点是否已经配置 Nginx 伪静态规则</p>
                                                <div class="mb-1"></div>
                                                <p class="text-muted">三、如果以上均有效配置 请联系本站管理员开放权限</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card border shadow-none">
                                    <a href="#faqs-api-retransmission-collapse" class="text-dark collapsed" data-bs-toggle="collapse" aria-haspopup="true" aria-expanded="false" aria-controls="faqs-api-retransmission-collapse">
                                        <div class="bg-light p-3">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar-xs">
                                                        <div class="avatar-title rounded-circle font-size-22">
                                                            <i class="uil uil-shield-check"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1 overflow-hidden">
                                                    <h5 class="font-size-16 mb-1">代理站的描述文件如何自己签名？</h5>
                                                    <p class="text-muted text-truncate mb-0">代理站默认生成的描述文件是不会进行签名的，需要手动签名。</p>
                                                </div>
                                                <div class="flex-shrink-0">
                                                    <i class="mdi mdi-chevron-up accor-down-icon font-size-16"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    <div id="faqs-api-retransmission-collapse" class="collapse" data-bs-parent="#faqs-accordion">
                                        <div class="p-4">
                                            <div class="row">
                                                <h5 class="font-size-16 mt-2">方法一、使用 SSL 证书 与 OpenSSL 进行签名</h5>
                                                <div class="mb-2"></div>
                                                <p class="text-muted">一、从腾讯云或其他SSL证书机构申请 SSL 证书，并且下载用于 Apache 的证书</p>
                                                <div class="mb-1"></div>
                                                <p class="text-muted">二、创建文件夹：SSL 将下载下来的证书移动到 SSL 文件夹内</p>
                                                <div class="mb-1"></div>
                                                <p class="text-muted">三、将未签名的文件 /api/udid.mobileconfig 移动到 SSL</p>
                                                <div class="mb-1"></div>
                                                <p class="text-muted">四、这个时候 SSL文件夹内有：udid.mobileconfig（描述文件）、root.crt（根证书）、cert.crt（域名证书）、cert.key（域名密钥）等文件</p>
                                                <div class="mb-1"></div>
                                                <p class="text-muted">五、根据以上文件名 打开 终端 cd SSL（你的 SSL 文件夹路径）</p>
                                                <div class="mb-1"></div>
                                                <p class="text-muted">六、执行命令：openssl smime -sign -in udid.mobileconfig -out signed.mobileconfig -signer cert.crt -inkey cert.key -certfile root_bundle.crt -outform der -nodetach</p>
                                                <div class="mb-1"></div>
                                                <p class="text-muted">签名后会生成 signed.mobileconfig 文件 这个文件就是已签名的描述文件了</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card border shadow-none">
                                    <a href="#faqs-web-hook-collapse" class="text-dark collapsed" data-bs-toggle="collapse" aria-haspopup="true" aria-expanded="false" aria-controls="faqs-web-hook-collapse">
                                        <div class="bg-light p-3">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar-xs">
                                                        <div class="avatar-title rounded-circle font-size-22">
                                                            <i class="uil uil-pricetag-alt"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1 overflow-hidden">
                                                    <h5 class="font-size-16 mb-1">代理站如何使用全局HOOK功能？</h5>
                                                    <p class="text-muted text-truncate mb-0">为了方便代理站点更好宣传，系统内置全局HOOK功能，避免溯源等不必要问题</p>
                                                </div>
                                                <div class="flex-shrink-0">
                                                    <i class="mdi mdi-chevron-up accor-down-icon font-size-16"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    <div id="faqs-web-hook-collapse" class="collapse" data-bs-parent="#faqs-accordion">
                                        <div class="p-4">
                                            <div class="row">
                                                <h5 class="font-size-16 mt-2">第一步、打开 HTML 源码 配置全局 HOOK 功能</h5>
                                                <div class="mb-2"></div>
                                                <img src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/images/faq/hook-1.jpg" alt="第一步">
                                                <h5 class="font-size-16 mt-2">注意：由于主题不同 具体功能可能会有些许差异</h5>
                                                <div class="mb-2"></div>
                                                <p class="text-muted">详情配置以及其他功能配置请看 HTML 源码内的注释</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
@endsection