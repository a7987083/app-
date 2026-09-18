define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var html = function (value) { return $('<div/>').text(value == null ? '' : String(value)).html(); };
    var Controller = {
        index: function () {}, metadata: function () {}, binding: function () {},
        governance: function () { $('.ipa-governance-card').on('click', function () { $('.ipa-governance-card').removeClass('ipa-active'); $(this).addClass('ipa-active'); }); },
        task: function () {
            var loadTasks = function () {
                Fast.api.ajax({url:'ipa_center/task_list',loading:false}, function (data) {
                    var rows=data.rows||[], body=$('#ipa-task-table tbody'); body.empty();
                    if(!rows.length){body.append('<tr><td colspan="8" class="text-center text-muted">暂无扫描任务</td></tr>');return false;}
                    $.each(rows,function(_,row){var c=row.cursor||{};var discovered=['新 '+(c['new']||0),'变化 '+(c.changed||0),'未变 '+(c.unchanged||0),'缺失 '+(c.missing||0)].join(' / ');var progress=(row.progress_current||0)+' / '+(row.progress_total||0);body.append('<tr><td>#'+html(row.id)+'</td><td>'+html(row.trigger_type)+'</td><td>'+html(row.stage)+'</td><td>'+html(progress)+'</td><td>'+html(discovered)+'</td><td>'+html(row.started_at_text)+'</td><td>'+html(row.finished_at_text)+'</td><td>'+html(row.state)+'</td></tr>');});
                    return false;
                },function(){return false;});
            };
            var start=function(refresh){Fast.api.ajax({url:'ipa_center/scan_start',data:{refresh:refresh?1:0}},function(){loadTasks();return false;});};
            $('.btn-ipa-scan').on('click',function(){start(false);});
            $('.btn-ipa-scan-refresh').on('click',function(){start(true);});
            $('.btn-ipa-task-refresh').on('click',loadTasks);
            loadTasks(); window.setInterval(loadTasks,5000);
        },
        writeback: function () {},
        setting: function () {
            $('.btn-ipa-source-save').on('click',function(){Fast.api.ajax({url:'ipa_center/source_save',data:$('#ipa-source-form').serialize()},function(){window.location.reload();return false;});});
            $('.btn-ipa-source-test').on('click',function(){Fast.api.ajax({url:'ipa_center/source_test',data:{}},function(){window.location.reload();return false;});});
        },
        api:{bindevent:function(){Form.api.bindevent($('form'));}}
    };
    return Controller;
});
