define(['jquery', 'bootstrap', 'backend'], function ($, undefined, Backend) {
    var state = {base: null, preview: null, files: []};

    function esc(v) { return $('<div/>').text(v == null ? '' : String(v)).html(); }
    function url(path) { return Fast.api.fixurl(path); }

    function panelHtml() {
        return ''
            + '<div class="panel panel-success" id="section-codegen">'
            + '  <div class="panel-heading"><strong>OC 接入代码</strong> <span class="text-muted">自动读取 Dylib、版本、Bootstrap、API 和验证密钥</span></div>'
            + '  <div class="panel-body">'
            + '    <div class="row">'
            + '      <div class="col-sm-5 form-group"><label>目标 Dylib</label><select id="dcg-dylib" class="form-control"><option value="">请选择 Dylib</option></select></div>'
            + '      <div class="col-sm-5 form-group"><label>版本</label><select id="dcg-version" class="form-control"><option value="">先选择 Dylib</option></select></div>'
            + '      <div class="col-sm-2 form-group"><label>&nbsp;</label><button type="button" class="btn btn-default btn-block" data-toggle="collapse" data-target="#dcg-options"><i class="fa fa-sliders"></i> 选项</button></div>'
            + '    </div>'
            + '    <div id="dcg-options" class="collapse">'
            + '      <div class="well well-sm" style="margin-bottom:12px">'
            + '        <div class="row" style="margin-bottom:-15px">'
            + '          <div class="col-sm-4 form-group"><label>类前缀</label><input id="dcg-prefix" class="form-control" value="ZON" maxlength="8"></div>'
            + '          <div class="col-sm-4 form-group"><label>最低 iOS</label><input id="dcg-ios" class="form-control" value="13.0"></div>'
            + '          <div class="col-sm-4 form-group"><label>超时（秒）</label><input id="dcg-timeout" type="number" min="3" max="120" class="form-control" value="10"></div>'
            + '        </div>'
            + '      </div>'
            + '    </div>'
            + '    <div id="dcg-summary" class="well well-sm text-muted">请选择一个已注册 Dylib。</div>'
            + '    <div class="form-group" style="margin-bottom:8px">'
            + '      <button type="button" id="dcg-preview" class="btn btn-primary"><i class="fa fa-eye"></i> 预览</button> '
            + '      <button type="button" id="dcg-generate" class="btn btn-success" disabled><i class="fa fa-download"></i> 生成并下载 ZIP</button> '
            + '      <span id="dcg-status" class="text-muted" style="margin-left:10px">尚未预览</span>'
            + '    </div>'
            + '    <div class="alert alert-warning" style="padding:8px 12px;margin-bottom:10px"><strong>安全：</strong><code>*DylibConfig.m</code> 会包含客户端验证密钥，只用于受控工程；不要提交到公开仓库。</div>'
            + '    <div id="dcg-validation"></div>'
            + '    <div id="dcg-preview-area" class="hidden">'
            + '      <div class="row" style="margin:0;border:1px solid #e5e5e5;">'
            + '        <div class="col-sm-3" id="dcg-files" style="padding:0;max-height:420px;overflow:auto;border-right:1px solid #eee;"><div class="text-muted" style="padding:12px">预览后显示文件。</div></div>'
            + '        <div class="col-sm-9" style="padding:10px"><pre id="dcg-code" style="height:400px;overflow:auto;background:#101419;color:#e8edf2;border:0;padding:14px;font-family:Menlo,Monaco,Consolas,monospace;font-size:12px;line-height:1.5;white-space:pre;">// preview</pre></div>'
            + '      </div>'
            + '    </div>'
            + '  </div>'
            + '</div>';
    }

    function installPanel() {
        if ($('#section-codegen').length) return;
        if ($('#codegen-slot').length) $('#codegen-slot').html(panelHtml());
        else $('#section-register').after(panelHtml());

        $('#dylib-list tbody tr').each(function () {
            var $row = $(this);
            $('#dcg-dylib').append('<option value="' + esc($row.data('id')) + '">' + esc($row.attr('data-name')) + ' / ' + esc($row.attr('data-key')) + '</option>');
        });
    }

    function draft() {
        return {
            dylib_id: parseInt($('#dcg-dylib').val() || '0', 10),
            version_id: parseInt($('#dcg-version').val() || '0', 10),
            class_prefix: $('#dcg-prefix').val() || 'ZON',
            deployment_target: $('#dcg-ios').val() || '13.0',
            timeout: parseInt($('#dcg-timeout').val() || '10', 10)
        };
    }

    function markDirty() {
        state.preview = null;
        $('#dcg-generate').prop('disabled', true);
        $('#dcg-status').attr('class', 'text-warning').text('配置已变化，请重新预览');
    }

    function renderValidation(v) {
        v = v || {};
        var html = '';
        if (v.errors && v.errors.length) html += '<div class="alert alert-danger"><b>校验错误</b><br>' + esc(v.errors.join('\n')).replace(/\n/g, '<br>') + '</div>';
        if (v.warnings && v.warnings.length) html += '<div class="alert alert-warning"><b>注意</b><br>' + esc(v.warnings.join('\n')).replace(/\n/g, '<br>') + '</div>';
        $('#dcg-validation').html(html);
    }

    function renderSummary(c) {
        if (!c) { $('#dcg-summary').text('请选择一个已注册 Dylib。'); return; }
        var boot = (c.bootstrap_urls || []).length;
        var apis = (c.api_endpoints || []).length;
        $('#dcg-summary').html(
            '<strong>' + esc(c.dylib_name || '') + '</strong> <code>' + esc(c.dylib_key || '') + '</code>'
            + '　版本 <code>' + esc(c.dylib_version || '未登记') + (c.dylib_build ? ' (' + esc(c.dylib_build) + ')' : '') + '</code>'
            + '　Bootstrap <code>' + boot + '</code>'
            + '　API <code>' + apis + '</code>'
            + '　密钥 ' + (c.verify_secret_present ? '<span class="label label-success">已配置</span>' : '<span class="label label-danger">缺失</span>')
        );
    }

    function renderVersions(rows, selected) {
        var html = '';
        $.each(rows || [], function (_, row) {
            var label = row.version + (row.build ? ' (' + row.build + ')' : '') + ' · ' + row.state;
            html += '<option value="' + esc(row.id) + '" ' + (parseInt(row.id, 10) === parseInt(selected, 10) ? 'selected' : '') + '>' + esc(label) + '</option>';
        });
        $('#dcg-version').html(html || '<option value="">尚未登记版本</option>');
    }

    function clearPreview(message) {
        state.preview = null;
        state.files = [];
        $('#dcg-preview-area').addClass('hidden');
        $('#dcg-files').html('<div class="text-muted" style="padding:12px">预览后显示文件。</div>');
        $('#dcg-code').text('// preview');
        $('#dcg-generate').prop('disabled', true);
        if (message) $('#dcg-status').attr('class', 'text-muted').text(message);
    }

    function loadCurrent(keepVersion) {
        var dylibId = parseInt($('#dcg-dylib').val() || '0', 10);
        if (!dylibId) {
            state.base = null;
            $('#dcg-version').html('<option value="">先选择 Dylib</option>');
            $('#dcg-summary').text('请选择一个已注册 Dylib。');
            $('#dcg-validation').empty();
            clearPreview('尚未预览');
            return;
        }
        var params = draft();
        if (!keepVersion) params.version_id = 0;
        layer.load(1, {shade: 0.05});
        $.getJSON(url('general/occodegen/current'), params, function (ret) {
            layer.closeAll('loading');
            if (!ret || ret.code !== 200 || !ret.data) { layer.alert((ret && ret.msg) || '读取 Dylib 生成配置失败', {icon: 2}); return; }
            state.base = ret.data.config;
            renderVersions(ret.data.versions || [], ret.data.selected_version_id);
            renderSummary(ret.data.config);
            renderValidation(ret.data.validation);
            clearPreview('已读取当前配置，尚未预览');
        }).fail(function () { layer.closeAll('loading'); layer.alert('读取 Dylib 生成配置失败', {icon: 2}); });
    }

    function showFile(index) {
        index = parseInt(index, 10);
        if (!state.files[index]) return;
        $('#dcg-files a').removeClass('active').css({'background':'','font-weight':''});
        $('#dcg-files a[data-index="' + index + '"]').addClass('active').css({'background':'#f0f7ff','font-weight':'600'});
        $('#dcg-code').text(state.files[index].content || '');
    }

    function renderFiles(files) {
        state.files = files || [];
        var html = '';
        $.each(state.files, function (i, f) {
            html += '<a href="javascript:;" data-index="' + i + '" style="display:block;padding:8px 10px;border-bottom:1px solid #f4f4f4;word-break:break-all"><i class="fa fa-file-code-o"></i> ' + esc(f.path) + '</a>';
        });
        $('#dcg-files').html(html || '<div class="text-muted" style="padding:12px">没有生成文件。</div>');
        $('#dcg-preview-area').removeClass('hidden');
        if (state.files.length) showFile(0);
    }

    function preview() {
        var params = draft();
        if (!params.dylib_id) { layer.alert('请先选择 Dylib', {icon: 0}); return; }
        layer.load(1, {shade: 0.05});
        $.ajax({
            type: 'POST', url: url('general/occodegen/preview'), dataType: 'json', data: params,
            success: function (ret) {
                layer.closeAll('loading');
                if (!ret || !ret.data) { layer.alert((ret && ret.msg) || '生成预览失败', {icon: 2}); return; }
                renderSummary(ret.data.config); renderValidation(ret.data.validation); renderFiles(ret.data.files || []);
                if (ret.code === 200 && ret.data.validation && ret.data.validation.valid) {
                    state.preview = {hash: ret.data.config_hash, params: params};
                    $('#dcg-generate').prop('disabled', false);
                    $('#dcg-status').attr('class', 'text-success').text('预览已锁定 · ' + String(ret.data.config_hash || '').substr(0, 12));
                } else {
                    state.preview = null; $('#dcg-generate').prop('disabled', true);
                    $('#dcg-status').attr('class', 'text-danger').text(ret.msg || '配置校验失败');
                }
            },
            error: function () { layer.closeAll('loading'); layer.alert('生成预览请求失败', {icon: 2}); }
        });
    }

    function generate() {
        if (!state.preview) { layer.alert('请先预览并通过校验', {icon: 0}); return; }
        var params = $.extend({}, state.preview.params, {config_hash: state.preview.hash});
        layer.load(1, {shade: 0.05});
        $.ajax({
            type: 'POST', url: url('general/occodegen/generate'), dataType: 'json', data: params,
            success: function (ret) {
                layer.closeAll('loading');
                if (!ret || ret.code !== 200 || !ret.data) { layer.alert((ret && ret.msg) || '生成失败', {icon: 2}); return; }
                $('#dcg-status').attr('class', 'text-success').text('已生成 · SHA256 ' + String(ret.data.sha256 || '').substr(0, 16));
                window.location.href = url(ret.data.download_url);
            },
            error: function () { layer.closeAll('loading'); layer.alert('生成 ZIP 请求失败', {icon: 2}); }
        });
    }

    return {
        init: function () {
            installPanel();
            $(document).off('.zonoeDylibCodegen');
            $(document).on('change.zonoeDylibCodegen', '#dcg-dylib', function () { loadCurrent(false); });
            $(document).on('change.zonoeDylibCodegen', '#dcg-version', function () { loadCurrent(true); });
            $(document).on('input.zonoeDylibCodegen change.zonoeDylibCodegen', '#dcg-prefix,#dcg-ios,#dcg-timeout', markDirty);
            $(document).on('click.zonoeDylibCodegen', '#dcg-preview', preview);
            $(document).on('click.zonoeDylibCodegen', '#dcg-generate', generate);
            $(document).on('click.zonoeDylibCodegen', '#dcg-files a[data-index]', function () { showFile($(this).data('index')); });
        }
    };
});
