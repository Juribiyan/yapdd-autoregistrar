<?php
define('CONFIG_ENVIRONMENT', 'instant'); 	// "standalone" or "instant" (when using with instant-0chan)
define('INSTANT_CONFIG', __DIR__.'/../config.php'); // path to instan 0chan's config.php
define('DOMAIN', ''); // Your domain (without mail. prefix)
define('PDD_TOKEN', ''); // https://pddimp.yandex.ru/api2/admin/get_token
define('SALT', ''); // renter random characters
define('MAILBOXES_PER_IP', 3);
define('MAIL_IP_TABLE', 'mailip');
$banned_names = array('admin', 'webmaster', 'support');

$ip = isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER['REMOTE_ADDR']; // подходящий для вашего окружения заголовок для получения IP-адреса пользователя