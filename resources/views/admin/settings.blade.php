@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('系统设置') }}@endsection
@section('link')
<style>
    /* 主题轮播图样式 */
    #themeCarousel .carousel-item {
        transition: transform 0.6s ease-in-out;
    }
    
    #themeCarousel .card {
        border: none;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        border-radius: 15px;
        overflow: hidden;
    }
    
    #themeCarousel .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.2);
    }
    
    #themeCarousel .card-body {
        padding: 2rem;
    }
    
    #themeCarousel .carousel-indicators button {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin: 0 4px;
        border: 2px solid #fff;
        background-color: rgba(255,255,255,0.5);
        transition: all 0.3s ease;
    }
    
    #themeCarousel .carousel-indicators button.active {
        background-color: #fff;
        transform: scale(1.2);
    }
    
    #themeCarousel .carousel-control-prev,
    #themeCarousel .carousel-control-next {
        width: 5%;
        color: #333;
    }
    
    #themeCarousel .carousel-control-prev-icon,
    #themeCarousel .carousel-control-next-icon {
        background-color: rgba(0,0,0,0.1);
        border-radius: 50%;
        width: 40px;
        height: 40px;
        transition: background-color 0.3s ease;
    }
    
    #themeCarousel .carousel-control-prev-icon:hover,
    #themeCarousel .carousel-control-next-icon:hover {
        background-color: rgba(0,0,0,0.3);
    }
    
    .badge {
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
        border-radius: 20px;
    }
    
    /* 主题预览样式 */
    #themePreview .theme-card {
        border: none;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        border-radius: 15px;
        overflow: hidden;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    #themePreview .theme-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.2);
    }
    
    #themePreview .theme-card-body {
        padding: 2rem;
        color: white;
        text-align: center;
    }
    
    #themePreview .theme-carousel {
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 1rem;
        position: relative;
        background: #f8f9fa;
    }
    
    #themePreview .theme-carousel .carousel-item img {
        width: 100%;
        height: 400px;
        object-fit: contain;
        object-position: center;
        background: #fff;
    }
    
    #themePreview .carousel-indicators {
        bottom: 15px;
        margin-bottom: 0;
    }
    
    #themePreview .carousel-indicators button {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin: 0 3px;
        border: 0;
        background-color: rgba(255,255,255,0.6);
        transition: all 0.3s ease;
        opacity: 0.7;
    }
    
    #themePreview .carousel-indicators button.active {
        background-color: #fff;
        transform: scale(1.3);
        opacity: 1;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    
    /* 优化的左右切换按钮 */
    #themePreview .carousel-control-prev,
    #themePreview .carousel-control-next {
        width: 50px;
        height: 50px;
        top: 50%;
        transform: translateY(-50%);
        opacity: 0;
        transition: all 0.3s ease;
        z-index: 10;
    }
    
    #themePreview .theme-carousel:hover .carousel-control-prev,
    #themePreview .theme-carousel:hover .carousel-control-next {
        opacity: 1;
    }
    
    #themePreview .carousel-control-prev {
        left: 15px;
    }
    
    #themePreview .carousel-control-next {
        right: 15px;
    }
    
    #themePreview .carousel-control-prev-icon,
    #themePreview .carousel-control-next-icon {
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(10px);
        border-radius: 50%;
        width: 50px;
        height: 50px;
        transition: all 0.3s ease;
        border: 2px solid rgba(255,255,255,0.3);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    #themePreview .carousel-control-prev-icon::before,
    #themePreview .carousel-control-next-icon::before {
        content: '';
        width: 12px;
        height: 12px;
        border-top: 2px solid #fff;
        border-right: 2px solid #fff;
        display: block;
    }
    
    #themePreview .carousel-control-prev-icon::before {
        transform: rotate(-135deg);
        margin-left: 3px;
    }
    
    #themePreview .carousel-control-next-icon::before {
        transform: rotate(45deg);
        margin-right: 3px;
    }
    
    #themePreview .carousel-control-prev-icon:hover,
    #themePreview .carousel-control-next-icon:hover {
        background: rgba(0,0,0,0.8);
        border-color: rgba(255,255,255,0.5);
        transform: scale(1.1);
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    }
    
    /* 隐藏默认的背景图标 */
    #themePreview .carousel-control-prev-icon,
    #themePreview .carousel-control-next-icon {
        background-image: none;
    }
    
    #themePreview .no-images {
        background: linear-gradient(135deg, #e0e0e0 0%, #bdbdbd 100%);
        color: #666;
        padding: 3rem;
        text-align: center;
        border-radius: 10px;
        margin-bottom: 1rem;
    }
    
    .badge {
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
        border-radius: 20px;
    }
