define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            var stateLabels = {
                active: '正式启用',
                testing: '测试中',
                deprecated: '已弃用（仍允许）',
                blocked: '已阻止',
                revoked: '已撤销'
            };
            var actionLabels = {
                allow: '允许使用',
                disable_feature: '停用受保护功能',
                show_message: '显示提示',
                block: '阻止使用'
            };
            var resultLabels = {
                ok: '验证通过',
                ok_testing: '验证通过（测试版本）',
                ok_deprecated: '验证通过（已弃用版本）',
                timestamp_invalid: '请求时间已过期',
                nonce_invalid: 'Nonce 格式无效',
                signature_invalid: '签名格式无效',
                signature_mismatch: '签名校验失败',
                replay_detected: '检测到重复请求',
                dylib_unknown: 'Dylib 未注册或已停用',
                dylib_key_unconfigured: 'Dylib 验证密钥未配置',
                dylib_key_unavailable: 'Dylib 验证密钥不可用',
                license_invalid: '设备授权无效或已过期',
                blacklisted: '设备已被封禁',
                bundle_not_allowed: 'BundleID 未授权',
                version_unknown: 'Dylib 版本未登记',
                version_blocked: 'Dylib 版本已阻止',
                version_revoked: 'Dylib 版本已撤销',
                integrity_mismatch: 'Dylib 文件指纹不匹配',
                bad_request: '请求参数不完整',
                server_error: '验证服务异常'
            };

            function label(map, value) {
                return Object.prototype.hasOwnProperty.call(map, value) ? map[value] : value;
            }

            function refreshAll() {
                $('#version-table').bootstrapTable('refresh');
                $('#binding-table').bootstrapTable('refresh');
                $('#verify-log-table').bootstrapTable('refresh');
            }

            function resetDylibForm() {
                var $form = $('#dylib-form');
                $form[0].reset();
                $form.find('[name=id]').val('0');
                $form.find('[name=enabled]').val('1');
                $form.find('[name=dylib_key]').prop('readonly', false);
                $('#verify-secret').attr('type', 'password').val('');
                $('.js-dylib-submit-label').text('注册 Dylib');
                $('#cancel-dylib-edit').addClass('hidden');
            }

            $('#version-table').bootstrapTable({
                url: 'dylib_center/versions',
                sidePagination: 'server',
                pagination: true,
                pageSize: 50,
                pageList: [20, 50, 100, 200],
                columns: [[
                    {field: 'id', title: 'ID'},
                    {field: 'dylib_id', title: 'Dylib ID'},
                    {field: 'version', title: '版本'},
                    {field: 'build', title: 'Build'},
                    {field: 'state', title: '版本状态', formatter: function (v) { return label(stateLabels, v); }},
                    {field: 'offline_grace', title: '离线容错（秒）'},
                    {field: 'fail_action', title: '失败动作', formatter: function (v) { return label(actionLabels, v); }},
                    {field: 'sha256', title: 'SHA256', formatter: function (v) { return v ? v.substr(0, 12) + '…' : '未限制'; }},
                    {field: 'notice', title: '客户端提示'}
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
                    {field: 'dylib_id', title: 'Dylib ID'},
                    {field: 'bundle_id', title: 'BundleID'},
                    {field: 'enabled', title: '授权状态', formatter: function (v) { return parseInt(v, 10) ? '已启用' : '已停用'; }},
                    {field: 'fail_action_override', title: '失败动作覆盖', formatter: function (v) { return v ? label(actionLabels, v) : '继承 Dylib'; }},
                    {field: 'offline_grace_override', title: '离线覆盖（秒）', formatter: function (v) { return parseInt(v, 10) > 0 ? v : '继承'; }}
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
                    {field: 'udid_hash', title: '设备标识哈希（前12位）'},
                    {field: 'bundle_id', title: '游戏 BundleID'},
                    {field: 'dylib_key', title: 'Dylib Key'},
                    {field: 'dylib_version', title: 'Dylib 版本'},
                    {field: 'result_code', title: '验证结果', formatter: function (v) { return label(resultLabels, v); }},
                    {field: 'action', title: '客户端动作', formatter: function (v) { return label(actionLabels, v); }},
                    {field: 'latency_ms', title: '耗时（ms）'},
                    {field: 'created_at', title: '验证时间', formatter: Table.api.formatter.datetime}
                ]]
            });

            $('#generate-secret').on('click', function () {
                Fast.api.ajax({url: 'dylib_center/generateVerifySecret', type: 'POST'}, function (data) {
                    if (data && data.secret) {
                        $('#verify-secret').attr('type', 'text').val(data.secret);
                        Toastr.success('验证密钥已生成。请保存，并同步写入对应 Objective-C Dylib 配置。');
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

            $('#cancel-dylib-edit').on('click', function () {
                resetDylibForm();
            });

            $('#dylib-list').on('click', '.js-dylib-edit', function () {
                var $row = $(this).closest('tr');
                var $form = $('#dylib-form');
                $form.find('[name=id]').val($row.data('id'));
                $form.find('[name=dylib_key]').val($row.attr('data-key')).prop('readonly', true);
                $form.find('[name=name]').val($row.attr('data-name'));
                $form.find('[name=default_offline_grace]').val($row.attr('data-grace'));
                $form.find('[name=default_fail_action]').val($row.attr('data-action'));
                $form.find('[name=enabled]').val($row.attr('data-enabled'));
                $('#verify-secret').attr('type', 'password').val('');
                $('.js-dylib-submit-label').text('保存修改');
                $('#cancel-dylib-edit').removeClass('hidden');
                $('html,body').animate({scrollTop: $('#section-register').offset().top - 20}, 150);
            });

            $('#dylib-list').on('click', '.js-dylib-toggle', function () {
                var $row = $(this).closest('tr');
                var enabled = String($(this).data('enabled'));
                var verb = enabled === '1' ? '启用' : '停用';
                Fast.api.ajax({
                    url: 'dylib_center/setDylibEnabled',
                    type: 'POST',
                    data: {id: $row.data('id'), enabled: enabled}
                }, function () {
                    Toastr.success('Dylib 已' + verb);
                    location.reload();
                    return false;
                });
            });

            $('#dylib-list').on('click', '.js-dylib-delete', function () {
                var $row = $(this).closest('tr');
                var name = $row.attr('data-name');
                Layer.confirm(
                    '确定删除“' + name + '”吗？只有从未产生版本、游戏授权或验证记录的 Dylib 才允许删除；已有历史的 Dylib 必须使用“停用”。',
                    {title: '删除 Dylib（二次确认）'},
                    function (index) {
                        Layer.close(index);
                        Fast.api.ajax({
                            url: 'dylib_center/deleteDylib',
                            type: 'POST',
                            data: {id: $row.data('id')}
                        }, function () {
                            Toastr.success('Dylib 已删除');
                            location.reload();
                            return false;
                        });
                    }
                );
            });

            $('#dylib-list').on('click', '.js-dylib-integration', function () {
                var $row = $(this).closest('tr');
                $('#integration-name').text($row.attr('data-name'));
                $('#integration-key').text($row.attr('data-key'));
                $('html,body').animate({scrollTop: $('#section-integration').offset().top - 20}, 150);
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
