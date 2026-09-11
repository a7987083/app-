define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    function esc(value) {
        return $('<div/>').text(value == null ? '' : String(value)).html();
    }

    function jobId(prefix) {
        return (prefix || 'update') + '_' + (new Date().getTime()) + '_' + Math.random().toString(16).slice(2, 12);
    }

    function sourceConfig(source) {
        return source === 'github' ? {
            source: 'github',
            title: 'GitHub 在线更新',
            checkUrl: 'general/config/github_update',
            installUrl: 'general/config/github_system_update'
        } : {
            source: 'nuosike',
            title: '原版在线更新',
            checkUrl: 'general/config/update',
            installUrl: 'general/config/system_update'
        };
    }

    function stageListHtml(currentStage) {
        var stages = [
            ['source', '读取更新源'],
            ['download', '下载更新包'],
            ['sha256', 'SHA256 校验'],
            ['extract', '安全检查与解压'],
            ['backup', '备份程序和数据库'],
            ['database', '数据库迁移'],
            ['files', '覆盖程序文件'],
            ['verify', '文件完整性校验'],
            ['config', '同步站点配置'],
            ['version', '写入新版本'],
            ['complete', '最终验证']
        ];
        var order = {};
        for (var i = 0; i < stages.length; i++) order[stages[i][0]] = i;
        var current = order.hasOwnProperty(currentStage) ? order[currentStage] : -1;
        var html = '<div class="zonoe-update-stages" style="margin-top:12px;">';
        $.each(stages, function (idx, item) {
            var icon = idx < current ? '✓' : (idx === current ? '●' : '○');
            var color = idx < current ? '#18bc9c' : (idx === current ? '#337ab7' : '#999');
            html += '<div style="padding:3px 0;color:' + color + ';">' + icon + ' ' + esc(item[1]) + '</div>';
        });
        return html + '</div>';
    }

    function progressHtml(data) {
        data = data || {};
        var progress = parseInt(data.progress || 0, 10);
        if (progress < 0) progress = 0;
        if (progress > 100) progress = 100;
        var statusText = data.message || '正在准备更新';
        var versions = '';
        if (data.from_version || data.to_version) {
            versions = '<div style="margin-bottom:10px;color:#666;">版本：<b>' + esc(data.from_version || '-') + '</b> → <b>' + esc(data.to_version || '-') + '</b></div>';
        }
        return '<div style="padding:8px 4px;text-align:left;">' +
            versions +
            '<div style="font-size:16px;font-weight:600;margin-bottom:8px;">' + esc(statusText) + '</div>' +
            '<div class="progress" style="height:20px;margin-bottom:5px;"><div class="progress-bar progress-bar-striped active" role="progressbar" style="width:' + progress + '%;min-width:2em;">' + progress + '%</div></div>' +
            stageListHtml(data.stage || '') +
            '</div>';
    }

    function resultHtml(data, ok) {
        data = data || {};
        var html = '<div style="text-align:left;padding:4px 2px;line-height:1.8;">';
        html += '<h4 style="margin-top:0;color:' + (ok ? '#18bc9c' : '#d9534f') + ';">' + (ok ? '✓ 更新成功' : '✕ 更新失败') + '</h4>';
        if (data.from_version || data.to_version) {
            html += '<p>版本：<b>' + esc(data.from_version || '-') + '</b> → <b>' + esc(data.to_version || '-') + '</b></p>';
        }
        html += '<p>状态：' + esc(data.message || (ok ? '更新完成' : '更新失败')) + '</p>';
        if (typeof data.integrity_verified !== 'undefined') html += '<p>程序完整性：' + (data.integrity_verified ? '通过' : '未通过') + '</p>';
        if (typeof data.database_migrated !== 'undefined') html += '<p>数据库：' + (data.database_migrated ? '已执行迁移' : '无需迁移') + '</p>';
        if (!ok) html += '<p>自动回滚：' + (data.rollback ? '<span style="color:#18bc9c;">已完成</span>' : '<span style="color:#d9534f;">未完成或无需回滚</span>') + '</p>';
        var installed = data.installed || [];
        if (installed.length) {
            var last = installed[installed.length - 1];
            if (last.sha256_verified) html += '<p>SHA256：通过</p>';
            if (last.files_verified) html += '<p>文件覆盖校验：通过</p>';
            if (last.backup) html += '<p style="word-break:break-all;">备份：' + esc(last.backup) + '</p>';
        }
        return html + '</div>';
    }

    function pollProgress(job, layerIndex, onDone) {
        var stopped = false;
        var timer = setInterval(function () {
            if (stopped) return;
            $.ajax({
                type: 'GET',
                url: 'general/config/update_status',
                dataType: 'json',
                data: {job_id: job},
                success: function (ret) {
                    if (!ret || ret.code !== 200 || !ret.data) return;
                    var data = ret.data;
                    if (data.status === 'success' || data.status === 'failed') {
                        stopped = true;
                        clearInterval(timer);
                        layer.title(data.status === 'success' ? '更新完成' : '更新失败', layerIndex);
                        $('#layui-layer' + layerIndex + ' .layui-layer-content').html(resultHtml(data, data.status === 'success'));
                        $('#layui-layer' + layerIndex + ' .layui-layer-btn').show();
                        if (typeof onDone === 'function') onDone(data);
                    } else {
                        $('#layui-layer' + layerIndex + ' .layui-layer-content').html(progressHtml(data));
                    }
                }
            });
        }, 700);
        return function () {
            stopped = true;
            clearInterval(timer);
        };
    }

    function runUpdate(source, force) {
        var cfg = sourceConfig(source);
        var job = jobId('update');
        var layerIndex = layer.open({
            type: 1,
            title: cfg.title,
            area: ['560px', 'auto'],
            shadeClose: false,
            closeBtn: 0,
            content: '<div style="padding:16px;">' + progressHtml({progress: 2, stage: 'source', message: '正在启动更新任务'}) + '</div>',
            btn: ['完成'],
            yes: function (idx) {
                layer.close(idx);
                location.reload();
            },
            success: function () {
                $('#layui-layer' + layerIndex + ' .layui-layer-btn').hide();
            }
        });
        var stopPoll = pollProgress(job, layerIndex);
        $.ajax({
            type: 'POST',
            url: cfg.installUrl,
            dataType: 'json',
            data: {force: force ? 1 : 0, job_id: job},
            success: function (ret) {
                if (ret && (ret.code === 200 || ret.code === 204)) return;
                setTimeout(function () {
                    $.getJSON('general/config/update_status', {job_id: job}, function (statusRet) {
                        if (statusRet && statusRet.code === 200 && statusRet.data) {
                            $('#layui-layer' + layerIndex + ' .layui-layer-content').html(resultHtml(statusRet.data, false));
                            $('#layui-layer' + layerIndex + ' .layui-layer-btn').show();
                        } else {
                            stopPoll();
                            $('#layui-layer' + layerIndex + ' .layui-layer-content').html(resultHtml({message: (ret && ret.msg) || '更新请求失败'}, false));
                            $('#layui-layer' + layerIndex + ' .layui-layer-btn').show();
                        }
                    });
                }, 800);
            },
            error: function () {
                stopPoll();
                $('#layui-layer' + layerIndex + ' .layui-layer-content').html(resultHtml({message: cfg.title + '请求失败'}, false));
                $('#layui-layer' + layerIndex + ' .layui-layer-btn').show();
            }
        });
    }

    function checkUpdate(source) {
        var cfg = sourceConfig(source);
        layer.load(1, {shade: 0.2});
        $.ajax({
            type: 'POST',
            url: cfg.checkUrl,
            dataType: 'json',
            success: function (ret) {
                layer.closeAll('loading');
                var data = ret && ret.data ? ret.data : {};
                if (!ret || (ret.code !== 200 && ret.code !== 204)) {
                    layer.alert((ret && ret.msg) || '检查更新失败', {icon: 2});
                    return;
                }
                var changelog = data.changelog ? esc(data.changelog).replace(/\n/g, '<br>') : '暂无更新说明';
                var html = '<div style="text-align:left;line-height:1.8;">' +
                    '<p>当前版本：<b>' + esc(data.local_version || '-') + '</b></p>' +
                    '<p>最新版本：<b>' + esc(data.last_version || data.local_version || '-') + '</b></p>' +
                    '<p>状态：' + esc(ret.msg || '') + '</p>' +
                    (data.sha256 ? '<p style="font-size:12px;color:#777;word-break:break-all;">SHA256：' + esc(data.sha256) + '</p>' : '') +
                    '<div style="margin-top:10px;"><b>更新说明</b><div style="background:#f7f7f7;border:1px solid #eee;padding:10px;margin-top:5px;max-height:260px;overflow:auto;">' + changelog + '</div></div>' +
                    '</div>';
                var canInstall = ret.code === 200 || !!data.can_reinstall;
                layer.open({
                    type: 1,
                    title: cfg.title,
                    area: ['580px', 'auto'],
                    content: '<div style="padding:16px;">' + html + '</div>',
                    btn: canInstall ? [ret.code === 200 ? '立即更新' : '重新安装当前版本', '更新历史', '关闭'] : ['更新历史', '关闭'],
                    yes: function (idx) {
                        if (!canInstall) {
                            layer.close(idx);
                            openHistory();
                            return;
                        }
                        layer.close(idx);
                        runUpdate(source, !!data.incomplete || ret.code === 204);
                    },
                    btn2: function (idx) {
                        layer.close(idx);
                        openHistory();
                        return false;
                    }
                });
            },
            error: function () {
                layer.closeAll('loading');
                layer.alert(cfg.title + '检查失败', {icon: 2});
            }
        });
    }

    function historyRowsHtml(list) {
        if (!list || !list.length) return '<div style="padding:20px;text-align:center;color:#999;">暂无更新历史</div>';
        var html = '<div style="max-height:420px;overflow:auto;"><table class="table table-striped table-bordered" style="margin:0;"><thead><tr><th>时间</th><th>类型</th><th>版本</th><th>状态</th><th>操作</th></tr></thead><tbody>';
        $.each(list, function (_, row) {
            var type = row.type === 'rollback' ? '回滚' : '更新';
            var status = row.status === 'success' ? '<span class="text-success">成功</span>' : '<span class="text-danger">失败</span>';
            var version = esc(row.from_version || '-') + ' → ' + esc(row.to_version || '-');
            var action = row.type === 'update' && row.status === 'success' && row.backups && row.backups.length ? '<button class="btn btn-xs btn-warning zonoe-rollback" data-id="' + esc(row.id) + '">回滚</button>' : '-';
            html += '<tr><td>' + esc(row.created_at || '-') + '</td><td>' + type + '</td><td>' + version + '</td><td>' + status + '</td><td>' + action + '</td></tr>';
        });
        return html + '</tbody></table></div>';
    }

    function openHistory() {
        layer.load(1, {shade: 0.2});
        $.getJSON('general/config/update_history', {limit: 20}, function (ret) {
            layer.closeAll('loading');
            var list = ret && ret.data && ret.data.list ? ret.data.list : [];
            layer.open({
                type: 1,
                title: '更新历史 / 回滚',
                area: ['760px', 'auto'],
                content: '<div style="padding:12px;">' + historyRowsHtml(list) + '</div>',
                btn: ['关闭']
            });
        }).fail(function () {
            layer.closeAll('loading');
            layer.alert('读取更新历史失败', {icon: 2});
        });
    }

    function runRollback(historyId) {
        var job = jobId('rollback');
        var idx = layer.open({
            type: 1,
            title: '版本回滚',
            area: ['560px', 'auto'],
            shadeClose: false,
            closeBtn: 0,
            content: '<div style="padding:16px;">' + progressHtml({progress: 5, stage: 'rollback', message: '正在启动回滚任务'}) + '</div>',
            btn: ['完成'],
            yes: function (i) { layer.close(i); location.reload(); },
            success: function () { $('#layui-layer' + idx + ' .layui-layer-btn').hide(); }
        });
        pollProgress(job, idx);
        $.ajax({
            type: 'POST',
            url: 'general/config/update_rollback',
            dataType: 'json',
            data: {history_id: historyId, job_id: job}
        });
    }

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'general/config/index',
                    add_url: 'general/config/add',
                    edit_url: 'general/config/edit',
                    del_url: 'general/config/del',
                    multi_url: 'general/config/multi',
                    table: 'config'
                }
            });

            var table = $("#table");
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [[
                    {field: 'state', checkbox: true},
                    {field: 'id', title: __('Id')},
                    {field: 'name', title: __('Name')},
                    {field: 'intro', title: __('Intro')},
                    {field: 'group', title: __('Group')},
                    {field: 'type', title: __('Type')},
                    {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                ]]
            });
            Table.api.bindevent(table);

            $("form.edit-form").data("validator-options", {
                display: function (elem) {
                    return $(elem).closest('tr').find("td:first").text();
                }
            });
            Form.api.bindevent($("form.edit-form"));

            $("form#add-form").data("validator-options", {
                ignore: ':hidden',
                rules: {
                    content: function () {
                        return ['radio', 'checkbox', 'select', 'selects'].indexOf($("#add-form select[name='row[type]']").val()) > -1;
                    },
                    extend: function () {
                        return $("#add-form select[name='row[type]']").val() == 'custom';
                    }
                }
            });
            Form.api.bindevent($("form#add-form"), function () {
                setTimeout(function () { location.reload(); }, 1500);
            });

            $(document).on("change", "form#add-form select[name='row[type]']", function () {
                $("#add-content-container").toggleClass("hide", ['select', 'selects', 'checkbox', 'radio'].indexOf($(this).val()) > -1 ? false : true);
            });

            $(document).on("click", ".rulelist > li > a", function () {
                var ruleArr = $("#rule").val() == '' ? [] : $("#rule").val().split(";");
                var rule = $(this).data("value");
                var index = ruleArr.indexOf(rule);
                if (index > -1) ruleArr.splice(index, 1); else ruleArr.push(rule);
                $("#rule").val(ruleArr.join(";"));
                $(this).parent().toggleClass("active");
            });

            $('input[name="row[mail_from]"]').parent().next().append('<a class="btn btn-info testmail">' + __('Send a test message') + '</a>');
            $(document).on("click", ".testmail", function () {
                var that = this;
                Layer.prompt({title: __('Please input your email'), formType: 0}, function (value) {
                    Backend.api.ajax({url: "general/config/emailtest", data: $(that).closest("form").serialize() + "&receiver=" + value});
                });
            });

            $(document).on("click", ".btn-delcfg", function () {
                var that = this;
                Layer.confirm(__('Are you sure you want to delete this item?'), {icon: 3, title: '提示'}, function (index) {
                    Backend.api.ajax({url: "general/config/del", data: {name: $(that).data("name")}}, function () {
                        $(that).closest("tr").remove();
                        Layer.close(index);
                    });
                });
            });

            window.update = function () { checkUpdate('nuosike'); };
            window.githubUpdate = function () { checkUpdate('github'); };

            var githubButton = $('button[onclick="githubUpdate()"]');
            if (githubButton.length && !$('#zonoe-update-history').length) {
                githubButton.after(' <button type="button" id="zonoe-update-history" class="btn btn-default btn-embossed"><i class="fa fa-history"></i> 更新历史</button>');
            }
            $(document).off('click.zonoeHistory').on('click.zonoeHistory', '#zonoe-update-history', openHistory);
            $(document).off('click.zonoeRollback').on('click.zonoeRollback', '.zonoe-rollback', function () {
                var id = $(this).data('id');
                layer.confirm('将恢复该次更新前的程序文件、数据库和版本号。确定继续吗？', {icon: 3, title: '确认回滚'}, function (idx) {
                    layer.close(idx);
                    layer.closeAll();
                    runRollback(id);
                });
            });
        },
        add: function () { Controller.api.bindevent(); },
        edit: function () { Controller.api.bindevent(); },
        api: {
            bindevent: function () { Form.api.bindevent($("form[role=form]")); }
        }
    };
    return Controller;
});
