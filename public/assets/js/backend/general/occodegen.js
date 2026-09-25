define(['jquery', 'bootstrap', 'backend'], function ($, undefined, Backend) {
    var state = {base:null, preview:null, files:[]};
    function esc(v){ return $('<div/>').text(v == null ? '' : String(v)).html(); }
    function markDirty(){ state.preview = null; $('#cg-generate').prop('disabled', true); $('#cg-status').attr('class','zonoe-codegen-status text-warning').text('配置已修改，请重新预览'); }
    function renderEndpoints(rows){
        var html = '';
        $.each(rows || [], function(i,row){
            html += '<tr data-index="'+i+'">'
                + '<td class="text-center"><input class="cg-enabled" type="checkbox" '+(row.enabled ? 'checked' : '')+'></td>'
                + '<td><code class="cg-key">'+esc(row.key || '')+'</code></td>'
                + '<td><input class="form-control input-sm cg-name" value="'+esc(row.name || '')+'"></td>'
                + '<td><input class="form-control input-sm cg-path" value="'+esc(row.path || '')+'"></td>'
                + '<td><select class="form-control input-sm cg-method"><option value="GET" '+(row.method === 'GET' ? 'selected' : '')+'>GET</option><option value="POST" '+(row.method === 'POST' ? 'selected' : '')+'>POST</option></select></td>'
                + '<td><input class="form-control input-sm cg-auth" value="'+esc(row.auth || '')+'"></td>'
                + '<td><input class="form-control input-sm cg-description" value="'+esc(row.description || '')+'"></td>'
                + '</tr>';
        });
        $('#cg-endpoints').html(html || '<tr><td colspan="7" class="text-muted">当前没有接口。</td></tr>');
    }
    function loadConfig(){
        layer.load(1,{shade:0.05});
        $.getJSON('general/occodegen/current', function(ret){
            layer.closeAll('loading');
            if (!ret || ret.code !== 200 || !ret.data || !ret.data.config){ layer.alert((ret && ret.msg) || '读取当前配置失败',{icon:2}); return; }
            state.base = ret.data.config;
            var c = state.base;
            $('#cg-project').val(c.project_name || ''); $('#cg-prefix').val(c.class_prefix || ''); $('#cg-base-url').val(c.base_url || '');
            $('#cg-ios').val(c.deployment_target || '13.0'); $('#cg-timeout').val(c.timeout || 15); $('#cg-user-agent').val(c.user_agent || '');
            $('#cg-include-disabled').prop('checked', !!c.include_disabled); renderEndpoints(c.endpoints || []);
            $('#cg-config-json').val(JSON.stringify(c,null,2)); $('#cg-files').html('<div class="text-muted" style="padding:12px;">点击“生成预览”后显示文件。</div>'); $('#cg-code').text('// preview');
            state.preview = null; state.files = []; $('#cg-generate').prop('disabled',true); $('#cg-validation').empty(); $('#cg-status').attr('class','zonoe-codegen-status text-muted').text('已读取当前配置，尚未预览');
        }).fail(function(){ layer.closeAll('loading'); layer.alert('读取当前配置失败',{icon:2}); });
    }
    function collect(){
        var c = $.extend(true, {}, state.base || {});
        c.project_name = $('#cg-project').val(); c.class_prefix = $('#cg-prefix').val(); c.base_url = $('#cg-base-url').val();
        c.deployment_target = $('#cg-ios').val(); c.timeout = parseInt($('#cg-timeout').val() || '15',10); c.user_agent = $('#cg-user-agent').val(); c.include_disabled = $('#cg-include-disabled').is(':checked');
        c.endpoints = [];
        $('#cg-endpoints tr[data-index]').each(function(){
            var idx = parseInt($(this).data('index'),10), base = (state.base && state.base.endpoints && state.base.endpoints[idx]) || {}, $tr=$(this);
            c.endpoints.push({key:$tr.find('.cg-key').text(),name:$tr.find('.cg-name').val(),path:$tr.find('.cg-path').val(),method:$tr.find('.cg-method').val(),auth:$tr.find('.cg-auth').val(),description:$tr.find('.cg-description').val(),enabled:$tr.find('.cg-enabled').is(':checked'),fields:base.fields || []});
        });
        return c;
    }
    function renderValidation(v){
        v = v || {}; var html='';
        if (v.errors && v.errors.length) html += '<div class="alert alert-danger zonoe-codegen-warning"><b>校验错误</b><br>'+esc(v.errors.join('\n')).replace(/\n/g,'<br>')+'</div>';
        if (v.warnings && v.warnings.length) html += '<div class="alert alert-warning zonoe-codegen-warning"><b>注意</b><br>'+esc(v.warnings.join('\n')).replace(/\n/g,'<br>')+'</div>';
        $('#cg-validation').html(html);
    }
    function showFile(index){
        index = parseInt(index,10); if (!state.files[index]) return;
        $('#cg-files a').removeClass('active'); $('#cg-files a[data-index="'+index+'"]').addClass('active'); $('#cg-code').text(state.files[index].content || '');
    }
    function renderFiles(files){
        state.files = files || []; var html='';
        $.each(state.files,function(i,f){ html += '<a href="javascript:;" data-index="'+i+'"><i class="fa fa-file-code-o"></i> '+esc(f.path)+'</a>'; });
        $('#cg-files').html(html || '<div class="text-muted" style="padding:12px;">没有生成文件。</div>'); if (state.files.length) showFile(0);
    }
    function preview(){
        var cfg=collect(); layer.load(1,{shade:0.05});
        $.ajax({type:'POST',url:'general/occodegen/preview',dataType:'json',data:{config:JSON.stringify(cfg)},success:function(ret){
            layer.closeAll('loading'); if (!ret || !ret.data){ layer.alert((ret && ret.msg) || '生成预览失败',{icon:2}); return; }
            renderValidation(ret.data.validation); $('#cg-config-json').val(JSON.stringify(ret.data.config || cfg,null,2)); renderFiles(ret.data.files || []);
            if (ret.code === 200 && ret.data.validation && ret.data.validation.valid){ state.preview={config:ret.data.config,hash:ret.data.config_hash}; $('#cg-generate').prop('disabled',false); $('#cg-status').attr('class','zonoe-codegen-status text-success').text('预览已锁定 · '+String(ret.data.config_hash || '').substr(0,12)); }
            else { state.preview=null; $('#cg-generate').prop('disabled',true); $('#cg-status').attr('class','zonoe-codegen-status text-danger').text(ret.msg || '配置校验失败'); }
        },error:function(){ layer.closeAll('loading'); layer.alert('生成预览请求失败',{icon:2}); }});
    }
    function generate(){
        if (!state.preview){ layer.alert('请先生成预览并通过校验',{icon:0}); return; }
        layer.load(1,{shade:0.05});
        $.ajax({type:'POST',url:'general/occodegen/generate',dataType:'json',data:{config:JSON.stringify(state.preview.config),config_hash:state.preview.hash},success:function(ret){
            layer.closeAll('loading'); if (!ret || ret.code !== 200 || !ret.data){ layer.alert((ret && ret.msg) || '生成失败',{icon:2}); return; }
            $('#cg-status').attr('class','zonoe-codegen-status text-success').text('已生成 · SHA256 '+String(ret.data.sha256 || '').substr(0,16));
            window.location.href = ret.data.download_url;
        },error:function(){ layer.closeAll('loading'); layer.alert('生成 ZIP 请求失败',{icon:2}); }});
    }
    var Controller={index:function(){
        loadConfig();
        $(document).off('change.zonoeCodegen input.zonoeCodegen').on('change.zonoeCodegen input.zonoeCodegen','#cg-project,#cg-prefix,#cg-base-url,#cg-ios,#cg-timeout,#cg-user-agent,#cg-include-disabled,#cg-endpoints input,#cg-endpoints select',markDirty);
        $(document).off('click.zonoeCodegenPreview').on('click.zonoeCodegenPreview','#cg-preview',preview);
        $(document).off('click.zonoeCodegenGenerate').on('click.zonoeCodegenGenerate','#cg-generate',generate);
        $(document).off('click.zonoeCodegenReset').on('click.zonoeCodegenReset','#cg-reset',loadConfig);
        $(document).off('click.zonoeCodegenFile').on('click.zonoeCodegenFile','#cg-files a[data-index]',function(){showFile($(this).data('index'));});
    }};
    return Controller;
});
