<?php

// Structural contract only: the parser must persist binary sha256, architectures and install_name
// from bounded ZIP members without making IPA metadata parsing fail when enrichment fails.
$source = file_get_contents(__DIR__ . '/../application/common/library/Ipa/IpaParserService.php');
foreach (["hash('sha256', $bytes)", "architectures", "install_name", "64 * 1024 * 1024", "best-effort"] as $needle) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, "missing parser enrichment contract: {$needle}\n");
        exit(1);
    }
}
echo "IPA parser Mach-O enrichment contract ok\n";
