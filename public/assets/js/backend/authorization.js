define(['jquery', 'bootstrap', 'backend'], function ($, undefined, Backend) {
    var Controller = {
        index: function () {
        },
        transfers: function () {
            Controller.api.bindClear();
        },
        events: function () {
            Controller.api.bindClear();
        },
        diagnostic: function () {
        },
        api: {
            bindClear: function () {
                $(document)
                    .off('click.authorization-clear', '.btn-clear-auth-log')
                    .on('click.authorization-clear', '.btn-clear-auth-log', function () {
                        var button = $(this);
                        if (button.hasClass('disabled')) {
                            return false;
                        }

                        var message = button.data('confirm') || '确定执行清空操作吗？';
                        Layer.confirm(message, function (index) {
                            Layer.close(index);
                            button.addClass('disabled').attr('aria-disabled', 'true');

                            Backend.api.ajax({
                                url: button.data('url'),
                                data: {clear: 1},
                                complete: function () {
                                    button.removeClass('disabled').removeAttr('aria-disabled');
                                }
                            }, function () {
                                Controller.api.emptyRows(button);
                                Controller.api.invalidateOverview();
                            });
                        });
                        return false;
                    });
            },
            emptyRows: function (button) {
                var target = $(button.data('target'));
                if (!target.length) {
                    return;
                }
                var colspan = parseInt(button.data('empty-colspan'), 10) || 1;
                var text = button.data('empty-text') || '暂无记录';
                target.html('<tr><td colspan="' + colspan + '" class="text-muted text-center"></td></tr>');
                target.find('td').text(text);
            },
            invalidateOverview: function () {
                // FastAdmin keeps opened menu pages in iframe tabs. Closing the
                // overview tab invalidates its stale DOM; the next click opens
                // it from the authoritative database state immediately.
                Backend.api.closetabs('authorization/index');
            }
        }
    };
    return Controller;
});
