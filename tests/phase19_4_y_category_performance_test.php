<?php

$root = dirname(__DIR__);

function expect_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function read_text($path)
{
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "FAIL: unable to read {$path}\n");
        exit(1);
    }
    return $content;
}

$controller = read_text($root . '/application/admin/controller/Category.php');
$add = read_text($root . '/application/admin/view/category/add.html');
$edit = read_text($root . '/application/admin/view/category/edit.html');
$js = read_text($root . '/public/assets/js/backend/category.js');
$manifest = read_text($root . '/release/online-update-files.txt');

// add/edit must never rebuild the entire Category tree for the hidden pid field.
expect_true(strpos($controller, 'buildParentList') === false, 'Category controller must not contain buildParentList');
expect_true(strpos($controller, 'Category::select()') === false, 'edit path must not call Category::select() full-table load');
expect_true(strpos($controller, 'use fast\\Tree;') === false, 'Category controller must not depend on Tree');
expect_true(strpos($controller, '$params[\'pid\'] = 0;') !== false, 'add must force pid=0');
expect_true(strpos($controller, '$params[\'pid\'] = (int)$row[\'pid\'];') !== false, 'edit must preserve existing pid');

// Hidden parent selectpicker must be replaced by one cheap hidden input.
expect_true(strpos($add, 'name="row[pid]" type="hidden" value="0"') !== false, 'add must use hidden pid=0');
expect_true(strpos($edit, 'name="row[pid]" type="hidden" value="{$row.pid}"') !== false, 'edit must keep original pid in hidden input');
expect_true(strpos($add, 'parentList') === false && strpos($edit, 'parentList') === false, 'templates must not render parentList');
expect_true(strpos($add, 'id="c-pid"') === false && strpos($edit, 'id="c-pid"') === false, 'templates must not initialize hidden pid selectpicker');

// User explicitly keeps 1000 rows/page.
expect_true(strpos($js, 'pageSize: 1000') !== false, 'category list must keep pageSize 1000');
expect_true(strpos($js, 'pageList: [200, 500, 1000]') !== false, 'category list must keep 200/500/1000 page sizes');

// add/edit close only; both row and toolbar delete remove rows locally and suppress full refresh.
expect_true(substr_count($js, 'Controller.api.bindevent(Controller.api.closeWithoutParentRefresh)') >= 2, 'add/edit must use no-refresh success callback');
expect_true(strpos($js, "bootstrapTable('remove'") !== false, 'delete success must remove row locally');
expect_true(strpos($js, '#toolbar .btn-del') !== false, 'toolbar delete must install no-refresh callback');
expect_true(strpos($js, '.btn-delone') !== false, 'row delete must install no-refresh callback');
expect_true(strpos($js, 'return false;') !== false, 'CRUD callbacks must suppress FastAdmin automatic refresh');

foreach ([
    'application/admin/controller/Category.php',
    'application/admin/view/category/add.html',
    'application/admin/view/category/edit.html',
    'public/assets/js/backend/category.js',
] as $required) {
    expect_true(strpos($manifest, $required) !== false, "online update manifest missing {$required}");
}

echo "OK phase19_4_y_category_performance_test no_full_tree=passed page_size_1000=passed no_refresh=passed online_update=passed\n";
