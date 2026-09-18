<?php
require_once dirname(__DIR__) . '/application/common/library/IpaWritebackTemplate.php';

use app\common\library\IpaWritebackTemplate;

function phase205Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase20_writeback_contract_test: {$message}\n");
        exit(1);
    }
}

phase205Assert(in_array('name', IpaWritebackTemplate::allowedTargets(), true), 'name target allowed');
phase205Assert(in_array('keywords', IpaWritebackTemplate::allowedTargets(), true), 'keywords target allowed');
phase205Assert(in_array('managed_block', IpaWritebackTemplate::allowedStrategies(), true), 'managed block supported');

$managed = IpaWritebackTemplate::managedBlock('人工说明', "版本：1.2.3\n最低系统要求：iOS 15.0");
phase205Assert(strpos($managed, '<!-- IPA_META_START -->') !== false, 'managed block start');
phase205Assert(strpos($managed, '人工说明') !== false, 'human text preserved');
$managed2 = IpaWritebackTemplate::managedBlock($managed, '版本：2.0.0');
phase205Assert(substr_count($managed2, '<!-- IPA_META_START -->') === 1, 'managed block replaced not duplicated');
phase205Assert(strpos($managed2, '版本：2.0.0') !== false, 'managed block updated');

phase205Assert(IpaWritebackTemplate::shouldApply('empty', '', 'x') === true, 'empty applies to blank');
phase205Assert(IpaWritebackTemplate::shouldApply('empty', 'old', 'x') === false, 'empty preserves existing');
phase205Assert(IpaWritebackTemplate::shouldApply('changed', 'old', 'new') === true, 'changed applies on diff');
phase205Assert(IpaWritebackTemplate::shouldApply('preview', 'old', 'new') === false, 'preview never mutates');

$category = ['name'=>'错误名称','nickname'=>'9.9.9','image'=>'','bt1a'=>'/old','bt2a'=>'1','keywords'=>'人工说明'];
$metadata = [
    'package_name'=>'Demo App','package_version'=>'1.2.3','package_build'=>'123','bundle_id'=>'com.demo.app','minimum_ios'=>'15.0',
    'public_url'=>'https://example.test/Demo.ipa','file_size'=>123456,
    'normalized_metadata_json'=>json_encode(['primary_icon'=>['name'=>'AppIcon60x60@2x.png']]),
    'confidence_json'=>json_encode(['package_name'=>'exact','package_version'=>'exact','public_url'=>'exact','file_size'=>'exact'])
];
$preview = IpaWritebackTemplate::preview($category,$metadata,IpaWritebackTemplate::defaultRules());
phase205Assert(count($preview) === 6, 'six default preview rules');
$applyCount=0;foreach($preview as $row)if($row['will_apply'])$applyCount++;
phase205Assert($applyCount >= 5, 'default preview detects expected changes');

$thrown=false;
try{IpaWritebackTemplate::validateRules([
    ['rule_key'=>'a','source_field'=>'package_name','target_field'=>'name','strategy'=>'changed','min_confidence'=>'exact','enabled'=>1],
    ['rule_key'=>'b','source_field'=>'package_version','target_field'=>'name','strategy'=>'always','min_confidence'=>'exact','enabled'=>1],
]);}catch(Exception $e){$thrown=true;}
phase205Assert($thrown,'duplicate enabled target rejected');

$controller=file_get_contents(dirname(__DIR__).'/application/admin/controller/IpaCenter.php');
foreach(['writebackRules','writebackSeed','writebackSave','writebackRandomPreview'] as $action){phase205Assert(strpos($controller,'function '.$action.'(')!==false,'controller action '.$action);}
$view=file_get_contents(dirname(__DIR__).'/application/admin/view/ipa_center/writeback.html');
foreach(['保存为新版本','随机选择已绑定 IPA 测试','IPA_META_START','ipa-writeback-preview-table'] as $needle){phase205Assert(strpos($view,$needle)!==false,'writeback UI '.$needle);}
$sql=file_get_contents(dirname(__DIR__).'/application/admin/command/Install/phase20_ipa_writeback.sql');
foreach(['writeback_rules','writeback_seed','writeback_save','writeback_random_preview'] as $rule){phase205Assert(strpos($sql,$rule)!==false,'auth rule '.$rule);}

echo "OK phase20_writeback_contract_test\n";