</style>
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-12">
             <div class="card">
                <div class="card-body">
                    <h4 class="card-title">系统设置</h4>
                    <p class="card-title-desc">教程与文档：{{ $getConfig['doc_url'] }}</p>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="nav flex-column nav-pills">
                                <a class="nav-link mb-2 active" data-bs-toggle="pill" href="#v-pills-site">站点配置</a>
                                <a class="nav-link mb-2" data-bs-toggle="pill" href="#v-pills-sign">签名配置</a>
                                <a class="nav-link mb-2" data-bs-toggle="pill" href="#v-pills-batch">批量签名</a>
                                <a class="nav-link mb-2" data-bs-toggle="pill" href="#v-pills-basics">基础配置</a>
                                <a class="nav-link mb-2" data-bs-toggle="pill" href="#v-pills-optimization">系统优化</a>
                                <a class="nav-link mb-2" data-bs-toggle="pill" href="#v-pills-theme">主题切换</a>
                                <a class="nav-link mb-2" data-bs-toggle="pill" href="#v-pills-auth">网络验证</a>
                                <a class="nav-link mb-2" data-bs-toggle="pill" href="#v-pills-storage">存储配置</a>
                                <a class="nav-link mb-2" data-bs-toggle="pill" href="#v-pills-application">APP配置</a>
                                <a class="nav-link mb-2" data-bs-toggle="pill" href="#v-pills-exclusion-update">排除更新</a>
                                <a class="nav-link mb-2" data-bs-toggle="pill" href="#v-pills-enterprise-certificate">企业证书</a>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="tab-content text-muted mt-4 mt-md-0">
                                <div class="tab-pane fade show active" id="v-pills-site">
                                    <form class="needs-validation" novalidate>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">网站名称</label>
                                                    <input type="text" class="form-control" name="app[name]" value="{{ $arr['app']['name'] }}">
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">后台地址</label>
                                                    <div class="input-group">
                                                        <div class="input-group-text">https://{{ request()->host() }}/</div>
                                                        <input type="text" class="form-control" placeholder="admin" name="admin[path]" value="{{ $arr['admin']['path'] }}">
                                                        <div class="input-group-text">/login</div>
                                                    </div>
                                                </div>
                                            </div>
                                            {{--
                                            <div class="col-md-12">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">静态资源地址</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" placeholder="注意：未下载解压前请勿留空！" name="basics[cdn]" value="{{ config('api.basics.cdn') }}">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>默认使用美国加速CDN</strong>
                                                    <br>如需改为本地资源请 <a style="color: #0A84FF;" href="https://us-cdn.ser8848.cn/theme.zip">点击此次下载压缩包</a>
                                                    <br>然后上传到 {{ base_path('public') }} 目录下解压
                                                    <br>最后将上方的 {{ config('api.basics.cdn') }} 删除留空即可
                                                    <br>或者您也可以根据以上方法创建自己的静态资源CDN地址
                                                </div>
                                            </div>
                                            --}}
                                            <div class="col-md-6">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">安装包最大上传限制</label>
                                                    <div class="input-group">
                                                        <div class="input-group-text">最大可上传安装包</div>
                                                        <input type="text" class="form-control" placeholder="数值单位 MB" name="basics[upload_max]" value="{{ config('api.basics.upload_max', 500) }}">
                                                        <div class="input-group-text">MB</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">安装包分块上传大小</label>
                                                    <div class="input-group">
                                                        <div class="input-group-text">上传每个分块大小</div>
                                                        <input type="text" class="form-control" placeholder="数值单位 MB" name="basics[upload_chunk]" value="{{ config('api.basics.upload_chunk', 5) }}">
                                                        <div class="input-group-text">MB</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">卡密生成模式设置</label>
                                                    <select name="basics[code_generate_model]" class="form-control select2">
                                                        <option value="" {{ (empty(config('api.basics.code_generate_model'))) ?'selected':'' }}>默认UUID4模式</option>
                                                        <option value="custom" {{ (config('api.basics.code_generate_model') == 'custom') ?'selected':'' }}>自定义卡密模式</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">自定义卡密模式生成默认前缀</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" placeholder="卡密前缀" name="basics[code_generate_prefix]" value="{{ config('api.basics.code_generate_prefix') }}">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">自定义卡密模式生成长度设置</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" placeholder="数字单位" name="basics[code_generate_length]" value="{{ config('api.basics.code_generate_length', '36') }}">
                                                        <div class="input-group-text">位总长度</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">自定义卡密模式生成字符设置</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" placeholder="生成的字符集合" name="basics[code_generate_character]" value="{{ config('api.basics.code_generate_character', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ') }}">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">互联网信息服务备案号</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" placeholder="互联网信息服务备案号" name="basics[icpbeian]" value="{{ config('api.basics.icpbeian') }}">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">增值电信业务经营许可证</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" placeholder="增值电信业务经营许可证" name="basics[icplicence]" value="{{ config('api.basics.icplicence') }}">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">公安机关互联网站安全备案号</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" placeholder="公安机关互联网站安全备案号" name="basics[wanganbeian]" value="{{ config('api.basics.wanganbeian') }}">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">企业微信机器 WebHookApi 通知</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" placeholder="https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=********-****-****-****-************" name="basics[weixin_web_hook_url]" value="{{ config('api.basics.weixin_web_hook_url') }}">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">限制代理主题下载</label>
                                                    <div>
                                                        <textarea name="basics[disable_theme]" class="form-control" rows="5" placeholder="例如要限制new-qnq.blade.php主题则输入new-qnq即可，一行一个">{{ str_replace('\r\n', "\n", $arr['basics']['disable_theme'] ?? '') }}</textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>后台地址</strong>
                                                    <br>此处后台地址指的是当前页面 即 管理员登录地址
                                                    <br>默认管理员登录地址为：https://{{ request()->host() }}/admin/login
                                                    <br>假设您在输入框内填写：speed
                                                    <br>那么后台管理地址就是：https://{{ request()->host() }}/speed/login
                                                    <hr>
                                                    <strong>自定义卡密</strong>
                                                    <br>每次生成兑换码可设置卡密前缀、留空则使用默认前缀
                                                    <br>自定义卡密生成必须配置：字符、长度【长度过短容易重复】
                                                    <br>此模式最大可生成：{{ customCodeAssembly() }} 个不重复卡密
                                                    <br>以上计算需要每次保存更新后刷新页面显示
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary" type="submit">保存信息</button>
                                    </form>
                                </div>


                                <div class="tab-pane fade show" id="v-pills-sign">
                                    <form class="needs-validation" novalidate>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">是否开启签名 APPEX 文件【老版本默认移除 APPEX】</label>
                                                    <select name="basics[ipa_appex]" class="form-control select2">
                                                        <option value="on" {{ (config('api.basics.ipa_appex', 'off') == 'on') ?'selected':'' }}>开启</option>
                                                        <option value="off" {{ (config('api.basics.ipa_appex', 'off') != 'on') ?'selected':'' }}>关闭</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">前台安装包修改开关</label>
                                                    <select name="basics[ipa_editor]" class="form-control select2">
                                                        <option value="on" {{ ($arr['basics']['ipa_editor'] == 'on') ?'selected':'' }}>开启</option>
                                                        <option value="off" {{ ($arr['basics']['ipa_editor'] == 'off') ?'selected':'' }}>关闭</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">证书剩余天数自换新</label>
                                                    <input type="text" class="form-control" name="basics[cert_new_day]" value="{{ $arr['basics']['cert_new_day'] ?? 30 }}" required>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>使用说明</strong>
                                                    <br>前台安装包修改开关
                                                    <br>关闭后：默认主题下->在线签名页面只能提交签名一个多开，且默认名称、包ID、图标等等。
                                                    <br>证书剩余天数自换新
                                                    <br>例如设置 30，某个设备码在平台已有证书并且证书有效期剩余 30 天时，则会自动换新证书而不是继续拉取老证书。
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">代理默认模式卡密生成</label>
                                                    <select name="basics[default_code_generate]" class="form-control select2">
                                                        <option value="off" {{ ($arr['basics']['default_code_generate'] == 'off') ?'selected':'' }}>关闭</option>
                                                        <option value="on" {{ ($arr['basics']['default_code_generate'] == 'on') ?'selected':'' }}>开启</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">代理秒出证书卡密生成</label>
                                                    <select name="basics[good_code_generate]" class="form-control select2">
                                                        <option value="off" {{ ($arr['basics']['good_code_generate'] == 'off') ?'selected':'' }}>关闭</option>
                                                        <option value="on" {{ ($arr['basics']['good_code_generate'] == 'on') ?'selected':'' }}>开启</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">代理预约证书卡密生成</label>
                                                    <select name="basics[processing_code_generate]" class="form-control select2">
                                                        <option value="off" {{ ($arr['basics']['processing_code_generate'] == 'off') ?'selected':'' }}>关闭</option>
                                                        <option value="on" {{ ($arr['basics']['processing_code_generate'] == 'on') ?'selected':'' }}>开启</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>卡密生成说明</strong>
                                                    <br>如果全部关闭、代理将无法生成卡密、请根据需求执行开关。
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">自有证书开关</label>
                                                    <select name="basics[own_cert]" class="form-control select2">
                                                        <option value="on" {{ ($arr['basics']['own_cert'] == 'on') ?'selected':'' }}>开启</option>
                                                        <option value="off" {{ ($arr['basics']['own_cert'] == 'off') ?'selected':'' }}>关闭</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">平台兑换开关</label>
                                                    <select name="basics[udid_cert]" class="form-control select2">
                                                        <option value="on" {{ ($arr['basics']['udid_cert'] == 'on') ?'selected':'' }}>开启</option>
                                                        <option value="off" {{ ($arr['basics']['udid_cert'] == 'off') ?'selected':'' }}>关闭</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">免费证书开关</label>
                                                    <select name="basics[free_cert]" class="form-control select2">
                                                        <option value="on" {{ ($arr['basics']['free_cert'] == 'on') ?'selected':'' }}>开启</option>
                                                        <option value="off" {{ ($arr['basics']['free_cert'] == 'off') ?'selected':'' }}>关闭</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>签名模式开关</strong>
                                                    <br>可根据需求动态开关：自有证书（用户自行上传证书）、平台兑换（兑换码兑换）、免费证书（接口提供免费证书）签名模式
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">默认模式卡密首选生成模式</label>
                                                    <select name="basics[default_code_preferred_create_model]" class="form-control select2">
                                                        <option value="private" {{ ($arr['basics']['default_code_preferred_create_model'] == 'private') ?'selected':'' }}>使用私有池证书</option>
                                                        <option value="public" {{ ($arr['basics']['default_code_preferred_create_model'] == 'public') ?'selected':'' }}>使用公共池证书</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>默认模式卡密首选生成模式说明</strong>
                                                    <br>由于默认模式卡密优先级为：第三方平台 > 证书平台
                                                    <br>所以当您同时销售多种卡密时、并且需要使用第三方平台【公共池/私有池】时
                                                    <br>您可以将此处设置您所需的模式、这样就能同时消耗证书平台的证书以及第三方平台的证书、并且进行明确的价格区分。
                                                    <br>此选项在卡密类型为"默认模式卡密"时，会覆盖下方的"首次生成模式"选项。
                                                    <br>
                                                    <br>
                                                    <strong>卡密类型补充说明以便了解此选项用途</strong>
                                                    <br>默认模式：不进行筛选、根据您平台证书进行添加、优先级：第三方平台 > 证书平台
                                                    <br>预约证书：优先筛选证书平台私有池卡设备证书、排除第三方平台（必须有私有池证书）
                                                    <br>秒出证书：优先筛选证书平台私有池秒出证书、排除第三方平台（当没有可用私有池证书时、自动切换公池证书）
                                                    <br>
                                                    <br>例子选择：首次生成模式=私有池，默认模式卡密首选生成模式=公共池
                                                    <br>例子结果：那么当卡密类型为"默认模式"时，这个卡密兑换将使用"第三方公共池"创建设备。
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">首次生成模式</label>
                                                    <select name="basics[first_create_model]" class="form-control select2">
                                                        <option value="private" {{ ($arr['basics']['first_create_model'] == 'private') ?'selected':'' }}>使用私有池证书</option>
                                                        <option value="public" {{ ($arr['basics']['first_create_model'] == 'public') ?'selected':'' }}>使用公共池证书</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">售后生成模式</label>
                                                    <select name="basics[after_create_model]" class="form-control select2">
                                                        <option value="private" {{ ($arr['basics']['after_create_model'] == 'private') ?'selected':'' }}>使用私有池证书</option>
                                                        <option value="public" {{ ($arr['basics']['after_create_model'] == 'public') ?'selected':'' }}>使用公共池证书</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">异常设备处理</label>
                                                    <select name="basics[exception_udid]" class="form-control select2">
                                                        <option value="on" {{ ($arr['basics']['exception_udid'] == 'on') ?'selected':'' }}>再次使用新证书添加</option>
                                                        <option value="off" {{ ($arr['basics']['exception_udid'] == 'off') ?'selected':'' }}>直接输出异常的原因</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>使用说明</strong>
                                                    <br>生成模式：
                                                    <br>首次生成模式：第一次激活兑换码所使用的模式
                                                    <br>售后生成模式：证书过期或吊销后所使用的模式
                                                    <br>异常设备处理（卡设备【24 至 72 小时】不合格【30天内】）：
                                                    <br>再次使用新证书添加：当创建新P12证书出现异常设备，再次使用不卡设备证书添加、如果没有不卡设备的证书则停止（输出设备状态）
                                                    <br>直接输出异常的原因：输出设备状态
                                                    <br>注：异常设备处理并非单单处理异常设备（如接口问题、苹果官方维护也包含在内）
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">安装上限分钟</label>
                                                    <input type="text" class="form-control" name="basics[auto_cleanup]" value="{{ $arr['basics']['auto_cleanup'] }}" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">多开数量上限</label>
                                                    <input type="text" class="form-control" name="basics[multiple_max]" value="{{ $arr['basics']['multiple_max'] }}" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">购买卡密地址</label>
                                                    <input type="text" class="form-control" name="basics[buy]" value="{{ $arr['basics']['buy'] }}">
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>使用说明</strong>
                                                    <br>安装上限：假设设置60，则签名成功后的60分钟内安装有效。超出时间自动清除安装包。
                                                    <br>安装数量：假设设置30，则单次签名任务最多可多开30个。
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">兑换模式设备ID签名每小时上限</label>
                                                    <input type="text" class="form-control" name="basics[udid_sign_max]" value="{{ $arr['basics']['udid_sign_max'] }}" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">其他模式证书ID签名每小时上限</label>
                                                    <input type="text" class="form-control" name="basics[not_code_cert_sign_max]" value="{{ $arr['basics']['not_code_cert_sign_max'] }}" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">老用户免兑换码恢复证书开关</label>
                                                    <select name="basics[not_code_restore_cert]" class="form-control select2">
                                                        <option value="on" {{ ($arr['basics']['not_code_restore_cert'] == 'on') ?'selected':'' }}>开启</option>
                                                        <option value="off" {{ ($arr['basics']['not_code_restore_cert'] != 'on') ?'selected':'' }}>关闭</option>
                                                    </select>
                                                </div>

                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>使用说明</strong>
                                                    <br>兑换模式设备ID签名每小时上限：兑换码签名模式下每个UDID每小时可签名次数上限、设置5、则一小时内最多可提交签名任务5次
                                                    <br>其他模式证书ID签名每小时上限：非兑换码签名模式下每个证书ID每小时可签名次数上限、设置5、则一小时内最多可提交签名任务5次
                                                    <br>老用户免兑换码恢复证书开关（建议只用于迁移系统时使用）：
                                                    <br>开启后老用户免兑换码直接查询对接站点的证书，证书正常将生成对应售后天数（根据证书实际到期天数而定）0售后次数的兑换码查询的设备码
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary" type="submit">保存信息</button>
                                    </form>
                                </div>
                                <div class="tab-pane fade" id="v-pills-batch">
                                    <form class="needs-validation" novalidate>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">批量签名任务线程</label>
                                                    <input type="text" class="form-control" name="basics[max_processes]" value="{{ $arr['basics']['max_processes'] }}" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">批量签名压缩级别</label>
                                                    <select name="basics[zip_level]" class="form-control select2">
                                                        <option value="9" {{ ($arr['basics']['zip_level'] == '9') ?'selected':'' }}>9 压缩率</option>
                                                        <option value="8" {{ ($arr['basics']['zip_level'] == '8') ?'selected':'' }}>8 压缩率</option>
                                                        <option value="7" {{ ($arr['basics']['zip_level'] == '7') ?'selected':'' }}>7 压缩率</option>
                                                        <option value="6" {{ ($arr['basics']['zip_level'] == '6') ?'selected':'' }}>6 压缩率</option>
                                                        <option value="5" {{ ($arr['basics']['zip_level'] == '5') ?'selected':'' }}>5 压缩率</option>
                                                        <option value="4" {{ ($arr['basics']['zip_level'] == '4') ?'selected':'' }}>4 压缩率</option>
                                                        <option value="3" {{ ($arr['basics']['zip_level'] == '3') ?'selected':'' }}>3 压缩率</option>
                                                        <option value="2" {{ ($arr['basics']['zip_level'] == '2') ?'selected':'' }}>2 压缩率</option>
                                                        <option value="1" {{ ($arr['basics']['zip_level'] == '1') ?'selected':'' }}>1 压缩率</option>
                                                        <option value="0" {{ ($arr['basics']['zip_level'] == '0') ?'selected':'' }}>0 压缩率</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">批量签名标识设置</label>
                                                    <input type="text" class="form-control" name="basics[batch_set_id]" value="{{ $arr['basics']['batch_set_id'] }}" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">批量签名名称开关</label>
                                                    <select name="basics[batch_set_name_switch]" class="form-control select2">
                                                        <option value="on" {{ ($arr['basics']['batch_set_name_switch'] == 'on') ?'selected':'' }}>开启</option>
                                                        <option value="off" {{ ($arr['basics']['batch_set_name_switch'] == 'off') ?'selected':'' }}>关闭</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">批量签名名字设置</label>
                                                    <input type="text" class="form-control" name="basics[batch_set_name]" value="{{ $arr['basics']['batch_set_name'] }}">
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>使用说明</strong>
                                                    <br>批量签名压缩级别说明：0-9
                                                    <br>数值越大：速度越慢 安装包大小越小
                                                    <br>数值越小：速度越快 安装包大小越大

                                                    <hr>批量签名任务线程说明：
                                                    <br>请根据自己服务器CPU性能以及安装包大小进行合理设置
                                                    <br>默认为单线程，提交签名 20 个，则每次签名 1 个包，直到全部完成
                                                    <br>假设设置为10，提交签名 20 个，则每次签名 10 个包，直到全部完成

                                                    <hr>批量签名标识设置说明：
                                                    <br>假设批量签名标识设置为：.
                                                    <br>前端选择签名安装包ID为：cn.v-team.cn
                                                    <br>签名后安装包则为：cn.v-team.cn.{多开数值}

                                                    <hr>批量签名名字开关说明：
                                                    <br>只有批量签名名称开关设置为：开启
                                                    <br>批量签名名字设置才会生效、如果为关闭、则全部名称统一

                                                    <hr>批量签名名字设置说明：
                                                    <br>假设批量签名每次设置为：-
                                                    <br>前端选择签名安装包名称为：签名工具
                                                    <br>签名后安装包则为：签名工具-{多开数值}
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary" type="submit">保存信息</button>
                                    </form>
                                </div>
                                <div class="tab-pane fade" id="v-pills-basics" aria-labelledby="v-pills-basics-tab">
                                    <form class="needs-validation" novalidate>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">查询下载P12证书开关</label>
                                                    <select name="basics[cert_download]" class="form-control select2">
                                                        <option value="on" {{ ($arr['basics']['cert_download'] == 'on') ?'selected':'' }}>开启</option>
                                                        <option value="off" {{ ($arr['basics']['cert_download'] == 'off') ?'selected':'' }}>关闭</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">全能签前端保持置顶开关</label>
                                                    <select name="basics[qnq_top]" class="form-control select2">
                                                        <option value="on" {{ ($arr['basics']['qnq_top'] == 'on') ?'selected':'' }}>开启</option>
                                                        <option value="off" {{ ($arr['basics']['qnq_top'] == 'off') ?'selected':'' }}>关闭</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>证书下载开关</strong>
                                                    <br>开启后：默认主题下->查询签名页面可看到下载证书页面
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">API接口地址</label>
                                                    <input type="text" class="form-control" name="api[url]" value="{{ $arr['api']['url'] }}" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">API接口账户</label>
                                                    <input type="email" class="form-control" name="api[email]" value="{{ $arr['api']['email'] }}" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">API接口密钥</label>
                                                    <input type="text" class="form-control" name="api[token]" value="{{ $arr['api']['token'] }}" required>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>API接口配置</strong>
                                                    <br>API接口配置可对接{{ $getConfig['doc_url'] }}的iOS开发者证书管理平台（包含限额版本、对外版本、自搭版本）
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>获取UDID描述文件说明</strong>
                                                    <br>回调接口地址：https://{{ request()->getHost() }}/api/get_udid
                                                    <br>描述文件路径：{{ $arr['public_path'] }}/udid.mobileconfig
                                                    <br>描述文件路径存在则使用路径文件，反之系统自动生成。
                                                    <br>文件名包含_signed 为已签名描述文件、不包含的为未签名描述文件。
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary" type="submit">保存信息</button>
                                    </form>
                                </div>
                                <div class="tab-pane fade" id="v-pills-optimization" aria-labelledby="v-pills-optimization-tab">
                                    <form class="needs-validation" novalidate>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">服务器地区选项</label>
                                                    <select name="app[country]" class="form-control select2">
                                                        <option value="CN" {{ ($arr['app']['country'] == 'CN') ?'selected':'' }}>中国大陆地区</option>
                                                        <option value="US" {{ ($arr['app']['country'] == 'US') ?'selected':'' }}>国外其他地区</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">兑换码限流量</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" placeholder="单位：秒" name="basics[code_redeem_window_seconds]" value="{{ config('api.basics.code_redeem_window_seconds', '30') }}" required>
                                                        <div class="input-group-text">秒请求限流一次</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">Redis地址</label>
                                                    <input type="text" class="form-control" name="redis[host]" value="{{ $arr['redis']['host'] }}" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">Redis端口</label>
                                                    <input type="text" class="form-control" name="redis[port]" value="{{ $arr['redis']['port'] }}" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">Redis密码</label>
                                                    <input type="text" class="form-control" name="redis[password]" value="{{ $arr['redis']['password'] }}">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">Redis缓存</label>
                                                    <select name="default[cache]" class="form-control select2">
                                                        <option value="redis" {{ ($arr['default']['cache'] == 'redis') ?'selected':'' }}>开启Redis缓存</option>
                                                        <option value="file" {{ ($arr['default']['cache'] == 'file') ?'selected':'' }}>关闭Redis缓存</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">Redis队列</label>
                                                    <select name="default[queue]" class="form-control select2">
                                                        <option value="redis" {{ ($arr['default']['queue'] == 'redis') ?'selected':'' }}>开启Redis队列</option>
                                                        <option value="database" {{ ($arr['default']['queue'] == 'database') ?'selected':'' }}>关闭Redis队列</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">Redis会话</label>
                                                    <select name="default[session]" class="form-control select2">
                                                        <option value="redis" {{ ($arr['default']['session'] == 'redis') ?'selected':'' }}>开启Redis会话</option>
                                                        <option value="file" {{ ($arr['default']['session'] == 'file') ?'selected':'' }}>关闭Redis会话</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>配置Redis用途</strong>
                                                    <br>配置Redis后系统自动优化提高系统的性能、减轻服务器负担、加快访问速度。
                                                    <br>Redis本程序主要用于：系统缓存、SESSION会话、队列任务等等。
                                                    <br>默认关闭三个优化开关，用户量大以及使用量大的用户必开「否则概率出现异常错误」
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>Supervisor守护</strong>
                                                    <br>为确保队列工作器一直在后台运行，并在意外关闭时自动重新启动，请添加Supervisor守护。
                                                    <br>运行目录：{{ $arr['base_path'] }}
                                                    <br>启动命令：{{ $arr['command'] }}
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary" type="submit">保存信息</button>
                                    </form>
                                </div>
                                <div class="tab-pane fade" id="v-pills-theme" aria-labelledby="v-pills-theme-tab">
                                    <form class="needs-validation" novalidate>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">前端主题包</label>
                                                    <select name="default[theme]" class="form-control select2" id="themeSelector">
                                                        @foreach($themes as $theme)
                                                            <option value="{{ $theme['key'] }}" 
                                                                    data-name="{{ $theme['name'] }}" 
                                                                    data-introduction="{{ $theme['introduction'] }}" 
                                                                    data-version="{{ $theme['version'] }}" 
                                                                    data-images="{{ json_encode($theme['images']) }}"
                                                                    {{ ($arr['default']['theme'] == $theme['key']) ? 'selected' : '' }}>
                                                                {{ $theme['name'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <div id="themePreview">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>主题开发文档：{{ $getConfig['doc_url'] }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary" type="submit">保存信息</button>
                                    </form>
                                </div>
                                <div class="tab-pane fade" id="v-pills-auth" aria-labelledby="v-pills-auth-tab">
                                    <form class="needs-validation" novalidate>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">网络验证接口开关</label>
                                                    <select name="authorization[control]" class="form-control select2">
                                                        <option value="on" {{ (config('api.authorization.control', 'off') == 'on') ?'selected':'' }}>开启授权验证</option>
                                                        <option value="off" {{ (config('api.authorization.control', 'off') == 'off') ?'selected':'' }}>关闭授权验证</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">自动注入网络验证</label>
                                                    <select name="authorization[injection]" class="form-control select2">
                                                        <option value="on" {{ (config('api.authorization.injection', 'off') == 'on') ?'selected':'' }}>开启自动注入</option>
                                                        <option value="off" {{ (config('api.authorization.injection', 'off') == 'off') ?'selected':'' }}>关闭自动注入</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">远程控制公告设置</label>
                                                    <select name="authorization[notice]" class="form-control select2">
                                                        <option value="off" {{ (config('api.authorization.notice', 'off') == 'off') ?'selected':'' }}>不弹软件公告弹窗</option>
                                                        <option value="on" {{ (config('api.authorization.notice', 'off') == 'on') ?'selected':'' }}>弹出软件公告弹窗</option>
                                                        <option value="official" {{ (config('api.authorization.notice', 'off') == 'official') ?'selected':'' }}>弹出软件公告弹窗+官网跳转按钮</option>
                                                        <option value="exit" {{ (config('api.authorization.notice', 'off') == 'exit') ?'selected':'' }}>弹出软件公告弹窗+官网跳转按钮+闪退</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">软件心跳间隔秒数</label>
                                                    <input type="text" class="form-control" name="authorization[time]" value="{{ config('api.authorization.time', 5) }}" required>
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>网络验证说明</strong>
                                                    <br>网络验证接口开关：关闭后"授权验证|弹框功能"将失效，建议开启。
                                                    <br>自动注入网络验证：当开启自动注入后，您的所有安装包签名时系统会自动注入。
                                                    <br>软件心跳间隔秒数：软件心跳间隔时间，用于实时检测授权状态【默认 5秒 设置 0 则不检测】。
                                                    <br>注意：开启自动注入后，您的软件如果已经有防注入等功能则需要自行修改软件进行适配，否则无法正常使用！
                                                    <hr>此库为简易功能版本，如需定制高级防护或功能请联系作者：1276117137。
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary" type="submit">保存信息</button>
                                    </form>
                                </div>
                                <div class="tab-pane fade" id="v-pills-storage" aria-labelledby="v-pills-storage-tab">
                                    <form class="needs-validation" novalidate>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">文件存储模式</label>
                                                    <select name="default[files]" class="form-control select2">
                                                        <option value="local" {{ ($arr['default']['files'] == 'local') ?'selected':'' }}>本地文件存储</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary" type="submit">保存信息</button>
                                    </form>
                                </div>
                                <div class="tab-pane fade" id="v-pills-application" aria-labelledby="v-pills-application-tab">
                                    <form class="needs-validation" novalidate>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">自动添加软件源</label>
                                                    <input type="text" class="form-control" name="application[official_source]" value="{{ $arr['application']['official_source'] }}">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">教程地址</label>
                                                    <input type="text" class="form-control" name="application[tutorial]" value="{{ $arr['application']['tutorial'] }}">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">群聊地址</label>
                                                    <input type="text" class="form-control" name="application[group_chat]" value="{{ $arr['application']['group_chat'] }}">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">反馈地址</label>
                                                    <input type="text" class="form-control" name="application[feedback]" value="{{ $arr['application']['feedback'] }}">
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="alert alert-primary alert-dismissible">
                                                    <strong>20250918版本以后采用自主开发</strong>
                                                    <br>基于 Swift6 + SwiftUI + SwiftData 开发
                                                    <br>仅供此系统用户使用、禁止第三方证书导入
                                                    <br>软件源管理：普通软件源、加密软件源解析导入
                                                    <br>重签名修改：图标、包ID、版本号、Info.plist文件、以及其他包内容
                                                    <br>重签名删除：跳转URL、插件、手表、动态库、静态库、签名后移除描述文件等
                                                    <br>重签名其他：注入动/静态库、增/改/删Info.plist内容、开关权限、修复白图标等等
                                                    <br>批量化导入：支持批量化本地文件导入、以及其他应用共享导入、自动化识别文件类型
                                                    <br>软件源拉黑：后台拉黑指定软件源使签名工具不支持添加、拉黑软件源支持模糊黑名单
                                                    <br>拉黑设备码：后台动态拉黑设备签名工具闪退（解除拉黑需后台删除黑名单并且重新打开工具）
                                                    <hr>最后声明：此APP为作者纯源码开发、捆绑本系统以及授权贴牌 App 形式。最低支持 iOS17+
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary" type="submit">保存信息</button>
                                    </form>
                                </div>
                                <div class="tab-pane fade" id="v-pills-exclusion-update" aria-labelledby="v-pills-exclusion-update-tab">
                                    <form class="needs-validation" novalidate>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="mb-3 position-relative">
                                                    <label class="form-label">整站更新排除列表</label>
                                                    <div>
                                                        <textarea name="basics[exclusion_update]" class="form-control" rows="20">{{ str_replace('\r\n', "\n", $arr['basics']['exclusion_update']) }}</textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary" type="submit">保存信息</button>
                                    </form>
                                </div>
                                <div class="tab-pane fade" id="v-pills-enterprise-certificate" aria-labelledby="v-pills-enterprise-certificate-tab">
                                    <div id="certificate-info-container">
                                        <!-- 证书信息将在这里渲染 -->
                                    </div>
                                    <div class="mt-4">
                                        <button id="upload-certificate-btn" class="btn btn-primary">上传或更新免费证书</button>
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
    <script>
        (function() {
            'use strict';
            
            // 渲染主题预览
            function renderThemePreview(selectedOption) {
                const themeData = {
                    key: selectedOption.value,
                    name: selectedOption.getAttribute('data-name'),
                    introduction: selectedOption.getAttribute('data-introduction'),
                    version: selectedOption.getAttribute('data-version'),
                    images: JSON.parse(selectedOption.getAttribute('data-images') || '[]')
                };
                const previewContainer = document.getElementById('themePreview');
                let html = '';
                html += `
                    <div class="theme-card">
                        <div class="theme-card-body">
                            <h5 class="mb-3">
                                <i class="fas fa-palette me-2"></i>${themeData['name']}
                            </h5>
                            <p class="fs-6 mb-3">${themeData['introduction']}</p>
                            <div class="row">
                                <div class="col-6">
                                    <small class="badge bg-light text-dark">
                                        <i class="fas fa-tag me-1"></i>版本: ${themeData['version']}
                                    </small>
                                </div>
                                <div class="col-6">
                                    <small class="badge bg-light text-dark">
                                        <i class="fas fa-file-code me-1"></i>${themeData['key']}.blade.php
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                `;
                if (themeData.images && themeData.images.length > 0) {
                    const carouselId = 'themeCarousel_' + themeData.key;
                    html += `
                        <div id="${carouselId}" class="carousel slide theme-carousel" data-bs-ride="carousel">
                            ${themeData.images.length > 1 ? `
                            <div class="carousel-indicators">
                                ${themeData.images.map((_, index) => `
                                    <button type="button" data-bs-target="#${carouselId}" data-bs-slide-to="${index}" 
                                            class="${index === 0 ? 'active' : ''}" aria-current="${index === 0 ? 'true' : 'false'}" 
                                            aria-label="图片 ${index + 1}"></button>
                                `).join('')}
                            </div>
                            ` : ''}
                            <div class="carousel-inner">
                                ${themeData.images.map((image, index) => `
                                    <div class="carousel-item ${index === 0 ? 'active' : ''}">
                                        <img src="${image}" class="d-block w-100" alt="${themeData.name} 预览图 ${index + 1}" 
                                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPuWbvueJh+WKoOi9veWkseaViTwvdGV4dD48L3N2Zz4='; this.onerror=null;">
                                    </div>
                                `).join('')}
                            </div>
                            ${themeData.images.length > 1 ? `
                            <button class="carousel-control-prev" type="button" data-bs-target="#${carouselId}" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">上一个</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#${carouselId}" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">下一个</span>
                            </button>
                            ` : ''}
                        </div>
                    `;
                } else {
                    html += `
                        <div class="no-images">
                            <i class="fas fa-image fa-3x mb-3"></i>
                            <h5>该主题没有演示图片</h5>
                            <p class="mb-0">可在 public/theme/${themeData.key}/preview/ 目录下添加预览图片</p>
                        </div>
                    `;
                }            
                previewContainer.innerHTML = html;
            }
            
            // 渲染证书信息
            function renderCertificateInfo() {
                let p12 = '{{ $free_cert['p12'] ?? "" }}';
                let mobileprovision = '{{ $free_cert['mobileprovision'] ?? "" }}';
                let password = '{{ $free_cert['password'] ?? "" }}';
                
                const certificateContainer = document.getElementById('certificate-info-container');
                
                if (p12 && mobileprovision && password) {
                    layer.load(2);
                    SendAjax({
                        'url': '/api/inspection_certificate',
                        'data': {
                            p12: p12,
                            mobileprovision: mobileprovision,
                            password: password
                        },
                        'successCallBack': function(response) {
                            if (response.data) {
                                // 解析证书数据
                                const certData = response.data;
                                const certificates = certData.certificates || {};
                                const mobileprovision = certData.mobileprovision || {};
                                const permissions = certData.permissions || {};
                                
                                // 权限英文到中文的映射
                                const permissionsMap = {
                                    'push_to_talk': '推动谈话',
                                    'journaling_suggestions': '日志记录建议',
                                    'multitasking_camera_access': 'iPad多任务相机访问',
                                    'shallow_depth_and_pressure': '浅深度和压力',
                                    'accessibility_merchant_api_control': '辅助功能商户API控制',
                                    'matter_allow_setup_payload': 'Matter允许设置负载',
                                    'access_wifi_information': '访问Wi-Fi信息',
                                    'app_groups': '应用程序组',
                                    'critical_messaging': '关键消息',
                                    'in_app_purchase': '应用内购买',
                                    'default_calling_app': '默认通话应用',
                                    'associated_domains': '关联域',
                                    'autofill_credential_provider': '自动填充凭证提供程序',
                                    'default_messaging_app': '默认消息应用',
                                    'sustained_execution': '持续执行',
                                    'classKit_environment': 'ClassKit环境',
                                    'driverKit_communicates_with_drivers': 'DriverKit与驱动程序通信',
                                    'driverKit_allow_third_party_userclients': '允许第三方用户客户端',
                                    'healthkit': 'HealthKit',
                                    'healthkit_access': 'HealthKit访问',
                                    'homekit': 'HomeKit',
                                    'hotspot_configuration': '热点配置',
                                    'id_verifier_display_only': 'ID验证器-仅显示',
                                    'managed_app_installation_ui': '托管应用安装UI',
                                    'communication_notifications': '通讯通知',
                                    'time_sensitive_notifications': '时间敏感通知',
                                    'inter_app_audio': '应用间音频',
                                    'multipath': '多路径',
                                    'network_extensions': '网络扩展',
                                    'nfc_tag_reading': 'NFC标签读取',
                                    'sim_inserted_for_wireless_carriers': 'SIM卡插入',
                                    'push_notifications_environment': '推送通知环境',
                                    'sensitive_content_analysis': '敏感内容分析',
                                    'siri': 'Siri',
                                    'personal_vpn': '个人VPN',
                                    'wireless_accessory_configuration': '无线配件配置',
                                    'wallet_pass_type_identifiers': 'Wallet通行证类型',
                                    'group_session': '群组会话',
                                    'hls_interstitial_previews': 'HLS插播预览',
                                    'app_attest_opt_in': '应用程序证明选择加入',
                                    'spatial_audio_profile': '空间音频配置文件',
                                    'low_latency_hls': '低延迟HLS',
                                    'shared_with_you': '与你共享',
                                    'app_attest_environment': '应用程序证明环境',
                                    'extended_virtual_addressing': '扩展虚拟寻址',
                                    'mdm_managed_associated_domains': 'MDM管理的关联域',
                                    'shared_with_you_collaboration': '共享协作',
                                    '5g_network_slicing_app_category': '5G网络切片应用类别',
                                    'on_demand_install_capable': '按需安装能力',
                                    'default_navigation_app': '默认导航应用',
                                    '5g_network_slicing_traffic_category': '5G网络切片流量类别',
                                    'healthkit_estimate_recalibration': 'HealthKit估算重新校准',
                                    'head_pose': '头部姿势',
                                    'keychain_access_groups': '钥匙串访问组',
                                    'weatherkit': 'WeatherKit',
                                    'apple_pay_later_merchandising': 'Apple Pay Later商品推广',
                                    'debugging_allow': '调试权限',
                                    'increased_debugging_memory_limit': '增加调试内存限制',
                                    'game_center': '游戏中心',
                                    'increased_memory_limit': '增加内存限制',
                                    'healthKit_background_delivery': 'HealthKit后台交付',
                                    'fileprovider_testing_mode': '文件提供程序测试模式',
                                    'default_translation_app': '默认翻译应用',
                                    'user_fonts': '用户字体',
                                    'wifi': 'Wi-Fi',
                                    'hotspot': '热点',
                                    'aps': '推送服务',
                                    'keychain': '钥匙串',
                                    'debug': '调试',
                                    'health': '健康',
                                    'groups': '群组',
                                    'purchase': '购买',
                                    'domains': '域',
                                    'autofill': '自动填充',
                                    'class': '课堂',
                                    'home': '家庭',
                                    'game': '游戏',
                                    'audio': '音频',
                                    'networkextension': '网络扩展',
                                    'nfc': 'NFC',
                                    'vpn': 'VPN',
                                    'wireless': '无线',
                                    'hls_low_latency': 'HLS低延迟',
                                    'health_access': '健康访问'
                                };
                                
                                // 获取已启用和未启用的权限
                                const enabledCapabilities = [];
                                const disabledCapabilities = [];
                                
                                for (const [key, value] of Object.entries(permissions)) {
                                    const displayName = permissionsMap[key] || key;
                                    if (value === true) {
                                        enabledCapabilities.push({key: key, name: displayName});
                                    } else if (value === false) {
                                        disabledCapabilities.push({key: key, name: displayName});
                                    }
                                }
                                
                                // 关闭加载指示器
                                layer.closeAll('loading');
                                
                                // 构建证书详情页面
                                certificateContainer.innerHTML = `
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body p-4">
                                            <h4 class="card-title text-primary mb-4">
                                                <i class="fas fa-lock me-2"></i> 证书详情
                                            </h4>
                                            <div class="row g-4">
                                                <div class="col-md-6">
                                                    <div class="card border-0 bg-light h-100">
                                                        <div class="card-body p-4">
                                                            <h5 class="mb-3 d-flex align-items-center">
                                                                <i class="fas fa-file-certificate me-2 text-primary"></i>
                                                                P12 证书文件
                                                            </h5>
                                                            <div class="row mb-2">
                                                                <div class="col-5 text-muted">证书名称：</div>
                                                                <div class="col-7 fw-medium">${certificates.name || '未知'}</div>
                                                            </div>
                                                            <div class="row mb-2">
                                                                <div class="col-5 text-muted">证书UID：</div>
                                                                <div class="col-7 fw-medium">${certificates.uid || '未知'}</div>
                                                            </div>
                                                            <div class="row mb-2">
                                                                <div class="col-5 text-muted">签发单位：</div>
                                                                <div class="col-7 fw-medium">${certificates.unit || '未知'}</div>
                                                            </div>
                                                            <div class="row mb-2">
                                                                <div class="col-5 text-muted">有效期：</div>
                                                                <div class="col-7 fw-medium">${certificates.from || '未知'} 至 ${certificates.to || '未知'}</div>
                                                            </div>
                                                            <div class="row mb-2">
                                                                <div class="col-5 text-muted">摘销状态：</div>
                                                                <div class="col-7 fw-medium">
                                                                    <span class="badge ${certificates.is_revoked === false ? 'bg-success' : 'bg-danger'}">
                                                                        ${certificates.is_revoked === false ? '正常' : '已摘销'}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card border-0 bg-light h-100">
                                                        <div class="card-body p-4">
                                                            <h5 class="mb-3 d-flex align-items-center">
                                                                <i class="fas fa-file-alt me-2 text-primary"></i>
                                                                描述文件
                                                            </h5>
                                                            <div class="row mb-2">
                                                                <div class="col-5 text-muted">文件名称：</div>
                                                                <div class="col-7 fw-medium text-truncate">${mobileprovision.name || '未知'}</div>
                                                            </div>
                                                            <div class="row mb-2">
                                                                <div class="col-5 text-muted">应用ID：</div>
                                                                <div class="col-7 fw-medium">${mobileprovision.id || '未知'}</div>
                                                            </div>
                                                            <div class="row mb-2">
                                                                <div class="col-5 text-muted">证书类型：</div>
                                                                <div class="col-7 fw-medium">${mobileprovision.cert_type || '未知'}</div>
                                                            </div>
                                                            <div class="row mb-2">
                                                                <div class="col-5 text-muted">有效期：</div>
                                                                <div class="col-7 fw-medium">${mobileprovision.from || '未知'} 至 ${mobileprovision.to || '未知'}</div>
                                                            </div>
                                                            <div class="row mb-2">
                                                                <div class="col-5 text-muted">支持平台：</div>
                                                                <div class="col-7 fw-medium">${mobileprovision.platform ? mobileprovision.platform.join(', ') : '未知'}</div>
                                                            </div>
                                                            <div class="row mb-2">
                                                                <div class="col-5 text-muted">剩余天数：</div>
                                                                <div class="col-7 fw-medium">
                                                                    <span class="badge ${mobileprovision.cert_end_date > 30 ? 'bg-success' : 'bg-danger'}">
                                                                        ${mobileprovision.cert_end_date || '0'}天
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card shadow-sm">
                                        <div class="card-body p-4">
                                            <h4 class="card-title text-primary mb-4">
                                                <i class="fas fa-key me-2"></i> 证书权限
                                            </h4>
                                            
                                            <h5 class="mb-3 d-flex align-items-center">
                                                <i class="fas fa-check-circle me-2 text-success"></i>
                                                已启用权限
                                            </h5>
                                            <div class="mb-4">
                                                ${enabledCapabilities.length > 0 
                                                  ? enabledCapabilities.map(cap => `<span class="badge bg-success rounded-pill m-1 p-2" title="${cap.key}">${cap.name}</span>`).join('') 
                                                  : '<p class="text-muted">无启用权限</p>'}
                                            </div>
                                            
                                            <h5 class="mb-3 d-flex align-items-center">
                                                <i class="fas fa-times-circle me-2 text-secondary"></i>
                                                未启用权限
                                            </h5>
                                            <div>
                                                ${disabledCapabilities.length > 0 
                                                  ? disabledCapabilities.map(cap => `<span class="badge bg-light text-dark rounded-pill m-1 p-2" title="${cap.key}">${cap.name}</span>`).join('') 
                                                  : '<p class="text-muted">无未启用权限</p>'}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            } else {
                                certificateContainer.innerHTML = `
                                    <div class="alert alert-warning">
                                        <h4 class="alert-heading">证书验证失败</h4>
                                        <p>证书信息无法解析，请检查证书文件和密码是否正确。</p>
                                    </div>
                                `;
                            }
                        },
                        'errorCallBack': function() {
                            certificateContainer.innerHTML = `
                                <div class="alert alert-danger">
                                    <h4 class="alert-heading">证书验证出错</h4>
                                    <p>请求证书信息时发生错误，请稍后再试。</p>
                                </div>
                            `;
                        }
                    });
                } else {
                    certificateContainer.innerHTML = `
                        <div class="alert alert-info">
                            <h4 class="alert-heading">尚未上传免费证书</h4>
                            <br>
                            <p>请点击下方按钮上传企业证书文件和密码。</p>
                        </div>
                    `;
                }
            }
            
            // 上传证书
            function uploadCertificate() {
                layer.open({
                    type: 1,
                    anim: 'slideDown',
                    title: '上传开发者证书',
                    content: `
                    <div style="padding: 16px;">
                        <small>您可以手动上传新的企业开发者证书供应用户使用。【部分主题支持】</small>
                        <hr>
                        <form class="layui-form" id="uploadCert">
                            <input type="hidden" name="udid" value="Enterprise" lay-verify="required" class="layui-input">
                            <textarea name="p12" placeholder="点击上传 p12 证书文件" class="layui-textarea" lay-verify="p12" readonly></textarea>
                            <hr class="ws-space-16">
                            <textarea name="mobileprovision" placeholder="点击上传 mobileprovision 描述文件" class="layui-textarea" lay-verify="mobileprovision" readonly></textarea>
                            <hr class="ws-space-16">
                            <input type="text" name="password" placeholder="在此输入证书密码" lay-verify="password" class="layui-input">
                            <hr class="ws-space-16">
                            <button class="layui-btn layui-btn-fluid" lay-submit lay-filter="uploadCert">上传更新</button>
                        </form>
                    </div>
                    `,
                    success: function(){
                        layui.form.render();
                        $('textarea[name="p12"]').click(function() {
                            const p12 = document.createElement('input');
                            p12['type'] = 'file';
                            p12['accept'] = 'application/x-pkcs12';
                            $('body').append(p12);
                            let p12_data = '';
                            p12.onchange = (event) => {
                                const p12File = event['target']['files'][0];
                                if (p12File) {
                                    try {
                                        if (p12File.name.endsWith('.p12')) {
                                            let reader = new FileReader();
                                            reader.onload = function(e) {
                                                p12_data = e['target']['result'].replace(/:(.*?);/, ':application/x-pkcs12;');
                                                const Textarea = $('#uploadCert textarea[name="p12"]');
                                                if (Textarea) {
                                                    Textarea.val(p12_data.split(',')[1]);
                                                }
                                            };
                                            reader.readAsDataURL(p12File);
                                        } else {
                                            layer.msg(`${p12File['name']} 不是证书文件`);
                                        }
                                    } catch (error) {
                                        layer.msg('解析证书文件数据时发生错误');
                                    } finally {
                                        if (p12['parentNode']) {
                                            p12['parentNode'].removeChild(p12);
                                        }
                                    }
                                }
                            };
                            p12.click();
                        });
                        $('textarea[name="mobileprovision"]').click(function() {
                            const mobileprovision = document.createElement('input');
                            mobileprovision.type = 'file';
                            mobileprovision.accept = 'application/x-apple-aspen-mobileprovision';
                            $('body').append(mobileprovision);
                            let mobileprovision_data = '';
                            mobileprovision.onchange = (event) => {
                                const mobileprovisionFile = event['target']['files'][0];
                                if (mobileprovisionFile) {
                                    try {
                                        if (mobileprovisionFile.name.endsWith('.mobileprovision')) {
                                            let reader = new FileReader();
                                            reader.onload = function(e) {
                                                mobileprovision_data = e['target']['result'].replace(/:(.*?);/, ':application/x-apple-aspen-mobileprovision;');
                                                const Textarea = $('#uploadCert textarea[name="mobileprovision"]');
                                                if (Textarea) {
                                                    Textarea.val(mobileprovision_data.split(',')[1]);
                                                }
                                            };
                                            reader.readAsDataURL(mobileprovisionFile);
                                        } else {
                                            layer.msg(`${mobileprovisionFile['name']} 不是证书文件`);
                                        }
                                    } catch (error) {
                                        layer.msg('解析证书文件数据时发生错误');
                                    } finally {
                                        if (mobileprovision['parentNode']) {
                                            mobileprovision['parentNode'].removeChild(mobileprovision);
                                        }
                                    }
                                }
                            };
                            mobileprovision.click();
                        });
                        layui.form.verify({
                            p12: function(value) {
                                if (!value) {
                                    return '请先上传 p12 证书文件';
                                }
                            },
                            mobileprovision: function(value) {
                                if (!value) {
                                    return '请先上传 mobileprovision 描述文件';
                                }
                            },
                            password: function(value) {
                                if (!value) {
                                    return '证书密码不能为空';
                                }
                            },
                        });
                        layui.form.on('submit(uploadCert)', function(formData) {
                            layer.load(2);
                            SendAjax({
                                'url': systemPath+'/developer/update',
                                'data': {
                                    p12: formData['field']['p12'],
                                    mobileprovision: formData['field']['mobileprovision'],
                                    password: formData['field']['password'],
                                    udid: formData['field']['udid'],
                                },
                                'successCallBack': function () {
                                    layer.msg('上传新的免费企业证书成功', { icon: 1, time: 3000 });
                                }
                            });
                            return false;
                        });
                    }
                });
            }
            
            window.addEventListener('load', function() {
                $(".select2").select2();
                $('a[data-bs-toggle="pill"]').on('shown.bs.tab', function () {
                    $(".select2").select2();
                    
                    // 如果切换到企业证书标签，渲染证书信息
                    if ($(this).attr('href') === '#v-pills-enterprise-certificate') {
                        renderCertificateInfo();
                    }
                });
                
                // 初始化显示当前选中的主题
                const themeSelector = document.getElementById('themeSelector');
                if (themeSelector) {
                    const selectedOption = themeSelector.options[themeSelector.selectedIndex];
                    renderThemePreview(selectedOption);
                    
                    // 监听主题选择变化
                    $('#themeSelector').on('change', function() {
                        const selectedOption = this.options[this.selectedIndex];
                        renderThemePreview(selectedOption);
                    });
                }
                
                // 证书上传按钮事件监听
                document.getElementById('upload-certificate-btn').addEventListener('click', uploadCertificate);
                
                // 如果当前标签是企业证书，渲染证书信息
                if (window.location.hash === '#v-pills-enterprise-certificate') {
                    renderCertificateInfo();
                }
                
                const forms = document.getElementsByClassName('needs-validation');
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
                                'url': systemPath+'/settings',
                                'data': $("form").serialize(),
                                'successCallBack': function () {
                                    layer.msg('修改成功', { icon: 1, time: 3000 });
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
