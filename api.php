<?php
define('API_HOST', 'https://pddimp.yandex.ru');
define('DEBUG', false);

mb_internal_encoding("UTF-8");
error_reporting(E_ALL ^ E_NOTICE);
if (!headers_sent()) {
  header('Content-Type: text/html; charset=utf-8');
}

session_start();
require 'common_config.php';
define('TOP_COOKIE', 2147483647); // max cookie life (http://stackoverflow.com/a/22479460/1561204)
if(CONFIG_ENVIRONMENT == 'instant')
	require __DIR__.'/../config.php';
else
	require 'standalone_config.php';
$tc_db->SetFetchMode(ADODB_FETCH_ASSOC);

$valid_ip_rx = "/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/i";

if(!preg_match($valid_ip_rx, $ip))
	retreat('wrong-ip');

if(isset($_COOKIE['yapdd_autoreg_id'])) {
	$uid = $_COOKIE['yapdd_autoreg_id'];
}
else 
	$newuser = true;

if(!isset($_GET['action']))
	retreat('no-action');
$action = $_GET['action'];

if($action == 'view') {
	if(isset($_POST['do_login']))
		$uid = login();

	if(!$uid) {
    is_virgin_ip();
    advance(array());
  }

  $logins = cull_boxes($uid);
  if(empty($logins) && $uid != md5($ip.SALT)) {
    rm_cookie('yapdd_autoreg_id');
    advance(array());
  }
	advance($logins);
}

function is_virgin_ip() {
  global $tc_db, $ip;
  $ip_used = $tc_db->GetOne('SELECT COUNT(1) FROM `'.MAIL_IP_TABLE.'` WHERE `uid`=?', array(md5($ip.SALT)));
  if($ip_used)
    retreat('must-login');
  return;
}

if($action=="add") {
	check_creds(true);

  if(in_array($_POST['login'], $banned_names))
    retreat('banned-name');

  $captcha = $_SESSION['security_code'];
  unset($_SESSION['security_code']);
	if(!isset($_POST['captcha']) || $captcha != mb_strtoupper($_POST['captcha']) || empty($captcha))
		retreat('wrong-captcha');
	
	if($newuser) {
    is_virgin_ip();
		$uid = md5($ip.SALT);
  }

  $logins = cull_boxes($uid, $_POST['login']);
  if(count($logins) >= MAILBOXES_PER_IP)
    retreat('max-mailbox-count-reached');
  if(empty($logins) && $uid != md5($ip.SALT)) {
    $uid = md5($ip.SALT);
    $newuser = true;
  }

  if($newuser) 
    setcookie("yapdd_autoreg_id", $uid, TOP_COOKIE);

	$added = add_mailbox($_POST['login'], $_POST['password']);

	advance($added);
}

if($action=="delete") {
  if($newuser)
    retreat('unregistered');
  check_creds();
  $login = $tc_db->GetOne('SELECT `login` FROM `'.MAIL_IP_TABLE.'` WHERE `uid`=? AND `login`=? AND `passhash`=?', array($uid, $_POST['login'], md5($_POST['password'].SALT)));
  if($login && !empty($login)) {
    delete_mailbox($login);
    advance($login);
  }
  else retreat('wrong-password');
}

function check_creds($twopass=false) {
  $valid_login_rx = "/^[a-z](?:[a-z0-9-.]{0,28}[a-z0-9])?$/i";
  $valid_password_rx = "/^[0-9a-z!@#$%^&*()_\\+:;,.-]{6,20}$/i";

  if(!isset($_POST['login']) || !isset($_POST['password']) || ($twopass && !isset($_POST['password2'])))
    retreat('fill-form');
  if($twopass && ($_POST['password'] != $_POST['password2']))
    retreat('passwords-different');
  if($_POST['login'] == $_POST['password'])
    retreat('password=login');
  if(!preg_match($valid_login_rx, $_POST['login']))
    retreat('invalid-login');
  if(!preg_match($valid_password_rx, $_POST['password']))
    retreat('invalid-password');
}

