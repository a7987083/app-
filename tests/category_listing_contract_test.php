<?php

function categoryListingFail($message)
{
    fwrite(STDERR, "FAIL category_listing_contract_test: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/application/admin/controller/Category.php');
$js = file_get_contents($root . '/public/assets/js/backend/category.js');
$addView = file_get_contents($root . '/application/admin/view/category/add.html');
$editView = file_get_contents($root . '/application/admin/view/category/edit.html');

foreach (array(
    "protected \$searchFields = 'name'",
    "buildparams('name')",
    "[200, 500, 1000]",
    "->limit(\$offset, \$limit)",
    "field('id,type,name,nickname,keywords,bt2b,beizhu,image,weigh,status')",
) as $needle) {
    if (strpos($controller, $needle) === false) {
        categoryListingFail('controller missing: ' . $needle);
    }
}

// 1809 performance contract: add/edit must not rebuild the entire Category tree.
foreach (array('buildParentList', 'Category::select()', 'use fast\\Tree;') as $legacyTree) {
    if (strpos($controller, $legacyTree) !== false) {
        categoryListingFail('legacy full-tree path remains: ' . $legacyTree);
    }
}
if (strpos($controller, '$params[\'pid\'] = 0;') === false) {
    categoryListingFail('add does not force pid=0');
}
if (strpos($controller, '$params[\'pid\'] = (int)$row[\'pid\'];') === false) {
    categoryListingFail('edit does not preserve existing pid');
}

foreach (array(
    "sidePagination: 'server'",
    'pagination: true',
    "paginationVAlign: 'both'",
    'pageSize: 1000',
    'pageList: [200, 500, 1000]',
    "params.type = currentType",
    "table.bootstrapTable('refresh', {pageNumber: 1})",
) as $needle) {
    if (strpos($js, $needle) === false) {
        categoryListingFail('javascript missing: ' . $needle);
    }
}

foreach (array(
    "field: 'keywords', title: __('软件说明'), width:'360px', visible: false",
    "field: 'beizhu', title: __('备注'), visible: false",
    "field: 'image', title: __('应用图标'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image, visible: false",
    "field: 'weigh', title: __('Weigh'), visible: false",
) as $needle) {
    if (strpos($js, $needle) === false) {
        categoryListingFail('default hidden column missing: ' . $needle);
    }
}

foreach (array('var allRows', 'applyCategorySearch', "params.search = ''", 'bootstrapTable.onSearch') as $legacy) {
    if (strpos($js, $legacy) !== false) {
        categoryListingFail('legacy client-side full-list search remains: ' . $legacy);
    }
}

// Add/edit are intentionally manual-refresh only.
if (substr_count($js, 'Controller.api.bindevent(Controller.api.closeWithoutParentRefresh)') < 2 ||
    strpos($js, 'parent.Layer.close(index)') === false) {
    categoryListingFail('category add/edit do not use no-refresh close callback');
}
$addStart = strpos($js, 'add: function ()');
$editStart = strpos($js, 'edit: function ()');
$addBody = substr($js, $addStart, $editStart - $addStart);
if (strpos($addBody, 'btn-refresh') !== false) {
    categoryListingFail('category add still refreshes the parent list');
}

// Hidden pid must stay lightweight and must not become selectpicker again.
if (strpos($addView, 'name="row[pid]" type="hidden" value="0"') === false) {
    categoryListingFail('add pid is not hidden pid=0');
}
if (strpos($editView, 'name="row[pid]" type="hidden" value="{$row.pid}"') === false) {
    categoryListingFail('edit pid is not preserved in hidden input');
}
if (strpos($addView, 'parentList') !== false || strpos($editView, 'parentList') !== false ||
    strpos($addView, '<select id="c-pid"') !== false || strpos($editView, '<select id="c-pid"') !== false) {
    categoryListingFail('hidden pid full-tree select remains');
}

if (strpos($addView, '<option value="1" selected>付费</option>') === false) {
    categoryListingFail('new category default payment mode is not paid');
}

echo "OK category_listing_contract_test\n";
