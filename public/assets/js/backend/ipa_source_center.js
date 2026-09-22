define(['jquery','bootstrap','backend','table','form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            function esc(v){ return $('<div>').text(v === null || typeof v === 'undefined' ? '' : String(v)).html(); }
            $('#mysql-source-table').bootstrapTable({
                url:'ipa_source_center/listSources', sidePagination:'server', pagination:false,
                columns:[[
                    {field:'id',title:'ID'},
                    {field:'name',title:'名称'},
                    {field:'slug',title:'Slug'},
                    {field:'host',title:'连接',formatter:function(v,r){return esc(r.host+':'+r.port+'/'+r.database_name);}},
                    {field:'table_name',title:'应用表'},
                    {field:'priority',title:'优先级'},
                    {field:'enabled',title:'状态',formatter:function(v){return parseInt(v,10)===1?'<span class="label label-success">启用</span>':'<span class="label label-default">停用</span>'; }},
                    {field:'allow_write',title:'写回',formatter:function(v){return parseInt(v,10)===1?'<span class="label label-warning">允许</span>':'<span class="label label-info">只读</span>'; }},
                    {field:'operate',title:'操作',formatter:function(v,r){
                        var data=' data-id="'+r.id+'" data-name="'+esc(r.name)+'" data-slug="'+esc(r.slug)+'" data-host="'+esc(r.host)+'" data-port="'+r.port+'" data-db="'+esc(r.database_name)+'" data-user="'+esc(r.username)+'" data-table="'+esc(r.table_name)+'" data-priority="'+r.priority+'" data-enabled="'+r.enabled+'" data-write="'+r.allow_write+'"';
                        return '<button class="btn btn-xs btn-success btn-mysql-test" data-id="'+r.id+'">测试</button> <button class="btn btn-xs btn-primary btn-mysql-edit"'+data+'>编辑</button> <button class="btn btn-xs btn-danger btn-mysql-delete" data-id="'+r.id+'" data-name="'+esc(r.name)+'">删除</button>';
                    }}
                ]]
            });
            function reset(){
                var f=$('#mysql-source-form'); f[0].reset(); f.find('[name=id]').val('0'); f.find('[name=host]').val('127.0.0.1'); f.find('[name=port]').val('3306'); f.find('[name=table_name]').val('fa_category'); f.find('[name=priority]').val('100'); f.find('[name=enabled]').prop('checked',true); f.find('[name=allow_write]').prop('checked',false); $('#mysql-source-cancel').addClass('hide');
            }
            $('#mysql-source-form').on('submit',function(e){ e.preventDefault(); Fast.api.ajax({url:'ipa_source_center/saveSource',type:'POST',data:$(this).serialize()},function(){ $('#mysql-source-table').bootstrapTable('refresh'); reset(); return false; }); });
            $(document).on('click','.btn-mysql-edit',function(){
                var b=$(this),f=$('#mysql-source-form');
                f.find('[name=id]').val(b.data('id')); f.find('[name=name]').val(b.attr('data-name')); f.find('[name=slug]').val(b.attr('data-slug')); f.find('[name=host]').val(b.attr('data-host')); f.find('[name=port]').val(b.attr('data-port')); f.find('[name=database_name]').val(b.attr('data-db')); f.find('[name=username]').val(b.attr('data-user')); f.find('[name=password]').val(''); f.find('[name=table_name]').val(b.attr('data-table')); f.find('[name=priority]').val(b.attr('data-priority')); f.find('[name=enabled]').prop('checked',String(b.attr('data-enabled'))==='1'); f.find('[name=allow_write]').prop('checked',String(b.attr('data-write'))==='1'); $('#mysql-source-cancel').removeClass('hide'); $('html,body').animate({scrollTop:f.offset().top-70},150);
            });
            $('#mysql-source-cancel').on('click',reset);
            $(document).on('click','.btn-mysql-test',function(){ Fast.api.ajax({url:'ipa_source_center/testSource',type:'POST',data:{id:$(this).data('id')}},function(resp){ Toastr.success(resp.msg||'连接成功'); return false; }); });
            $(document).on('click','.btn-mysql-delete',function(){ var b=$(this); Layer.confirm('确定删除软件源“'+esc(b.attr('data-name'))+'”吗？只会删除软件源配置和本地比对缓存，不会删除目标数据库数据。',{title:'删除软件源'},function(i){ Layer.close(i); Fast.api.ajax({url:'ipa_source_center/deleteSource',type:'POST',data:{id:b.data('id')}},function(){ $('#mysql-source-table').bootstrapTable('refresh'); return false; }); }); });
        }
    };
    return Controller;
});
