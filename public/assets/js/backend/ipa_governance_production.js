define(['jquery'], function ($) {
    'use strict';

    var html = function (v) {
        return $('<div/>').text(v == null ? '' : String(v)).html();
    };

    var selectedIds = function () {
        var ids = [];
        $('#ipa-governance-table .ipa-gov-select:checked').each(function () {
            var id = parseInt($(this).val(), 10);
            if (id > 0) ids.push(id);
        });
        return ids;
    };

    var decorateGovernanceRows = function () {
        var body = $('#ipa-governance-table tbody');
        body.find('tr').each(function () {
            var tr = $(this);
            if (tr.data('phase207-decorated')) return;
            var action = tr.find('.btn-gov-preview,.btn-gov-ignore,.btn-gov-verify').first();
            var id = parseInt(action.data('id'), 10);
            if (id > 0) {
                tr.prepend('<td class="text-center"><input type="checkbox" class="ipa-gov-select" value="' + id + '"></td>');
                tr.data('phase207-decorated', 1);
            } else {
                var td = tr.find('td').first();
                if (td.length && parseInt(td.attr('colspan'), 10) === 7) td.attr('colspan', 8);
                tr.data('phase207-decorated', 1);
            }
        });
    };

    var loadFailures = function () {
        Fast.api.ajax({url: 'ipa_center/governance_failures', type: 'GET', loading: false, data: {limit: 100}}, function (data) {
            var rows = data.rows || [], stats = data.stats || {};
            $('#ipa-gov-failed-count').text(stats.failed || 0);
            $('#ipa-gov-interrupted-count').text(stats.interrupted || 0);
            var body = $('#ipa-governance-failure-table tbody');
            body.empty();
            if (!rows.length) {
                body.append('<tr><td colspan="9" class="text-center text-muted">当前没有 failed / interrupted 治理操作</td></tr>');
                return false;
            }
            $.each(rows, function (_, r) {
                var label = r.state === 'interrupted' ? 'warning' : 'danger';
                body.append('<tr>' +
                    '<td><code>' + html(r.operation_id || '') + '</code>' + (r.retry_of ? '<br><small>retry_of: ' + html(r.retry_of) + '</small>' : '') + '</td>' +
                    '<td>#' + html(r.issue_id || 0) + '</td>' +
                    '<td><span class="label label-' + label + '">' + html(r.state || '') + '</span></td>' +
                    '<td>' + html(r.operation_type || '') + '<br><small>' + html(r.mode || '') + '</small></td>' +
                    '<td>#' + html(r.category_id || 0) + ' / #' + html(r.metadata_id || 0) + '</td>' +
                    '<td style="max-width:320px;word-break:break-all" class="text-danger">' + html(r.error_message || '') + '</td>' +
                    '<td>' + html(r.started_at_text || '') + '</td>' +
                    '<td>' + html(r.finished_at_text || '') + '</td>' +
                    '<td><button type="button" class="btn btn-xs btn-warning btn-ipa-governance-retry" data-operation="' + html(r.operation_id || '') + '">重新预览并重试</button></td>' +
                    '</tr>');
            });
            return false;
        }, function () { return false; });
    };

    return function () {
        var table = document.getElementById('ipa-governance-table');
        if (table && window.MutationObserver) {
            new MutationObserver(decorateGovernanceRows).observe(table.querySelector('tbody'), {childList: true});
        }
        decorateGovernanceRows();

        $('#ipa-gov-select-all').on('change', function () {
            $('#ipa-governance-table .ipa-gov-select').prop('checked', $(this).is(':checked'));
        });

        $('.btn-ipa-governance-batch').on('click', function () {
            var ids = selectedIds();
            if (!ids.length) {
                Toastr.warning('请先勾选需要批量治理的异常');
                return;
            }
            Fast.api.ajax({
                url: 'ipa_center/governance_batch_preview',
                data: {issue_ids_json: JSON.stringify(ids)}
            }, function (data) {
                var text = '已选择：' + data.selected_count + '\n' +
                    '可安全执行：' + data.applicable_count + '\n' +
                    '跳过：' + data.skipped_count + '\n' +
                    'batch_hash：' + data.batch_hash + '\n\n' +
                    '批量模式不会自动移动 OpenList 文件；路径异常仅允许以 OpenList 当前地址更新数据库。';
                Layer.confirm('<pre style="white-space:pre-wrap">' + html(text) + '</pre><p class="text-danger">执行前会重新生成计划；任何计划漂移都会拒绝整个批次。</p>', {title: 'Phase 20.7 批量治理预览'}, function (index) {
                    Layer.close(index);
                    Fast.api.ajax({
                        url: 'ipa_center/governance_batch_apply',
                        data: {issue_ids_json: JSON.stringify(ids), batch_hash: data.batch_hash}
                    }, function (result) {
                        Toastr.success('成功 ' + (result.success_count || 0) + '，失败 ' + (result.failed_count || 0) + '，跳过 ' + (result.skipped_count || 0));
                        $('.btn-ipa-governance-refresh').trigger('click');
                        loadFailures();
                        return false;
                    });
                });
                return false;
            });
        });

        $('.btn-ipa-governance-scan-interrupted').on('click', function () {
            Fast.api.ajax({url: 'ipa_recovery/scan_interrupted', data: {stale_seconds: 300}}, function (data) {
                Toastr.success('已标记中断操作：' + (data.interrupted || 0));
                loadFailures();
                return false;
            });
        });

        $('#ipa-governance-failure-table').on('click', '.btn-ipa-governance-retry', function () {
            var operationId = $(this).data('operation');
            Layer.confirm('重试不会复用旧 plan；系统会根据当前数据重新 preview、执行并 verify。确认继续？', {title: '治理恢复'}, function (index) {
                Layer.close(index);
                Fast.api.ajax({url: 'ipa_recovery/retry', data: {operation_id: operationId}}, function () {
                    $('.btn-ipa-governance-refresh').trigger('click');
                    loadFailures();
                    return false;
                });
            });
        });

        $('.btn-ipa-governance-failures-refresh').on('click', loadFailures);
        loadFailures();
    };
});
