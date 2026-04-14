<?php
/**
 * Sidecar-missing test.
 *
 * If the sidecar file is missing but .crob exists, Brain must warn (via
 * error_log) and treat provenance as empty — not crash, not silently continue.
 */

require_once __DIR__ . '/../src/Brain.php';

$tmpDir = __DIR__ . '/tmp-brain-data';
if (is_dir($tmpDir)) {
    array_map('unlink', glob("$tmpDir/*") ?: []);
    rmdir($tmpDir);
}
mkdir($tmpDir, 0755, true);

// Populate knowledge + sidecar
$brain = new Brain($tmpDir);
$brain->learn('GSAP', Brain::REL_IS, 'animation library', 0.5, 'https://greensock.com');
unset($brain);

// Delete only the sidecar, leave .crob intact
@unlink($tmpDir . '/crob.provenance.json');

if (!file_exists($tmpDir . '/crob.crob')) {
    echo "FAIL: setup did not create crob.crob\n";
    exit(1);
}
if (file_exists($tmpDir . '/crob.provenance.json')) {
    echo "FAIL: sidecar delete did not work\n";
    exit(1);
}

// Capture error_log output by redirecting to a tmp file
$errorLog = $tmpDir . '/error.log';
ini_set('error_log', $errorLog);

$failures = [];

try {
    $brain2 = new Brain($tmpDir);
} catch (Throwable $e) {
    $failures[] = "Brain construction crashed: " . $e->getMessage();
    $brain2 = null;
}

if ($brain2 !== null) {
    // Provenance should be empty
    $prov = $brain2->getProvenance('GSAP', Brain::REL_IS);
    if (!empty($prov)) {
        $failures[] = "provenance should be empty after sidecar missing, got: " . json_encode($prov);
    }

    // Knowledge should still be intact
    $q = $brain2->query('GSAP');
    if (!$q || empty($q['knowledge'])) {
        $failures[] = ".crob knowledge was lost when sidecar missing";
    }
}

// Verify warning was logged
if (file_exists($errorLog)) {
    $logContents = file_get_contents($errorLog);
    if (stripos($logContents, 'provenance sidecar missing') === false) {
        $failures[] = "error_log did not contain warning about missing sidecar";
    }
} else {
    $failures[] = "error_log was not written";
}

array_map('unlink', glob("$tmpDir/*") ?: []);
rmdir($tmpDir);

if (empty($failures)) {
    echo "PASS: missing sidecar warns and continues with empty provenance\n";
    exit(0);
}

echo "FAIL:\n";
foreach ($failures as $f) echo "  - $f\n";
exit(1);
