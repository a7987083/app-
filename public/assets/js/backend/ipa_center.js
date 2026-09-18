define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {},
        metadata: function () {},
        binding: function () {},
        governance: function () {
            $('.ipa-governance-card').on('click', function () {
                $('.ipa-governance-card').removeClass('ipa-active');
                $(this).addClass('ipa-active');
            });
        },
        task: function () {},
        writeback: function () {},
        setting: function () {},
        api: {bindevent: function () {Form.api.bindevent($('form'));}}
    };
    return Controller;
});
