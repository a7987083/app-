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
                    {
                        field: 'status',
                        title: '状态',
                        formatter: function (value, row) {
                            var map = {
                                discovered: '<span class="label label-info">待解析</span>',
                                parsing: '<span class="label label-warning">解析中</span>',
                                parsed: '<span class="label label-success">已解析</span>',
                                parse_failed: '<span class="label label-danger">解析失败</span>',
                                missing: '<span class="label label-default">已缺失</span>'
                            };
                            var html = map[value] || Controller.api.escape(value || '');
                            if (value === 'parse_failed' && row.last_error) {
                                html += ' <span class="text-danger" title="' + Controller.api.escape(row.last_error) + '">详情</span>';
                            }
                            return html;
                        }
                    },
                    {field: 'last_seen_at', title: '最后发现', formatter: Table.api.formatter.datetime},
                    {
                        field: 'operate',
                        title: '操作',
                        formatter: function (value, row) {
                            var buttons = [];
                            if (row.status === 'parsed') {
                                buttons.push('<button type="button" class="btn btn-xs btn-primary btn-writeback" data-id="' + row.id + '">手动写回</button>');
                                buttons.push('<button type="button" class="btn btn-xs btn-default btn-parse-retry" data-id="' + row.id + '">重新解析</button>');
                            } else if (row.status === 'parse_failed') {
                                buttons.push('<button type="button" class="btn btn-xs btn-danger btn-parse-retry" data-id="' + row.id + '">重试解析</button>');
                            } else if (row.status === 'discovered') {
                                buttons.push('<span class="text-muted">等待解析 Worker</span>');
                            } else if (row.status === 'parsing') {
                                buttons.push('<span class="text-muted">解析中…</span>');
                            }
                            return buttons.join(' ');
                        }
                    }
                ]]
            });

            function resetSourceForm() {
                var form = $('#source-form');
                form.find('[name="id"]').val('0');
                form.find('[name="name"]').val('');
                form.find('[name="base_url"]').val('');
                form.find('[name="root_path"]').val('/');
                form.find('[name="token"]').val('');
                form.find('[name="enabled"]').val('1');
                form.find('[name="scan_page_size"]').val('500');
                form.find('[name="request_timeout"]').val('20');
                $('#source-save-label').text('保存');
                $('#source-edit-cancel').addClass('hide');
            }

            $('#source-form').on('submit', function (e) {
                e.preventDefault();
                Fast.api.ajax({url: 'ipa_center/saveSource', type: 'POST', data: $(this).serialize()}, function () {
                    location.reload();
                    return false;
                });
            });

            $(document).on('click', '.btn-source-edit', function () {
                var button = $(this);
                var form = $('#source-form');
                form.find('[name="id"]').val(button.data('id'));
                form.find('[name="name"]').val(button.attr('data-name') || '');
                form.find('[name="base_url"]').val(button.attr('data-base-url') || '');
                form.find('[name="root_path"]').val(button.attr('data-root-path') || '/');
                form.find('[name="token"]').val('');
                form.find('[name="enabled"]').val(String(button.attr('data-enabled')));
                form.find('[name="scan_page_size"]').val(button.attr('data-scan-page-size') || '500');
                form.find('[name="request_timeout"]').val(button.attr('data-request-timeout') || '20');
                $('#source-save-label').text('保存修改');
                $('#source-edit-cancel').removeClass('hide');
                $('html,body').animate({scrollTop: form.offset().top - 80}, 150);
            });

            $('#source-edit-cancel').on('click', function () {
                resetSourceForm();
            });

            $(document).on('click', '.btn-source-delete', function () {
                var button = $(this);
                var id = parseInt(button.data('id'), 10) || 0;
                var name = button.attr('data-name') || ('#' + id);
                Layer.confirm('确定删除 OpenList 数据源“' + Controller.api.escape(name) + '”吗？\n该源的扫描任务、IPA 资产、Mach-O 索引和 IPA 绑定记录会一并删除；fa_category 不会被删除。', {title: '删除数据源'}, function (index) {
                    Layer.close(index);
                    Fast.api.ajax({url: 'ipa_center/deleteSource', type: 'POST', data: {id: id}}, function () {
                        location.reload();
                        return false;
                    });
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

            $(document).on('click', '.btn-parse-retry', function () {
                var button = $(this);
                Fast.api.ajax({
                    url: 'ipa_center/retryParse',
                    type: 'POST',
                    data: {asset_id: button.data('id')}
                }, function () {
                    $('#asset-table').bootstrapTable('refresh');
                    return false;
                });
            });

            var selectedCategoryId = 0;
            $(document).on('click', '.btn-writeback', function () {
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
