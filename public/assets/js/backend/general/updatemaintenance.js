define(['jquery', 'bootstrap', 'backend'], function ($, undefined, Backend) {
    function esc(value) {
        return $('<div/>').text(value == null ? '' : String(value)).html();
    }

    function bytes(value) {
        var n = parseInt(value || 0, 10);
        var units = ['B', 'KB', 'MB', 'GB', 'TB'];
        var i = 0;
        while (n >= 1024 && i < units.length - 1) {
            n = n / 1024;
            i++;
        }
        return (i === 0 ? n : n.toFixed(n >= 10 ? 1 : 2)) + ' ' + units[i];
    }

    function historyText(row) {
        if (!row) return '暂无记录';
        var version = esc(row.from_version || '-') + ' → ' + esc(row.to_version || '-');
        var status = row.status === 'success' ? '<span class="text-success">成功</span>' : '<span class="text-danger">失败</span>';
        return '<div><b>' + version + '</b> · ' + status + '</div><div class="text-muted">' + esc(row.created_at || '-') + '</div>';
    }

    function storageHtml(storage) {
        storage = storage || {};
        var labels = {backups: '备份', history: '历史', status: '任务状态', cache: '缓存'};
        var html = '<table class="table table-bordered zonoe-ops-table"><thead><tr><th>类别</th><th>数量</th><th>占用</th></tr></thead><tbody>';
        $.each(['backups', 'history', 'status', 'cache'], function (_, key) {
            var row = storage[key] || {};
            html += '<tr><td>' + labels[key] + '</td><td>' + esc(row.count || 0) + '</td><td>' + bytes(row.bytes || 0) + '</td></tr>';
        });
        return html + '</tbody></table>';
    }

    function jobsHtml(jobs) {
        jobs = jobs || {};
        var rows = jobs.running || [];
        if (!rows.length) return '<span class="text-success">当前没有运行中的更新任务</span>';
        var staleMap = {};
        $.each(jobs.stale || [], function (_, row) { staleMap[row.job_id] = true; });
        var html = '<table class="table table-striped table-bordered zonoe-ops-table"><thead><tr><th>Job ID</th><th>阶段</th><th>进度</th><th>最后更新</th><th>状态</th></tr></thead><tbody>';
        $.each(rows, function (_, row) {
            html += '<tr><td style="word-break:break-all;">' + esc(row.job_id || '-') + '</td><td>' + esc(row.stage || '-') + '</td><td>' + esc(row.progress || 0) + '%</td><td>' + esc(row.updated_at || '-') + '</td><td>' + (staleMap[row.job_id] ? '<span class="text-danger">疑似中断</span>' : '<span class="text-info">运行中</span>') + '</td></tr>';
        });
        return html + '</tbody></table>';
    }

    function render(data) {
        data = data || {};
        var release = data.release || {};
        var storage = data.storage || {};
        var jobs = data.jobs || {};
        var lock = data.lock || {};
        var history = data.history || {};

        $('#ops-version').text(release.version || '-');
        if (release.integrity_verified === true) {
            $('#ops-integrity').attr('class', 'zonoe-ops-sub zonoe-ops-good').text('文件完整性：通过 · ' + (release.tag || ''));
        } else if (release.integrity_verified === false) {
            $('#ops-integrity').attr('class', 'zonoe-ops-sub zonoe-ops-bad').text('文件完整性：异常');
        } else {
            $('#ops-integrity').attr('class', 'zonoe-ops-sub zonoe-ops-warn').text('文件完整性：未配置签名');
        }

        $('#ops-backups').text((storage.backups && storage.backups.count) || 0);
        $('#ops-backup-bytes').text(bytes(storage.backups && storage.backups.bytes));
        $('#ops-running').text(jobs.running_count || 0);
        $('#ops-stale').attr('class', 'zonoe-ops-sub ' + ((jobs.stale_count || 0) ? 'zonoe-ops-bad' : 'zonoe-ops-good')).text('疑似中断：' + (jobs.stale_count || 0));

        if (lock.busy === true) {
            $('#ops-lock').attr('class', 'zonoe-ops-number zonoe-ops-warn').text('占用中');
        } else if (lock.busy === false) {
            $('#ops-lock').attr('class', 'zonoe-ops-number zonoe-ops-good').text('空闲');
        } else {
            $('#ops-lock').attr('class', 'zonoe-ops-number').text(lock.exists ? '未知' : '无锁文件');
        }
        $('#ops-lock-meta').text(lock.metadata || (lock.exists ? ('锁文件年龄 ' + (lock.age_seconds || 0) + ' 秒') : '当前没有锁文件'));

        $('#ops-latest-update').html(historyText(history.latest_update));
        $('#ops-latest-rollback').html(historyText(history.latest_rollback));
        $('#ops-storage').html(storageHtml(storage));
        $('#ops-jobs').html(jobsHtml(jobs));
    }

    function loadSnapshot() {
        layer.load(1, {shade: 0.1});
        $.getJSON('general/updatemaintenance/index', function (ret) {
            layer.closeAll('loading');
            if (!ret || ret.code !== 200 || !ret.data) {
                layer.alert((ret && ret.msg) || '读取更新运维状态失败', {icon: 2});
                return;
            }
            render(ret.data);
        }).fail(function () {
            layer.closeAll('loading');
            layer.alert('读取更新运维状态失败', {icon: 2});
        });
    }

    function cleanup(apply) {
        layer.load(1, {shade: 0.1});
        $.ajax({
            type: 'POST',
            url: 'general/updatemaintenance/cleanup',
            dataType: 'json',
            data: {apply: apply ? 1 : 0},
            success: function (ret) {
                layer.closeAll('loading');
                if (!ret || ret.code !== 200 || !ret.data) {
                    layer.alert((ret && ret.msg) || '安全清理失败', {icon: 2});
                    return;
                }
                var data = ret.data;
                var candidates = data.candidates || {};
                var deleted = data.deleted || {};
                var html = '<div class="alert ' + (apply ? 'alert-success' : 'alert-info') + '">' +
                    '<b>' + esc(ret.msg || '') + '</b><br>' +
                    '状态文件：' + ((candidates.status || []).length) + '，历史：' + ((candidates.history || []).length) + '，备份：' + ((candidates.backups || []).length) +
                    (apply ? ('<br>实际释放：' + bytes(deleted.bytes || 0)) : '<br>这是预览，没有删除任何文件。') +
                    '</div>';
                $('#ops-cleanup-result').html(html);
                if (apply) loadSnapshot();
            },
            error: function () {
                layer.closeAll('loading');
                layer.alert('安全清理请求失败', {icon: 2});
            }
        });
    }

    var Controller = {
        panel: function () {
            loadSnapshot();
            $(document).off('click.zonoeOpsRefresh').on('click.zonoeOpsRefresh', '#ops-refresh', loadSnapshot);
            $(document).off('click.zonoeOpsPreview').on('click.zonoeOpsPreview', '#ops-preview', function () { cleanup(false); });
            $(document).off('click.zonoeOpsClean').on('click.zonoeOpsClean', '#ops-clean', function () {
                layer.confirm('仅会删除超过保留期、且不再被回滚记录引用的数据。确定执行安全清理吗？', {icon: 3, title: '确认安全清理'}, function (idx) {
                    layer.close(idx);
                    cleanup(true);
                });
            });
        },
        index: function () {}
    };
    return Controller;
});
