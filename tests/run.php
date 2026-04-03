<?php

require __DIR__ . '/bootstrap.php';

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    require $file;
}

$passed = 0;
$failed = 0;

foreach ($GLOBALS['kbs_tests'] as $test) {
    try {
        if (function_exists('kbs_test_reset_env')) {
            kbs_test_reset_env();
        }
        $test['callback']();
        $passed++;
        fwrite(STDOUT, "[PASS] {$test['name']}\n");
    } catch (Throwable $throwable) {
        $failed++;
        fwrite(STDERR, "[FAIL] {$test['name']}: {$throwable->getMessage()}\n");
    }
}

fwrite(STDOUT, sprintf("\n%d passed, %d failed\n", $passed, $failed));

exit($failed > 0 ? 1 : 0);
