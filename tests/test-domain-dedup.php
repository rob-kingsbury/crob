<?php
/**
 * Domain-level source dedup test.
 *
 * Two URLs on the same domain (with and without www.) must count as ONE source.
 * Otherwise DuckDuckGo results pages on the same site would produce false
 * corroboration.
 */

require_once __DIR__ . '/../src/Brain.php';

$tmpDir = __DIR__ . '/tmp-brain-data';
if (is_dir($tmpDir)) {
    array_map('unlink', glob("$tmpDir/*") ?: []);
    rmdir($tmpDir);
}
mkdir($tmpDir, 0755, true);

$brain = new Brain($tmpDir);
$failures = [];

// First learn from greensock.com/page1
$brain->learn('GSAP', Brain::REL_IS, 'animation library', 0.5, 'https://greensock.com/page1');

// Second learn from www.greensock.com/page2 — should be same source (www. stripped)
$r = $brain->learn('GSAP', Brain::REL_IS, 'animation library', 0.5, 'https://www.greensock.com/page2');

// This should be restatement (same domain after www strip, same object)
// not corroborated.
if ($r['tier'] !== 'restatement') {
    $failures[] = "expected tier=restatement (same domain), got {$r['tier']}";
}

// Sidecar should show one source, not two
$prov = $brain->getProvenance('GSAP', Brain::REL_IS);
if (count($prov['sources']) !== 1) {
    $failures[] = "expected 1 source after domain dedup, got " . count($prov['sources']);
}
if (!in_array('greensock.com', $prov['sources'], true)) {
    $failures[] = "expected 'greensock.com' in sources, got: " . implode(',', $prov['sources']);
}

// Now learn from a DIFFERENT domain — should corroborate
$r = $brain->learn('GSAP', Brain::REL_IS, 'animation library', 0.5, 'https://css-tricks.com/gsap');
if ($r['tier'] !== 'corroborated') {
    $failures[] = "expected tier=corroborated (new domain), got {$r['tier']}";
}

// direct_teach sentinel should be stored as-is and count as one stable source
$brain2 = new Brain($tmpDir . '-teach');
@mkdir($tmpDir . '-teach', 0755, true);
$brain2->learn('PHP', Brain::REL_IS, 'programming language', 0.9, 'direct_teach');
$r = $brain2->learn('PHP', Brain::REL_IS, 'programming language', 0.9, 'direct_teach');
if ($r['tier'] !== 'restatement') {
    $failures[] = "expected second direct_teach to be restatement, got {$r['tier']}";
}
$prov2 = $brain2->getProvenance('PHP', Brain::REL_IS);
if (count($prov2['sources']) !== 1 || $prov2['sources'][0] !== 'direct_teach') {
    $failures[] = "expected direct_teach as single source, got: " . json_encode($prov2['sources']);
}

// Cleanup
array_map('unlink', glob("$tmpDir/*") ?: []);
rmdir($tmpDir);
array_map('unlink', glob("$tmpDir-teach/*") ?: []);
@rmdir($tmpDir . '-teach');

if (empty($failures)) {
    echo "PASS: domain-level dedup collapses URL variants and handles sentinels\n";
    exit(0);
}

echo "FAIL:\n";
foreach ($failures as $f) echo "  - $f\n";
exit(1);
