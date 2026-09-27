<?php

require_once __DIR__ . '/../application/common/library/update/UpdateHttpClient.php';
require_once __DIR__ . '/../application/common/library/update/UpdateSourceInterface.php';
require_once __DIR__ . '/../application/common/library/update/GitHubUpdateSource.php';
require_once __DIR__ . '/../application/common/library/update/UpdateSqlRunner.php';
require_once __DIR__ . '/../application/common/library/update/UpdateInstaller.php';

use app\common\library\update\GitHubUpdateSource;
use app\common\library\update\UpdateHttpClient;
use app\common\library\update\UpdateInstaller;
use app\common\library\update\UpdateSqlRunner;

function fail_phase12($message)
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function ok_phase12($condition, $message)
{
    if (!$condition) {
        fail_phase12($message);
    }
}

class Phase12FakeUpdateHttpClient extends UpdateHttpClient
{
    public $calls = [];
    private $responses;

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function get($url, $maxBytes = self::MAX_TEXT_BYTES)
    {
        $this->calls[] = $url;
        return array_key_exists($url, $this->responses) ? $this->responses[$url] : false;
    }
}

ok_phase12(UpdateInstaller::isSafeArchivePath('program/application/a.php'), 'normal ZIP path should be accepted');
ok_phase12(!UpdateInstaller::isSafeArchivePath('../application/a.php'), 'parent traversal must be rejected');
ok_phase12(!UpdateInstaller::isSafeArchivePath('program/../../a.php'), 'nested parent traversal must be rejected');
ok_phase12(!UpdateInstaller::isSafeArchivePath('/etc/passwd'), 'absolute path must be rejected');
ok_phase12(!UpdateInstaller::isSafeArchivePath('C:/Windows/a.php'), 'drive-letter path must be rejected');

ok_phase12(UpdateInstaller::isProtectedRelativePath('application/database.php'), 'database.php must be protected');
ok_phase12(UpdateInstaller::isProtectedRelativePath('public/uploads/a.png'), 'public/uploads must be protected');
ok_phase12(UpdateInstaller::isProtectedRelativePath('runtime/cache/a.php'), 'runtime must be protected');
ok_phase12(!UpdateInstaller::isProtectedRelativePath('application/admin/controller/general/Config.php'), 'normal program file must remain updatable');

$sql = "-- comment\nINSERT INTO `t` (`v`) VALUES ('a;b');\n# comment 2\nUPDATE `t` SET `v`=\"c;d\" WHERE `v`='a;b';\n";
$parts = UpdateSqlRunner::splitStatements($sql);
ok_phase12(count($parts) === 2, 'SQL splitter must keep semicolons inside quoted values');
ok_phase12(strpos($parts[0], "'a;b'") !== false, 'first SQL statement should preserve quoted semicolon');

ok_phase12(GitHubUpdateSource::versionFromTag('source-v20260912') === '20260912', 'GitHub source tag version parsing failed');
ok_phase12(GitHubUpdateSource::versionFromTag('release-202609120230') === '202609120230', 'timestamp tag version parsing failed');
ok_phase12(GitHubUpdateSource::versionFromTag('other') === '', 'unrelated GitHub release must be ignored');

$releaseUrl = GitHubUpdateSource::RELEASES_URL;
$zipDigest = str_repeat('a', 64);
$shaFallback = str_repeat('b', 64);
$releasePayloadWithDigest = json_encode([
    [
        'tag_name' => 'source-v2026092416',
        'draft' => false,
        'prerelease' => false,
        'name' => '2416',
        'body' => '',
        'assets' => [
            ['name' => 'zonoe-online-update.zip', 'browser_download_url' => 'https://example.invalid/2416.zip', 'digest' => 'sha256:' . str_repeat('c', 64)],
            ['name' => 'zonoe-online-update.zip.sha256', 'browser_download_url' => 'https://example.invalid/2416.sha256'],
        ],
    ],
    [
        'tag_name' => 'source-v2026092417',
        'draft' => false,
        'prerelease' => false,
        'name' => '2417',
        'body' => '',
        'assets' => [
            ['name' => 'zonoe-online-update.zip', 'browser_download_url' => 'https://example.invalid/2417.zip', 'digest' => 'sha256:' . $zipDigest],
            ['name' => 'zonoe-online-update.zip.sha256', 'browser_download_url' => 'https://example.invalid/2417.sha256'],
        ],
    ],
]);
$fakeDigest = new Phase12FakeUpdateHttpClient([$releaseUrl => $releasePayloadWithDigest]);
$latestDigest = (new GitHubUpdateSource($fakeDigest))->latest();
ok_phase12(is_array($latestDigest) && $latestDigest['version'] === '2026092417', 'latest GitHub release should resolve');
ok_phase12($latestDigest['sha256'] === $zipDigest, 'GitHub asset digest should be used directly');
ok_phase12(count($fakeDigest->calls) === 1, 'latest check with GitHub asset digest must use a single HTTP request');

