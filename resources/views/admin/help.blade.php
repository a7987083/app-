@extends(config('api.admin.path', 'admin').'.layouts.master')
@section('title'){{ __('帮助列表') }}@endsection
@section('link')
    <link href="{{ config('api.basics.cdn') }}/theme/bootstrap-v5.2.1/js/datatables/datatables.min.css" rel="stylesheet" type="text/css">
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="DataTable" class="table table-striped table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;"></table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
    <script>
        let DataTable = $('#DataTable');
        let helpTableList = {
            extend: 'collection',
            text: '更多操作',
            buttons: [
                {
                    text: '清空所有', action: function () {
                        layer.confirm('您将清空所有帮助，并且重置数据库递增？', {
                            btn: ['确定清空', '取消']
                        }, function() {
                            layer.load(2);
                            SendAjax({
                                url: systemPath+'/help/delete',
                                data: {
                                    type: 'all'
                                },
                                type: 'delete',
                                'successCallBack': function (response) {
                                    DataTable.DataTable().row($(this)).remove().draw();
                                    layer.msg(response['message'], { icon: 1, time: 3000 });
                                }
                            });
                        }, function() {
                            layer.msg('已取消操作');
                        });
                    }
                }
            ],
            fade: 'button-left'
        };
        DataTablesConfig.buttons.push(helpTableList);
        DataTable.DataTable({
            dom: DataTablesConfig.dom,
            language: DataTablesConfig.language,
            processing: DataTablesConfig.processing,
            serverSide: DataTablesConfig.serverSide,
            pagingType: DataTablesConfig.pagingType,
            buttons: DataTablesConfig.buttons,
            ajax: function(data, callback) {
                SendAjax({
                    url: systemPath+'/help',
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
            order: [[ 3, 'desc' ]],
            columns: [
                {
                    title: '<small>ID</small>',
                    data: 'id',
                    render: function(data, type, row) {
                        return '<small>'+row['id']+'</small>';
                    }
                },
                {
                    title: '<small>标题</small>',
                    data: 'title',
                    render: function(data, type, row) {
                        return '<small>'+row['title']+'</small>';
                    }
                },
                {
                    title: '<small>内容</small>',
                    data: 'content',
                    render: function(data, type, row) {
                        return '<small>'+row['content']+'</small>';
                    }
                },
                {
                    title: '<small>创建日期</small>',
                    data: 'created_at',
                    render: function(data, type, row) {
                        return '<small>'+row['created_at']+'</small>';
                    }
                },
                {
                    title: '<small>更新日期</small>',
                    data: 'updated_at',
                    render: function(data, type, row) {
                        return '<small>'+row['updated_at']+'</small>';
                    }
                },
                {
                    title: '<small>功能操作</small>',
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function() {
                        return `
                    <div class="edit-btn badge bg-warning">编辑数据</div>
                    <div class="delete-btn badge bg-danger">删除数据</div>
                    `;
                    }
                },
            ]
        });
        DataTable.on('click', '.edit-btn', function() {
            let table = DataTable.DataTable();
            let data = table.row($(this)).data();
            if (data === undefined) {
                data = table.row($(this).closest('tr')).data();
            }
            RedirectTo(systemPath+'/help/edit/'+data['id']);
        });
        DataTable.on('click', '.delete-btn', function() {
            let table = DataTable.DataTable();
            let data = table.row($(this)).data();
            if (data === undefined) {
                data = table.row($(this).closest('tr')).data();
            }
            layer.confirm('您确定要删除帮助 “'+data.title+'” 吗？', {
                btn: ['确定', '取消']
            }, function() {
                layer.load(2);
                SendAjax({
                    url: systemPath+'/help/delete',
                    data: {
                        id: data['id']
                    },
                    type: 'delete',
                    'successCallBack': function (response) {
                        DataTable.DataTable().row($(this)).remove().draw();
                        layer.msg(response['message'], { icon: 1, time: 3000 });
                    }
                });
            }, function() {
                layer.msg('已取消操作');
            });
        });
    </script>
@endsection