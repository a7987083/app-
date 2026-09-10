define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'category/index',
                    add_url: 'category/add',
                    edit_url: 'category/edit',
                    del_url: 'category/del',
                    multi_url: 'category/multi',
                    dragsort_url: 'ajax/weigh',
                    table: 'category',
                }
            });

            var table = $("#table");
            var currentType = 'all';
            var tableOptions = {
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'id',
                sortName: 'weigh',
                sidePagination: 'server',
                pagination: true,
                pageSize: 1000,
                pageList: [200, 500, 1000],
                commonSearch: false,
                search: true,
                searchAlign: 'left',
                queryParams: function (params) {
                    params.type = currentType;
                    return params;
                },
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'type', title: __('Type'), operate: false, searchList: Config.searchList, formatter: Table.api.formatter.label},
                        {field: 'name', title: __('应用名称'), align: 'left'},
                        {field: 'nickname', title: __('版本号')},
                        {field: 'keywords', title: __('软件说明'), width:'360px', visible: false},
                        {field: 'bt2b', title: '是否付费', searchList: {'0': '免费', '1': '付费解锁'}, formatter: Table.api.formatter.label},
                        {field: 'beizhu', title: __('备注'), visible: false},
                        {field: 'image', title: __('应用图标'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image, visible: false},
                        {field: 'weigh', title: __('Weigh'), visible: false},
                        {field: 'status', title: __('Status'), operate: false, formatter: Table.api.formatter.status},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            };

            table.bootstrapTable(tableOptions);
            $(".fixed-table-toolbar .search input").attr("placeholder", "搜索应用名称");
            Table.api.bindevent(table);

            $('a[data-toggle="tab"]').on('shown.bs.tab', function () {
                currentType = $(this).attr("href").replace('#', '');
                table.bootstrapTable('refresh', {pageNumber: 1});
                return false;
            });
        },
        add: function () {
            Controller.api.bindevent();
            setTimeout(function () {
                $("#c-type").trigger("change");
            }, 100);
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            toPickerValue: function (val) {
                var hex = String(val || "").replace(/^#/, "").trim();
                if (/^[0-9a-fA-F]{3}$/.test(hex)) {
                    hex = hex.split("").map(function (c) {
                        return c + c;
                    }).join("");
                }
                if (!/^[0-9a-fA-F]{6}$/.test(hex)) {
                    hex = "000000";
                }
                return "#" + hex.toLowerCase();
            },
            toStoreValue: function (val) {
                return String(val || "").replace(/^#/, "").toLowerCase();
            },
            initColorPicker: function () {
                var $text = $("#c-bt1b");
                var $picker = $("#c-bt1b-picker");
                if (!$text.length || !$picker.length) {
                    return;
                }
                var syncPickerFromText = function () {
                    $picker.val(Controller.api.toPickerValue($text.val()));
                };
                syncPickerFromText();
                $picker.on("input change", function () {
                    $text.val(Controller.api.toStoreValue($(this).val())).trigger("change");
                });
                $text.on("input blur", function () {
                    var val = String($(this).val() || "").trim();
                    if (!val) {
                        return;
                    }
                    $picker.val(Controller.api.toPickerValue(val));
                    if (val.charAt(0) === "#") {
                        $text.val(Controller.api.toStoreValue(val));
                    }
                });
                $text.closest("form").on("reset", function () {
                    setTimeout(syncPickerFromText, 0);
                });
            },
            bindevent: function () {
                $(document).on("change", "#c-type", function () {
                    $("#c-pid option[data-type='all']").prop("selected", true);
                    $("#c-pid option").removeClass("hide");
                    $("#c-pid option[data-type!='" + $(this).val() + "'][data-type!='all']").addClass("hide");
                    $("#c-pid").data("selectpicker") && $("#c-pid").selectpicker("refresh");
                });
                Controller.api.initColorPicker();
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});