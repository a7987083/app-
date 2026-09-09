@extends('agent.layouts.master')
@section('title'){{ __('数据统计') }}@endsection
@section('link')
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/datatables/datatables.min.css" rel="stylesheet" type="text/css">
@endsection

@section('content')
<div class="row">
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span>{{ $agent['credit'] }}</span> 点</h4>
                    <p class="text-muted mb-0">账户当前剩余可用点数</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span>{{ $agent['price'] }}</span> 点 / 台</h4>
                    <p class="text-muted mb-0">账户当前默认卡密收费</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span>{{ $agent['good_price'] }}</span> 点 / 台</h4>
                    <p class="text-muted mb-0">账户当前秒出卡密收费</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span>{{ $agent['processing_price'] }}</span> 点 / 台</h4>
                    <p class="text-muted mb-0">账户当前预约卡密收费</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span>{{ $agent['ipad_price'] }}</span> 点 / 台</h4>
                    <p class="text-muted mb-0">iPad账户当前默认卡密收费</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span>{{ $agent['ipad_good_price'] }}</span> 点 / 台</h4>
                    <p class="text-muted mb-0">iPad账户当前秒出卡密收费</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span>{{ $agent['ipad_processing_price'] }}</span> 点 / 台</h4>
                    <p class="text-muted mb-0">iPad账户当前预约卡密收费</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span>{{ $code['all'] }}</span> 个</h4>
                    <p class="text-muted mb-0">账户当前的总卡密数量</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span>{{ $code['enabled'] }}</span> 个</h4>
                    <p class="text-muted mb-0">账户未激活的卡密数量</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span>{{ $code['disabled'] }}</span> 个</h4>
                    <p class="text-muted mb-0">账户已激活的卡密数量</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span>{{ $code['default'] }}</span> 个</h4>
                    <p class="text-muted mb-0">账户默认类型卡密数量</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span>{{ $code['good'] }}</span> 个</h4>
                    <p class="text-muted mb-0">账户秒出证书卡密数量</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span>{{ $code['processing'] }}</span> 个</h4>
                    <p class="text-muted mb-0">账户预约证书卡密数量</p>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4">今日激活卡密</h4>
                <div class="table-responsive">
                    <table id="DataTable" class="table table-striped table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;"></table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('script')
<script src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/jquery-knob/jquery-knob.min.js"></script>
<script>
    let DataTable = $('#DataTable');
    DataTable.DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        buttons: DataTablesConfig.buttons,
        ajax: function(data, callback) {
            SendAjax({
                url: systemPath+'/console',
                data: data,
                successCallBack: function(response) {
                    const responseJSON = {};
                    responseJSON.draw = response['data']['draw'];
                    responseJSON.recordsTotal = response['data']['recordsTotal'];
                    responseJSON.recordsFiltered = response['data']['recordsFiltered'];
                    responseJSON.data = response['data']['data'];
                    callback(responseJSON);
                },
            });
        },
        order: [[ 9, 'desc' ]],
        columns: [
            {
                title: '<small>卡密信息</small>',
                data: 'code',
                render: function(data, type, row) {
                    return '<small>' + row['code'] + '</small>';
                }
            },
            {
                title: '<small>绑定设备</small>',
                data: 'udid',
                render: function(data, type, row) {
                    if (row['udid']) {
                        return '<small>' + row['udid'] + '</small>';
                    } else {
                        return '<small></small>';
                    }
                }
            },
            {
                title: '<small>备注信息</small>',
                data: 'remark',
                render: function(data, type, row) {
                    if (row.remark) {
                        return '<small>' + row['remark'] + '</small>';
                    } else {
                        return '<small></small>';
                    }
                }
            },
            {
                title: '<small>卡密类型</small>',
                data: 'type',
                render: function(data, type, row) {
                    switch (row['type']) {
                        case 'default':
                            return '<div class="badge bg-primary">默认模式</div>';
                        case 'processing':
                            return '<div class="badge bg-warning">预约证书</div>';
                        case 'good':
                            return '<div class="badge bg-success">秒出证书</div>';
                    }
                }
            },
            {
                title: '<small>售后天数</small>',
                data: 'after_sale_day',
                render: function(data, type, row) {
                    return '<small>' + row['after_sale_day'] + '</small>';
                }
            },
            {
                title: '<small>售后次数</small>',
                data: 'after_sale_num',
                render: function(data, type, row) {
                    return '<small>' + row['after_sale_num'] + '</small>';
                }
            },
            {
                title: '<small>已用售后</small>',
                data: 'use_after_sale',
                render: function(data, type, row) {
                    return '<small>' + row['use_after_sale'] + '</small>';
                }
            },
            {
                title: '<small>激活日期</small>',
                data: 'verified_at',
                render: function(data, type, row) {
                    return '<small>'+row['verified_at']+'</small>';
                }
            },
            {
                title: '<small>到期日期</small>',
                data: 'maturity_at',
                render: function(data, type, row) {
                    return '<small>'+row['maturity_at']+'</small>';
                }
            },
            {
                title: '<small>创建日期</small>',
                data: 'created_at',
                render: function(data, type, row) {
                    return '<small>'+row['created_at']+'</small>';
                }
            }
        ]
    });
</script>
@endsection