define(['jquery', 'bootstrap', 'toastr', 'layer', 'lang'], function ($, undefined, Toastr, Layer, Lang) {
    var Fast = {
        config: {
            toastr: {
                "closeButton": true,"debug": false,"newestOnTop": false,"progressBar": false,"positionClass": "toast-top-center","preventDuplicates": false,"onclick": null,"showDuration": "300","hideDuration": "1000","timeOut": "5000","extendedTimeOut": "1000","showEasing": "swing","hideEasing": "linear","showMethod": "fadeIn","hideMethod": "fadeOut"
            }
        },
        events: {
            onAjaxSuccess: function (ret, onAjaxSuccess) {
                var data = typeof ret.data !== 'undefined' ? ret.data : null;
                var msg = typeof ret.msg !== 'undefined' && ret.msg ? ret.msg : __('Operation completed');
                if (typeof onAjaxSuccess === 'function') {
                    var result = onAjaxSuccess.call(this, data, ret);
                    if (result === false) return;
                }
                Toastr.success(msg);
            },
            onAjaxError: function (ret, onAjaxError) {
                var data = typeof ret.data !== 'undefined' ? ret.data : null;
                if (typeof onAjaxError === 'function') {
                    var result = onAjaxError.call(this, data, ret);
                    if (result === false) return;
                }
                Toastr.error(ret.msg);
            },
            onAjaxResponse: function (response) {
                try {
                    var ret = typeof response === 'object' ? response : JSON.parse(response);
                    if (!ret.hasOwnProperty('code')) $.extend(ret, {code: -2, msg: response, data: null});
                } catch (e) {
                    var ret = {code: -1, msg: e.message, data: null};
                }
                return ret;
            }
        },
        api: {
            ajax: function (options, success, error) {
                options = typeof options === 'string' ? {url: options} : options;
                var index;
                if (typeof options.loading === 'undefined' || options.loading) index = Layer.load(options.loading || 0);
                options = $.extend({
                    type: "POST",
                    dataType: "json",
                    success: function (ret) {
                        index && Layer.close(index);
                        ret = Fast.events.onAjaxResponse(ret);
                        if (ret.code === 1) Fast.events.onAjaxSuccess(ret, success);
                        else Fast.events.onAjaxError(ret, error);
                    },
                    error: function (xhr) {
                        index && Layer.close(index);
                        var msg = xhr.statusText || ('HTTP ' + xhr.status);
                        var data = null;
                        if (xhr.responseJSON && typeof xhr.responseJSON === 'object') {
                            if (xhr.responseJSON.msg) msg = xhr.responseJSON.msg;
                            if (typeof xhr.responseJSON.data !== 'undefined') data = xhr.responseJSON.data;
                        } else if (xhr.responseText) {
                            try {
                                var parsed = JSON.parse(xhr.responseText);
                                if (parsed && parsed.msg) msg = parsed.msg;
                                if (parsed && typeof parsed.data !== 'undefined') data = parsed.data;
                            } catch (e) {
                                var text = String(xhr.responseText).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                                if (text) msg = text.substring(0, 500);
                            }
                        }
                        Fast.events.onAjaxError({code: xhr.status, msg: msg, data: data}, error);
                    }
                }, options);
                return $.ajax(options);
            },
            fixurl: function (url) {
                if (url.substr(0, 1) !== "/") {
                    var r = new RegExp('^(?:[a-z]+:)?//', 'i');
                    if (!r.test(url)) url = Config.moduleurl + "/" + url;
                } else if (url.substr(0, 8) === "/addons/") {
                    url = Config.__PUBLIC__.replace(/(\/*$)/g, "") + url;
                }
                return url;
            },
            cdnurl: function (url, domain) {
                var rule = new RegExp("^((?:[a-z]+:)?\\/\\/|data:image\\/)", "i");
                var url = rule.test(url) ? url : Config.upload.cdnurl + url;
                if (domain && !rule.test(url)) {
                    domain = typeof domain === 'string' ? domain : location.origin;
                    url = domain + url;
                }
                return url;
            },
            query: function (name, url) {
                if (!url) url = window.location.href;
                name = name.replace(/[\[\]]/g, "\\$&");
                var regex = new RegExp("[?&/]" + name + "([=/]([^&#/?]*)|&|#|$)"), results = regex.exec(url);
                if (!results) return null;
                if (!results[2]) return '';
                return decodeURIComponent(results[2].replace(/\+/g, " "));
            },
            open: function (url, title, options) {
                title = options && options.title ? options.title : (title ? title : "");
                url = Fast.api.fixurl(url);
                url = url + (url.indexOf("?") > -1 ? "&" : "?") + "dialog=1";
                var area = Fast.config.openArea != undefined ? Fast.config.openArea : [$(window).width() > 800 ? '800px' : '95%', $(window).height() > 600 ? '600px' : '95%'];
                options = $.extend({
                    type: 2,title: title,shadeClose: true,shade: false,maxmin: true,moveOut: true,area: area,content: url,zIndex: Layer.zIndex,
                    success: function (layero, index) {
                        var that = this;$(layero).data("callback", that.callback);Layer.setTop(layero);
                        try {
                            var frame = Layer.getChildFrame('html', index);var layerfooter = frame.find(".layer-footer");Fast.api.layerfooter(layero, index, that);
                            if (layerfooter.size() > 0) {
                                var MutationObserver = window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver;
                                if (MutationObserver) {
                                    var target = layerfooter[0];var observer = new MutationObserver(function (mutations) {Fast.api.layerfooter(layero, index, that);mutations.forEach(function () {});});
                                    observer.observe(target, {attributes: true, childList: true, characterData: true, subtree: true});
                                }
                            }
                        } catch (e) {}
                        if ($(layero).height() > $(window).height()) Layer.style(index, {top: 0,height: $(window).height()});
                    }
                }, options ? options : {});
                if ($(window).width() < 480 || (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream && top.$(".tab-pane.active").size() > 0)) {
                    options.area = [top.$(".tab-pane.active").width() + "px", top.$(".tab-pane.active").height() + "px"];
                    options.offset = [top.$(".tab-pane.active").scrollTop() + "px", "0px"];
                }
                return Layer.open(options);
            },
            close: function (data) {
                var index = parent.Layer.getFrameIndex(window.name);var callback = parent.$("#layui-layer" + index).data("callback");parent.Layer.close(index);
                if (typeof callback === 'function') callback.call(undefined, data);
            },
            layerfooter: function (layero, index, that) {
                var frame = Layer.getChildFrame('html', index);var layerfooter = frame.find(".layer-footer");
                if (layerfooter.size() > 0) {
                    $(".layui-layer-footer", layero).remove();var footer = $("<div />").addClass('layui-layer-btn layui-layer-footer');footer.html(layerfooter.html());
                    if ($(".row", footer).size() === 0) $(">", footer).wrapAll("<div class='row'></div>");
                    footer.insertAfter(layero.find('.layui-layer-content'));
                    footer.on("click", ".btn", function () {
                        if ($(this).hasClass("disabled") || $(this).parent().hasClass("disabled")) return;
                        var index = footer.find('.btn').index(this);$(".btn:eq(" + index + ")", layerfooter).trigger("click");
                    });
                    var titHeight = layero.find('.layui-layer-title').outerHeight() || 0;var btnHeight = layero.find('.layui-layer-btn').outerHeight() || 0;$("iframe", layero).height(layero.height() - titHeight - btnHeight);
                }
                if (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream) {
                    var titHeight = layero.find('.layui-layer-title').outerHeight() || 0;var btnHeight = layero.find('.layui-layer-btn').outerHeight() || 0;$("iframe", layero).parent().css("height", layero.height() - titHeight - btnHeight);$("iframe", layero).css("height", "100%");
                }
            },
            success: function (options, callback) {
                var type = typeof options === 'function';if (type) callback = options;
                return Layer.msg(__('Operation completed'), $.extend({offset: 0, icon: 1}, type ? {} : options), callback);
            },
            error: function (options, callback) {
                var type = typeof options === 'function';if (type) callback = options;
                return Layer.msg(__('Operation failed'), $.extend({offset: 0, icon: 2}, type ? {} : options), callback);
            },
            msg: function (message, url) {
                var callback = typeof url === 'function' ? url : function () {if (typeof url !== 'undefined' && url) location.href = url;};
                Layer.msg(message, {time: 2000}, callback);
            },
            toastr: Toastr,layer: Layer
        },
        lang: function () {
            var args = arguments,string = args[0],i = 1;string = string.toLowerCase();
            if (typeof Lang !== 'undefined' && typeof Lang[string] !== 'undefined') {
                if (typeof Lang[string] == 'object') return Lang[string];string = Lang[string];
            } else if (string.indexOf('.') !== -1 && false) {
                var arr = string.split('.');var current = Lang[arr[0]];
                for (var i = 1; i < arr.length; i++) {current = typeof current[arr[i]] != 'undefined' ? current[arr[i]] : '';if (typeof current != 'object') break;}
                if (typeof current == 'object') return current;string = current;
            } else string = args[0];
            return string.replace(/%((%)|s|d)/g, function (m) {var val = null;if (m[2]) val = m[2];else {val = args[i];if (m === '%d') {val = parseFloat(val);if (isNaN(val)) val = 0;}i++;}return val;});
        },
        init: function () {
            $.ajaxSetup({beforeSend: function (xhr, setting) {setting.url = Fast.api.fixurl(setting.url);}});
            Layer.config({skin: 'layui-layer-fast'});
            $(window).keyup(function (e) {if (e.keyCode == 27) {if ($(".layui-layer").size() > 0) {var index = 0;$(".layui-layer").each(function () {index = Math.max(index, parseInt($(this).attr("times")));});if (index) Layer.close(index);}}});
            Toastr.options = Fast.config.toastr;
        }
    };
    window.Layer = Layer;window.Toastr = Toastr;window.__ = Fast.lang;window.Fast = Fast;Fast.init();return Fast;
});
