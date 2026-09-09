<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="keywords" content="{{ config('app.name') }},极速网络UDID签名系统,极速网络Apple签名系统V2,极速网络,iOS签名,iOS在线签名,UDID重签">
        <meta name="description" content="极速网络Apple签名系统V2基于：Laravel10.x、PHP8.2、MySQL、Redis开发，支持自有证书签名、免费共享证书签名、兑换码模式签名、修改包ID、包名称、替换图标、修改权限等功能">
        <title>@yield('code') @yield('title')</title>
        <style>
            html,body{background-color:#fff;color:#636b6f;font-family:'Nunito',sans-serif;font-weight:100;height:100vh;margin:0;}.full-height{height:100vh;}.flex-center{align-items:center;display:flex;justify-content:center;}.position-ref{position:relative;}.code{border-right:2px solid;font-size:26px;padding:0 15px 0 15px;text-align:center;}.message{font-size:18px;text-align:center;}
        </style>
    </head>
    <body>
        <div class="flex-center position-ref full-height">
            <div class="code">@yield('code')</div>
            <div class="message" style="padding: 10px;">@yield('message')</div>
        </div>
    </body>
</html>