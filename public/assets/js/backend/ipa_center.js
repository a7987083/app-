define(['jquery','bootstrap','backend','table','form'],function($,undefined,Backend,Table,Form){
    var html=function(v){return $('<div/>').text(v==null?'':String(v)).html();};
    var Controller={
        index:function(){
            var load=function(){
                Fast.api.ajax({url:'ipa_center/index',type:'GET',loading:false},function(data){
                    var s=data.stats||{},source=data.source||{};
                    $('#ipa-dashboard-total').text(s.metadata_total||0);
                    $('#ipa-dashboard-parsed').text(s.parsed||0);
                    $('#ipa-dashboard-pending').text(s.pending||0);
                    $('#ipa-dashboard-failed').text(s.failed||0);
                    $('#ipa-dashboard-bound').text(s.bound||0);
                    $('#ipa-dashboard-unbound').text(s.unbound||0);
                    $('#ipa-dashboard-governance').text(s.governance||0);
                    $('#ipa-dashboard-source-configured').text(source.configured?'已配置':'未配置').toggleClass('label-success',!!source.configured).toggleClass('label-default',!source.configured);
                    $('#ipa-dashboard-health').text(source.last_health||'unknown');
                    $('#ipa-dashboard-checked').text(source.last_checked_at||'-');
                    $('#ipa-dashboard-scanned').text(source.last_scan_at||'-');
                    var table=$('#ipa-dashboard-task-table');
                    if(!table.data('bootstrap.table')){
                        table.bootstrapTable({pagination:false,search:false,showRefresh:false,columns:[[ 
                            {field:'id',title:'ID',formatter:function(v){return '#'+html(v);}},
                            {field:'trigger_type',title:'触发'},
                            {field:'stage',title:'阶段'},
                            {field:'state',title:'状态'},
                            {field:'started_at_text',title:'开始'},
                            {field:'finished_at_text',title:'结束'}
                        ]]});
                    }
                    table.bootstrapTable('load',data.tasks||[]);
                    return false;
                },function(){return false;});
            };
            $('.btn-ipa-dashboard-refresh').on('click',load);
            load();
        },

        metadata:function(){
            var state='';
            Table.api.init({extend:{index_url:'ipa_center/metadata_list',table:'ipa_metadata'}});
            var table=$('#ipa-meta-table');
            table.bootstrapTable({
                url:$.fn.bootstrapTable.defaults.extend.index_url,
                pk:'id',
                sortName:'id',
                sortOrder:'desc',
                sidePagination:'server',
                pagination:true,
                pageSize:50,
                pageList:[20,50,100],
                search:false,
                showRefresh:false,
                queryParams:function(params){
                    params.page=Math.floor((params.offset||0)/(params.limit||50))+1;
                    params.q=$('#ipa-meta-search').val()||'';
                    params.state=state;
                    return params;
                },
                columns:[[ 
                    {field:'id',title:'ID',sortable:true,formatter:function(v){return '#'+html(v);}},
                    {field:'file_name',title:'IPA',formatter:function(v,r){return '<strong>'+html(v||'-')+'</strong><br><small>'+html(r.remote_path||'')+'</small>'; }},
                    {field:'package_name',title:'应用 / Bundle ID',formatter:function(v,r){return html(v||'-')+'<br><code>'+html(r.bundle_id||'-')+'</code>'; }},
                    {field:'package_version',title:'Version / Build',formatter:function(v,r){return html(v||'-')+' / '+html(r.package_build||'-');}},
                    {field:'minimum_ios',title:'最低 iOS'},
                    {field:'architectures',title:'架构',formatter:function(v){return html($.isArray(v)?v.join(', '):(v||'-'));}},
                    {field:'primary_icon',title:'主图标',formatter:function(v){return v&&v.name?html(v.name+' '+(v.width||0)+'×'+(v.height||0)):'-';}},
                    {field:'range_bytes',title:'Range',formatter:function(v,r){return v?((v/1024/1024).toFixed(2)+' MB / '+(r.range_requests||0)+' req'):'-';}},
                    {field:'parser_version',title:'Parser',formatter:function(v,r){return 'v'+html(v||0)+'<br><small>'+html(r.parsed_at_text||'')+'</small>'; }},
                    {field:'parse_state',title:'状态',sortable:true,formatter:function(v,r){return html(v||'')+(r.parse_error?'<br><small class="text-danger">'+html(r.parse_error)+'</small>':'');}}
                ]]
            });
            Table.api.bindevent(table);
            $('.ipa-meta-filter button').on('click',function(){
                state=$(this).data('state')||'';
                $('.ipa-meta-filter button').removeClass('btn-primary').addClass('btn-default');
                $(this).removeClass('btn-default').addClass('btn-primary');
                table.bootstrapTable('refresh',{pageNumber:1});
            });
            $('.btn-ipa-meta-refresh').on('click',function(){table.bootstrapTable('refresh');});
            $('#ipa-meta-search').on('keyup change',function(e){if(e.type==='change'||e.keyCode===13)table.bootstrapTable('refresh',{pageNumber:1});});
            $('.btn-ipa-parse').on('click',function(){Fast.api.ajax({url:'ipa_center/parse_start',data:{}},function(){setTimeout(function(){table.bootstrapTable('refresh');},1200);return false;});});
        },

        binding:function(){
            Table.api.init({extend:{index_url:'ipa_center/binding_list',table:'ipa_binding'}});
            var table=$('#ipa-binding-table');
            var reload=function(){table.bootstrapTable('refresh');};
            var actions=function(r){
                if(r.binding_id){return '<button class="btn btn-xs btn-danger btn-binding-remove" data-id="'+html(r.binding_id)+'">解除绑定</button>';}
                return '<button class="btn btn-xs btn-success btn-binding-auto" data-mid="'+html(r.id)+'">精确 URL 自动绑定</button> '+
                    '<button class="btn btn-xs btn-default btn-binding-candidates" data-mid="'+html(r.id)+'">查看候选</button> '+
                    '<button class="btn btn-xs btn-primary btn-binding-manual" data-mid="'+html(r.id)+'">手动绑定</button>';
            };
            table.bootstrapTable({
                url:$.fn.bootstrapTable.defaults.extend.index_url,
                pk:'id',pagination:false,search:false,showRefresh:false,
                responseHandler:function(res){
                    var data=res&&res.data?res.data:res;
                    var stats=data&&data.stats?data.stats:{};
                    $('#ipa-bound-count').text(stats.bound||0);
                    $('#ipa-unbound-count').text(stats.unbound||0);
                    return data||{rows:[],total:0};
                },
                columns:[[ 
                    {field:'file_name',title:'IPA',formatter:function(v,r){return '<strong>'+html(v||'-')+'</strong><br><small>'+html(r.remote_path||'')+'</small>'; }},
                    {field:'package_name',title:'解析身份',formatter:function(v,r){return '<strong>'+html(v||'-')+'</strong><br><code>'+html(r.bundle_id||'-')+'</code><br><small>'+html((r.package_version||'-')+' / '+(r.package_build||'-'))+'</small>'; }},
                    {field:'public_url',title:'OpenList 地址',formatter:function(v){return '<small>'+html(v||'-')+'</small>'; }},
                    {field:'binding_id',title:'数据库绑定',formatter:function(v,r){return v?('#'+html(r.category_id)+' '+html(r.category_name||'')+'<br><small>'+html(r.category_version||'')+'</small>'):'<span class="text-warning">未绑定</span>'; }},
                    {field:'operate',title:'操作',formatter:function(v,r){return actions(r);},events:{
                        'click .btn-binding-auto':function(e,v,r){Fast.api.ajax({url:'ipa_center/binding_apply',data:{metadata_id:r.id,mode:'auto_exact'}},function(){reload();return false;});},
                        'click .btn-binding-remove':function(e,v,r){Layer.confirm('确认解除当前稳定绑定？',function(i){Layer.close(i);Fast.api.ajax({url:'ipa_center/binding_remove',data:{binding_id:r.binding_id}},function(){reload();return false;});});},
                        'click .btn-binding-manual':function(e,v,r){Layer.prompt({title:'输入数据库 category ID'},function(value,index){var cid=parseInt(value,10);if(!cid){Toastr.error('category ID 无效');return;}Layer.close(index);Fast.api.ajax({url:'ipa_center/binding_apply',data:{metadata_id:r.id,category_id:cid,mode:'manual'}},function(){reload();return false;});});},
                        'click .btn-binding-candidates':function(e,v,r){Fast.api.ajax({url:'ipa_center/binding_candidates',loading:false,data:{metadata_id:r.id}},function(data){var rows=data.rows||[],text=rows.length?$.map(rows,function(x){return '#'+x.category_id+' '+x.name+' / '+x.version+' / '+(x.authoritative?'精确URL':'人工参考')+' / '+x.reasons.join(',');}).join('\n'):'没有候选';Layer.alert('<pre style="white-space:pre-wrap">'+html(text)+'</pre>',{title:'绑定候选'});return false;});}
                    }}
                ]]
            });
            Table.api.bindevent(table);
            $('.btn-ipa-binding-refresh').on('click',reload);
        },

        governance:function(){
            var kind='duplicate';
            var setStats=function(s){s=s||{};$('#ipa-gov-duplicate').text(s.duplicate||0);$('#ipa-gov-mismatch').text(s.mismatch||0);$('#ipa-gov-missing').text(s.missing||0);$('#ipa-gov-version').text(s.version||0);};
            var actions=function(r){var id=html(r.id);if(r.issue_type==='version_mismatch'||r.issue_type==='metadata_mismatch')return '<button class="btn btn-xs btn-success btn-gov-preview" data-id="'+id+'" data-mode="ipa_to_db">以 IPA 为准</button> <button class="btn btn-xs btn-default btn-gov-ignore" data-id="'+id+'">保留数据库</button> <button class="btn btn-xs btn-info btn-gov-verify" data-id="'+id+'">重新检测</button>';if(r.issue_type==='path_mismatch')return '<button class="btn btn-xs btn-warning btn-gov-preview" data-id="'+id+'" data-mode="db_to_openlist">以数据库为准改 OpenList</button> <button class="btn btn-xs btn-success btn-gov-preview" data-id="'+id+'" data-mode="openlist_to_db">以 OpenList 为准改数据库</button> <button class="btn btn-xs btn-info btn-gov-verify" data-id="'+id+'">重新检测</button>';return '<button class="btn btn-xs btn-default btn-gov-ignore" data-id="'+id+'">忽略 30 天</button> <button class="btn btn-xs btn-info btn-gov-verify" data-id="'+id+'">重新检测</button>';};
            var load=function(){Fast.api.ajax({url:'ipa_center/governance_list',loading:false,data:{kind:kind,limit:200}},function(data){setStats(data.stats);var rows=data.rows||[],body=$('#ipa-governance-table tbody');body.empty();if(!rows.length){body.append('<tr><td colspan="7" class="text-center text-muted">当前分类没有未解决异常</td></tr>');return false;}$.each(rows,function(_,r){body.append('<tr><td>#'+html(r.category_id)+' <strong>'+html(r.category_name||'')+'</strong><br><small>'+html(r.file_name||'')+'</small></td><td>'+html(r.issue_type)+'<br><code>'+html(r.field_name)+'</code></td><td>'+html(r.db_value)+'</td><td>'+html(r.actual_value)+'</td><td>'+html(r.reason)+'</td><td>'+actions(r)+'</td><td><span class="label label-warning">待修复</span></td></tr>');});return false;},function(){return false;});};
            $('.ipa-governance-card').on('click',function(){kind=$(this).data('kind')||'';$('.ipa-governance-card').removeClass('ipa-active');$(this).addClass('ipa-active');load();});
            $('.btn-ipa-governance-refresh').on('click',function(){Fast.api.ajax({url:'ipa_center/governance_refresh',data:{}},function(data){setStats(data.stats);load();return false;});});
            $('#ipa-governance-table').on('click','.btn-gov-preview',function(){var id=$(this).data('id'),mode=$(this).data('mode');Fast.api.ajax({url:'ipa_center/governance_preview',data:{issue_id:id,mode:mode}},function(data){var p=data.plan||{},payload=p.payload||{};var text='动作：'+p.action+'\n模式：'+p.mode+'\n旧值/路径：'+(payload.old||payload.from||'')+'\n新值/路径：'+(payload.new||payload.to||'')+'\nplan_hash：'+p.plan_hash;Layer.confirm('<pre style="white-space:pre-wrap">'+html(text)+'</pre><p class="text-danger">确认执行后系统会重新验证；若数据已变化将拒绝旧计划。</p>',{title:'修复预览'},function(index){Layer.close(index);Fast.api.ajax({url:'ipa_center/governance_apply',data:{issue_id:id,mode:mode,plan_hash:p.plan_hash}},function(){load();return false;});});return false;});});
            $('#ipa-governance-table').on('click','.btn-gov-ignore',function(){Fast.api.ajax({url:'ipa_center/governance_ignore',data:{issue_id:$(this).data('id'),days:30}},function(){load();return false;});});
            $('#ipa-governance-table').on('click','.btn-gov-verify',function(){Fast.api.ajax({url:'ipa_center/governance_verify',data:{issue_id:$(this).data('id')}},function(){load();return false;});});
            load();
        },

        task:function(){
            Table.api.init({extend:{index_url:'ipa_center/task_list',table:'ipa_scan_task'}});
            var table=$('#table');
            table.bootstrapTable({
                url:$.fn.bootstrapTable.defaults.extend.index_url,
                pk:'id',sortName:'id',sortOrder:'desc',pagination:false,search:false,showRefresh:false,
                columns:[[ 
                    {field:'id',title:'ID',formatter:function(v){return '#'+html(v);}},
                    {field:'trigger_type',title:'触发'},
                    {field:'stage',title:'阶段'},
                    {field:'progress_current',title:'进度',formatter:function(v,r){return html((v||0)+' / '+(r.progress_total||0));}},
                    {field:'cursor',title:'发现结果',formatter:function(v){v=v||{};return html('新 '+(v['new']||0)+' / 变化 '+(v.changed||0)+' / 未变 '+(v.unchanged||0)+' / 缺失 '+(v.missing||0));}},
                    {field:'started_at_text',title:'开始'},
                    {field:'finished_at_text',title:'结束'},
                    {field:'state',title:'状态'}
                ]]
            });
            Table.api.bindevent(table);
            var refresh=function(){table.bootstrapTable('refresh',{silent:true});};
            var start=function(force){Fast.api.ajax({url:'ipa_center/scan_start',data:{refresh:force?1:0}},function(){refresh();return false;});};
            $('.btn-ipa-scan').on('click',function(){start(false);});
            $('.btn-ipa-scan-refresh').on('click',function(){start(true);});
            $('.btn-ipa-task-refresh').on('click',refresh);
            window.setInterval(refresh,5000);
        },

        writeback:function(){
            var currentRules=[];
            var renderRules=function(data){currentRules=data.rules||[];var body=$('#ipa-writeback-rule-table tbody');body.empty();$('#ipa-writeback-version').text(data.version||0);$.each(currentRules,function(i,r){body.append('<tr data-index="'+i+'"><td><code>'+html(r.source_field)+'</code></td><td><code>category.'+html(r.target_field)+'</code></td><td><select class="form-control input-sm ipa-rule-strategy"><option>preview</option><option>empty</option><option>changed</option><option>always</option><option>managed_block</option><option>ignore</option></select></td><td><select class="form-control input-sm ipa-rule-confidence"><option>exact</option><option>derived</option><option>fallback</option></select></td><td><input type="checkbox" class="ipa-rule-enabled" '+(parseInt(r.enabled,10)?'checked':'')+'></td></tr>');var tr=body.find('tr:last');tr.find('.ipa-rule-strategy').val(r.strategy);tr.find('.ipa-rule-confidence').val(r.min_confidence);});};
            var loadRules=function(){Fast.api.ajax({url:'ipa_center/writeback_rules',loading:false},function(data){renderRules(data||{});return false;},function(){return false;});};
            var renderPreview=function(payload){var sample=payload.sample||{};$('#ipa-writeback-sample').html('<strong>#'+html(sample.category_id||'')+' '+html(sample.category_name||'')+'</strong><br><span class="text-muted">'+html(sample.remote_path||'')+'</span>');var rows=payload.preview||[],body=$('#ipa-writeback-preview-table tbody');body.empty();if(!rows.length){body.append('<tr><td colspan="6" class="text-center text-muted">没有可预览字段</td></tr>');return;}$.each(rows,function(_,row){var result=row.will_apply?'<span class="label label-success">将写入</span>':'<span class="label label-default">保持不变</span>';if(!row.confidence_ok)result='<span class="label label-warning">可信度不足</span>';body.append('<tr><td>'+html(row.target_field)+'</td><td>'+html(row.old_value)+'</td><td>'+html(row.new_value)+'</td><td>'+html(row.strategy)+'</td><td>'+html(row.confidence)+'</td><td>'+result+'</td></tr>');});};
            $('#ipa-writeback-seed').on('click',function(){Fast.api.ajax({url:'ipa_center/writeback_seed',data:{}},function(){loadRules();return false;});});
            $('#ipa-writeback-save').on('click',function(){var rules=[];$('#ipa-writeback-rule-table tbody tr').each(function(){var i=parseInt($(this).data('index'),10),base=currentRules[i]||{};rules.push({rule_key:base.rule_key,source_field:base.source_field,target_field:base.target_field,strategy:$(this).find('.ipa-rule-strategy').val(),min_confidence:$(this).find('.ipa-rule-confidence').val(),enabled:$(this).find('.ipa-rule-enabled').is(':checked')?1:0,options_json:base.options_json||'{}'});});Fast.api.ajax({url:'ipa_center/writeback_save',data:{rules_json:JSON.stringify(rules)}},function(data){Toastr.success('已保存模板 v'+html(data.version||''));loadRules();return false;});});
            $('#ipa-writeback-random').on('click',function(){Fast.api.ajax({url:'ipa_center/writeback_random_preview',type:'GET'},function(data){renderPreview(data||{});return false;});});
            loadRules();
        },

        setting:function(){
            var form=$('#ipa-source-form');
            Form.api.bindevent(form,function(){Toastr.success('IPA 网络源已保存');setTimeout(function(){location.reload();},500);return false;});
            $('.btn-ipa-source-test').on('click',function(){
                if(form.data('validator') && !form.data('validator').isFormValid()) return false;
                Fast.api.ajax({url:'ipa_center/source_test',data:form.serialize()},function(){
                    $('#ipa-source-health').text('ok / '+new Date().toLocaleString());
                    Toastr.success('OpenList 连接正常');
                    return false;
                });
            });
        },

        api:{bindevent:function(){Form.api.bindevent($('form[role=form]'));}}
    };
    return Controller;
});
