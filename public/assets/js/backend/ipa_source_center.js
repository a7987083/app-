define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'ipa_source_center/listSources',
                    add_url: 'ipa_source_center/add',
                    edit_url: 'ipa_source_center/edit',
                    del_url: 'ipa_source_center/del',
                    table: 'ipa_software_source'
                }
            });

            var table = $('#mysql-source-table');
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'priority',
                sortOrder: 'desc',
                sidePagination: 'server',
                pagination: false,
                search: false,
                columns: [[
                    {checkbox: true},
                    {field: 'id', title: 'ID'},
                    {field: 'name', title: '名称'},
                    {field: 'slug', title: 'Slug'},
                    {field: 'host', title: '连接', formatter: function (v, r) {
                        return Controller.api.escape(r.host + ':' + r.port + '/' + r.database_name);
                    }},
                    {field: 'table_name', title: '应用表'},
                    {field: 'priority', title: '优先级'},
                    {field: 'enabled', title: '状态', formatter: function (v) {
                        return parseInt(v, 10) === 1 ? '<span class="label label-success">启用</span>' : '<span class="label label-default">停用</span>';
                    }},
                    {field: 'allow_write', title: '写回', formatter: function (v) {
                        return parseInt(v, 10) === 1 ? '<span class="label label-warning">允许</span>' : '<span class="label label-info">只读</span>';
                    }},
                    {
                        field: 'operate',
                        title: __('Operate'),
                        table: table,
                        events: Table.api.events.operate,
                        buttons: [{
                            name: 'test',
                            text: '测试',
                            icon: 'fa fa-plug',
                            classname: 'btn btn-xs btn-success btn-source-test',
                            click: function (e, value, row) {
                                Fast.api.ajax({
                                    url: 'ipa_source_center/testSource',
                                    type: 'POST',
                                    data: {id: row.id}
                                });
                            }
                        }],
                        formatter: Table.api.formatter.operate
                    }
                ]]
            });
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            escape: function (v) {
                return $('<div>').text(v === null || typeof v === 'undefined' ? '' : String(v)).html();
            },
            bindevent: function () {
                Form.api.bindevent($('form[role=form]'));
            }
        }
    };
    return Controller;
});
