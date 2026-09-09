<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.3.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.3.1/css/bootstrap-extended.css" rel="stylesheet">
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.3.1/css/main.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid my-5">
    <div class="row">
        <div class="col-12 col-md-8 col-lg-6 col-xl-5 col-xxl-4 mx-auto">
            <div class="card rounded-4">
                <div class="card-body p-5">
                    <h4 class="fw-bold">后台登录</h4>
                    <p class="mb-0">{{ config('app.name') }}</p>
                    <div class="form-body my-4">
                        @error('errors')
                        <div class="alert alert-danger" role="alert">
                            {{ $message }}
                        </div>
                        @enderror
                        <form class="row g-3" method="POST" action="{{ url(config('api.admin.path', 'admin').'/login') }}">
                            @csrf
                            <div class="col-12">
                                <label for="email" class="form-label">后台账号</label>
                                <input type="email" class="form-control @error('errors') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                            </div>
                            <div class="col-12">
                                <label for="password" class="form-label">后台密码</label>
                                <input type="password" class="form-control @error('errors') is-invalid @enderror" name="password" required autocomplete="current-password">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                                    <label class="form-check-label">记住密码</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">登录</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>