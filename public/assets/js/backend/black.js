define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'black/index' + location.search,
                    add_url: 'black/add',
                    edit_url: 'black/edit',
                    del_url: 'black/del',
                    multi_url: 'black/multi',
                    table: 'black',
                }
            });

            var table = $("#table");

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'udid', title: __('UDID设备码')},
                        {field: 'addtime', title: __('添加时间'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'usetime', title: __('使用时间'), operate: 'RANGE', addclass: 'datetimerange', formatter: Controller.api.formatter.useTime},
                        {field: 'endtime', title: __('到期时间'), operate: 'RANGE', addclass: 'datetimerange', formatter: Controller.api.formatter.endTime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
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
            formatter: {
                useTime: function (value, row, index) {
                    if (!value || parseInt(value, 10) === 0) {
                        return '<span class="text-muted">未使用</span>';
                    }
                    return Table.api.formatter.datetime.apply(this, arguments);
                },
                endTime: function (value, row, index) {
                    var endtime = parseInt(value, 10) || 0;
                    if (endtime === 0) {
                        return '<span class="text-success">永久</span>';
                    }
                    var formatted = Table.api.formatter.datetime.apply(this, arguments);
                    if (endtime <= Math.floor(Date.now() / 1000)) {
                        return '<span class="text-muted">已过期</span> ' + formatted;
                    }
                    return formatted;
                }
            },
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
