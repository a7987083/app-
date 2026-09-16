define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'kami/index' + location.search,
                    add_url: 'kami/add',
                    edit_url: 'kami/edit',
                    del_url: 'kami/del',
                    multi_url: 'kami/multi',
                    table: 'kami',
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
                        {field: 'kami', title: __('Kami')},
                        {field: 'udid', title: __('Udid')},
                        {field: 'kmyp', title: __('Kmyp'), searchList: {1: '月卡', 2: '季卡', 3: '年卡', 4: '日卡', 5: '周卡'}, formatter: Table.api.formatter.flag},
                        {field: 'card_scope', title: '卡密用途', searchList: {1: '全软件源', 2: '仅验证', 3: '指定App'}, formatter: function (value) {
                            var labels = {1: '<span class="label label-success">全软件源</span>', 2: '<span class="label label-default">仅验证</span>', 3: '<span class="label label-info">指定App</span>'};
                            return labels[parseInt(value || 1, 10)] || labels[1];
                        }},
                        {field: 'jh', title: __('Jh'), searchList: {0: '未激活', 1: '已激活'}, formatter: Table.api.formatter.label},
                        {field: 'transfer_count', title: '换绑次数', operate: false},
                        {field: 'addtime', title: __('Addtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'usetime', title: __('Usetime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'endtime', title: __('Endtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
            Controller.api.bindScopeControls();
        },
        edit: function () {
            Controller.api.bindevent();
            Controller.api.bindScopeControls();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            bindScopeControls: function () {
                var refresh = function () {
                    var scope = parseInt($("input[name='row[card_scope]']:checked").val() || '1', 10);
                    var box = $('#card-app-targets');
                    if (scope === 3) {
                        box.show();
                    } else {
                        box.hide();
                    }
                    var picker = box.find('.selectpicker');
                    if (picker.length && $.fn.selectpicker) {
                        picker.selectpicker('refresh');
                    }
                };
                $(document).off('change.cardScope', "input[name='row[card_scope]']").on('change.cardScope', "input[name='row[card_scope]']", refresh);
                refresh();
            }
        }
    };
    return Controller;
});