$releasePayloadFallback = json_encode([
    [
        'tag_name' => 'source-v2026092415',
        'draft' => false,
        'prerelease' => false,
        'name' => '2415',
        'body' => '',
        'assets' => [
            ['name' => 'zonoe-online-update.zip', 'browser_download_url' => 'https://example.invalid/2415.zip'],
            ['name' => 'zonoe-online-update.zip.sha256', 'browser_download_url' => 'https://example.invalid/2415.sha256'],
        ],
    ],
    [
        'tag_name' => 'source-v2026092417',
        'draft' => false,
        'prerelease' => false,
        'name' => '2417',
        'body' => '',
        'assets' => [
            ['name' => 'zonoe-online-update.zip', 'browser_download_url' => 'https://example.invalid/2417.zip'],
            ['name' => 'zonoe-online-update.zip.sha256', 'browser_download_url' => 'https://example.invalid/2417.sha256'],
        ],
    ],
]);
$fakeFallback = new Phase12FakeUpdateHttpClient([
    $releaseUrl => $releasePayloadFallback,
    'https://example.invalid/2417.sha256' => $shaFallback . "  zonoe-online-update.zip\n",
]);
$after = (new GitHubUpdateSource($fakeFallback))->packagesAfter('2026092416');
ok_phase12(count($after) === 1 && $after[0]['version'] === '2026092417', 'packagesAfter should filter versions before SHA lookup');
ok_phase12($after[0]['sha256'] === $shaFallback, 'selected update should resolve companion SHA256');
ok_phase12(count($fakeFallback->calls) === 2, 'packagesAfter must not fetch SHA256 for historical releases that will not be installed');
ok_phase12(!in_array('https://example.invalid/2415.sha256', $fakeFallback->calls, true), 'historical SHA256 request must be eliminated');

$controller = file_get_contents(__DIR__ . '/../application/admin/controller/general/Config.php');
ok_phase12(strpos($controller, "check('nuosike')") !== false, 'original updater must use shared manager');
ok_phase12(strpos($controller, "install('nuosike'") !== false, 'original installer must use shared manager');
ok_phase12(strpos($controller, "check('github')") !== false, 'GitHub updater check endpoint missing');
ok_phase12(strpos($controller, "install('github'") !== false, 'GitHub updater install endpoint missing');
ok_phase12(strpos($controller, 'CURLOPT_SSL_VERIFYPEER,false') === false, 'legacy TLS-disable logic must be removed from Config updater');
ok_phase12(strpos($controller, 'function carry_sql') === false, 'legacy silent SQL runner must no longer remain in controller');

$view = file_get_contents(__DIR__ . '/../application/admin/view/general/config/index.html');
ok_phase12(strpos($view, '>检查更新</button>') !== false, 'original update button must be retained');
ok_phase12(strpos($view, 'GitHub 在线更新') !== false, 'GitHub online update button missing');
ok_phase12(strpos($view, 'github_system_update') !== false, 'GitHub install endpoint not wired in UI');

$http = file_get_contents(__DIR__ . '/../application/common/library/update/UpdateHttpClient.php');
ok_phase12(strpos($http, 'CURLOPT_SSL_VERIFYPEER, $verify') !== false, 'update HTTP client must verify TLS by default');
ok_phase12(strpos($http, "SOURCE_UPDATE_VERIFY_TLS") !== false, 'emergency TLS compatibility flag missing');

$manager = file_get_contents(__DIR__ . '/../application/common/library/update/UpdateManager.php');
ok_phase12(strpos($manager, 'LOCK_EX | LOCK_NB') !== false, 'update lock missing');
ok_phase12(strpos($manager, 'localManifest') !== false, 'local update manifest reader missing');
ok_phase12(strpos($manager, '$localManifest[\'file_sign\']') !== false, 'integrity check must use local ver.json file_sign');
ok_phase12(strpos($manager, '$latest[\'file_sign\']') === false, 'remote file_sign must not be used for local tamper detection');

$authorization = file_get_contents(__DIR__ . '/../application/admin/controller/Authorization.php');
ok_phase12(strpos($authorization, "runtime' . DS . 'update_backup") !== false, 'diagnostic default backup path must stay inside site open_basedir');
ok_phase12(strpos($authorization, '@is_dir($directory)') !== false, 'diagnostic backup probe must tolerate open_basedir restrictions');
$upgrade121 = file_get_contents(__DIR__ . '/../tools/phase12_1_upgrade.sql');
ok_phase12(strpos($upgrade121, "100-`transfer_count`") !== false, 'Phase 12.1 transfer quota migration missing');
ok_phase12(strpos($upgrade121, "DEFAULT '100'") !== false, 'Phase 12.1 transfer quota default missing');

$backup = file_get_contents(__DIR__ . '/../application/common/library/update/UpdateBackup.php');
ok_phase12(strpos($backup, 'database.sql') !== false, 'database backup missing');
ok_phase12(strpos($backup, 'rollback') !== false, 'rollback implementation missing');

fwrite(STDOUT, "Phase 12 updater contract checks passed.\n");
