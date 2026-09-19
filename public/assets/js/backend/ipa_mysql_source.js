define(['jquery','bootstrap','backend','table','form'],function($,undefined,Backend,Table,Form){
    'use strict';
    var html=function(v){return $('<div/>').text(v==null?'':String(v)).html();};
    var table=$('#mysql-source-table');
    var reset=function(){
        var form=$('#mysql-source-form');
        form[0].reset();
        form.find('[name=id]').val('');
        form.find('[name=host]').val('127.0.0.1');form.find('[name=port]').val('3306');form.find('[name=table]').val('fa_category');form.find('[name=priority]').val('100');
        form.find('[name=password]').val('');form.find('[name=enabled][type=checkbox]').prop('checked',true);
        form.find('.save-label').text('新增软件源');$('#btn-source-cancel').addClass('hide');
    };
    var reload=function(){table.bootstrapTable('refresh',{silent:true});};
    var Controller={
        index:function(){
            Table.api.init({extend:{table:'ipa_mysql_source'}});
            var events={
                'click .btn-source-test':function(e,v,r){Fast.api.ajax({url:'ipa_mysql_source/source_test',data:{id:r.id}},function(data){Toastr.success('连接正常'+(data&&data.latency_ms!==undefined?' · '+data.latency_ms+' ms':''));reload();return false;});},
                'click .btn-source-edit':function(e,v,r){var f=$('#mysql-source-form');f.find('[name=id]').val(r.id);f.find('[name=name]').val(r.name||'');f.find('[name=slug]').val(r.slug||'');f.find('[name=host]').val(r.host||'127.0.0.1');f.find('[name=port]').val(r.port||3306);f.find('[name=database]').val(r.database||'');f.find('[name=username]').val(r.username||'');f.find('[name=password]').val('');f.find('[name=table]').val(r.table||'fa_category');f.find('[name=priority]').val(r.priority==null?100:r.priority);f.find('[name=enabled][type=checkbox]').prop('checked',parseInt(r.enabled,10)===1);f.find('.save-label').text('保存修改');$('#btn-source-cancel').removeClass('hide');$('html,body').animate({scrollTop:f.offset().top-70},150);},
                'click .btn-source-delete':function(e,v,r){Layer.confirm('确定删除软件源 '+html(r.name||r.slug)+'？此操作只删除连接配置，不会删除原数据库数据。',function(i){Layer.close(i);Fast.api.ajax({url:'ipa_mysql_source/source_delete',data:{id:r.id}},function(){reload();return false;});});}
            };
            table.bootstrapTable({url:'ipa_mysql_source/source_list',pagination:false,search:false,showRefresh:true,showToggle:false,showColumns:false,pk:'id',sortName:'priority',sortOrder:'asc',responseHandler:function(res){var d=res&&res.data!==undefined?res.data:res;d=d||{};return {rows:d.rows||[],total:parseInt(d.total||0,10)};},columns:[[
                {field:'name',title:'名称',formatter:function(v,r){return '<strong>'+html(v||'-')+'</strong><br><small>'+html(r.slug||'')+'</small>'; }},
                {field:'host',title:'连接',formatter:function(v,r){return html((v||'')+':'+(r.port||3306)+'/'+(r.database||''));}},
                {field:'table',title:'应用表',formatter:function(v){return '<code>'+html(v||'fa_category')+'</code>'; }},
                {field:'priority',title:'优先级'},
                {field:'enabled',title:'状态',formatter:function(v,r){var health=r.last_health||'unknown';return (parseInt(v,10)?'<span class="label label-success">启用</span>':'<span class="label label-default">停用</span>')+' <small>'+html(health)+'</small>'; }},
                {field:'operate',title:'操作',events:events,formatter:function(){return '<button class="btn btn-xs btn-info btn-source-test">测试</button> <button class="btn btn-xs btn-primary btn-source-edit">编辑</button> <button class="btn btn-xs btn-danger btn-source-delete">删除</button>';}}
            ]]});
            Table.api.bindevent(table);
            $('#btn-source-save').on('click',function(){var form=$('#mysql-source-form'),data={};$.each(form.serializeArray(),function(_,x){data[x.name]=x.value;});data.enabled=form.find('[name=enabled][type=checkbox]').is(':checked')?1:0;Fast.api.ajax({url:'ipa_mysql_source/source_save',data:data},function(){reset();reload();return false;});});
            $('#btn-source-cancel').on('click',reset);
        }
    };
    return Controller;
});