function cull_boxes($uid, $check_login=false) {
  global $tc_db;
  $my_logins = $tc_db->GetAll('SELECT `login` FROM `'.MAIL_IP_TABLE.'` WHERE `uid`=?', array($uid));
  $existing_logins = array();

  if($my_logins && !empty($my_logins)) 
    foreach($my_logins as $login) {
      $login = $login['login'];
      $login_exists = check_mailbox($login);
      if($login_exists == -1) //something went wrong
        retreat('api-error');
      if($login_exists == 0) {//mailbox does not exist (anymore)
        delete_mailbox($login, 'db');
      }
      else {
        if($login == $check_login) 
          retreat('occupied-by-you');
        $existing_logins []= $login;
      }
    }
  return $existing_logins;
}

function login() {
  check_creds();
  $uid = check_user($_POST['login'], $_POST['password']);
  if(!$uid) retreat('not-found');
  setcookie("yapdd_autoreg_id", $uid, TOP_COOKIE);
  return $uid;
}

function check_user($login, $password) {
  global $tc_db;
  $fetched_uid = $tc_db->GetOne('SELECT `uid` FROM `'.MAIL_IP_TABLE.'` WHERE `login`=? AND `passhash`=?', array($login, md5($password.SALT)));
  if(!$fetched_uid) return null;
  $exists = check_mailbox($login);
  if(!$exists) {
    delete_mailbox($login, 'db');
    return null;
  }
  return $fetched_uid;
}

function check_mailbox($login) {
	$result = api_request('/api2/admin/email/counters', array(
		"login" => $login
	), 'GET');
	if(!$result) return -1;
	if($result['success'] == 'ok') return 1;
	if($result['error'] == 'account_not_found') return 0;
	else retreat($result['error']);
}

function add_mailbox($login, $password) {
	global $tc_db, $uid, $newuser;
	$result = api_request('/api2/admin/email/add', array(
		"login" => $login,
		"password" => $password
	), 'POST');
	if(!$result)
		retreat('curl-error');
	if($result['success'] == 'ok') {
		$insert_result = $tc_db->Execute('INSERT INTO `'.MAIL_IP_TABLE.'` (`uid`, `login`, `passhash`) VALUES(?, ?, ?)', array($uid, $login, md5($password.SALT)));
		if(!$insert_result || $tc_db->Affected_Rows() < 1) {
			delete_mailbox($login, 'ya');
			retreat('mysql_error');
		}
		return $login;
	}
	else
		retreat($result['error']);
}

function delete_mailbox($login, $from='all') {
  global $tc_db;
	if($from!='db') {
		$result = api_request('/api2/admin/email/del', array(
			"login" => $login
		), 'POST');
		if(!$result)
			retreat('curl-error');
		if($result['success'] != 'ok')
			retreat($result['error']);
	}
	if($from!='ya') {
		$tc_db->Execute('DELETE FROM `'.MAIL_IP_TABLE.'` WHERE `login`=?', array($login));
	}
}

function api_request($path, $fields, $method="POST") {
	$url = API_HOST.$path;
	$fields['domain'] = DOMAIN;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('PddToken: '.PDD_TOKEN));

	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	foreach($fields as $key=>$value) { 
    $value = preg_replace(array('/&/', '/@/'), array('%26', '%40'), $value);
    $fields_string .= $key.'='.$value.'&'; 
  }
	rtrim($fields_string, '&');

	if($method == "POST") {
		curl_setopt($ch,CURLOPT_POST, count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
		curl_setopt($ch,CURLOPT_URL, $url);
	}
	elseif($method == "GET") {
		curl_setopt($ch,CURLOPT_URL, $url.'?'.$fields_string);
	}
	else
		return array("curl_error" => 'wrong-method');

	curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);

	$result = curl_exec($ch);

	if(curl_errno($ch)) { 
	   retreat('curl-error');
	} 

	curl_close($ch);

	return (array)json_decode($result);
}

function retreat($errmsg) {
  exit(json_encode(array(
    "error" => $errmsg
  )));
}

function advance($data) {
	exit(json_encode(array(
    "error" => false,
    "data" => $data
  )));
}

function rm_cookie($key) {
  setcookie($key, "", time()-3600);
}

function debug_log() {
  if(DEBUG)
    call_user_func_array('var_dump', func_get_args());
}