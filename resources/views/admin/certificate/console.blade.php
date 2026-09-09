@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('数据统计') }}@endsection
@section('link')
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
@endsection

@section('content')
<div class="row">
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span id="api_credit">0.00</span> 点</h4>
                    <p class="text-muted mb-0">当前接口剩余额度</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span id="public_credit">0.00</span> 点</h4>
                    <p class="text-muted mb-0">当前公池剩余额度</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span id="public_earnings_day">0.00</span> 点</h4>
                    <p class="text-muted mb-0">公池销售今日收益</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div>
                    <h4 class="mb-1 mt-1"><span id="public_earnings_all">0.00</span> 点</h4>
                    <p class="text-muted mb-0">公池销售历史收益</p>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4">开发者证书管理 - 新闻中心</h4>
                <div class="activity-feed mb-0 ps-2" id="news" style="max-height: 246px;">
                    <div id="usersNewsList"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card bg-primary">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-sm-8">
                        <p class="text-white font-size-13">
                            <b>面向开发者的优势与便捷：</b>
                            <br>
                            <br><b>一、高效文档与接口细化（ISP）</b>
                            <br>拥有强大的接口开发文档，以便开发者快速上手开发自定义高效平台。并且将大型接口拆分为更小、更具体的接口，以便开发者只需调用它们所需的功能，而不需要依赖于不相关的功能，提高代码的可维护性和可扩展性。
                            <br>
                            <br><b>二、适配当下主流第三方平台接口</b>
                            <br>平台根据用户需求量快速开发适配当下主流平台接口，以便普通用户实现无缝衔接对接本平台，无需自主开发即可快速上手。
                        </p>
                        <div class="mt-4">
                            <a href="https://api-cer.doc.dvffz8.cn" class="btn btn-success waves-effect waves-light" target="_blank">前往开发者文档</a>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="mt-4 mt-sm-0">
                            <img src="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/images/setup-analytics-amico.svg" class="img-fluid" alt="开发者">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-xl-6">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4">平台证书统计</h4>
                <div id="usersCertificatesChart" data-colors='["--bs-secondary", "--bs-success", "--bs-danger"]' class="apex-charts"></div>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4">平台设备统计</h4>
                <div id="usersDevicesChart" data-colors='["--bs-warning", "--bs-info", "--bs-secondary" ,"--bs-success", "--bs-danger", "--bs-primary", "--bs-dark", "--bs-success"]' class="apex-charts"></div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4">审核中的设备</h4>
                <div class="table-responsive">
                    <table id="usersProcessing" class="table table-striped table-bordered dt-responsive nowrap"></table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4">不合格的设备</h4>
                <div class="table-responsive">
                    <table id="usersIneligible" class="table table-striped table-bordered dt-responsive nowrap"></table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    layer.load(2);
    SendAjax({
        'url': systemPath+'/certificate/v1/lists_news',
        'successCallBack': function (response) {
            const data = Object.values(response['data']['data']);
            const usersNewsList = document.getElementById('usersNewsList');
            data.forEach(function(item) {
                const li = document.createElement('li');
                li.className = 'feed-item';
                const div = document.createElement('div');
                div.className = 'feed-item-list';
                const title = document.createElement('p');
                title.className = 'text-muted mb-1 font-size-13';
                title.innerHTML = item['created_at']+' <small class="d-inline-block ms-1">'+item['publisher']+'</small>';
                const content = document.createElement('p');
                content.className = 'mb-0';
                content.innerHTML = item['content'];
                div.appendChild(title);
                div.appendChild(content);
                li.appendChild(div);
                usersNewsList.appendChild(li);
            });
            const news = document.getElementById('news');
            news.setAttribute('data-simplebar', 'init');
            const simpleBar = news.closest('[data-simplebar="init"]');
            new SimpleBar(simpleBar);
        }
    });
    layer.load(2);
    SendAjax({
        'url': systemPath+'/certificate/v1/chart',
        'successCallBack': function (response) {
            lay('#api_price').html(response['data']['api_price']);
            lay('#api_credit').html(response['data']['api_credit']);
            lay('#public_credit').html(response['data']['public_credit']);
            lay('#public_earnings_day').html(response['data']['public_earnings_day']);
            lay('#public_earnings_all').html(response['data']['public_earnings_all']);
            let usersDevicesChart_colors = GetChartColorsArray('usersDevicesChart');
            let usersCertificatesChart_colors = GetChartColorsArray('usersCertificatesChart');
            if (usersDevicesChart_colors) {
                let usersDevicesChart = {
                    chart: {
                        height: 320,
                        type: 'pie',
                        toolbar: {
                            show: true
                        }
                    },
                    series: response['data']['devices'],
                    labels: [
                        'Vision Pro',
                        'iPhone',
                        'Watch',
                        'iPad',
                        'iPod',
                        'Mac',
                        'TV',
                        '公共池'
                    ],
                    colors: usersDevicesChart_colors,
                    legend: {
                        show: true,
                        position: 'bottom',
                        horizontalAlign: 'center',
                        verticalAlign: 'middle',
                        floating: false,
                        fontSize: '14px',
                        offsetX: 0
                    },
                    responsive: [{
                        breakpoint: 600,
                        options: {
                            chart: {
                                height: 240
                            },
                            legend: {
                                show: false
                            }
                        }
                    }]
                };
                let chart = new ApexCharts(document.querySelector('#usersDevicesChart'), usersDevicesChart);
                chart.render();
            }
            if (usersCertificatesChart_colors) {
                let usersCertificatesChart = {
                    chart: {
                        height: 320,
                        type: 'donut',
                        toolbar: {
                            show: true
                        }
                    },
                    series: response['data']['certificates'],
                    labels: [
                        '未检测的证书',
                        '不卡设备证书',
                        '卡设备的证书'
                    ],
                    colors: usersCertificatesChart_colors,
                    legend: {
                        show: true,
                        position: 'bottom',
                        horizontalAlign: 'center',
                        verticalAlign: 'middle',
                        floating: false,
                        fontSize: '14px',
                        offsetX: 0
                    },
                    responsive: [{
                        breakpoint: 600,
                        options: {
                            chart: {
                                height: 240
                            },
                            legend: {
                                show: false
                            }
                        }
                    }]
                };
                let chart = new ApexCharts(document.querySelector('#usersCertificatesChart'), usersCertificatesChart);
                chart.render();
            }
        }
    });
    $('#usersProcessing').DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        buttons: DataTablesConfig.buttons,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        ajax: function(data, callback) {
            data['columns'][1]['search']['value'] = 'PROCESSING';
            SendAjax({
                'url': systemPath+'/certificate/v1/lists_devices',
                'data': data,
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
        order: [[ 8, 'desc' ]],
        columns: [
            {
                title: '<small>设备码</small>',
                data: 'udid',
                render: function(data, type, row) {
                    return '<small>'+row['udid']+'</small>';
                }
            },
            {
                title: '<small>设备状态</small>',
                data: 'status',
                render: function(data, type, row) {
                    let custom;
                    switch(row['status']) {
                        case 'ENABLED':
                            custom = '<div class="badge bg-success">设备可用</div>';
                            break;
                        case 'DISABLED':
                            custom = '<div class="badge bg-secondary">设备禁用</div>';
                            break;
                        case 'PROCESSING':
                            custom = '<div class="badge bg-secondary"">苹果审核</div>';
                            break;
                        case 'INELIGIBLE':
                            custom = '<div class="badge bg-danger">不合格的</div>';
                            break;
                        default:
                            custom = '<div class="badge bg-secondary">未知状态</div>';
                            break;
                    }
                    return custom;
                }
            },
            {
                title: '<small>设备ID</small>',
                data: 'devices_id',
                render: function(data, type, row) {
                    return '<small>'+row['devices_id']+'</small>';
                }
            },
            {
                title: '<small>描述文件ID</small>',
                data: 'profiles_id',
                render: function(data, type, row) {
                    return '<small>'+row['profiles_id']+'</small>';
                }
            },
            {
                title: '<small>备注信息</small>',
                data: 'remark',
                render: function(data, type, row) {
                    return '<small>'+row['remark']+'</small>';
                }
            },
            {
                title: '<small>设备机型</small>',
                data: 'model',
                render: function(data, type, row) {
                    return '<small>'+row['model']+'</small>';
                }
            },
            {
                title: '<small>设备系统</small>',
                data: 'platform',
                render: function(data, type, row) {
                    return '<small>'+row['platform']+'</small>';
                }
            },
            {
                title: '<small>设备类型</small>',
                data: 'deviceClass',
                render: function(data, type, row) {
                    return '<small>'+row['deviceClass']+'</small>';
                }
            },
            {
                title: '<small>添加日期</small>',
                data: 'adddate_at',
                render: function(data, type, row) {
                    return '<small>'+row['adddate_at']+'</small>';
                }
            },
            {
                title: '<small>更新日期</small>',
                data: 'updated_at',
                render: function(data, type, row) {
                    return '<small>'+row['updated_at']+'</small>';
                }
            },
        ]
    });
    $('#usersIneligible').DataTable({
        dom: DataTablesConfig.dom,
        language: DataTablesConfig.language,
        buttons: DataTablesConfig.buttons,
        processing: DataTablesConfig.processing,
        serverSide: DataTablesConfig.serverSide,
        pagingType: DataTablesConfig.pagingType,
        ajax: function(data, callback) {
            data['columns'][1]['search']['value'] = 'INELIGIBLE';
            SendAjax({
                'url': systemPath+'/certificate/v1/lists_devices',
                'data': data,
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
        order: [[ 8, 'desc' ]],
        columns: [
            {
                title: '<small>设备码</small>',
                data: 'udid',
                render: function(data, type, row) {
                    return '<small>'+row['udid']+'</small>';
                }
            },
            {
                title: '<small>设备状态</small>',
                data: 'status',
                render: function(data, type, row) {
                    let custom;
                    switch(row['status']) {
                        case 'ENABLED':
                            custom = '<div class="badge bg-success">设备可用</div>';
                            break;
                        case 'DISABLED':
                            custom = '<div class="badge bg-secondary">设备禁用</div>';
                            break;
                        case 'PROCESSING':
                            custom = '<div class="badge bg-secondary"">苹果审核</div>';
                            break;
                        case 'INELIGIBLE':
                            custom = '<div class="badge bg-danger">不合格的</div>';
                            break;
                        default:
                            custom = '<div class="badge bg-secondary">未知状态</div>';
                            break;
                    }
                    return custom;
                }
            },
            {
                title: '<small>设备ID</small>',
                data: 'devices_id',
                render: function(data, type, row) {
                    return '<small>'+row['devices_id']+'</small>';
                }
            },
            {
                title: '<small>描述文件ID</small>',
                data: 'profiles_id',
                render: function(data, type, row) {
                    return '<small>'+row['profiles_id']+'</small>';
                }
            },
            {
                title: '<small>备注信息</small>',
                data: 'remark',
                render: function(data, type, row) {
                    return '<small>'+row['remark']+'</small>';
                }
            },
            {
                title: '<small>设备机型</small>',
                data: 'model',
                render: function(data, type, row) {
                    return '<small>'+row['model']+'</small>';
                }
            },
            {
                title: '<small>设备系统</small>',
                data: 'platform',
                render: function(data, type, row) {
                    return '<small>'+row['platform']+'</small>';
                }
            },
            {
                title: '<small>设备类型</small>',
                data: 'deviceClass',
                render: function(data, type, row) {
                    return '<small>'+row['deviceClass']+'</small>';
                }
            },
            {
                title: '<small>添加日期</small>',
                data: 'adddate_at',
                render: function(data, type, row) {
                    return '<small>'+row['adddate_at']+'</small>';
                }
            },
            {
                title: '<small>更新日期</small>',
                data: 'updated_at',
                render: function(data, type, row) {
                    return '<small>'+row['updated_at']+'</small>';
                }
            },
        ]
    });
</script>
@endsection