define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            var stateLabels = {
                active: '正式使用',
                testing: '测试中',
                deprecated: '已弃用（仍允许）',
                blocked: '已阻止',
                revoked: '已撤销'
            };
            var actionLabels = {
                allow: '允许继续使用',
                disable_feature: '禁用受保护功能',
                show_message: '只显示提示',
                block: '完全阻止使用'
            };
            var accessLabels = {
                '': '所有授权',
                basic: '普通授权',
                app_plus: '指定 App 高级授权',
                global_plus: '全软件源高级授权'
            };
            var resultLabels = {
                ok: '验证通过', ok_testing: '验证通过（测试版本）', ok_deprecated: '验证通过（已弃用版本）',
                challenge_expired: 'Challenge 已过期', challenge_replayed: 'Challenge 已使用或不存在', challenge_mismatch: 'Challenge 上下文不匹配',
                device_auth_invalid: '设备认证参数无效', device_signature_invalid: '设备签名校验失败', device_key_mismatch: '设备公钥不匹配', device_enrollment_denied: '设备公钥首次登记被拒绝',
                protocol_unsupported: '客户端协议版本过旧', dylib_unknown: 'Dylib 未注册或已停用',
                license_invalid: '设备授权无效或已过期', blacklisted: '设备已被封禁', bundle_not_allowed: '旧版 BundleID 授权未命中',
                app_identity_incomplete: 'App 身份参数不完整', app_identity_unknown: '未识别当前 App 身份',
                app_identity_unbound: '解析 IPA 尚未绑定软件源 App', app_identity_ambiguous: 'App 身份匹配到多个软件源 App',
                app_identity_inactive: '对应 App 已停用或不可锁定', app_identity_required: '指定 App 卡需要 App 身份',
                app_not_authorized: '指定 App 卡不适用于当前 App', version_unknown: 'Dylib 版本未登记',
                version_blocked: 'Dylib 版本已阻止', version_revoked: 'Dylib 版本已撤销', integrity_mismatch: 'Dylib 文件指纹不匹配',
                bad_request: '请求参数不完整', server_error: '验证服务异常'
            };

            function label(map, value) { return Object.prototype.hasOwnProperty.call(map, value) ? map[value] : value; }
            function activateTab(target) { var $link = $('#dylib-center-tabs a[href="' + target + '"]'); if ($link.length) $link.tab('show'); }
            function dylibRowById(id) { return $('#dylib-list tbody tr[data-id="' + parseInt(id || 0, 10) + '"]'); }
            function currentDylibId() { return parseInt($('#global-dylib-select').val() || '0', 10); }
            function currentDylibKey() { var $row = dylibRowById(currentDylibId()); return $row.length ? String($row.attr('data-key') || '') : ''; }

            function setIntegrationContext(id) {
                var $row = dylibRowById(id);
                if (!$row.length) {
                    $('#integration-name').text('请选择已注册 Dylib');
                    $('#integration-key').text('');
                    $('#integration-config-url').text('GET /index/dylib_verify/config?dylib_key=...');
                    return;
                }
                var name = $row.attr('data-name') || '';
                var key = $row.attr('data-key') || '';
                $('#integration-name').text(name);
                $('#integration-key').text(key);
                $('#integration-config-url').text('GET /index/dylib_verify/config?dylib_key=' + key);
            }

            function syncCurrentDylib(id, source) {
                id = parseInt(id || 0, 10);
                if ($('#global-dylib-select').length && parseInt($('#global-dylib-select').val() || '0', 10) !== id) $('#global-dylib-select').val(id || '');
                if ($('#version-form [name=dylib_id]').length && id) $('#version-form [name=dylib_id]').val(String(id));
                if ($('#dcg-dylib').length && source !== 'codegen' && parseInt($('#dcg-dylib').val() || '0', 10) !== id) $('#dcg-dylib').val(id || '').trigger('change');
                setIntegrationContext(id);
                if ($('#log-filter-dylib').length) $('#log-filter-dylib').val(id ? currentDylibKey() : '');
                if ($('#version-table').data('bootstrap.table')) $('#version-table').bootstrapTable('refresh', {pageNumber: 1});
                if ($('#verify-log-table').data('bootstrap.table')) $('#verify-log-table').bootstrapTable('refresh', {pageNumber: 1});
            }

            function installGlobalDylibSelector() {
                if ($('#global-dylib-select').length) return;
                var options = '<option value="">全部 / 尚未选择</option>';
                $('#dylib-list tbody tr').each(function () {
                    var $r = $(this);
                    options += '<option value="' + $r.data('id') + '">' + $('<div/>').text(($r.attr('data-name') || '') + ' / ' + ($r.attr('data-key') || '')).html() + '</option>';
                });
                $('#dylib-center-tabs').before(
                    '<div class="well well-sm" id="global-dylib-context" style="margin-bottom:12px">' +
                    '<div class="row"><div class="col-sm-8"><label style="margin-right:8px">当前 Dylib</label>' +
                    '<select id="global-dylib-select" class="form-control" style="display:inline-block;width:min(520px,80%)">' + options + '</select></div>' +
                    '<div class="col-sm-4 text-muted" style="padding-top:7px">选择一次后，OC 代码、版本、接入说明和验证记录同步切换。</div></div></div>'
                );
            }

            function installVersionHelp() {
                var $form = $('#version-form');
                if (!$form.length || $('#version-help-2411').length) return;
                $('#section-version .panel-heading .text-muted').text('管理允许使用的 Dylib 版本；日常只需填写版本号、内部构建号和状态');
                $form.before('<div id="version-help-2411" class="alert alert-info" style="padding:10px 12px">' +
                    '<b>怎么用：</b>“版本号”是对外版本（如 1.2.3）；“内部构建号”用于同一版本多次重新编译（如 45、46）。正常发布选择“正式使用”。</div>');
                $form.find('[name=version]').attr('maxlength', '64');
                $form.find('[name=build]').attr('maxlength', '64').attr('placeholder', '内部构建号（可选，如 45）').attr('title', '同一版本重新编译时用于区分不同构建');
                var $advanced = $form.children('div').first();
                if ($advanced.length) $advanced.remove();
                if (!$form.find('[name=sha256]').length) $form.append('<input type="hidden" name="sha256" value="">');
                if (!$form.find('[name=file_size]').length) $form.append('<input type="hidden" name="file_size" value="0">');
                if (!$form.find('[name=offline_grace]').length) $form.append('<input type="hidden" name="offline_grace" value="900">');
                if (!$form.find('[name=fail_action]').length) $form.append('<input type="hidden" name="fail_action" value="disable_feature">');
                if (!$form.find('[name=notice]').length) $form.append('<input type="hidden" name="notice" value="">');
            }

            function collectVersionPayload($form, forcedId) {
                function value(name, fallback) {
                    var $field = $form.find('[name=' + name + ']');
                    if (!$field.length) return fallback;
                    var raw = $field.val();
                    return $.trim(String(raw == null ? '' : raw));
                }
                var offlineRaw = value('offline_grace', '900');
                return {
                    id: parseInt(forcedId == null ? value('id', '0') : forcedId, 10) || 0,
                    dylib_id: parseInt(value('dylib_id', '0'), 10) || 0,
                    version: value('version', ''),
                    build: value('build', ''),
                    sha256: value('sha256', '').toLowerCase(),
                    file_size: Math.max(0, parseInt(value('file_size', '0'), 10) || 0),
                    state: value('state', 'testing') || 'testing',
                    offline_grace: offlineRaw === '' ? 900 : parseInt(offlineRaw, 10),
                    fail_action: value('fail_action', 'disable_feature') || 'disable_feature',
                    notice: value('notice', '')
                };
            }

            function validateVersionPayload(data) {
                if (!data.dylib_id) { Layer.alert('请选择 Dylib。', {icon:0, title:'版本信息不完整'}); return false; }
                if (!data.version) { Layer.alert('请填写版本号。', {icon:0, title:'版本信息不完整'}); return false; }
                if (data.version.length > 64) { Layer.alert('版本号不能超过 64 个字符。', {icon:0, title:'版本信息有误'}); return false; }
                if (data.build.length > 64) { Layer.alert('内部构建号不能超过 64 个字符。', {icon:0, title:'版本信息有误'}); return false; }
                if (data.sha256 && !/^[a-f0-9]{64}$/.test(data.sha256)) { Layer.alert('文件 SHA256 必须是 64 位十六进制字符；不需要文件校验时请留空。', {icon:0, title:'文件校验格式有误'}); return false; }
                if (isNaN(data.offline_grace) || data.offline_grace < 0 || data.offline_grace > 86400) { Layer.alert('离线可用时间必须为 0-86400 秒。', {icon:0, title:'离线设置有误'}); return false; }
                if ($.inArray(data.state, ['testing','active','deprecated','blocked','revoked']) === -1) { Layer.alert('版本状态无效。', {icon:0}); return false; }
                if ($.inArray(data.fail_action, ['disable_feature','show_message','block']) === -1) { Layer.alert('验证失败动作无效。', {icon:0}); return false; }
                if (data.notice.length > 1024) { Layer.alert('用户提示不能超过 1024 个字符。', {icon:0, title:'用户提示过长'}); return false; }
                return true;
            }

            function installVersionEditModal() {
                if ($('#version-edit-modal').length) return;
                $('body').append(
                    '<div class="modal fade" id="version-edit-modal" tabindex="-1" role="dialog" aria-hidden="true">' +
                    '<div class="modal-dialog modal-lg"><div class="modal-content">' +
                    '<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="关闭"><span aria-hidden="true">&times;</span></button>' +
                    '<h4 class="modal-title" id="version-edit-title">编辑 Dylib 版本</h4></div>' +
                    '<form id="version-edit-form"><div class="modal-body">' +
                    '<input type="hidden" name="id" value="0"><input type="hidden" name="dylib_id" value="0"><input type="hidden" name="file_size" value="0">' +
                    '<div class="alert alert-info" style="padding:8px 12px">正在编辑现有版本记录。下面字段与版本列表中的版本号、内部构建号、状态、离线可用、验证失败动作、文件校验和用户提示一一对应。</div>' +
                    '<div class="form-group"><label>Dylib</label><input type="text" class="form-control js-version-edit-dylib" readonly></div>' +
                    '<div class="row"><div class="col-sm-6 form-group"><label>版本号</label><input type="text" class="form-control" name="version" maxlength="64" required></div>' +
                    '<div class="col-sm-6 form-group"><label>内部构建号</label><input type="text" class="form-control" name="build" maxlength="64" placeholder="可选，如 45"></div></div>' +
                    '<div class="row"><div class="col-sm-6 form-group"><label>状态</label><select class="form-control" name="state">' +
                    '<option value="testing">测试中</option><option value="active">正式使用</option><option value="deprecated">已弃用（仍允许）</option><option value="blocked">已阻止</option><option value="revoked">已撤销</option>' +
                    '</select></div>' +
                    '<div class="col-sm-6 form-group"><label>离线可用（秒）</label><input type="number" min="0" max="86400" class="form-control" name="offline_grace" value="900"><p class="help-block" style="margin-bottom:0">0=不允许离线；900=15 分钟。</p></div></div>' +
                    '<div class="form-group"><label>验证失败时</label><select class="form-control" name="fail_action">' +
                    '<option value="disable_feature">禁用受保护功能</option><option value="show_message">只显示提示</option><option value="block">完全阻止使用</option>' +
                    '</select></div>' +
                    '<div class="form-group"><label>文件 SHA256</label><input type="text" class="form-control" name="sha256" maxlength="64" placeholder="留空 = 不校验文件指纹"><p class="help-block" style="margin-bottom:0">启用时填写 64 位 SHA256；列表中的“文件校验”由此字段决定。</p></div>' +
                    '<div class="form-group"><label>用户提示</label><textarea class="form-control" name="notice" rows="3" maxlength="1024" placeholder="验证失败时返回给客户端的提示，可留空"></textarea></div>' +
                    '</div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">取消</button><button type="submit" class="btn btn-primary">保存版本修改</button></div>' +
                    '</form></div></div></div>'
                );
                $('#version-edit-form').on('submit', function (e) {
                    e.preventDefault();
                    var $form = $(this), data = collectVersionPayload($form);
                    if (!validateVersionPayload(data)) return false;
                    Fast.api.ajax({url:'dylib_center/saveVersion',type:'POST',data:data}, function () {
                        Toastr.success('Dylib 版本修改已保存');
                        $('#version-edit-modal').modal('hide');
                        $('#version-table').bootstrapTable('refresh');
                        return false;
                    });
                    return false;
                });
            }

            function installNoticeHelp() {
                var $form = $('#notice-form');
                if (!$form.length || $('#notice-help-2411').length) return;
                $form.before('<div id="notice-help-2411" class="alert alert-info" style="padding:10px 12px"><b>远程通知：</b>用于向客户端发公告。可以编辑、停用或删除；没有公告时保持为空即可。通知 Key、Revision、优先级属于高级标识，普通公告保持默认值即可。</div>');
                var $access = $form.find('[name=min_access_level]');
                $access.find('option[value=""]').text('所有授权');
                $access.find('option[value=basic]').text('普通授权');
                $access.find('option[value=app_plus]').text('指定 App 高级授权');
                $access.find('option[value=global_plus]').text('全软件源高级授权');
            }

            function installLogToolbar() {
                if ($('#log-filter-bar').length) return;
                var dylibOptions = '<option value="">全部 Dylib</option>';
                $('#dylib-list tbody tr').each(function () {
                    var $r = $(this), key = $r.attr('data-key') || '', name = $r.attr('data-name') || '';
                    dylibOptions += '<option value="' + $('<div/>').text(key).html() + '">' + $('<div/>').text(name + ' / ' + key).html() + '</option>';
                });
                $('#verify-log-table').before(
                    '<div id="log-filter-bar" class="well well-sm" style="margin-bottom:10px">' +
                    '<div class="row">' +
                    '<div class="col-md-2 form-group"><label>Dylib</label><select id="log-filter-dylib" class="form-control">' + dylibOptions + '</select></div>' +
                    '<div class="col-md-2 form-group"><label>验证结果</label><select id="log-filter-result" class="form-control"><option value="">全部结果</option><option value="ok">验证通过</option><option value="device_signature_invalid">设备签名失败</option><option value="license_invalid">授权无效/过期</option><option value="version_unknown">版本未登记</option><option value="version_blocked">版本已阻止</option><option value="version_revoked">版本已撤销</option><option value="integrity_mismatch">文件指纹不匹配</option><option value="server_error">服务异常</option></select></div>' +
                    '<div class="col-md-2 form-group"><label>BundleID</label><input id="log-filter-bundle" class="form-control" placeholder="com.example.app"></div>' +
                    '<div class="col-md-2 form-group"><label>Dylib 版本</label><input id="log-filter-version" class="form-control" placeholder="1.2.3"></div>' +
                    '<div class="col-md-2 form-group"><label>UDID Hash 前缀</label><input id="log-filter-udid" class="form-control" placeholder="前几位即可"></div>' +
                    '<div class="col-md-2 form-group"><label>每页</label><select id="log-page-size" class="form-control"><option value="100">100</option><option value="500">500</option><option value="1000" selected>1000</option></select></div>' +
                    '</div><div class="row">' +
                    '<div class="col-md-3 form-group"><label>开始时间</label><input id="log-filter-from" type="datetime-local" class="form-control"></div>' +
                    '<div class="col-md-3 form-group"><label>结束时间</label><input id="log-filter-to" type="datetime-local" class="form-control"></div>' +
                    '<div class="col-md-6" style="padding-top:25px"><button id="log-apply-filter" class="btn btn-primary btn-sm">筛选</button> <button id="log-reset-filter" class="btn btn-default btn-sm">重置</button> <span style="margin-left:15px"></span><button id="log-delete-selected" class="btn btn-danger btn-sm">删除选中</button> <button id="log-delete-filtered" class="btn btn-danger btn-sm">删除当前筛选结果</button></div>' +
                    '</div></div>'
                );
            }

            function logFilterParams(params) {
                function epoch(selector) { var v = $(selector).val(); return v ? Math.floor(new Date(v).getTime() / 1000) : 0; }
                params.dylib_key = $('#log-filter-dylib').val() || '';
                params.result_code = $('#log-filter-result').val() || '';
                params.bundle_id = $('#log-filter-bundle').val() || '';
                params.dylib_version = $('#log-filter-version').val() || '';
                params.udid_hash = $('#log-filter-udid').val() || '';
                params.created_from = epoch('#log-filter-from');
                params.created_to = epoch('#log-filter-to');
                return params;
            }

            function refreshAll() { $('#version-table,#notice-table,#verify-log-table').each(function () { if ($(this).data('bootstrap.table')) $(this).bootstrapTable('refresh'); }); }
            function resetDylibForm() {
                var $form = $('#dylib-form'); $form[0].reset(); $form.find('[name=id]').val('0'); $form.find('[name=enabled]').val('1');
                $form.find('[name=dylib_key]').prop('readonly', false);
                $('.js-dylib-submit-label').text('注册 Dylib'); $('#cancel-dylib-edit').addClass('hidden');
            }
            function resetVersionForm() {
                var $form = $('#version-form'); $form[0].reset();
                $form.find('[name=sha256]').val(''); $form.find('[name=file_size]').val('0'); $form.find('[name=offline_grace]').val('900');
                $form.find('[name=fail_action]').val('disable_feature'); $form.find('[name=notice]').val(''); $form.find('[name=state]').val('testing');
                if (currentDylibId()) $form.find('[name=dylib_id]').val(String(currentDylibId()));
            }
            function editVersion(row) {
                installVersionEditModal();
                var $form = $('#version-edit-form'), $dylib = dylibRowById(row.dylib_id);
                $form.find('[name=id]').val(row.id == null ? '0' : row.id);
                $form.find('[name=dylib_id]').val(row.dylib_id == null ? '0' : row.dylib_id);
                $form.find('.js-version-edit-dylib').val($dylib.length ? (($dylib.attr('data-name') || '') + ' / ' + ($dylib.attr('data-key') || '')) : ('Dylib #' + row.dylib_id));
                $.each(['version','build','state','sha256','file_size','offline_grace','fail_action','notice'], function (_, field) {
                    $form.find('[name=' + field + ']').val(row[field] == null ? '' : row[field]);
                });
                $('#version-edit-title').text('编辑 Dylib 版本 #' + row.id);
                $('#version-edit-modal').modal('show');
            }
            function resetNoticeForm() {
                var $form = $('#notice-form'); $form[0].reset(); $form.find('[name=id]').val('0'); $form.find('[name=revision]').val('1');
                $form.find('[name=priority]').val('0'); $form.find('[name=category_id]').val('0'); $form.find('[name=enabled]').val('1'); $form.find('[name=starts_at]').val('0'); $form.find('[name=ends_at]').val('0');
            }

            installGlobalDylibSelector(); installVersionHelp(); installVersionEditModal(); installNoticeHelp(); installLogToolbar();
            $('#dylib-list').closest('.panel-body').find('.help-block').first().text('删除 Dylib 会同时删除其版本、旧游戏授权和验证记录；此操作不可恢复。');
            $('#integration-detail p').filter(function () { return $(this).text().indexOf('运行配置：') === 0; }).html('运行配置：<code id="integration-config-url">GET /index/dylib_verify/config?dylib_key=...</code>');

            $('#version-table').bootstrapTable({
                url: 'dylib_center/versions', sidePagination: 'server', pagination: true, pageSize: 50, pageList: [20, 50, 100, 500, 1000],
                queryParams: function (params) { params.dylib_id = currentDylibId(); return params; },
                columns: [[
                    {field:'id',title:'ID'}, {field:'version',title:'版本号'}, {field:'build',title:'内部构建号', formatter:function(v){return v || '—';}},
                    {field:'state',title:'状态',formatter:function(v){return label(stateLabels,v);}},
                    {field:'offline_grace',title:'离线可用',formatter:function(v){v=parseInt(v||0,10);return v>=60?Math.round(v/60)+' 分钟':v+' 秒';}},
                    {field:'fail_action',title:'验证失败时',formatter:function(v){return label(actionLabels,v);}},
                    {field:'sha256',title:'文件校验',formatter:function(v){return v ? '已启用 · '+v.substr(0,12)+'…' : '未启用';}},
                    {field:'notice',title:'用户提示',formatter:function(v){return v || '—';}},
                    {field:'operate',title:'操作',formatter:function(){return '<button type="button" class="btn btn-xs btn-primary js-version-edit">编辑</button> <button type="button" class="btn btn-xs btn-danger js-version-delete">删除</button>';},events:{
                        'click .js-version-edit':function(e,v,row){e.stopPropagation();editVersion(row);},
                        'click .js-version-delete':function(e,v,row){Layer.confirm('确定删除版本“'+row.version+(row.build?' ('+row.build+')':'')+'”吗？删除后客户端使用该版本会返回“版本未登记”。',{title:'删除 Dylib 版本'},function(i){Layer.close(i);Fast.api.ajax({url:'dylib_center/deleteVersion',type:'POST',data:{id:row.id}},function(){Toastr.success('Dylib 版本已删除');resetVersionForm();$('#version-table').bootstrapTable('refresh');return false;});});}
                    }}
                ]]
            });

            $('#notice-table').bootstrapTable({
                url:'dylib_center/notices',sidePagination:'server',pagination:true,pageSize:20,pageList:[20,50,100,500],
                columns:[[
                    {field:'id',title:'ID'},{field:'app_name',title:'目标 App'},{field:'min_access_level',title:'最低权限',formatter:function(v){return label(accessLabels,v||'');}},
                    {field:'title',title:'标题'},{field:'enabled',title:'状态',formatter:function(v){return parseInt(v,10)?'<span class="label label-success">启用</span>':'<span class="label label-default">停用</span>'; }},
                    {field:'operate',title:'操作',formatter:function(v,row){return '<button type="button" class="btn btn-xs btn-primary js-notice-edit">编辑</button> '+(parseInt(row.enabled,10)?'<button type="button" class="btn btn-xs btn-warning js-notice-toggle" data-enabled="0">停用</button>':'<button type="button" class="btn btn-xs btn-success js-notice-toggle" data-enabled="1">启用</button>')+' <button type="button" class="btn btn-xs btn-danger js-notice-delete">删除</button>';},events:{
                        'click .js-notice-edit':function(e,v,row){var $f=$('#notice-form');$.each(['id','notice_key','revision','priority','category_id','min_access_level','title','message','primary_title','primary_action','primary_url','secondary_title','secondary_action','secondary_url','starts_at','ends_at','enabled'],function(_,field){$f.find('[name='+field+']').val(row[field]==null?'':row[field]);});activateTab('#tab-notices');setTimeout(function(){$('html,body').animate({scrollTop:$f.offset().top-20},150);},80);},
                        'click .js-notice-toggle':function(e,v,row){var enabled=String($(e.currentTarget).data('enabled'));Fast.api.ajax({url:'dylib_center/setNoticeEnabled',type:'POST',data:{id:row.id,enabled:enabled}},function(){Toastr.success(enabled==='1'?'通知已启用':'通知已停用');$('#notice-table').bootstrapTable('refresh');return false;});},
                        'click .js-notice-delete':function(e,v,row){Layer.confirm('确定删除通知“'+(row.title||row.notice_key)+'”吗？',{title:'删除远程通知'},function(i){Layer.close(i);Fast.api.ajax({url:'dylib_center/deleteNotice',type:'POST',data:{id:row.id}},function(){Toastr.success('通知已删除');resetNoticeForm();$('#notice-table').bootstrapTable('refresh');return false;});});}
                    }}
                ]]
            });

            $('#verify-log-table').bootstrapTable({
                url:'dylib_center/logs',sidePagination:'server',pagination:true,search:true,pageSize:1000,pageList:[100,500,1000],clickToSelect:true,
                queryParams:function(params){return logFilterParams(params);},
                columns:[[
                    {checkbox:true},{field:'id',title:'ID'},{field:'udid_hash',title:'设备标识哈希（前12位）'},{field:'bundle_id',title:'运行 App BundleID'},
                    {field:'dylib_key',title:'Dylib Key'},{field:'dylib_version',title:'Dylib 版本'},{field:'result_code',title:'验证结果',formatter:function(v){return label(resultLabels,v);}},
                    {field:'action',title:'客户端动作',formatter:function(v){return label(actionLabels,v);}},{field:'latency_ms',title:'耗时（ms）'},{field:'created_at',title:'验证时间',formatter:Table.api.formatter.datetime},
                    {field:'operate',title:'操作',formatter:function(){return '<button type="button" class="btn btn-xs btn-danger js-log-delete">删除</button>';},events:{'click .js-log-delete':function(e,v,row){e.stopPropagation();Layer.confirm('确定删除这条验证记录吗？',{title:'删除验证记录'},function(i){Layer.close(i);Fast.api.ajax({url:'dylib_center/deleteLogs',type:'POST',data:{mode:'selected',ids:String(row.id)}},function(){Toastr.success('验证记录已删除');$('#verify-log-table').bootstrapTable('refresh');return false;});});}}}
                ]]
            });

            require(['backend/dylib_codegen_inline'], function (Codegen) { Codegen.init(); if (currentDylibId()) $('#dcg-dylib').val(String(currentDylibId())).trigger('change'); });

            $(document).on('change', '#global-dylib-select', function(){syncCurrentDylib($(this).val(),'global');});
            $(document).on('change', '#dcg-dylib', function(){var id=parseInt($(this).val()||'0',10);syncCurrentDylib(id,'codegen');});
            $('#log-apply-filter').on('click',function(){$('#verify-log-table').bootstrapTable('refresh',{pageNumber:1});});
            $('#log-reset-filter').on('click',function(){$('#log-filter-bar input').val('');$('#log-filter-result,#log-filter-dylib').val('');$('#log-page-size').val('1000');$('#verify-log-table').bootstrapTable('refreshOptions',{pageSize:1000});$('#verify-log-table').bootstrapTable('refresh',{pageNumber:1});});
            $('#log-page-size').on('change',function(){$('#verify-log-table').bootstrapTable('refreshOptions',{pageSize:parseInt($(this).val(),10)});});
            $('#log-delete-selected').on('click',function(){var rows=$('#verify-log-table').bootstrapTable('getSelections')||[];if(!rows.length){Layer.alert('请先勾选要删除的验证记录',{icon:0});return;}var ids=$.map(rows,function(r){return r.id;}).join(',');Layer.confirm('确定删除选中的 '+rows.length+' 条验证记录吗？',{title:'批量删除验证记录'},function(i){Layer.close(i);Fast.api.ajax({url:'dylib_center/deleteLogs',type:'POST',data:{mode:'selected',ids:ids}},function(data){Toastr.success('已删除 '+((data&&data.deleted)||rows.length)+' 条记录');$('#verify-log-table').bootstrapTable('refresh');return false;});});});
            $('#log-delete-filtered').on('click',function(){var params=logFilterParams({});var hasFilter=!!(params.dylib_key||params.result_code||params.bundle_id||params.dylib_version||params.udid_hash||params.created_from||params.created_to);if(!hasFilter){Layer.alert('请至少设置一个筛选条件后再删除筛选结果，避免误清空全部验证记录。',{icon:0});return;}Layer.confirm('确定永久删除当前筛选条件命中的全部验证记录吗？此操作不可恢复。',{title:'删除筛选结果'},function(i){Layer.close(i);params.mode='filtered';Fast.api.ajax({url:'dylib_center/deleteLogs',type:'POST',data:params},function(data){Toastr.success('已删除 '+((data&&data.deleted)||0)+' 条记录');$('#verify-log-table').bootstrapTable('refresh',{pageNumber:1});return false;});});});

            $('#dylib-center-tabs a[data-toggle="tab"]').on('shown.bs.tab',function(){window.setTimeout(function(){$('#version-table,#notice-table,#verify-log-table').each(function(){if($(this).data('bootstrap.table'))$(this).bootstrapTable('resetView');});},30);});
            $('#dylib-form').on('submit',function(e){e.preventDefault();Fast.api.ajax({url:'dylib_center/saveDylib',type:'POST',data:$(this).serialize()},function(){location.reload();return false;});});
            $('#runtime-config-form').on('submit',function(e){e.preventDefault();Fast.api.ajax({url:'dylib_center/saveRuntimeConfig',type:'POST',data:$(this).serialize()},function(){Toastr.success('高级运行配置已保存；配置版本已自动递增。');location.reload();return false;});});
            $('#notice-form').on('submit',function(e){e.preventDefault();Fast.api.ajax({url:'dylib_center/saveNotice',type:'POST',data:$(this).serialize()},function(){Toastr.success('远程通知已保存');resetNoticeForm();$('#notice-table').bootstrapTable('refresh');return false;});});
            $('#version-form').on('submit',function(e){
                e.preventDefault();
                var $form=$(this),data=collectVersionPayload($form,0);
                if(!validateVersionPayload(data)) return false;
                Fast.api.ajax({url:'dylib_center/saveVersion',type:'POST',data:data},function(){Toastr.success('Dylib 版本已添加');resetVersionForm();$('#version-table').bootstrapTable('refresh');return false;});
                return false;
            });
            $('#reset-notice-form').on('click',resetNoticeForm);$('#cancel-dylib-edit').on('click',resetDylibForm);

            $('#dylib-list').on('click','.js-dylib-edit',function(){var $row=$(this).closest('tr'),$form=$('#dylib-form');syncCurrentDylib($row.data('id'),'list');$form.find('[name=id]').val($row.data('id'));$form.find('[name=dylib_key]').val($row.attr('data-key')).prop('readonly',true);$form.find('[name=name]').val($row.attr('data-name'));$form.find('[name=default_offline_grace]').val($row.attr('data-grace'));$form.find('[name=default_fail_action]').val($row.attr('data-action'));$form.find('[name=enabled]').val($row.attr('data-enabled'));$('.js-dylib-submit-label').text('保存修改');$('#cancel-dylib-edit').removeClass('hidden');activateTab('#tab-overview');setTimeout(function(){$('html,body').animate({scrollTop:$('#section-register').offset().top-20},150);},80);});
            $('#dylib-list').on('click','.js-dylib-toggle',function(){var $row=$(this).closest('tr'),enabled=String($(this).data('enabled')),verb=enabled==='1'?'启用':'停用';Fast.api.ajax({url:'dylib_center/setDylibEnabled',type:'POST',data:{id:$row.data('id'),enabled:enabled}},function(){Toastr.success('Dylib 已'+verb);location.reload();return false;});});
            $('#dylib-list').on('click','.js-dylib-delete',function(){var $row=$(this).closest('tr'),name=$row.attr('data-name');Layer.confirm('确定彻底删除“'+name+'”吗？该操作会同时删除此 Dylib 的版本、旧游戏授权和验证记录，且不可恢复。',{title:'彻底删除 Dylib（二次确认）'},function(i){Layer.close(i);Fast.api.ajax({url:'dylib_center/deleteDylib',type:'POST',data:{id:$row.data('id')}},function(){Toastr.success('Dylib 及其关联历史已删除');location.reload();return false;});});});
            $('#dylib-list').on('click','.js-dylib-integration',function(){var $row=$(this).closest('tr');syncCurrentDylib($row.data('id'),'list');activateTab('#tab-advanced');$('#integration-detail').collapse('show');setTimeout(function(){$('html,body').animate({scrollTop:$('#section-integration').offset().top-20},150);},100);});

            setInterval(function(){if(document.visibilityState==='visible')refreshAll();},60000);
        }
    };
    return Controller;
});