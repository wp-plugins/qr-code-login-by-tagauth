<?php

/*
Plugin Name: QR Code Login
Description: Use mobile to login your wordpress site by scanning QR Code. 
Version: 1.2
Author: iMC Info Tech Co., Ltd
Tags: QR Code Login, inhao, tagbook, scan qr code, login wp by qrcode, apsense
Author URI: http://www.imetacloud.com
*/

$tagauth_plugin_url = plugins_url('' , __FILE__);
add_action('plugins_loaded', 'init_qr_js_list');

register_activation_hook( __FILE__, 'qr_auth_pluginInstall' );
register_uninstall_hook( __FILE__, 'qr_auth_pluginUninstall' );

if ( is_admin() ) {
	add_filter('plugin_action_links', 'tagauth_plugin_action_links', 10, 2);
	add_action('admin_menu', 'tagauth_admin_menu');
}

function tagauth_admin_menu() {
    add_dashboard_page('Get QR Key', 'Get QR Key', 'read', 'get_qr_key', 'get_qr_key');
	add_options_page('', 'TagAuth', 'manage_options', __FILE__, 'TagAuth_Settings', '', 6);
}

function tagauth_user_menu() {
    add_dashboard_page('Get QR Key', 'Get QR Key', 'read', 'get_qr_key', 'get_qr_key');
}

function qr_auth_pluginInstall() {
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $sql = "CREATE TABLE IF NOT EXISTS `srv_tagauth_code` (
            `code_id` int(11) NOT NULL AUTO_INCREMENT,
            `code_value` varchar(50) NOT NULL,
            `code_expdate` int(11) NOT NULL,
            `code_state` int(11) NOT NULL,
            `code_uid` int(11) NOT NULL,
            PRIMARY KEY (`code_id`),
            KEY `code_value` (`code_value`)
          ) ENGINE=MyISAM AUTO_INCREMENT=1 ;";
    dbDelta($sql);
    $sql = "CREATE TABLE IF NOT EXISTS `srv_tagauth_user` (
            `uid` int(11) NOT NULL DEFAULT '0',
            `token` varchar(50) NOT NULL,
            `created` int(11) NOT NULL,
            PRIMARY KEY (`uid`)
          ) ENGINE=MyISAM;";
    dbDelta($sql);
}

function qr_auth_pluginUninstall() {
    global $wpdb;
	$wpdb->query("DROP TABLE IF EXISTS srv_tagauth_code");
	$wpdb->query("DROP TABLE IF EXISTS srv_tagauth_user");
	delete_option('tagauth_site_id');
	delete_option('tagauth_site_key');
}

function init_qr_js_list() {
    wp_enqueue_script('my_qrcode_js', plugins_url('/qrcode.js', __FILE__));
}

add_action('login_footer', 'add_qr_login_link');

function add_qr_login_link() {
	$sr = get_site_url().'/?action=ta_login';
?>
    <div style="width:150px;margin:10px auto"><a href="<?php  echo $sr;?>" class="button-primary">Login with QR Code</a></div>
<?php
}

