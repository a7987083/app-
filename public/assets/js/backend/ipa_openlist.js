define(['jquery','bootstrap','backend','form'], function ($, undefined, Backend, Form) {
    var Controller = {
        index: function () {
            var $form = $('#openlist-form');
            var $result = $('#openlist-test-result');

            function showResult(ok, message) {
                $result.removeClass('hide alert-success alert-danger')
                    .addClass(ok ? 'alert-success' : 'alert-danger')
                    .html((ok ? '<i class="fa fa-check-circle"></i> ' : '<i class="fa fa-times-circle"></i> ') + message);
            }

            function resetForm() {
                $form.find('[name=id]').val('0');
                $form.find('[name=base_url]').val('');
                $form.find('[name=token]').val('');
                $form.find('[name=root_path]').val('/');
                $form.find('[name=request_timeout]').val('20');
                $('#openlist-save-label').text('保存配置');
                $('#openlist-edit-cancel').addClass('hide');
                $result.addClass('hide').empty();
            }

            Form.api.bindevent($form, function (data, ret) {
                parent.Toastr.success(ret && ret.msg ? ret.msg : 'OpenList 连接已保存');
                window.location.reload();
                return false;
            });

            $('#openlist-test').on('click', function () {
                var payload = {
                    id: $form.find('[name=id]').val(),
                    base_url: $form.find('[name=base_url]').val(),
                    token: $form.find('[name=token]').val(),
                    root_path: $form.find('[name=root_path]').val(),
                    request_timeout: $form.find('[name=request_timeout]').val()
                };
                $result.addClass('hide').empty();
                Fast.api.ajax({
                    url: 'ipa_openlist/test',
                    type: 'POST',
                    data: payload
                }, function (data, ret) {
                    var msg = ret && ret.msg ? ret.msg : 'OpenList Token 验证成功';
                    if (data && data.root_path) {
                        msg += '；目录：' + $('<div>').text(data.root_path).html();
                    }
                    if (data && data.total !== null && typeof data.total !== 'undefined') {
                        msg += '；条目：' + parseInt(data.total, 10);
                    }
                    showResult(true, msg);
                    return false;
                }, function (ret) {
                    showResult(false, ret && ret.msg ? $('<div>').text(ret.msg).html() : 'OpenList 连接失败');
                    return false;
                });
            });

            $(document).on('click', '.btn-openlist-edit', function () {
                var $btn = $(this);
                $form.find('[name=id]').val($btn.attr('data-id') || '0');
                $form.find('[name=base_url]').val($btn.attr('data-base-url') || '');
                $form.find('[name=token]').val('');
                $form.find('[name=root_path]').val($btn.attr('data-root-path') || '/');
                $form.find('[name=request_timeout]').val($btn.attr('data-request-timeout') || '20');
                $('#openlist-save-label').text('保存修改');
                $('#openlist-edit-cancel').removeClass('hide');
                $result.addClass('hide').empty();
                $('html,body').animate({scrollTop: $form.offset().top - 80}, 150);
            });

            $('#openlist-edit-cancel').on('click', resetForm);
        }
    };
    return Controller;
});
