<?php

function categoryListingFail($message)
{
    fwrite(STDERR, "FAIL category_listing_contract_test: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/application/admin/controller/Category.php');
$js = file_get_contents($root . '/public/assets/js/backend/category.js');

foreach (array(
    "protected \$searchFields = 'name'",
    "buildparams('name')",
    "[200, 500, 1000]",
    "->limit(\$offset, \$limit)",
    "field('id,type,name,nickname,keywords,bt2b,beizhu,image,weigh,status')",
    "buildParentList()",
) as $needle) {
    if (strpos($controller, $needle) === false) {
        categoryListingFail('controller missing: ' . $needle);
    }
}

$indexStart = strpos($controller, 'public function index()');
$parentStart = strpos($controller, 'protected function buildParentList()');
$indexBody = substr($controller, $indexStart, $parentStart - $indexStart);
if (strpos($indexBody, "foreach (\$this->categorylist as \$v)") !== false) {
    categoryListingFail('index still filters the full in-memory category tree');
}

foreach (array(
    "sidePagination: 'server'",
    'pagination: true',
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

echo "OK category_listing_contract_test\n";