function TagAuth_Settings() {
	global $tagauth_plugin_url;
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    global $wpdb;    

	if (!empty($_POST['tag_authsettings_save'])) {

		global $wpdb;
		$site_id = $_POST['txt_siteid'];
		$site_key = $_POST['txt_apikey'];
		
		update_option('tagauth_site_id', $site_id);
		update_option('tagauth_site_key', $site_key);	

		?>

		<div id="message" class="updated">
			<p><strong><?php _e('Settings saved.') ?></strong></p>
		</div>
		<?php

	} 

	$site_id = (int) get_option('tagauth_site_id', 0);
	$site_key = get_option('tagauth_site_key', '');

?>
            
	<div class="wrap">

		<?php    echo "<h2>" . __( 'TagAuth Settings' ) . "</h2>"; ?>  
		<form action="<?php echo $_SERVER['REQUEST_URI']; ?>"  method="POST" name="frm1">
		<table class="widefat" style="width: auto;">
			<tr>
				<td>Enter Site ID : </td>
				<td><input type="text" name="txt_siteid" value="<?php echo $site_id?>"></td>
			</tr>
			<tr>
				<td>Enter API Key : </td>
				<td><input type="text" name="txt_apikey" value="<?php echo $site_key?>"></td>
			</tr>
		</table>
		<br>
		Auth URL: <u><?php echo get_site_url().'/?action=ta_auth'?></u>
		<br>
		<br>				
		<input type="submit" name="tag_authsettings_save" value="Save" class="button-primary">
		</form>
		<h2>How to get Site ID & API Key</h2>
		1. Download FREE APP "<b>Tagbook</b>" and create a free account. (See the download link below).<br>
		2. <a href="http://inhao.com/admin" target="_blank">Login iMC Platform</a>.<br>
		3. Click here to open <a href="http://inhao.com/developer" target="_blank">Developer Area</a>.<br>
		4. Click the left menu "Developer" and click "TagAuth", then create a site.<br>
		5. After creating a site, you may get site ID and API Key and fill this form.<br><br>
		
		<h3>Download the APP "<b>Tagbook</b>"</h3>
		You may find Tagbook on iOS App Store or Google Play by searching for the term "<b>Tagbook</b>".<br><br>
		<a href="https://play.google.com/store/apps/details?id=com.inhao.tagbook" target="_blank"><img src="<?php echo $tagauth_plugin_url ?>/btn_android.png"></a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="https://itunes.apple.com/us/app/tagbook/id700768972" target="_blank"><img src="<?php echo $tagauth_plugin_url ?>/btn_iphone.png"></a>
	</div>

<?php

}

function get_qr_key() {

	$plugin = plugin_basename(__FILE__);

    $user_id = get_current_user_id();
    global $wpdb;
	global $tagauth_plugin_url;
	
	$site_id = (int) get_option('tagauth_site_id', 0);
	$site_key = get_option('tagauth_site_key', '');

    if (!empty($_POST['Delete_QR_Key'])) {
        $wpdb->query('delete from srv_tagauth_user where uid = ' . $user_id);
    }

    $query = "select * from srv_tagauth_user where uid = $user_id";
    $info = $wpdb->get_results($query);

	if ($site_id == 0 || $site_key == '') 
	{
		echo "<h2>Please setup Site ID and API Key first. <a href='options-general.php?page=$plugin'>Click here to setup</a></h2>";
	}
	else 
	{
	    if (empty($info))
		{
			if (!empty($_POST['Create_QR_Key'])) {
				$token = get_random_string(20);
				$c_time = time();
				$query = "INSERT INTO srv_tagauth_user (uid, token, created) VALUES($user_id,'" . $token . "',$c_time)";
				$wpdb->query($query);
				show_qr_code($token);
			} else {
				?>
				<?php    echo "<h2>" . __( 'Create QR Key', '' ) . "</h2>"; ?>
				<div class="wrap">
					<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" enctype="multipart/form-data"  method="POST" name="frm_qr_key">
						<input type="submit" name="Create_QR_Key" value="Create QR Key" class="button-primary">
					</form>
				</div>    
				<?php
			}
		} else {
			// generate qr code
			$token = $info[0]->token;
			show_qr_code($token);
?>
		<div class="wrap">
		<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" enctype="multipart/form-data"  method="POST" name="frm_qr_del">
			<input type="submit" name="Delete_QR_Key" value="Delete QR Key" class="button-primary">
		</form>
			</div>

<?php
		}
	}
?>
	<h2>How to Login with QR Code</h2>
		1. Download FREE APP "<b>Tagbook</b>" and create a free account. (See the download link below).<br>
		2. Create QR Key in this page.<br>
		3. Run Tagbook on your mobile, and request a FREE QR Keychain in the tab "store" of Tagbook.<br>
		4. Scan the QR Key in this page to bind your account.<br>
		5. Login with QR Code by clicking the button "Scan QR Code" in the same QR Keychain Page.<br><br>
		
		<h3>Download the APP "<b>Tagbook</b>"</h3>
		You may find Tagbook on iOS App Store or Google Play by searching for the term "<b>Tagbook</b>".<br><br>
		<a href="https://play.google.com/store/apps/details?id=com.inhao.tagbook" target="_blank"><img src="<?php echo $tagauth_plugin_url ?>/btn_android.png"></a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="https://itunes.apple.com/us/app/tagbook/id700768972" target="_blank"><img src="<?php echo $tagauth_plugin_url ?>/btn_iphone.png"></a>
<?php
}

