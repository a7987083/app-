define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            function refreshAll() {
                $('#version-table').bootstrapTable('refresh');
                $('#binding-table').bootstrapTable('refresh');
                $('#verify-log-table').bootstrapTable('refresh');
            }

            $('#version-table').bootstrapTable({
                url: 'dylib_center/versions',
                sidePagination: 'server',
                pagination: true,
                pageSize: 50,
                pageList: [20, 50, 100, 200],
                columns: [[
                    {field: 'id', title: 'ID'},
                    {field: 'dylib_id', title: 'Dylib'},
                    {field: 'version', title: 'Version'},
                    {field: 'build', title: 'Build'},
                    {field: 'state', title: '状态'},
                    {field: 'offline_grace', title: '离线秒'},
                    {field: 'fail_action', title: '失败动作'},
                    {field: 'sha256', title: 'SHA256', formatter: function (v) { return v ? v.substr(0, 12) + '…' : ''; }},
                    {field: 'notice', title: '提示'}
                ]]
            });

            $('#binding-table').bootstrapTable({
                url: 'dylib_center/bindings',
                sidePagination: 'server',
                pagination: true,
                pageSize: 50,
                pageList: [20, 50, 100, 200],
                columns: [[
                    {field: 'id', title: 'ID'},
                    {field: 'dylib_id', title: 'Dylib'},
                    {field: 'bundle_id', title: 'Bundle ID'},
                    {field: 'enabled', title: '启用', formatter: function (v) { return parseInt(v, 10) ? '是' : '否'; }},
                    {field: 'fail_action_override', title: '失败动作覆盖'},
                    {field: 'offline_grace_override', title: '离线覆盖秒'}
                ]]
            });

            $('#verify-log-table').bootstrapTable({
                url: 'dylib_center/logs',
                sidePagination: 'server',
                pagination: true,
                search: true,
                pageSize: 50,
                pageList: [20, 50, 100, 200],
                columns: [[
                    {field: 'id', title: 'ID'},
                    {field: 'udid_hash', title: 'UDID Hash'},
                    {field: 'bundle_id', title: 'Bundle ID'},
                    {field: 'dylib_key', title: 'Dylib'},
                    {field: 'dylib_version', title: 'Version'},
                    {field: 'result_code', title: '结果'},
                    {field: 'action', title: '动作'},
                    {field: 'latency_ms', title: '延迟(ms)'},
                    {field: 'created_at', title: '时间', formatter: Table.api.formatter.datetime}
                ]]
            });

            $('#generate-secret').on('click', function () {
                Fast.api.ajax({url: 'dylib_center/generateVerifySecret', type: 'POST'}, function (data) {
                    if (data && data.secret) {
                        $('#verify-secret').attr('type', 'text').val(data.secret);
                        Toastr.success('验证密钥已生成，请保存并写入对应 OC dylib 配置');
                    }
                    return false;
                });
            });

            $('#dylib-form').on('submit', function (e) {
                e.preventDefault();
                Fast.api.ajax({url: 'dylib_center/saveDylib', type: 'POST', data: $(this).serialize()}, function () {
                    location.reload();
                    return false;
                });
            });

            $('#version-form').on('submit', function (e) {
                e.preventDefault();
                Fast.api.ajax({url: 'dylib_center/saveVersion', type: 'POST', data: $(this).serialize()}, function () {
                    $('#version-table').bootstrapTable('refresh');
                    return false;
                });
            });

            $('#binding-form').on('submit', function (e) {
                e.preventDefault();
                Fast.api.ajax({url: 'dylib_center/saveBinding', type: 'POST', data: $(this).serialize()}, function () {
                    $('#binding-table').bootstrapTable('refresh');
                    return false;
                });
            });

            setInterval(function () {
                if (document.visibilityState === 'visible') {
                    refreshAll();
                }
            }, 60000);
        }
    };
    return Controller;
});
