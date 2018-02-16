<?php

$cfg = require __DIR__ . '/../../vendor/mediawiki/mediawiki-phan-config/src/config.php';
// MigrationEditPage::$mTitle false-positive
$cfg['suppress_issue_types'][] = 'PhanDeprecatedProperty';

return $cfg;
