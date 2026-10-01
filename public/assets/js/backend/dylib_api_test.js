define(['jquery', 'bootstrap', 'backend', 'form'], function ($, undefined, Backend, Form) {
    var Controller = {
        index: function () {
            function pretty(value) {
                try { return JSON.stringify(value, null, 2); } catch (e) { return String(value); }
            }
            function parseJson(selector) {
                var raw = $.trim($(selector).val() || '');
                if (!raw) return {};
                try { return JSON.parse(raw); }
                catch (e) { Layer.alert('JSON 格式错误：' + e.message, {icon: 0}); return null; }
            }
            function setResult(data) {
                $('#tester-result').text(pretty(data));
                if (data && data.stage) {
                    $('#tester-meta').text(data.stage + ' · ' + (data.elapsed_ms || 0) + ' ms · ' + (data.endpoint || ''));
                } else {
                    $('#tester-meta').text('');
                }
            }
            function ajax(url, data, done) {
                Fast.api.ajax({url: url, type: 'POST', data: data}, function (resp) {
                    setResult(resp);
                    if (done) done(resp);
                    return false;
                });
            }
            function syncDylibKey() {
                var key = $('#tester-dylib').val() || '';
                if (!key) return;
                ['#challenge-payload', '#verify-payload'].forEach(function (selector) {
                    var obj = parseJson(selector);
                    if (obj === null) return;
                    obj.dylib_key = key;
                    $(selector).val(pretty(obj));
                });
            }

            $('#tester-dylib').on('change', syncDylibKey);

            $('#btn-config-test').on('click', function () {
                var key = $('#tester-dylib').val() || '';
                if (!key) { Layer.alert('请先选择 Dylib', {icon: 0}); return; }
                ajax('dylib_api_test/configTest', {dylib_key: key});
            });

            $('#btn-challenge-test').on('click', function () {
                var payload = parseJson('#challenge-payload');
                if (payload === null) return;
                ajax('dylib_api_test/challengeTest', {payload: JSON.stringify(payload)}, function (resp) {
                    var out = resp && resp.response ? resp.response : null;
                    if (!out || !out.ok) return;
                    var verify = parseJson('#verify-payload');
                    if (verify === null) return;
                    ['udid', 'dylib_key', 'device_public_key'].forEach(function (field) {
                        if (payload[field] !== undefined) verify[field] = payload[field];
                    });
                    verify.challenge_id = out.challenge_id || '';
                    verify.challenge = out.challenge || '';
                    $('#verify-payload').val(pretty(verify));
                    Toastr.success('Challenge 已生成并写入 Verify JSON；请用对应 P-256 私钥重新签名。');
                });
            });

            $('#btn-canonical-test').on('click', function () {
                var payload = parseJson('#verify-payload');
                if (payload === null) return;
                ajax('dylib_api_test/canonicalTest', {payload: JSON.stringify(payload)});
            });

            $('#btn-verify-test').on('click', function () {
                var payload = parseJson('#verify-payload');
                if (payload === null) return;
                ajax('dylib_api_test/verifyTest', {payload: JSON.stringify(payload)}, function () {
                    Toastr.info('Verify 已执行；bad_request 以及进入正式验证流程后的结果会写入验证记录。');
                });
            });

            syncDylibKey();
        }
    };
    return Controller;
});
