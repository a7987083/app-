<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\Route;
Route::rule('appstore','index/App/list');
Route::rule('log','index/App/log');
Route::rule('unbind','index/Index/unbind');
Route::rule('unbind/query','index/Index/unbindQuery');
Route::rule('license','index/Index/license');
return [
    '__alias__'   => [
    ],
    '__pattern__' => [
    ],
];
