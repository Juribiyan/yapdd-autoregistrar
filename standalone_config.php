<?php
define('KU_DBTYPE', 'mysqli');
define('KU_DBHOST', 'localhost');
define('KU_DBDATABASE', 'insert_name_here');
define('KU_DBUSERNAME', 'root');
define('KU_DBPASSWORD', '');
define('KU_DBUSEPERSISTENT', false);
// hCaptcha keys for your site. Obtain here: https://hCaptcha.com/?r=bcc1116c3b44
define('I0_HCAPTCHA_SITEKEY', '');
define('I0_HCAPTCHA_SECRET', '');

// do not edit below this line
require 'adodb/adodb.inc.php';
if (!isset($tc_db) && !isset($preconfig_db_unnecessary)) {
	$tc_db = &NewADOConnection(KU_DBTYPE);
	if (KU_DBUSEPERSISTENT) {
		$tc_db->PConnect(KU_DBHOST, KU_DBUSERNAME, KU_DBPASSWORD, KU_DBDATABASE) or retreat('SQL database connection error: ' . $tc_db->ErrorMsg());
	} else {
		$tc_db->Connect(KU_DBHOST, KU_DBUSERNAME, KU_DBPASSWORD, KU_DBDATABASE) or retreat('SQL database connection error: ' . $tc_db->ErrorMsg());
	}
}