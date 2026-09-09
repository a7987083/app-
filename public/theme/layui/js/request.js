function sendAjax(params) {
    var {
        $ = layui.$,
        layer = layui.layer,
        url = params.url,
        type = 'post',
        data = '',
        successCallBack,
        errorCallBack,
        completeCallBack,
    } = params;
    if (typeof params.successCallBack !== 'undefined') {
        successCallBack = params.successCallBack;
    } else {
        successCallBack = function (response) {
            if (response && response.data && response['data']['data']) {
                const message = response['data']['data'].message || '操作成功';
                layer.msg(message, { icon: 1, time: 3000 });
            } else {
                const message = response['data']['message'] || response;
                layer.msg(message, { icon: 1, time: 3000 });
            }
            return false;
        };
    }
    if (typeof errorCallBack !== 'undefined') {
        errorCallBack = params.errorCallBack;
    } else {
        errorCallBack = function (error) {
            if (error && error.response) {
                const { data } = error.response;
                const message = data.message;
                layer.msg(message, { icon: 2, time: 5000 });
            } else {
                layer.msg(error, { icon: 2, time: 5000 });
            }
            return false;
        };
    }
    if (typeof completeCallBack !== 'undefined') {
        completeCallBack = params.completeCallBack;
    } else {
        completeCallBack = function () {
            layer.closeLast('loading');
        };
    }
    axios({
        method: type,
        url: url,
        data: data,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(function (response) {
        successCallBack(response);
    })
    .catch(function (error) {
        errorCallBack(error);
    })
    .finally(function () {
        completeCallBack();
    });
}