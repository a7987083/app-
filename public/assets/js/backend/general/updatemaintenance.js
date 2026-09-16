define(['jquery', 'bootstrap', 'backend'], function ($, undefined, Backend) {
    var pollTimer = null;

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
    function categoryLabel(key) {
        return ({protected:'核心程序/发布资产',persistent:'业务持久数据',regenerable:'可重建缓存',log:'日志',backup:'备份/归档',temporary:'临时文件',unknown:'未识别文件'})[key] || key;
    }
    function summaryHtml(site) {
        if (!site || !site.index_ready) return '<div class="alert alert-info">尚未建立储存索引。系统扫描启动后，页面不会被阻塞；完成后会自动刷新。</div>';
        var buckets = site.buckets || {}, keys = ['protected','persistent','regenerable','log','backup','temporary','unknown'];
        var html = '<table class="table table-bordered zonoe-ops-table"><thead><tr><th>分类</th><th>文件数</th><th>占用</th><th>策略</th></tr></thead><tbody>';
        $.each(keys, function(_, key){
            var row = buckets[key] || {};
            var policy = (key === 'regenerable' || key === 'temporary') ? '<span class="text-success">自动安全清理</span>' : ((key === 'backup' || key === 'unknown') ? '<span class="text-warning">人工确认</span>' : '<span class="text-muted">默认保护</span>');
            html += '<tr><td>' + categoryLabel(key) + '</td><td>' + esc(row.count || 0) + '</td><td>' + bytes(row.bytes || 0) + '</td><td>' + policy + '</td></tr>';
        });
        return html + '</tbody></table>';
    }
    function fileTable(rows, selectable) {
        rows = rows || [];
        if (!rows.length) return '<span class="text-success">当前没有项目</span>';
        var html = '<table class="table table-striped table-bordered zonoe-ops-table"><thead><tr>';
        if (selectable) html += '<th style="width:40px"><input type="checkbox" id="site-select-all"></th>';
        html += '<th>路径</th><th>分类</th><th>大小</th><th>修改时间</th><th>判定</th></tr></thead><tbody>';
        $.each(rows, function(_, row){
            html += '<tr>';
            if (selectable) html += '<td><input type="checkbox" class="site-review-check" value="' + esc(row.path || '') + '"></td>';
            html += '<td>' + esc(row.path || '-') + '</td><td>' + esc(categoryLabel(row.category || '-')) + '</td><td>' + bytes(row.bytes || 0) + '</td><td>' + (row.mtime ? esc(new Date(row.mtime * 1000).toLocaleString()) : '-') + '</td><td>' + esc(row.reason || '-') + '</td></tr>';
        });
        return html + '</tbody></table>';
    }
    function jobsHtml(jobs) {
        jobs = jobs || {}; var rows = jobs.running || [];
        if (!rows.length) return '<span class="text-success">当前没有运行中的更新任务</span>';
        var html = '<table class="table table-bordered zonoe-ops-table"><thead><tr><th>Job ID</th><th>阶段</th><th>进度</th><th>最后更新</th></tr></thead><tbody>';
        $.each(rows, function(_, row){ html += '<tr><td>' + esc(row.job_id || '-') + '</td><td>' + esc(row.stage || '-') + '</td><td>' + esc(row.progress || 0) + '%</td><td>' + esc(row.updated_at || '-') + '</td></tr>'; });
        return html + '</tbody></table>';
    }

    function render(data) {
        data = data || {}; var release = data.release || {}, site = data.site_storage || {}, buckets = site.buckets || {}, total = buckets.total || {};
        var safeCount = ((buckets.regenerable || {}).count || 0) + ((buckets.temporary || {}).count || 0);
        var safeBytes = ((buckets.regenerable || {}).bytes || 0) + ((buckets.temporary || {}).bytes || 0);
        var reviewCount = ((buckets.backup || {}).count || 0) + ((buckets.unknown || {}).count || 0);
        var reviewBytes = ((buckets.backup || {}).bytes || 0) + ((buckets.unknown || {}).bytes || 0);
        $('#ops-version').text(release.version || '-');
        $('#ops-integrity').attr('class','zonoe-ops-sub ' + (release.integrity_verified === true ? 'zonoe-ops-good' : (release.integrity_verified === false ? 'zonoe-ops-bad' : 'zonoe-ops-warn'))).text(release.integrity_verified === true ? '文件完整性：通过' : (release.integrity_verified === false ? '文件完整性：异常' : '文件完整性：未配置签名'));
        if (site.index_ready) {
            $('#site-total-bytes').text(bytes(total.bytes || 0)); $('#site-total-files').text((total.count || 0) + ' 个文件');
            $('#site-safe-bytes').text(bytes(safeBytes)); $('#site-safe-files').text(safeCount + ' 个文件');
            $('#site-review-bytes').text(bytes(reviewBytes)); $('#site-review-files').text(reviewCount + ' 个文件 · 备份 + 未识别');
        }
        var engine = site.engine || {}, meta = site.meta || {};
        $('#site-engine').attr('class','zonoe-engine ' + (engine.mode === 'system-fast' ? 'text-success' : 'text-danger')).text(engine.mode === 'system-fast' ? '系统高速模式（GNU find + PHP CLI worker）' : '系统扫描能力不可用');
        $('#site-index-meta').html(site.index_ready ? ('索引更新时间：' + esc(meta.last_scan_at || '-') + ' · 扫描耗时：' + esc(meta.duration_seconds || 0) + ' 秒 · 源码目录跳过：' + esc((meta.skipped_source_dirs || []).join(', ') || '无')) : '尚未建立索引；首次扫描会自动启动。');
        $('#site-storage-summary').html(summaryHtml(site));
        $('#site-review-list').html(site.index_ready ? fileTable(site.review_candidates || [], true) : '等待索引...');
        $('#site-largest').html(site.index_ready ? fileTable(site.largest || [], false) : '等待索引...');
        $('#ops-latest-update').html(historyText((data.history || {}).latest_update));
        $('#ops-latest-rollback').html(historyText((data.history || {}).latest_rollback));
        $('#ops-jobs').html(jobsHtml(data.jobs || {}));
        renderJobStatuses(data.storage_jobs || {});
        if (!site.index_ready && engine.mode === 'system-fast' && !((data.storage_jobs || {}).scan || {}).running) startScan(true);
    }

    function setProgress(kind, status) {
        status = status || {}; var running = !!status.running, success = status.status === 'success', failed = status.status === 'failed' || status.status === 'stale';
        var box = $('#' + kind + '-progress-box'), bar = $('#' + kind + '-progress-bar'), text = $('#' + kind + '-progress-text');
        if (!running && !success && !failed) { box.hide(); return; }
        box.show();
        var p = status.progress;
        if (p === null || typeof p === 'undefined') {
            bar.css('width','100%').text('处理中').parent().addClass('active');
        } else {
            p = Math.max(0, Math.min(100, parseInt(p || 0, 10)));
            bar.css('width',p + '%').text(p + '%');
            if (!running) bar.parent().removeClass('active');
        }
        var extra = '';
        if (status.total) extra = ' · ' + (status.processed || 0) + ' / ' + status.total;
        if (kind === 'cleanup' && status.deleted_count) extra += ' · 已删除 ' + status.deleted_count + ' 个，释放 ' + bytes(status.deleted_bytes || 0);
        text.text((status.message || status.status || '') + extra);
        bar.toggleClass('progress-bar-danger', kind === 'cleanup' || failed).toggleClass('progress-bar-info', kind === 'scan' && !failed).toggleClass('progress-bar-success', success && !failed);
    }

    function renderJobStatuses(jobs) {
        setProgress('scan', jobs.scan || {}); setProgress('cleanup', jobs.cleanup || {});
        var running = ((jobs.scan || {}).running || (jobs.cleanup || {}).running);
        if (running) startPolling(); else stopPolling();
    }

    function loadSnapshot(silent) {
        if (!silent) layer.load(1, {shade:0.05});
        $.getJSON('general/updatemaintenance/index', function(ret){
            if (!silent) layer.closeAll('loading');
            if (!ret || ret.code !== 200 || !ret.data) { if (!silent) layer.alert((ret && ret.msg) || '读取储存状态失败',{icon:2}); return; }
            render(ret.data);
        }).fail(function(){ if (!silent) { layer.closeAll('loading'); layer.alert('读取储存状态失败',{icon:2}); } });
    }

    function startScan(auto) {
        $.ajax({type:'POST',url:'general/updatemaintenance/scan',dataType:'json',success:function(ret){
            if (!ret || ret.code !== 200) { if (!auto) layer.alert((ret && ret.msg) || '扫描启动失败',{icon:2}); return; }
            if (!auto) layer.msg(ret.msg || '扫描已启动');
            setProgress('scan',(ret.data || {}).status || {}); startPolling();
        },error:function(xhr){ if (!auto) layer.alert('扫描启动失败：' + (xhr.status || ''),{icon:2}); }});
    }

    function pollStatus() {
        $.getJSON('general/updatemaintenance/index?status_only=1', function(ret){
            if (!ret || ret.code !== 200 || !ret.data) return;
            var jobs = ret.data.storage_jobs || {}, scan = jobs.scan || {}, cleanup = jobs.cleanup || {};
            renderJobStatuses(jobs);
            if (!scan.running && scan.status === 'success' && !cleanup.running) loadSnapshot(true);
            if (!cleanup.running && cleanup.status === 'success' && !scan.running) loadSnapshot(true);
        });
    }
    function startPolling() { if (pollTimer) return; pollTimer = setInterval(pollStatus, 1000); pollStatus(); }
    function stopPolling() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

    function previewCleanup() {
        layer.load(1,{shade:0.05});
        $.ajax({type:'POST',url:'general/updatemaintenance/cleanup',dataType:'json',data:{apply:0},success:function(ret){
            layer.closeAll('loading'); if (!ret || ret.code !== 200 || !ret.data) { layer.alert((ret && ret.msg) || '预览失败',{icon:2}); return; }
            var d = ret.data; var html = '<div class="alert alert-info"><b>自动安全清理预览</b><br>候选：' + (d.count || 0) + ' 个，预计释放 ' + bytes(d.bytes || 0) + '<br>这里只读取上次扫描索引，不会重新扫描全站。</div>';
            if ((d.candidates || []).length) html += fileTable(d.candidates,false);
            $('#ops-cleanup-result').html(html);
        },error:function(){ layer.closeAll('loading'); layer.alert('预览请求失败',{icon:2}); }});
    }

    function startCleanup() {
        $.ajax({type:'POST',url:'general/updatemaintenance/cleanup',dataType:'json',data:{apply:1},success:function(ret){
            if (!ret || ret.code !== 200) { layer.alert((ret && ret.msg) || '安全清理启动失败',{icon:2}); return; }
            layer.msg(ret.msg || '安全清理已启动'); setProgress('cleanup',(ret.data || {}).status || {}); startPolling();
        },error:function(xhr){ layer.alert('安全清理启动失败：' + (xhr.status || ''),{icon:2}); }});
    }

    function deleteSelected() {
        var paths = []; $('.site-review-check:checked').each(function(){ paths.push($(this).val()); });
        if (!paths.length) { layer.msg('请先勾选要删除的备份或未识别文件'); return; }
        layer.confirm('将永久删除已勾选的 ' + paths.length + ' 个文件。保护文件仍会由服务端拒绝。确定继续吗？',{icon:3,title:'人工确认删除'},function(idx){
            layer.close(idx); layer.load(1,{shade:0.05});
            $.ajax({type:'POST',url:'general/updatemaintenance/cleanupSelected',dataType:'json',data:{paths:paths},success:function(ret){
                layer.closeAll('loading'); if (!ret || ret.code !== 200 || !ret.data) { layer.alert((ret && ret.msg) || '删除失败',{icon:2}); return; }
                var d = ret.data.deleted || {}; layer.alert('已删除 ' + (d.count || 0) + ' 个文件，释放 ' + bytes(d.bytes || 0),{icon:1},function(){ startScan(false); });
            },error:function(){ layer.closeAll('loading'); layer.alert('删除请求失败',{icon:2}); }});
        });
    }

    var Controller = {panel:function(){
        loadSnapshot(false);
        $(document).off('click.zonoeOpsRefresh').on('click.zonoeOpsRefresh','#ops-refresh',function(){ startScan(false); });
        $(document).off('click.zonoeOpsPreview').on('click.zonoeOpsPreview','#ops-preview',previewCleanup);
        $(document).off('click.zonoeOpsClean').on('click.zonoeOpsClean','#ops-clean',function(){ layer.confirm('自动安全清理会后台执行，并实时显示进度。仅删除已确认的缓存/临时文件。确定执行吗？',{icon:3,title:'确认自动安全清理'},function(idx){ layer.close(idx); startCleanup(); }); });
        $(document).off('change.zonoeSelectAll').on('change.zonoeSelectAll','#site-select-all',function(){ $('.site-review-check').prop('checked',this.checked); });
        $(document).off('click.zonoeDeleteSelected').on('click.zonoeDeleteSelected','#ops-delete-selected',deleteSelected);
    },index:function(){}};
    return Controller;
});
