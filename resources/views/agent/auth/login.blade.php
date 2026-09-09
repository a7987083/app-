<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }}丨代理登录</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/css/bootstrap.css" rel="stylesheet" type="text/css">
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/css/icons.css" rel="stylesheet" type="text/css">
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/css/app.css" rel="stylesheet" type="text/css">
    <link href="{{ config('api.basics.cdn') }}/theme/layui/css/layui.css"  rel="stylesheet" type="text/css">
</head>
<body class="authentication-bg">
    <div class="account-pages my-5 pt-sm-5">
        <div class="container">
            <div class="row align-items-center justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">
                    <div class="card">
                        <div class="card-body p-4">
                            <div class="p-2 mt-4">
                                @error('errors')
                                <div class="alert alert-danger" role="alert">
                                    {{ $message }}
                                </div>
                                @enderror
                                <form class="needs-validation" action="{{ route('agent.login') }}" method="POST" novalidate>
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label" for="email">{{ __('代理邮箱') }}</label>
                                        <input type="text" class="form-control @error('errors') is-invalid @enderror" name="email" placeholder="{{ __('输入代理邮箱') }}" value="{{ old('email') }}" required>
                                    </div>
                                    <div class="mb-3">
                                        <div class="float-end">
                                            <a href="{{ route('agent.forgot-password') }}" class="text-muted">{{ __('忘记密码了？') }}</a>
                                        </div>
                                        <label class="form-label" for="password">{{ __('代理密码') }}</label>
                                        <input type="password" class="form-control @error('errors') is-invalid @enderror" name="password" placeholder="{{ __('输入代理密码') }}" required>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="remember" {{ old('remember') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="auth-remember-check">{{ __('记住密码') }}</label>
                                    </div>
                                    <div class="mt-3 text-end">
                                        <button class="btn btn-primary w-sm waves-effect waves-light" type="submit">{{ __('登录') }}</button>
                                    </div>
                                    <div class="mt-4 text-center">
                                        <p class="mb-0">{{ __('还没有账号吗？') }}<a href="{{ route('agent.register') }}" class="fw-medium text-primary"> {{ __('立即注册') }} </a></p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/jquery/jquery.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/bootstrap/bootstrap.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/metismenu/metismenu.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/node-waves/node-waves.min.js"></script>
    <script src="{{ config('api.basics.cdn') }}/theme/layui/js/layui.js"></script>
</body>
</html>