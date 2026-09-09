@extends('errors::minimal')

@section('title', __('Server Error'))
@section('code', '500')
@section('message', __('服务异常错误，请联系管理员！'))
