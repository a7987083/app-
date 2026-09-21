define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'ipa_center/index'
                }
            });

            $('#job-table').bootstrapTable({
                url: 'ipa_center/jobs',
                sidePagination: 'server',
                pagination: true,
                pageSize: 50,
                pageList: [20, 50, 100, 200],
                sortName: 'id',
                sortOrder: 'desc',
                columns: [[
                    {field: 'id', title: 'ID'},
                    {field: 'source_id', title: 'Source'},
                    {field: 'mode', title: '模式'},
                    {field: 'status', title: '状态'},
                    {field: 'discovered_count', title: '发现'},
                    {field: 'processed_count', title: '完成'},
                    {field: 'failed_count', title: '失败'},
                    {field: 'worker_id', title: 'Worker'},
                    {field: 'created_at', title: '创建时间', formatter: Table.api.formatter.datetime}
                ]]
            });

            $('#asset-table').bootstrapTable({
                url: 'ipa_center/assets',
                sidePagination: 'server',
                pagination: true,
                search: true,
                pageSize: 100,
                pageList: [50, 100, 200, 500],
                sortName: 'id',
                sortOrder: 'desc',
                columns: [[
                    {field: 'id', title: 'ID'},
                    {field: 'name', title: 'IPA'},
                    {field: 'bundle_id', title: 'Bundle ID'},
                    {field: 'app_name', title: 'App'},
                    {field: 'app_version', title: 'Version'},
                    {field: 'build_version', title: 'Build'},
                    {field: 'size_bytes', title: '大小(B)'},
                    {field: 'status', title: '状态'},
                    {field: 'last_seen_at', title: '最后发现', formatter: Table.api.formatter.datetime},
                    {
                        field: 'operate',
                        title: '操作',
                        formatter: function (value, row) {
                            var disabled = row.status === 'parsed' ? '' : ' disabled';
                            return '<button type="button" class="btn btn-xs btn-primary btn-writeback' + disabled + '" data-id="' + row.id + '">手动写回</button>';
                        }
                    }
                ]]
            });

            $('#source-form').on('submit', function (e) {
                e.preventDefault();
                Fast.api.ajax({url: 'ipa_center/saveSource', type: 'POST', data: $(this).serialize()}, function () {
                    location.reload();
                    return false;
                });
            });

            $(document).on('click', '.btn-scan', function () {
                var button = $(this);
                Fast.api.ajax({
                    url: 'ipa_center/startScan',
                    type: 'POST',
                    data: {source_id: button.data('id'), mode: button.data('mode')}
                }, function () {
                    $('#job-table').bootstrapTable('refresh');
                    return false;
                });
            });

            var selectedCategoryId = 0;
            $(document).on('click', '.btn-writeback:not(.disabled)', function () {
                selectedCategoryId = 0;
                $('#wb-asset-id').val($(this).data('id'));
                $('#wb-category-search').val('');
                $('#wb-category-results').empty();
                $('#wb-preview').addClass('hide');
                $('#wb-diff-body').empty();
                $('#wb-warnings').empty();
                $('#wb-apply-btn').addClass('disabled');
                $('#writeback-modal').modal('show');
            });

            $('#wb-search-btn').on('click', function () {
                $.getJSON('ipa_center/categorySearch', {q: $('#wb-category-search').val()}, function (resp) {
                    var rows = resp && resp.rows ? resp.rows : [];
                    var html = '<div class="list-group">';
                    $.each(rows, function (_, row) {
                        html += '<button type="button" class="list-group-item wb-category" data-id="' + row.id + '">' +
                            '<strong>#' + row.id + ' ' + Controller.api.escape(row.name) + '</strong>' +
                            '<span class="text-muted">　版本 ' + Controller.api.escape(row.nickname || '') + '</span>' +
                            '</button>';
                    });
                    html += '</div>';
                    $('#wb-category-results').html(rows.length ? html : '<div class="alert alert-warning">未找到项目</div>');
                });
            });

            $('#wb-category-search').on('keypress', function (e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#wb-search-btn').click();
                }
            });

            $(document).on('click', '.wb-category', function () {
                selectedCategoryId = parseInt($(this).data('id'), 10) || 0;
                $('.wb-category').removeClass('active');
                $(this).addClass('active');
                Controller.api.loadWritebackPreview(parseInt($('#wb-asset-id').val(), 10) || 0, selectedCategoryId);
            });

            $('#wb-apply-btn').on('click', function () {
                if ($(this).hasClass('disabled') || !selectedCategoryId) {
                    return;
                }
                var fields = [];
                $('#wb-diff-body input[name="wb_fields[]"]:checked').each(function () {
                    fields.push($(this).val());
                });
                if (!fields.length) {
                    Toastr.warning('请至少选择一个写回字段');
                    return;
                }
                Fast.api.ajax({
                    url: 'ipa_center/writebackApply',
                    type: 'POST',
                    data: {
                        asset_id: parseInt($('#wb-asset-id').val(), 10) || 0,
                        category_id: selectedCategoryId,
                        fields: fields
                    }
                }, function () {
                    $('#writeback-modal').modal('hide');
                    Toastr.success('写回完成');
                    return false;
                });
            });
        },
        api: {
            escape: function (value) {
                return $('<div>').text(value === null || typeof value === 'undefined' ? '' : String(value)).html();
            },
            loadWritebackPreview: function (assetId, categoryId) {
                $.getJSON('ipa_center/writebackPreview', {asset_id: assetId, category_id: categoryId}, function (resp) {
                    if (!resp || parseInt(resp.code, 10) !== 1) {
                        Toastr.error(resp && resp.msg ? resp.msg : '预览失败');
                        return;
                    }
                    var data = resp.data;
                    var labels = {name: '应用名称', nickname: '版本号', bt1a: '安装包地址', bt2a: '文件大小(Byte)'};
                    var body = '';
                    $.each(['name', 'nickname', 'bt1a', 'bt2a'], function (_, field) {
                        var changed = !!data.diff[field];
                        body += '<tr class="' + (changed ? 'warning' : '') + '">' +
                            '<td><input type="checkbox" name="wb_fields[]" value="' + field + '" ' + (changed ? 'checked' : '') + '></td>' +
                            '<td>' + labels[field] + '</td>' +
                            '<td style="word-break:break-all">' + Controller.api.escape(data.current[field]) + '</td>' +
                            '<td style="word-break:break-all">' + Controller.api.escape(data.proposed[field]) + '</td>' +
                            '</tr>';
                    });
                    $('#wb-diff-body').html(body);
                    var warnings = data.warnings || [];
                    if (warnings.length) {
                        $('#wb-warnings').html('<div class="alert alert-warning">' + Controller.api.escape(warnings.join('；')) + '</div>');
                    } else {
                        $('#wb-warnings').empty();
                    }
                    $('#wb-preview').removeClass('hide');
                    $('#wb-apply-btn').removeClass('disabled');
                });
            }
        }
    };
    return Controller;
});
