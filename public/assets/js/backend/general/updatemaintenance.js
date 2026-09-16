define(['jquery', 'bootstrap', 'backend'], function ($, undefined, Backend) {
    function esc(value) {
        return $('<div/>').text(value == null ? '' : String(value)).html();
    }

    function bytes(value) {
        var n = parseInt(value || 0, 10);
        var units = ['B', 'KB', 'MB', 'GB', 'TB'];
        var i = 0;
        while (n >= 1024 && i < units.length - 1) { n = n / 1024; i++; }
        return (i === 0 ? n : n.toFixed(n >= 10 ? 1 : 2)) + ' ' + units[i];
    }

    function historyText(row) {
        if (!row) return '暂无记录';
        var version = esc(row.from_version || '-') + ' → ' + esc(row.to_version || '-');
        var status = row.status === 'success' ? '<span class="text-success">成功</span>' : '<span class="text-danger">失败</span>';
        return '<div><b>' + version + '</b> · ' + status + '</div><div class="text-muted">' + esc(row.created_at || '-') + '</div>';
    }

    function categoryLabel(key) {
        var labels = {
            protected: '核心程序/发布资产', persistent: '业务持久数据', regenerable: '可重建缓存',
            log: '日志', backup: '备份/归档', temporary: '临时文件', unknown: '未识别文件'
        };
        return labels[key] || key;
    }

    function summaryHtml(site) {
        var buckets = (site && site.buckets) || {};
        var keys = ['protected', 'persistent', 'regenerable', 'log', 'backup', 'temporary', 'unknown'];
        var html = '<table class="table table-bordered zonoe-ops-table"><thead><tr><th>分类</th><th>文件数</th><th>占用</th><th>处理策略</th></tr></thead><tbody>';
        $.each(keys, function (_, key) {
            var row = buckets[key] || {};
            var policy = (key === 'regenerable' || key === 'temporary') ? '<span class="text-success">自动安全清理</span>' :
                ((key === 'backup' || key === 'unknown') ? '<span class="text-warning">人工确认</span>' : '<span class="text-muted">默认保护</span>');
            html += '<tr><td>' + categoryLabel(key) + '</td><td>' + esc(row.count || 0) + '</td><td>' + bytes(row.bytes || 0) + '</td><td>' + policy + '</td></tr>';
        });
        html += '</tbody></table>';
        if (site && site.errors && site.errors.length) {
            html += '<div class="alert alert-warning">有 ' + site.errors.length + ' 个路径无法读取，未计入对应目录内容。</div>';
        }
        return html;
    }

    function fileTable(rows, selectable) {
        rows = rows || [];
        if (!rows.length) return '<span class="text-success">当前没有项目</span>';
        var html = '<table class="table table-striped table-bordered zonoe-ops-table"><thead><tr>';
        if (selectable) html += '<th style="width:40px"><input type="checkbox" id="site-select-all"></th>';
        html += '<th>路径</th><th>分类</th><th>大小</th><th>修改时间</th><th>判定</th></tr></thead><tbody>';
        $.each(rows, function (_, row) {
            html += '<tr>';
            if (selectable) html += '<td><input type="checkbox" class="site-review-check" value="' + esc(row.path || '') + '"></td>';
            html += '<td>' + esc(row.path || '-') + '</td><td>' + esc(categoryLabel(row.category || '-')) + '</td><td>' + bytes(row.bytes || 0) + '</td>' +
                '<td>' + (row.mtime ? esc(new Date(row.mtime * 1000).toLocaleString()) : '-') + '</td><td>' + esc(row.reason || '-') + '</td></tr>';
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
            html += '<tr><td>' + esc(row.job_id || '-') + '</td><td>' + esc(row.stage || '-') + '</td><td>' + esc(row.progress || 0) + '%</td><td>' + esc(row.updated_at || '-') + '</td><td>' +
                (staleMap[row.job_id] ? '<span class="text-danger">疑似中断</span>' : '<span class="text-info">运行中</span>') + '</td></tr>';
        });
        return html + '</tbody></table>';
    }

    function render(data) {
        data = data || {};
        var release = data.release || {};
        var site = data.site_storage || {};
        var buckets = site.buckets || {};
        var total = buckets.total || {};
        var safeCount = ((buckets.regenerable || {}).count || 0) + ((buckets.temporary || {}).count || 0);
        var safeBytes = ((buckets.regenerable || {}).bytes || 0) + ((buckets.temporary || {}).bytes || 0);
        var reviewCount = ((buckets.backup || {}).count || 0) + ((buckets.unknown || {}).count || 0);
        var reviewBytes = ((buckets.backup || {}).bytes || 0) + ((buckets.unknown || {}).bytes || 0);

        $('#ops-version').text(release.version || '-');
        $('#ops-integrity').attr('class', 'zonoe-ops-sub ' + (release.integrity_verified === true ? 'zonoe-ops-good' : (release.integrity_verified === false ? 'zonoe-ops-bad' : 'zonoe-ops-warn')))
            .text(release.integrity_verified === true ? '文件完整性：通过' : (release.integrity_verified === false ? '文件完整性：异常' : '文件完整性：未配置签名'));
        $('#site-total-bytes').text(bytes(total.bytes || 0));
        $('#site-total-files').text((total.count || 0) + ' 个文件');
        $('#site-safe-bytes').text(bytes(safeBytes));
        $('#site-safe-files').text(safeCount + ' 个文件');
        $('#site-review-bytes').text(bytes(reviewBytes));
        $('#site-review-files').text(reviewCount + ' 个文件 · 备份 + 未识别');
        $('#site-storage-summary').html(summaryHtml(site));
        $('#site-review-list').html(fileTable(site.review_candidates || [], true));
        $('#site-largest').html(fileTable(site.largest || [], false));
        $('#ops-latest-update').html(historyText((data.history || {}).latest_update));
        $('#ops-latest-rollback').html(historyText((data.history || {}).latest_rollback));
        $('#ops-jobs').html(jobsHtml(data.jobs || {}));
    }

    function loadSnapshot() {
        layer.load(1, {shade: 0.1});
        $.getJSON('general/updatemaintenance/index', function (ret) {
            layer.closeAll('loading');
            if (!ret || ret.code !== 200 || !ret.data) { layer.alert((ret && ret.msg) || '读取储存状态失败', {icon: 2}); return; }
            render(ret.data);
        }).fail(function () { layer.closeAll('loading'); layer.alert('读取储存状态失败', {icon: 2}); });
    }

    function cleanup(apply) {
        layer.load(1, {shade: 0.1});
        $.ajax({type:'POST', url:'general/updatemaintenance/cleanup', dataType:'json', data:{apply:apply ? 1 : 0}, success:function(ret){
            layer.closeAll('loading');
            if (!ret || ret.code !== 200 || !ret.data) { layer.alert((ret && ret.msg) || '安全清理失败', {icon:2}); return; }
            var data = ret.data, candidates = data.candidates || [], deleted = data.deleted || {};
            var html = '<div class="alert ' + (apply ? 'alert-success' : 'alert-info') + '"><b>' + esc(ret.msg || '') + '</b><br>候选文件：' + candidates.length +
                (apply ? ('<br>实际删除：' + (deleted.count || 0) + ' 个，释放 ' + bytes(deleted.bytes || 0)) : '<br>仅预览，没有删除任何文件。') + '</div>';
            if (candidates.length) html += fileTable(candidates, false);
            $('#ops-cleanup-result').html(html);
            if (apply) loadSnapshot();
        }, error:function(){ layer.closeAll('loading'); layer.alert('安全清理请求失败', {icon:2}); }});
    }

    function deleteSelected() {
        var paths = [];
        $('.site-review-check:checked').each(function(){ paths.push($(this).val()); });
        if (!paths.length) { layer.msg('请先勾选要删除的备份或未识别文件'); return; }
        layer.confirm('将永久删除已勾选的 ' + paths.length + ' 个文件。程序文件和业务保护文件即使伪造请求也不会被删除。确定继续吗？', {icon:3, title:'人工确认删除'}, function(idx){
            layer.close(idx); layer.load(1, {shade:0.1});
            $.ajax({type:'POST', url:'general/updatemaintenance/cleanupSelected', dataType:'json', data:{paths:paths}, success:function(ret){
                layer.closeAll('loading');
                if (!ret || ret.code !== 200 || !ret.data) { layer.alert((ret && ret.msg) || '删除失败', {icon:2}); return; }
                var d = ret.data.deleted || {};
                layer.alert('已删除 ' + (d.count || 0) + ' 个文件，释放 ' + bytes(d.bytes || 0) + '。' + ((ret.data.skipped || []).length ? ' 另有部分文件被安全策略拒绝。' : ''), {icon:1}, loadSnapshot);
            }, error:function(){ layer.closeAll('loading'); layer.alert('删除请求失败', {icon:2}); }});
        });
    }

    var Controller = {
        panel: function () {
            loadSnapshot();
            $(document).off('click.zonoeOpsRefresh').on('click.zonoeOpsRefresh', '#ops-refresh', loadSnapshot);
            $(document).off('click.zonoeOpsPreview').on('click.zonoeOpsPreview', '#ops-preview', function(){ cleanup(false); });
            $(document).off('click.zonoeOpsClean').on('click.zonoeOpsClean', '#ops-clean', function(){
                layer.confirm('自动安全清理只会删除已明确识别的可重建缓存/临时文件，并跳过 1 小时内的新文件。确定执行吗？', {icon:3, title:'确认自动安全清理'}, function(idx){ layer.close(idx); cleanup(true); });
            });
            $(document).off('change.zonoeSelectAll').on('change.zonoeSelectAll', '#site-select-all', function(){ $('.site-review-check').prop('checked', this.checked); });
            $(document).off('click.zonoeDeleteSelected').on('click.zonoeDeleteSelected', '#ops-delete-selected', deleteSelected);
        },
        index: function () {}
    };
    return Controller;
});
