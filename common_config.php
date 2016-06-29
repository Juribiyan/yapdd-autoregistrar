<?
define('CONFIG_ENVIRONMENT', 'instant'); 	// "standalone" or "instant" (when using with instant-0chan)
define('DOMAIN', 'yourdomain.tk');
define('PDD_TOKEN', 'insert_PDD_token'); // https://pddimp.yandex.ru/api2/admin/get_token
define('SALT', 'ENTER RANDOM SHIT');
define('MAILBOXES_PER_IP', 3);
define('MAIL_IP_TABLE', 'mailip');
$banned_names = array('admin', 'webmaster', 'support');

$ip = $_SERVER['REMOTE_ADDR']; // замените на подходящий для вашего окружения заголовок, например, CF-Connecting-IP