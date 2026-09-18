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

    function normalizedStage(currentStage, progress) {
        currentStage = String(currentStage || '');
        progress = parseInt(progress || 0, 10);
        if (currentStage === 'preparing' && progress >= 8) return 'download';
        if (currentStage === 'package_complete') return 'complete';
        return currentStage;
    }

    function stageListHtml(currentStage, progress) {
        currentStage = normalizedStage(currentStage, progress);
        var stages = [
            ['preparing', '准备更新任务'],
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
        var updated = data.updated_at ? '<div style="margin-top:7px;font-size:12px;color:#999;">最后更新：' + esc(data.updated_at) + '</div>' : '';
        if (data.from_version || data.to_version) {
            versions = '<div style="margin-bottom:10px;color:#666;">版本：<b>' + esc(data.from_version || '-') + '</b> → <b>' + esc(data.to_version || '-') + '</b></div>';
        }
        return '<div style="padding:8px 4px;text-align:left;">' +
            versions +
            '<div style="font-size:16px;font-weight:600;margin-bottom:8px;">' + esc(statusText) + '</div>' +
            '<div class="progress" style="height:20px;margin-bottom:5px;"><div class="progress-bar progress-bar-striped active" role="progressbar" style="width:' + progress + '%;min-width:2em;">' + progress + '%</div></div>' +
            updated +
            stageListHtml(data.stage || '', progress) +
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

    function activeUpdateStore(value) {
        try {
            if (value) window.sessionStorage.setItem('zonoe.update.active', JSON.stringify(value));
            else window.sessionStorage.removeItem('zonoe.update.active');
        } catch (e) {}
    }

    function pollProgress(job, layerIndex, onDone) {
        var stopped = false;
        var timer = null;
        function schedule(delay) {
            if (stopped) return;
            timer = setTimeout(tick, typeof delay === 'number' ? delay : 700);
        }
        function tick() {
            if (stopped) return;
            $.ajax({
                type: 'GET',
                url: 'general/config/update_status',
                cache: false,
                dataType: 'json',
                data: {job_id: job, _ts: new Date().getTime()},
                success: function (ret) {
                    if (!ret || ret.code !== 200 || !ret.data) {
                        schedule(900);
                        return;
                    }
                    var data = ret.data;
                    if (data.status === 'success' || data.status === 'failed') {
                        stopped = true;
                        if (timer) clearTimeout(timer);
                        activeUpdateStore(null);
                        layer.title(data.status === 'success' ? '更新完成' : '更新失败', layerIndex);
                        $('#layui-layer' + layerIndex + ' .layui-layer-content').html(resultHtml(data, data.status === 'success'));
                        $('#layui-layer' + layerIndex + ' .layui-layer-btn').show();
                        if (typeof onDone === 'function') onDone(data);
                    } else {
                        $('#layui-layer' + layerIndex + ' .layui-layer-content').html(progressHtml(data));
                        schedule(700);
                    }
                },
                error: function () {
                    // A transient status request must never turn the progress UI
                    // into a dead page. Keep the last real state and retry.
                    schedule(1100);
                }
            });
        }
        tick();
        return function () {
            stopped = true;
            if (timer) clearTimeout(timer);
        };
    }

    function openUpdateProgress(source, job, initial) {
        var cfg = sourceConfig(source);
        var layerIndex = layer.open({
            type: 1,
            title: cfg.title,
            area: ['560px', 'auto'],
            shadeClose: false,
            closeBtn: 0,
            content: '<div style="padding:16px;">' + progressHtml(initial || {progress: 3, stage: 'preparing', message: '正在准备更新'}) + '</div>',
            btn: ['完成'],
            yes: function (idx) {
                layer.close(idx);
                location.reload();
            },
            success: function (layero) {
                $(layero).find('.layui-layer-btn').hide();
            }
        });
        pollProgress(job, layerIndex);
        return layerIndex;
    }

    function runUpdate(source, force) {
        var cfg = sourceConfig(source);
        var job = jobId('update');
        activeUpdateStore({job: job, source: source});
        var layerIndex = openUpdateProgress(source, job, {progress: 3, stage: 'preparing', message: '正在启动更新任务'});
        $.ajax({
            type: 'POST',
            url: cfg.installUrl,
            dataType: 'json',
            data: {force: force ? 1 : 0, job_id: job},
            success: function (ret) {
                // The status channel is authoritative. Even a non-200 install
                // response is allowed to finish through the job status store.
                if (ret && (ret.code === 200 || ret.code === 204)) return;
                setTimeout(function () {
                    $.ajax({
                        type: 'GET',
                        url: 'general/config/update_status',
                        cache: false,
                        dataType: 'json',
                        data: {job_id: job, _ts: new Date().getTime()},
                        success: function (statusRet) {
                            if (statusRet && statusRet.code === 200 && statusRet.data && statusRet.data.status === 'failed') {
                                $('#layui-layer' + layerIndex + ' .layui-layer-content').html(resultHtml(statusRet.data, false));
                                $('#layui-layer' + layerIndex + ' .layui-layer-btn').show();
                                activeUpdateStore(null);
                            }
                        }
                    });
                }, 800);
            },
            error: function () {
                // Do not stop polling here: PHP may still be running the update
                // after a reverse-proxy/client connection interruption.
            }
        });
    }

    function restoreActiveUpdate() {
        var stored = null;
        try {
            stored = JSON.parse(window.sessionStorage.getItem('zonoe.update.active') || 'null');
        } catch (e) {
            stored = null;
        }
        if (!stored || !stored.job || !stored.source) return;
        $.ajax({
            type: 'GET',
            url: 'general/config/update_status',
            cache: false,
            dataType: 'json',
            data: {job_id: stored.job, _ts: new Date().getTime()},
            success: function (ret) {
                if (!ret || ret.code !== 200 || !ret.data) {
                    activeUpdateStore(null);
                    return;
                }
                if (ret.data.status === 'running') {
                    openUpdateProgress(stored.source, stored.job, ret.data);
                } else {
                    activeUpdateStore(null);
                }
            },
            error: function () {}
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
            content: '<div style="padding:16px;">' + progressHtml({progress: 3, stage: 'preparing', message: '正在启动回滚任务'}) + '</div>',
            btn: ['完成'],
            yes: function (i) { layer.close(i); location.reload(); },
            success: function (layero) { $(layero).find('.layui-layer-btn').hide(); }
        });
        pollProgress(job, idx);
        $.ajax({
            type: 'POST',
            url: 'general/config/update_rollback',
            dataType: 'json',
            data: {history_id: historyId, job_id: job}
        });
    }

    function openUpdateOperations() {
        Fast.api.open('general/updatemaintenance/panel', '更新运维中心', {
            area: ['92%', '88%'],
            maxmin: true,
            shadeClose: false
        });
    }

    function apiCenterRoot() {
        return $('#api-center');
    }

    function apiCenterUrl(name) {
        var root = apiCenterRoot();
        var value = root.data(name + '-url') || '';
        return value ? String(value) : '';
    }

    function rememberApiTab(tab) {
        try {
            window.sessionStorage.setItem('zonoe.api.outerTab', '#api-center');
            if (tab) window.sessionStorage.setItem('zonoe.api.innerTab', tab);
        } catch (e) {}
    }

    function restoreApiTab() {
        try {
            if (window.sessionStorage.getItem('zonoe.api.outerTab') !== '#api-center') return;
            $('a[href="#api-center"]').tab('show');
            var inner = window.sessionStorage.getItem('zonoe.api.innerTab') || '#project-api-list';
            $('a[href="' + inner + '"]').tab('show');
        } catch (e) {}
    }

    function apiRequest(name, data, onSuccess) {
        var url = apiCenterUrl(name);
        if (!url) {
            Layer.alert('API管理接口地址缺失: ' + name, {icon: 2});
            return;
        }
        Backend.api.ajax({
            url: url,
            data: data || {}
        }, function (data, ret) {
            if (typeof onSuccess === 'function') onSuccess(data || {}, ret || {});
            return false;
        });
    }

    function apiTestFieldRoot() {
        var root = $('#project-api-test-fields');
        if (root.length) return root;
        var legacy = $('#project-api-test-params');
        var group = legacy.closest('.form-group');
        if (!group.length) return $();
        group.attr('id', 'project-api-test-fields-group');
        group.html('<label class="control-label col-xs-12 col-sm-2">参数</label><div class="col-xs-12 col-sm-7" id="project-api-test-fields"><span class="text-muted">请选择API接口</span></div>');
        return $('#project-api-test-fields');
    }

    function renderApiTestFields(schema) {
        var root = apiTestFieldRoot();
        if (!root.length) return;
        schema = schema || {};
        var fields = $.isArray(schema.fields) ? schema.fields : [];
        if (!fields.length) {
            root.html('<span class="text-muted">此接口无需输入参数</span>');
            return;
        }
        var html = '';
        $.each(fields, function (_, field) {
            var name = String(field.name || '');
            if (!name) return;
            var required = field.required ? 1 : 0;
            html += '<div class="form-group" style="margin-left:0;margin-right:0;margin-bottom:10px;">' +
                '<label style="display:block;margin-bottom:4px;">' + esc(field.label || name) + (required ? ' <span class="text-danger">*</span>' : '') + '</label>' +
                '<input type="text" class="form-control api-test-field" data-required="' + required + '" data-label="' + esc(field.label || name) + '" name="test_params[' + esc(name) + ']" placeholder="' + esc(field.placeholder || '') + '">' +
                '</div>';
        });
        root.html(html);
    }

    function loadApiTestSchema(key) {
        var root = apiTestFieldRoot();
        if (!key) {
            if (root.length) root.html('<span class="text-muted">请选择API接口</span>');
            return;
        }
        if (root.length) root.html('<span class="text-muted"><i class="fa fa-spinner fa-spin"></i> 正在读取接口参数…</span>');
        $.ajax({
            type: 'GET',
            url: 'general/config/api_test_schema',
            cache: false,
            dataType: 'json',
            data: {endpoint_key: key, _ts: new Date().getTime()},
            success: function (ret) {
                if (!ret || ret.code !== 1 || !ret.data) {
                    renderApiTestFields({fields: []});
                    Layer.alert((ret && ret.msg) || '读取API参数失败', {icon: 2});
                    return;
                }
                $('#project-api-test-url').val(ret.data.url || $('#project-api-test-url').val());
                $('#project-api-test-method').val(ret.data.method || '');
                $('#project-api-test-auth').val(ret.data.auth || '');
                renderApiTestFields(ret.data);
            },
            error: function (xhr) {
                if (root.length) root.html('<span class="text-danger">读取参数失败 HTTP ' + esc(xhr.status) + '</span>');
            }
        });
    }

    function syncApiTestMeta() {
        var option = $('#project-api-test-key option:selected');
        var key = $('#project-api-test-key').val() || '';
        $('#project-api-test-url').val(option.data('url') || '');
        $('#project-api-test-method').val(option.data('method') || '');
        $('#project-api-test-auth').val(option.data('auth') || '');
        $('#project-api-test-result').hide().text('');
        loadApiTestSchema(key);
    }

    function selectApiForTest(key) {
        $('#project-api-test-key').val(String(key || ''));
        syncApiTestMeta();
        rememberApiTab('#project-api-test');
        $('a[href="#api-center"]').tab('show');
        $('a[href="#project-api-test"]').tab('show');
    }

    function formatApiLogTime(ts) {
        ts = parseInt(ts || 0, 10);
        if (!ts) return '-';
        var d = new Date(ts * 1000);
        function pad(v) { return v < 10 ? '0' + v : String(v); }
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' +
            pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }

    function renderApiLogs(rows) {
        var body = $('#project-api-log-body');
        if (!body.length) return;
        rows = $.isArray(rows) ? rows : [];
        if (!rows.length) {
            body.html('<tr><td colspan="8" class="text-muted text-center">暂无API请求日志</td></tr>');
            return;
        }
        var html = '';
        $.each(rows, function (_, row) {
            html += '<tr>' +
                '<td>' + esc(row.id) + '</td>' +
                '<td>' + esc(row.endpoint_key) + '</td>' +
                '<td>' + esc(row.method) + '</td>' +
                '<td>' + esc(row.path) + '</td>' +
                '<td>' + esc(row.ip) + '</td>' +
                '<td>' + esc(row.status_code) + '</td>' +
                '<td>' + esc(row.duration_ms) + ' ms</td>' +
                '<td>' + esc(formatApiLogTime(row.addtime)) + '</td>' +
                '</tr>';
        });
        body.html(html);
    }

    function refreshApiLogs() {
        var url = apiCenterUrl('logs');
        if (!url) {
            Layer.alert('请求日志接口地址缺失', {icon: 2});
            return;
        }
        var button = $('#project-api-logs-refresh');
        var status = $('#project-api-logs-status');
        button.prop('disabled', true);
        status.text('刷新中…');
        $.ajax({
            type: 'GET',
            url: url,
            dataType: 'json',
            data: {limit: $('#project-api-logs-limit').val() || 100},
            success: function (ret) {
                if (!ret || ret.code !== 1) {
                    Layer.alert((ret && ret.msg) || '读取请求日志失败', {icon: 2});
                    return;
                }
                renderApiLogs(ret.data || []);
                status.text('已刷新 ' + formatApiLogTime(Math.floor(Date.now() / 1000)));
            },
            error: function (xhr) {
                Layer.alert('读取请求日志失败 HTTP ' + xhr.status, {icon: 2});
            },
            complete: function () {
                button.prop('disabled', false);
            }
        });
    }

    function announcementMessageInput() {
        return $('textarea[name="row[message]"], input[name="row[message]"]').first();
    }

    function insertAnnouncementToken(token) {
        var input = announcementMessageInput();
        if (!input.length) return;
        var element = input.get(0);
        var value = String(input.val() || '');
        var start = typeof element.selectionStart === 'number' ? element.selectionStart : value.length;
        var end = typeof element.selectionEnd === 'number' ? element.selectionEnd : start;
        var next = value.substring(0, start) + token + value.substring(end);
        input.val(next).trigger('input').trigger('change').focus();
        if (typeof element.setSelectionRange === 'function') {
            var cursor = start + token.length;
            element.setSelectionRange(cursor, cursor);
        }
    }

    function bindAnnouncementTools() {
        $(document).off('click.zonoeAnnouncementVariable').on('click.zonoeAnnouncementVariable', '.announcement-variable', function () {
            insertAnnouncementToken(String($(this).data('token') || ''));
        });

        $(document).off('click.zonoeAnnouncementPreview').on('click.zonoeAnnouncementPreview', '#announcement-preview-submit', function () {
            var button = $(this);
            var input = announcementMessageInput();
            if (!input.length) {
                Layer.alert('未找到软件源公告输入框', {icon: 2});
                return;
            }

            button.prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: 'general/config/announcement_preview',
                dataType: 'json',
                data: {
                    message: input.val() || '',
                    udid: $('#announcement-preview-udid').val() || ''
                },
                success: function (ret) {
                    if (!ret || ret.code !== 1) {
                        Layer.alert((ret && ret.msg) || '公告预览失败', {icon: 2});
                        return;
                    }
                    var data = ret.data || {};
                    $('#announcement-preview-result')
                        .removeClass('hide')
                        .text(typeof data.message === 'undefined' ? '' : String(data.message));
                },
                error: function (xhr) {
                    Layer.alert('公告预览失败 HTTP ' + xhr.status, {icon: 2});
                },
                complete: function () {
                    button.prop('disabled', false);
                }
            });
        });
    }

    function bindApiCenter() {
        $(document).off('shown.bs.tab.zonoeApiOuter').on('shown.bs.tab.zonoeApiOuter', 'a[data-toggle="tab"]', function () {
            var href = $(this).attr('href') || '';
            if (href === '#api-center') rememberApiTab();
            if ($(this).closest('#api-center').length && href.indexOf('#project-api-') === 0) rememberApiTab(href);
        });

        $(document).off('click.zonoeApiToggle').on('click.zonoeApiToggle', '.api-toggle-btn', function () {
            var button = $(this);
            var enabled = parseInt(button.data('enabled'), 10) ? 1 : 0;
            var row = button.closest('tr');
            button.prop('disabled', true);
            apiRequest('toggle', {endpoint_key: button.data('key'), enabled: enabled}, function (data) {
                var actual = parseInt(data.enabled, 10) ? 1 : 0;
                var status = row.find('td').eq(5).find('.label');
                status.removeClass('label-success label-default')
                    .addClass(actual ? 'label-success' : 'label-default')
                    .text(actual ? '开启' : '关闭');
                button.removeClass('btn-success btn-warning')
                    .addClass(actual ? 'btn-warning' : 'btn-success')
                    .text(actual ? '关闭' : '开启')
                    .data('enabled', actual ? 0 : 1)
                    .prop('disabled', false);
            });
        });

        $(document).off('click.zonoeApiTestSelect').on('click.zonoeApiTestSelect', '.api-test-select', function () {
            selectApiForTest($(this).data('key'));
        });

        $(document).off('change.zonoeApiTest').on('change.zonoeApiTest', '#project-api-test-key', syncApiTestMeta);

        $(document).off('click.zonoeApiTest').on('click.zonoeApiTest', '#project-api-test-submit', function () {
            var key = $('#project-api-test-key').val();
            if (!key) {
                Layer.alert('请先选择API接口', {icon: 0});
                return;
            }
            var missing = '';
            $('#project-api-test-fields .api-test-field').each(function () {
                if (!missing && parseInt($(this).data('required'), 10) === 1 && $.trim($(this).val()) === '') {
                    missing = String($(this).data('label') || '必填参数');
                }
            });
            if (missing) {
                Layer.alert(missing + '不能为空', {icon: 0});
                return;
            }
            apiRequest('test', $('#project-api-test-form').serialize(), function (data) {
                $('#project-api-test-result').show().text(JSON.stringify(data || {}, null, 2));
            });
        });

        $(document).off('click.zonoeApiLogs').on('click.zonoeApiLogs', '#project-api-logs-refresh', function () {
            rememberApiTab('#project-api-logs');
            refreshApiLogs();
        });

        $(document).off('change.zonoeApiLogs').on('change.zonoeApiLogs', '#project-api-logs-limit', refreshApiLogs);

        $(document).off('click.zonoeApiAdd').on('click.zonoeApiAdd', '#project-api-add-submit', function () {
            apiRequest('save', $('#project-api-add-form').serialize(), function () {
                rememberApiTab('#project-api-list');
                location.reload();
            });
        });

        $(document).off('click.zonoeApiEditSelect').on('click.zonoeApiEditSelect', '.api-edit-select', function () {
            var button = $(this);
            $('#project-api-edit-id').val(button.data('id'));
            $('#project-api-edit-name').val(button.data('name'));
            $('#project-api-edit-slug').val(String(button.data('path') || '').replace(/^\/project-api\//, ''));
            $('#project-api-edit-handler').val(button.data('handler'));
            $('#project-api-edit-method').val(button.data('method'));
            $('#project-api-edit-auth').val(button.data('auth'));
            $('#project-api-edit-description').val(button.data('description'));
            $('#project-api-edit-enabled').prop('checked', parseInt(button.data('enabled'), 10) === 1);
            $('#project-api-edit-empty').addClass('hide');
            $('#project-api-edit-form').removeClass('hide');
            rememberApiTab('#project-api-edit');
            $('a[href="#project-api-edit"]').tab('show');
        });

        $(document).off('click.zonoeApiEdit').on('click.zonoeApiEdit', '#project-api-edit-submit', function () {
            apiRequest('save', $('#project-api-edit-form').serialize(), function () {
                rememberApiTab('#project-api-list');
                location.reload();
            });
        });

        $(document).off('click.zonoeApiDelete').on('click.zonoeApiDelete', '.api-delete-btn', function () {
            var button = $(this);
            Layer.confirm('确定删除自定义API“' + String(button.data('name') || '') + '”吗？', {icon: 3, title: '确认删除'}, function (index) {
                Layer.close(index);
                apiRequest('delete', {id: button.data('id')}, function () {
                    button.closest('tr').remove();
                });
            });
        });

        restoreApiTab();
        syncApiTestMeta();
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
                        $(that).closest('tr').remove();
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
            var historyButton = $('#zonoe-update-history');
            if (historyButton.length && !$('#zonoe-update-ops').length) {
                historyButton.after(' <button type="button" id="zonoe-update-ops" class="btn btn-primary btn-embossed"><i class="fa fa-dashboard"></i> 更新运维中心</button>');
            }
            $(document).off('click.zonoeHistory').on('click.zonoeHistory', '#zonoe-update-history', openHistory);
            $(document).off('click.zonoeOps').on('click.zonoeOps', '#zonoe-update-ops', openUpdateOperations);
            $(document).off('click.zonoeRollback').on('click.zonoeRollback', '.zonoe-rollback', function () {
                var id = $(this).data('id');
                layer.confirm('将恢复该次更新前的程序文件、数据库和版本号。确定继续吗？', {icon: 3, title: '确认回滚'}, function (idx) {
                    layer.close(idx);
                    layer.closeAll();
                    runRollback(id);
                });
            });

            bindAnnouncementTools();
            bindApiCenter();
            restoreActiveUpdate();
        },
        add: function () { Controller.api.bindevent(); },
        edit: function () { Controller.api.bindevent(); },
        api: {
            bindevent: function () { Form.api.bindevent($("form[role=form]")); }
        }
    };
    return Controller;
});