function show_qr_code($token) {
	$site_id = (int) get_option('tagauth_site_id', 0);
?>
    <div class="wrap">
	<h2>Bind Account with Your QR Keychain</h2>
    <input type="hidden" id="code" value="SET.<?php echo $site_id . '.' . $token; ?>">
    <input type="hidden" id="c" value="<?php echo $token; ?>">
    <div id="qrcode" style="margin-bottom:20px;background:#FFF;"></div>
    </div>
    <script type="text/javascript">
        var qrcode = new QRCode(document.getElementById("qrcode"), {
            width: 200,
            height: 200,
            correctLevel: 0,
        });
        qrcode.makeCode(jQuery("#code").val());
    </script>

<?php
}

function get_random_string($length = 20) {
    $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
    $token = "";
    $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
    for ($i = 0; $i < $length; $i++) {
        $n = rand(0, $alphaLength);
        $token.= $alphabet[$n];
    }
    return $token;
}

function HttpResponse($code, $data = null)
{
	$return = array(
		'code' => $code,
		'data' => $data);
	exit(json_encode($return));
}

add_action('parse_request', 'tagauth_query_request');
function tagauth_query_request(&$wp)
{
	global $wpdb;
	global $tagauth_plugin_url;

	$action = strtolower(trim($_GET['action']));
	if ($action == '')
		$action == strtolower(trim($_POST['action']));

	if ($action == 'ta_auth')
	{
		$pid = (int) $_GET['pid'];
		$code = trim($_GET['code']);
		$token = trim($_GET['token']);
		$key = trim($_GET['key']);
		$code = esc_sql($code);
		$token = esc_sql($token);

		$site_id = (int) get_option('tagauth_site_id', 0);
		$site_key = get_option('tagauth_site_key', '');
		
		if ($site_id != $pid || $key != $site_key)
			HttpResponse(1);

		$query = "SELECT * FROM srv_tagauth_user WHERE token = '$token' LIMIT 1";
		$info = $wpdb->get_results($query);
		if (empty($info))
			HttpResponse(1);

		$uid = $info[0]->uid;

		$query = "SELECT * FROM srv_tagauth_code WHERE code_value = '$code' AND code_state = 0 LIMIT 1";
		$codeinfo = $wpdb->get_results($query);
		if (empty($codeinfo))
			HttpResponse(2);


		if ($codeinfo[0]->code_expdate < time())
			HttpResponse(2);
			
		$code_id = $codeinfo[0]->code_id;
		$query = "UPDATE srv_tagauth_code SET code_uid = '$uid', code_state = 1 WHERE code_id = '$code_id'";
		$wpdb->query($query);
		HttpResponse(11);
		exit();
	}
	if ($action == 'ta_login')
	{
		wp_enqueue_script("jquery");

		//check login state
		$user_id = (int) get_current_user_id();

		if ($user_id > 0)
			header("Location: ".admin_url());

		$site_id = (int) get_option('tagauth_site_id', 0);
		$token = strtoupper(get_random_string());
		$wpdb->insert(
				'srv_tagauth_code', array(
				'code_value' => $token,
				'code_expdate' => time() + 60 * 5,
				'code_state' => '0',
				'code_uid' => '0',
					), array(
				'%s',
				'%d'
		   )
		);

		$lastid = $wpdb->insert_id;
		?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Log in By QR Code | Powered by TagAugh</title>
<style>
    .wrap1{
        margin: 20px auto;
		width:200px;
    }    
	body {
		font-family:Arial;
		padding-top:50px;
	}
</style>
<?php wp_print_scripts(); ?>
</head>
<body>

<?php    echo "<p align='center' style='font-size:20px'><b>" . __( 'Login with QR Code' ) . "</b></p>"; ?>  
<div class="wrap1">
<input type="hidden" id="code" value="AUTH.<?php echo $site_id.'.'.$token; ?>">
<input type="hidden" id="c" value="<?php echo $token; ?>">
<div id="qrcode"></div>
<div><img src="<?php echo $tagauth_plugin_url ?>/qrloading.gif" style="padding-left: 88px;padding-top: 10px;"></div>
</div>
<div style="width:500px;margin:40px auto">
<h3>Use Tagbook to Scan This QR Code</h3>
You may find Tagbook on iOS App Store or Google Play by searching for the term "<b>Tagbook</b>".<br><br>
<p align="center">
<a href="https://play.google.com/store/apps/details?id=com.inhao.tagbook" target="_blank"><img src="<?php echo $tagauth_plugin_url ?>/btn_android.png"></a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="https://itunes.apple.com/us/app/tagbook/id700768972" target="_blank"><img src="<?php echo $tagauth_plugin_url ?>/btn_iphone.png"></a>
<br><br>
<a href="<?php echo(admin_url());?>" style="font-size:13px">Return to Login Page</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href="http://inhao.com" style="font-size:13px" target="_blank">Powered by Inhao Tagbook</a>
</div>
<script type="text/javascript">
	var qrcode = new QRCode(document.getElementById("qrcode"), {
		width: 200,
		height: 200,
		correctLevel: 0,
	});
	qrcode.makeCode(jQuery("#code").val());
</script>
<script type="text/javascript">
function DetectQRCode()
{
	var c = jQuery("#c").val();
	jQuery.ajax({
		type: "POST",
		url: "<?php echo get_site_url()?>" + '/?action=ta_verify',
		data: {action: 'ta_verify', code: c},
		cache: false,
		dataType: 'json',
		success: function (ret) {
			if (ret.code == 1)
			{
				window.location.href = "<?php echo(admin_url());?>";
				return;
			}
			else if(ret.code==4){
				alert(ret.msg);
				return;
			}
			else
				setTimeout("DetectQRCode()", 5000);
		},
		error: function () {    
			alert('Internal error, please try it later.');
		}
	});
}
setTimeout("DetectQRCode()", 5000);
</script>
</body>
</html>
		<?php
		exit();
	}
	if ($action == 'ta_verify')
	{
		$status_code = 0;
		$msg = '';
		$res = array();

		$code_value = $_POST['code'];
		$code_value = esc_sql($code_value);
		$codeinfo = $wpdb->get_results("SELECT * FROM srv_tagauth_code WHERE code_value = '$code_value'");
		if (empty($codeinfo))
		{
			HttpResponse(10);
		}
		$uid = $codeinfo[0]->code_uid;
		if ($uid == 0)
		{
			HttpResponse(10);
		}
		if ($codeinfo[0]->code_expdate < time() || $codeinfo[0]->code_state > 1) //timeout or being used
		{
			$data['msg'] = 'The login page was timeout, please try it again.';
			HttpResponse(20, $data);
		}

		$member = $wpdb->get_results("SELECT * FROM srv_tagauth_user WHERE uid = $uid");
		if (empty($member))
		{
			$data['msg'] = 'The account was not found.';
			HttpResponse(30, $data);
		}

		//Set Login State
		wp_set_current_user($uid);
		wp_set_auth_cookie($uid);
		do_action('wp_login');

		//Update QR Code
		$query = "UPDATE srv_tagauth_code SET code_state = 11 WHERE code_value = '$code_value'";
		$wpdb->query($query);

		HttpResponse(1);
		exit();
	}
    return;
}

function tagauth_plugin_action_links( $links, $file ) {

	$plugin = plugin_basename(__FILE__);

    // check to make sure we are on the correct plugin
    if ( $file == $plugin ) {
         $settings_link = "<a href=\"options-general.php?page=$plugin\">" . __('Settings', 'tagauth') . '</a>';
        array_unshift( $links, $settings_link );
    }
 
    return $links;
}
?>