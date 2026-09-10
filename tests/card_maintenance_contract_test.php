<?php

function cardContractFail($message)
{
    fwrite(STDERR, "FAIL card_maintenance_contract_test: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/application/admin/controller/Kami.php');
$model = file_get_contents($root . '/application/admin/model/Kami.php');

foreach (array(
    'CardCodeGenerator::generateUniqueBatch',
    "->where('kami', 'in', \$candidates)",
    "if (\$inserted !== 1)",
    "in_array(\$type, [1, 2, 3, 4, 5], true)",
) as $needle) {
    if (strpos($controller, $needle) === false) {
        cardContractFail('Kami controller missing: ' . $needle);
    }
}
foreach (array('rand(', 'md5($timeSeed') as $legacy) {
    if (strpos($controller, $legacy) !== false) {
        cardContractFail('legacy weak generator remains: ' . $legacy);
    }
}

if (substr_count($model, "return \$value === '' ? 0") < 3) {
    cardContractFail('Kami NOT NULL time fields do not normalize blank values to zero');
}

echo "OK card_maintenance_contract_test\n";
