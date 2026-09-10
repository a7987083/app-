<?php

require_once dirname(__DIR__) . '/application/common/library/CardCodeGenerator.php';

use app\common\library\CardCodeGenerator;

function cardAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL card_code_generator_test: {$message}\n");
        exit(1);
    }
}

$code = CardCodeGenerator::generate('zn-');
cardAssert(strpos($code, 'ZN-') === 0, 'prefix should remain and be uppercased');
cardAssert((bool)preg_match('/^ZN-[0-9A-F]{12}$/', $code), 'legacy visible format');

$batch = CardCodeGenerator::generateBatch(200, 'p');
cardAssert(count($batch) === 200, 'batch size');
cardAssert(count(array_unique($batch)) === 200, 'batch uniqueness');
foreach ($batch as $item) {
    cardAssert((bool)preg_match('/^P[0-9A-F]{12}$/', $item), 'batch format');
}

$resolverCalls = 0;
$unique = CardCodeGenerator::generateUniqueBatch(8, 'u', function (array $candidates) use (&$resolverCalls) {
    $resolverCalls++;
    return $resolverCalls === 1 ? array($candidates[0]) : array();
});
cardAssert(count($unique) === 8 && count(array_unique($unique)) === 8, 'storage collision retry');
cardAssert($resolverCalls === 2, 'storage resolver retry count');

$failed = false;
try {
    CardCodeGenerator::generate(str_repeat('x', 117));
} catch (\InvalidArgumentException $e) {
    $failed = true;
}
cardAssert($failed, 'overlong prefix rejected');

echo "OK card_code_generator_test\n";
