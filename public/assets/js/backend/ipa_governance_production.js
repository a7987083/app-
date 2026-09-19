define(['jquery'], function ($) {
    'use strict';

    var html = function (v) { return $('<div/>').text(v == null ? '' : String(v)).html(); };
    var formatBytes = function (bytes, unit) { bytes=parseInt(bytes||0,10);unit=unit||'MB';var div=unit==='KB'?1024:1024*1024;return (bytes/div).toFixed(2)+' '+unit; };
    var cap = function (name) { return parseInt($('#ipa-phase207-capabilities').data(name),10)===1; };

    var selectedIds = function () {
        var ids=[];$('#ipa-governance-table .ipa-gov-select:checked').each(function(){var id=parseInt($(this).val(),10);if(id>0)ids.push(id);});return ids;
    };
    var selectedIgnoredIds = function () {
        var ids=[];$('#ipa-ignore-table .ipa-ignore-select:checked').each(function(){var id=parseInt($(this).val(),10);if(id>0)ids.push(id);});return ids;
    };

    var decorateGovernanceRows = function () {
        var body=$('#ipa-governance-table tbody');body.find('tr').each(function(){var tr=$(this);if(tr.data('phase207-decorated'))return;var action=tr.find('.btn-gov-preview,.btn-gov-ignore,.btn-gov-verify').first();var id=parseInt(action.data('id'),10);if(id>0){tr.prepend('<td class="text-center"><input type="checkbox" class="ipa-gov-select" value="'+id+'"></td>');tr.data('phase207-decorated',1);}else{var td=tr.find('td').first();if(td.length&&parseInt(td.attr('colspan'),10)===7)td.attr('colspan',8);tr.data('phase207-decorated',1);}});
    };

    var loadFailures = function () {
        Fast.api.ajax({url:'ipa_center/governance_failures',type:'GET',loading:false,data:{limit:100}},function(data){var rows=data.rows||[],stats=data.stats||{};$('#ipa-gov-failed-count').text(stats.failed||0);$('#ipa-gov-interrupted-count').text(stats.interrupted||0);var body=$('#ipa-governance-failure-table tbody');body.empty();if(!rows.length){body.append('<tr><td colspan="9" class="text-center text-muted">当前没有 failed / interrupted 治理操作</td></tr>');return false;}$.each(rows,function(_,r){var label=r.state==='interrupted'?'warning':'danger';var retry=cap('can-retry')?'<button type="button" class="btn btn-xs btn-warning btn-ipa-governance-retry" data-operation="'+html(r.operation_id||'')+'">重新预览并重试</button>':'<span class="text-muted">无重试权限</span>';body.append('<tr><td><code>'+html(r.operation_id||'')+'</code>'+(r.retry_of?'<br><small>retry_of: '+html(r.retry_of)+'</small>':'')+'</td><td>#'+html(r.issue_id||0)+'</td><td><span class="label label-'+label+'">'+html(r.state||'')+'</span></td><td>'+html(r.operation_type||'')+'<br><small>'+html(r.mode||'')+'</small></td><td>#'+html(r.category_id||0)+' / #'+html(r.metadata_id||0)+'</td><td style="max-width:320px;word-break:break-all" class="text-danger">'+html(r.error_message||'')+'</td><td>'+html(r.started_at_text||'')+'</td><td>'+html(r.finished_at_text||'')+'</td><td>'+retry+'</td></tr>');});return false;},function(){return false;});
    };

    var loadIgnored = function () {
        if(!cap('can-lifecycle-list'))return;
        Fast.api.ajax({url:'ipa_lifecycle/ignored_list',type:'GET',loading:false,data:{limit:100}},function(data){var rows=data.rows||[],stats=data.stats||{};$('#ipa-ignore-count').text(stats.ignored||0);$('#ipa-ignore-expired-count').text(stats.expired||0);var body=$('#ipa-ignore-table tbody');body.empty();if(!rows.length){body.append('<tr><td colspan="7" class="text-center text-muted">当前没有 ignored 治理项</td></tr>');return false;}$.each(rows,function(_,r){body.append('<tr><td class="text-center"><input type="checkbox" class="ipa-ignore-select" value="'+html(r.id)+'"></td><td>#'+html(r.id)+'</td><td>'+html(r.issue_type||'')+'</td><td>'+html(r.field_name||'')+'</td><td>'+html(r.reason||'')+'</td><td>'+html(r.ignore_until_text||'')+'</td><td>'+(r.expired?'<span class="label label-warning">已到期</span>':'<span class="label label-default">ignored</span>')+'</td></tr>');});return false;},function(){return false;});
    };

    var loadRangeMetrics = function () {
        var days=parseInt($('#ipa-range-window').val()||7,10);Fast.api.ajax({url:'ipa_production/metrics',type:'GET',loading:false,data:{days:days}},function(data){$('#ipa-range-parsed').text(data.parsed||0);$('#ipa-range-reused').text(data.reused||0);$('#ipa-range-bytes').text(formatBytes(data.range_bytes||0,'MB'));$('#ipa-range-requests').text(data.range_requests||0);$('#ipa-range-avg-request').text(formatBytes(data.avg_range_bytes_per_request||0,'KB'));var note='窗口 '+(data.days||days)+' 天；task items '+(data.window_items||0)+'，统计样本 '+(data.sampled_items||0)+'。';if(data.truncated)note+=' 当前窗口超过 5000 条，仅聚合最近 5000 条样本。';$('#ipa-range-note').text(note);return false;},function(){return false;});
    };

    return function () {
        var table=document.getElementById('ipa-governance-table');if(table&&window.MutationObserver)new MutationObserver(decorateGovernanceRows).observe(table.querySelector('tbody'),{childList:true});decorateGovernanceRows();
        $('#ipa-gov-select-all').on('change',function(){$('#ipa-governance-table .ipa-gov-select').prop('checked',$(this).is(':checked'));});
        $('#ipa-ignore-select-all').on('change',function(){$('#ipa-ignore-table .ipa-ignore-select').prop('checked',$(this).is(':checked'));});

        $('.btn-ipa-governance-batch').on('click',function(){var ids=selectedIds();if(!ids.length){Toastr.warning('请先勾选需要批量治理的异常');return;}Fast.api.ajax({url:'ipa_center/governance_batch_preview',data:{issue_ids_json:JSON.stringify(ids)}},function(data){var text='已选择：'+data.selected_count+'\n可安全执行：'+data.applicable_count+'\n跳过：'+data.skipped_count+'\nbatch_hash：'+data.batch_hash+'\n\n批量模式不会自动移动 OpenList 文件；路径异常仅允许以 OpenList 当前地址更新数据库。';Layer.confirm('<pre style="white-space:pre-wrap">'+html(text)+'</pre><p class="text-danger">执行前会重新生成计划；任何计划漂移都会拒绝整个批次。</p>',{title:'Phase 20.7 批量治理预览'},function(index){Layer.close(index);Fast.api.ajax({url:'ipa_center/governance_batch_apply',data:{issue_ids_json:JSON.stringify(ids),batch_hash:data.batch_hash}},function(result){Toastr.success('成功 '+(result.success_count||0)+'，失败 '+(result.failed_count||0)+'，跳过 '+(result.skipped_count||0));$('.btn-ipa-governance-refresh').trigger('click');loadFailures();return false;});});return false;});});

        $('.btn-ipa-ignore-batch').on('click',function(){var ids=selectedIds();if(!ids.length){Toastr.warning('请先勾选需要忽略的异常');return;}Layer.prompt({title:'忽略天数（1-365）',value:'30'},function(value,index){var days=parseInt(value,10);if(!days||days<1||days>365){Toastr.error('忽略天数必须为 1-365');return;}Layer.close(index);Fast.api.ajax({url:'ipa_lifecycle/ignore_batch',data:{issue_ids_json:JSON.stringify(ids),days:days}},function(data){Toastr.success('已忽略 '+(data.updated||0)+' 条，至 '+(data.ignore_until_text||''));$('.btn-ipa-governance-refresh').trigger('click');loadIgnored();return false;});});});

        $('.btn-ipa-governance-scan-interrupted').on('click',function(){Fast.api.ajax({url:'ipa_recovery/scan_interrupted',data:{stale_seconds:300}},function(data){Toastr.success('已标记中断操作：'+(data.interrupted||0));loadFailures();return false;});});
        $('#ipa-governance-failure-table').on('click','.btn-ipa-governance-retry',function(){var operationId=$(this).data('operation');Layer.confirm('重试不会复用旧 plan；系统会根据当前数据重新 preview、执行并 verify。确认继续？',{title:'治理恢复'},function(index){Layer.close(index);Fast.api.ajax({url:'ipa_recovery/retry',data:{operation_id:operationId}},function(){$('.btn-ipa-governance-refresh').trigger('click');loadFailures();return false;});});});

        $('.btn-ipa-ignore-sweep').on('click',function(){Fast.api.ajax({url:'ipa_lifecycle/sweep_expired',data:{}},function(data){Toastr.success('已恢复到期忽略项：'+(data.reopened||0));$('.btn-ipa-governance-refresh').trigger('click');loadIgnored();return false;});});
        $('.btn-ipa-ignore-refresh').on('click',loadIgnored);
        $('.btn-ipa-unignore-batch').on('click',function(){var ids=selectedIgnoredIds();if(!ids.length){Toastr.warning('请先勾选 ignored 项');return;}Fast.api.ajax({url:'ipa_lifecycle/unignore_batch',data:{issue_ids_json:JSON.stringify(ids)}},function(data){Toastr.success('已恢复 '+(data.updated||0)+' 条');$('.btn-ipa-governance-refresh').trigger('click');loadIgnored();return false;});});

        $('.btn-ipa-range-refresh').on('click',loadRangeMetrics);$('#ipa-range-window').on('change',loadRangeMetrics);
        $('.btn-ipa-retention-preview').on('click',function(){var days=parseInt($('#ipa-retention-days').val()||90,10);Fast.api.ajax({url:'ipa_production/retention_preview',data:{days:days}},function(data){var text='保留期：'+data.days+' 天\n可删除 scan task：'+data.task_count+'\n对应 task item：'+data.task_item_count+'\n可删除 success/superseded 审计：'+data.operation_log_count+'\nplan_hash：'+data.plan_hash+'\n\n受保护状态：'+(data.protected_states||[]).join(', ');if(data.capped)text+='\n\n当前候选达到单表 1000 条上限，本次只处理这一批。';Layer.confirm('<pre style="white-space:pre-wrap">'+html(text)+'</pre><p class="text-danger">这是不可逆历史清理；执行前会重新计算计划，计划变化则拒绝执行。</p>',{title:'Retention 预览'},function(index){Layer.close(index);Fast.api.ajax({url:'ipa_production/retention_apply',data:{days:days,plan_hash:data.plan_hash}},function(result){Toastr.success('已清理 task '+(result.deleted_tasks||0)+'、item '+(result.deleted_task_items||0)+'、audit '+(result.deleted_operation_logs||0));return false;});});return false;});});

        $('.btn-ipa-governance-failures-refresh').on('click',loadFailures);loadFailures();loadIgnored();loadRangeMetrics();
    };
});
