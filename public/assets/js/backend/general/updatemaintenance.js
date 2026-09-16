define(['jquery', 'bootstrap', 'backend'], function ($, undefined, Backend) {
    function esc(value) { return $('<div/>').text(value == null ? '' : String(value)).html(); }
    function bytes(value) {
        var n = parseInt(value || 0, 10), units = ['B','KB','MB','GB','TB'], i = 0;
        while (n >= 1024 && i < units.length - 1) { n = n / 1024; i++; }
        return (i === 0 ? n : n.toFixed(n >= 10 ? 1 : 2)) + ' ' + units[i];
    }
    function historyText(row) {
        if (!row) return '暂无记录';
        var status = row.status === 'success' ? '<span class="text-success">成功</span>' : '<span class="text-danger">失败</span>';
        return '<div><b>' + esc(row.from_version || '-') + ' → ' + esc(row.to_version || '-') + '</b> · ' + status + '</div><div class="text-muted">' + esc(row.created_at || '-') + '</div>';
    }
    function storageHtml(storage) {
        storage = storage || {};
        var rows = [
            ['更新备份', storage.backups || {}],
            ['更新历史', storage.history || {}],
            ['任务状态', storage.status || {}],
            ['更新缓存', storage.cache || {}]
        ];
        var html = '<table class="table table-bordered zonoe-ops-table"><thead><tr><th>类别</th><th>数量</th><th>占用</th></tr></thead><tbody>';
        $.each(rows, function(_, item){ html += '<tr><td>' + item[0] + '</td><td>' + esc(item[1].count || 0) + '</td><td>' + bytes(item[1].bytes || 0) + '</td></tr>'; });
        return html + '</tbody></table>';
    }
    function jobsHtml(jobs) {
        jobs = jobs || {}; var rows = jobs.running || [];
        if (!rows.length) return '<span class="text-success">当前没有运行中的更新任务</span>';
        var html = '<table class="table table-bordered zonoe-ops-table"><thead><tr><th>Job ID</th><th>阶段</th><th>进度</th><th>最后更新</th></tr></thead><tbody>';
        $.each(rows, function(_, row){ html += '<tr><td>' + esc(row.job_id || '-') + '</td><td>' + esc(row.stage || '-') + '</td><td>' + esc(row.progress || 0) + '%</td><td>' + esc(row.updated_at || '-') + '</td></tr>'; });
        return html + '</tbody></table>';
    }
    function lockHtml(lock) {
        lock = lock || {};
        if (!lock.exists) return '<span class="text-success">当前没有更新锁</span>';
        var state = lock.busy === true ? '<span class="text-danger">正在占用</span>' : (lock.busy === false ? '<span class="text-success">未占用</span>' : '<span class="text-warning">无法判断</span>');
        return '<div>状态：' + state + '</div><div class="text-muted">锁龄：' + esc(lock.age_seconds || 0) + ' 秒</div><div class="text-muted">元数据：' + esc(lock.metadata || '-') + '</div>';
    }
    function render(data) {
        data = data || {}; var release = data.release || {}, storage = data.storage || {}, jobs = data.jobs || {}, history = data.history || {};
        $('#ops-version').text(release.version || '-');
        $('#ops-integrity').attr('class','zonoe-ops-sub ' + (release.integrity_verified === true ? 'zonoe-ops-good' : (release.integrity_verified === false ? 'zonoe-ops-bad' : 'zonoe-ops-warn'))).text(release.integrity_verified === true ? '文件完整性：通过' : (release.integrity_verified === false ? '文件完整性：异常' : '文件完整性：未配置签名'));
        $('#ops-backups').text((storage.backups || {}).count || 0);
        $('#ops-backups-size').text(bytes((storage.backups || {}).bytes || 0));
        $('#ops-history-count').text(history.count || 0);
        $('#ops-running-count').text(jobs.running_count || 0);
        $('#ops-stale-count').text('疑似中断：' + (jobs.stale_count || 0));
        $('#ops-storage').html(storageHtml(storage));
        $('#ops-latest-update').html(historyText(history.latest_update));
        $('#ops-latest-rollback').html(historyText(history.latest_rollback));
        $('#ops-jobs').html(jobsHtml(jobs));
        $('#ops-lock').html(lockHtml(data.lock || {}));
    }
    function loadSnapshot(silent) {
        if (!silent) layer.load(1, {shade:0.05});
        $.getJSON('general/updatemaintenance/index', function(ret){
            if (!silent) layer.closeAll('loading');
            if (!ret || ret.code !== 200 || !ret.data) { if (!silent) layer.alert((ret && ret.msg) || '读取更新运维状态失败',{icon:2}); return; }
            render(ret.data);
        }).fail(function(){ if (!silent) { layer.closeAll('loading'); layer.alert('读取更新运维状态失败',{icon:2}); } });
    }
    function cleanup(apply) {
        layer.load(1,{shade:0.05});
        $.ajax({type:'POST',url:'general/updatemaintenance/cleanup',dataType:'json',data:{apply:apply ? 1 : 0},success:function(ret){
            layer.closeAll('loading');
            if (!ret || ret.code !== 200 || !ret.data) { layer.alert((ret && ret.msg) || '操作失败',{icon:2}); return; }
            var d = ret.data, c = d.candidates || {}, deleted = d.deleted || {};
            if (!apply) {
                $('#ops-cleanup-result').html('<div class="alert alert-info"><b>安全清理预览完成</b><br>状态文件：' + ((c.status || []).length) + '，历史：' + ((c.history || []).length) + '，备份：' + ((c.backups || []).length) + '<br>这是预览，没有删除任何文件。</div>');
            } else {
                $('#ops-cleanup-result').html('<div class="alert alert-success"><b>安全清理完成</b><br>状态文件：' + (deleted.status || 0) + '，历史：' + (deleted.history || 0) + '，备份：' + (deleted.backups || 0) + '，释放：' + bytes(deleted.bytes || 0) + '</div>');
                loadSnapshot(true);
            }
        },error:function(){ layer.closeAll('loading'); layer.alert('请求失败',{icon:2}); }});
    }
    var Controller = {panel:function(){
        loadSnapshot(false);
        $(document).off('click.zonoeOpsPreview').on('click.zonoeOpsPreview','#ops-preview',function(){ cleanup(false); });
        $(document).off('click.zonoeOpsClean').on('click.zonoeOpsClean','#ops-clean',function(){ layer.confirm('仅清理在线更新系统自身的过期状态、历史和未被引用的旧备份。确定执行吗？',{icon:3,title:'确认安全清理'},function(idx){ layer.close(idx); cleanup(true); }); });
    },index:function(){}};
    return Controller;
});
