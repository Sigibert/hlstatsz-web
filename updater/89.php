<?php
if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

// ---------------------------------------------
// Language Support
// ---------------------------------------------
echo "<h3>Language Support</h3>";

echo "<br /><b>Adding Language option...</b><br />";
$db->query("INSERT IGNORE INTO hlstats_Options (keyname, value, opttype) VALUES ('Language', '', 2)");
echo "&rarr; Language option added (skipped if already exists)<br />";
ob_flush();
flush();

// ---------------------------------------------
// Web version
// ---------------------------------------------
echo "<h3>Web version</h3>";

echo "<br /><b>Adding Web version option...</b><br />";
$db->query("INSERT IGNORE INTO hlstats_Options (keyname, value, opttype) VALUES ('webversion', '1', 2)");
echo "&rarr; Web version option added (skipped if already exists)<br />";
ob_flush();
flush();

// ---------------------------------------------
// Chart option
// ---------------------------------------------
echo "<h3>Chart's Option</h3>";

echo "<br /><b>Adding chart option for pChart or Chart.js...</b><br />";

$db->query("INSERT IGNORE INTO hlstats_Options (keyname, value, opttype) VALUES ('chart', '0', 2)");

$db->query("DELETE FROM hlstats_Options_Choices WHERE keyname = 'chart'");

$db->query("INSERT INTO hlstats_Options_Choices (keyname, value, text, isDefault) VALUES ('chart', 0,'pChart', 1)");
$db->query("INSERT INTO hlstats_Options_Choices (keyname, value, text, isDefault) VALUES ('chart', 1,'Chart.js', 0)");
echo "  &rarr; <b>Chart type</b> inserted (skipped if already exists)<br />";


$dbversion = 89;
echo "Updating database schema version.<br />";
$db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");

?>
