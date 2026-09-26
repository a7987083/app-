define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            require(['backend/dylib_codegen_inline'], function (Codegen) { Codegen.init(); });
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
            var accessLabels = {
                basic: '普通菜单',
                app_plus: '指定 App 高级权限',
                global_plus: '全软件源高级权限'
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
                bundle_not_allowed: '旧版 BundleID 授权未命中',
                app_identity_incomplete: 'App 身份参数不完整',
                app_identity_unknown: '未识别当前 App 身份',
                app_identity_unbound: '解析 IPA 尚未绑定软件源 App',
                app_identity_ambiguous: 'App 身份匹配到多个软件源 App',
                app_identity_inactive: '对应 App 已停用或不可锁定',
                app_identity_required: '指定 App 卡需要 v2 App 身份',
                app_not_authorized: '指定 App 卡不适用于当前 App',
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

            function activateTab(target) {
                var $link = $('#dylib-center-tabs a[href="' + target + '"]');
                if ($link.length) $link.tab('show');
            }

            function refreshAll() {
                $('#version-table').bootstrapTable('refresh');
                $('#notice-table').bootstrapTable('refresh');
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

            function ensureVersionFormControls() {
                var $form = $('#version-form');
                if (!$form.find('[name=id]').length) $form.prepend('<input type="hidden" name="id" value="0">');
                if (!$form.find('[name=file_size]').length) $form.prepend('<input type="hidden" name="file_size" value="0">');
                if (!$('#cancel-version-edit').length) $form.find('button[type=submit]').after(' <button class="btn btn-default hidden" type="button" id="cancel-version-edit">取消编辑</button>');
            }

            function resetVersionForm() {
                var $form = $('#version-form');
                $form[0].reset();
                $form.find('[name=id]').val('0');
                $form.find('[name=file_size]').val('0');
                $form.find('[name=offline_grace]').val('900');
                $form.find('[name=state]').val('testing');
                $form.find('[name=fail_action]').val('disable_feature');
                $form.find('button[type=submit]').text('添加版本');
                $('#cancel-version-edit').addClass('hidden');
            }

            function editVersion(row) {
                ensureVersionFormControls();
                var $form = $('#version-form');
                $.each(['id', 'dylib_id', 'version', 'build', 'sha256', 'file_size', 'state', 'offline_grace', 'fail_action', 'notice'], function (i, field) {
                    $form.find('[name=' + field + ']').val(row[field] === null || typeof row[field] === 'undefined' ? '' : row[field]);
                });
                $form.find('button[type=submit]').text('保存版本修改');
                $('#cancel-version-edit').removeClass('hidden');
                activateTab('#tab-versions');
                setTimeout(function () { $('html,body').animate({scrollTop: $('#version-form').offset().top - 20}, 150); }, 80);
            }

            function resetNoticeForm() {
                var $form = $('#notice-form');
                $form[0].reset();
                $form.find('[name=id]').val('0');
                $form.find('[name=revision]').val('1');
                $form.find('[name=priority]').val('0');
                $form.find('[name=category_id]').val('0');
                $form.find('[name=enabled]').val('1');
                $form.find('[name=starts_at]').val('0');
                $form.find('[name=ends_at]').val('0');
            }

            ensureVersionFormControls();
            $('#dylib-list').closest('.panel-body').find('.help-block').first().text('删除 Dylib 会同时删除其版本、旧游戏授权和验证记录；此操作不可恢复。');

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
                    {field: 'notice', title: '客户端提示'},
                    {
                        field: 'operate', title: '操作', formatter: function () {
                            return '<button type="button" class="btn btn-xs btn-primary js-version-edit">编辑</button> ' +
                                '<button type="button" class="btn btn-xs btn-danger js-version-delete">删除</button>';
                        },
                        events: {
                            'click .js-version-edit': function (e, value, row) { editVersion(row); },
                            'click .js-version-delete': function (e, value, row) {
                                Layer.confirm('确定删除版本 “' + row.version + (row.build ? ' (' + row.build + ')' : '') + '” 吗？删除后客户端使用该版本会返回 version_unknown。', {title: '删除 Dylib 版本'}, function (index) {
                                    Layer.close(index);
                                    Fast.api.ajax({url: 'dylib_center/deleteVersion', type: 'POST', data: {id: row.id}}, function () {
                                        Toastr.success('Dylib 版本已删除');
                                        resetVersionForm();
                                        $('#version-table').bootstrapTable('refresh');
                                        return false;
                                    });
                                });
                            }
                        }
                    }
                ]]
            });

            $('#notice-table').bootstrapTable({
                url: 'dylib_center/notices',
                sidePagination: 'server',
                pagination: true,
                pageSize: 20,
                pageList: [20, 50, 100],
                columns: [[
                    {field: 'id', title: 'ID'},
                    {field: 'notice_key', title: '通知 Key'},
                    {field: 'app_name', title: '目标 App'},
                    {field: 'min_access_level', title: '最低权限', formatter: function (v) { return v ? label(accessLabels, v) : '不限'; }},
                    {field: 'revision', title: 'Rev'},
                    {field: 'priority', title: '优先级'},
                    {field: 'title', title: '标题'},
                    {field: 'enabled', title: '状态', formatter: function (v) { return parseInt(v, 10) ? '启用' : '停用'; }},
                    {
                        field: 'operate', title: '操作', formatter: function () {
                            return '<button type="button" class="btn btn-xs btn-primary js-notice-edit">编辑</button>';
                        },
                        events: {
                            'click .js-notice-edit': function (e, value, row) {
                                var $form = $('#notice-form');
                                $.each(['id', 'notice_key', 'revision', 'priority', 'category_id', 'min_access_level', 'title', 'message',
                                    'primary_title', 'primary_action', 'primary_url', 'secondary_title', 'secondary_action', 'secondary_url',
                                    'starts_at', 'ends_at', 'enabled'], function (i, field) {
                                    $form.find('[name=' + field + ']').val(row[field] === null || typeof row[field] === 'undefined' ? '' : row[field]);
                                });
                                activateTab('#tab-notices');
                                setTimeout(function () { $('html,body').animate({scrollTop: $('#notice-form').offset().top - 20}, 150); }, 80);
                            }
                        }
                    }
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
                    {field: 'bundle_id', title: '运行 App BundleID'},
                    {field: 'dylib_key', title: 'Dylib Key'},
                    {field: 'dylib_version', title: 'Dylib 版本'},
                    {field: 'result_code', title: '验证结果', formatter: function (v) { return label(resultLabels, v); }},
                    {field: 'action', title: '客户端动作', formatter: function (v) { return label(actionLabels, v); }},
                    {field: 'latency_ms', title: '耗时（ms）'},
                    {field: 'created_at', title: '验证时间', formatter: Table.api.formatter.datetime}
                ]]
            });

            $('#dylib-center-tabs a[data-toggle="tab"]').on('shown.bs.tab', function () {
                window.setTimeout(function () {
                    $('#version-table,#notice-table,#verify-log-table').each(function () {
                        if ($(this).data('bootstrap.table')) $(this).bootstrapTable('resetView');
                    });
                }, 30);
            });

            $('#generate-secret').on('click', function () {
                Fast.api.ajax({url: 'dylib_center/generateVerifySecret', type: 'POST'}, function (data) {
                    if (data && data.secret) {
                        $('#verify-secret').attr('type', 'text').val(data.secret);
                        Toastr.success('验证密钥已生成。保存后由 OC 生成器自动带入；请妥善保管。');
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

            $('#runtime-config-form').on('submit', function (e) {
                e.preventDefault();
                Fast.api.ajax({url: 'dylib_center/saveRuntimeConfig', type: 'POST', data: $(this).serialize()}, function () {
                    Toastr.success('高级运行配置已保存；配置版本已自动递增。');
                    location.reload();
                    return false;
                });
            });

            $('#notice-form').on('submit', function (e) {
                e.preventDefault();
                Fast.api.ajax({url: 'dylib_center/saveNotice', type: 'POST', data: $(this).serialize()}, function () {
                    Toastr.success('远程通知已保存');
                    resetNoticeForm();
                    $('#notice-table').bootstrapTable('refresh');
                    return false;
                });
            });

            $('#reset-notice-form').on('click', resetNoticeForm);
            $('#cancel-dylib-edit').on('click', resetDylibForm);
            $('#cancel-version-edit').on('click', resetVersionForm);

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
                activateTab('#tab-overview');
                setTimeout(function () { $('html,body').animate({scrollTop: $('#section-register').offset().top - 20}, 150); }, 80);
            });

            $('#dylib-list').on('click', '.js-dylib-toggle', function () {
                var $row = $(this).closest('tr');
                var enabled = String($(this).data('enabled'));
                var verb = enabled === '1' ? '启用' : '停用';
                Fast.api.ajax({url: 'dylib_center/setDylibEnabled', type: 'POST', data: {id: $row.data('id'), enabled: enabled}}, function () {
                    Toastr.success('Dylib 已' + verb);
                    location.reload();
                    return false;
                });
            });

            $('#dylib-list').on('click', '.js-dylib-delete', function () {
                var $row = $(this).closest('tr');
                var name = $row.attr('data-name');
                Layer.confirm(
                    '确定彻底删除“' + name + '”吗？该操作会同时删除此 Dylib 的版本、旧游戏授权和验证记录，且不可恢复。',
                    {title: '彻底删除 Dylib（二次确认）'},
                    function (index) {
                        Layer.close(index);
                        Fast.api.ajax({url: 'dylib_center/deleteDylib', type: 'POST', data: {id: $row.data('id')}}, function () {
                            Toastr.success('Dylib 及其关联历史已删除');
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
                activateTab('#tab-advanced');
                $('#integration-detail').collapse('show');
                setTimeout(function () { $('html,body').animate({scrollTop: $('#section-integration').offset().top - 20}, 150); }, 100);
            });

            $('#version-form').on('submit', function (e) {
                e.preventDefault();
                Fast.api.ajax({url: 'dylib_center/saveVersion', type: 'POST', data: $(this).serialize()}, function () {
                    Toastr.success('Dylib 版本已保存');
                    resetVersionForm();
                    $('#version-table').bootstrapTable('refresh');
                    return false;
                });
            });

            setInterval(function () {
                if (document.visibilityState === 'visible') refreshAll();
            }, 60000);
        }
    };
    return Controller;
});