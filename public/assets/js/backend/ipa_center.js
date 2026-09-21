define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'ipa_center/index'
                }
            });

            $('#job-table').bootstrapTable({
                url: 'ipa_center/jobs',
                sidePagination: 'server',
                pagination: true,
                pageSize: 50,
                pageList: [20, 50, 100, 200],
                sortName: 'id',
                sortOrder: 'desc',
                columns: [[
                    {field: 'id', title: 'ID'},
                    {field: 'source_id', title: 'Source'},
                    {field: 'mode', title: '模式'},
                    {field: 'status', title: '状态'},
                    {field: 'discovered_count', title: '发现'},
                    {field: 'processed_count', title: '完成'},
                    {field: 'failed_count', title: '失败'},
                    {field: 'worker_id', title: 'Worker'},
                    {field: 'created_at', title: '创建时间', formatter: Table.api.formatter.datetime}
                ]]
            });

            $('#asset-table').bootstrapTable({
                url: 'ipa_center/assets',
                sidePagination: 'server',
                pagination: true,
                search: true,
                pageSize: 100,
                pageList: [50, 100, 200, 500],
                sortName: 'id',
                sortOrder: 'desc',
                columns: [[
                    {field: 'id', title: 'ID'},
                    {field: 'name', title: 'IPA'},
                    {field: 'bundle_id', title: 'Bundle ID'},
                    {field: 'app_name', title: 'App'},
                    {field: 'app_version', title: 'Version'},
                    {field: 'build_version', title: 'Build'},
                    {field: 'size_bytes', title: '大小(B)'},
                    {field: 'status', title: '状态'},
                    {field: 'last_seen_at', title: '最后发现', formatter: Table.api.formatter.datetime}
                ]]
            });

            $('#source-form').on('submit', function (e) {
                e.preventDefault();
                Fast.api.ajax({url: 'ipa_center/saveSource', type: 'POST', data: $(this).serialize()}, function () {
                    location.reload();
                    return false;
                });
            });

            $(document).on('click', '.btn-scan', function () {
                var button = $(this);
                Fast.api.ajax({
                    url: 'ipa_center/startScan',
                    type: 'POST',
                    data: {source_id: button.data('id'), mode: button.data('mode')}
                }, function () {
                    $('#job-table').bootstrapTable('refresh');
                    return false;
                });
            });
        }
    };
    return Controller;
});
