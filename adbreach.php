<?php

defined('ABSPATH') or die('Plugin file cannot be accessed directly.');
	
/*
 * Plugin Name:       Adbreach
 * Plugin URI:        http://adbreach.de/
 * Description:       Das <strong>Anti Adblock Wordpress Plugin</strong> - Lassen Sie Ihre Werbeanzeigen auf Ihrer Seite nicht mehr blockieren. Das Plugin sp&uuml;rt geblockte Werbung auf Ihrere Seite auf und macht Sie f&uuml;r den Adblocker unkenntlich. Registrieren Sie sich jetzt kostenlos auf <a href="http://adbreach.de/">adbreach.de</a>, damit wir Ihre Seite bei uns eintragen und Sie das Plugin nutzen können.
 * Version:           1.5.1
 * Author:            Robin Heckmann
 * Author URI:        http://adbreach.de/
 * Tags:			  anti adblock, anti-adblock, adblock blocken, adblock, adblock umgehen, block adblock
 * Text Domain:       adbreach
 * License:           GPLv3
 * Domain Path: 	  /languages
 * Text Domain: 	  adbreach
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
*/	



class adbreach {	
	function __construct() {
		$this->redirect();
		add_action('wp_head', array($this, 'verify'));
		add_action('wp_footer', array($this, 'unblock'));
		add_action('wp_footer', array($this, 'popup'));
		add_action('wp_footer', array($this, 'adsense'));	
		add_action('adbreach_cronjob', array($this, 'adbreach_cronjob'));
		register_activation_hook(__FILE__, array($this, 'activation'));
		register_deactivation_hook(__FILE__, array($this, 'deactivation'));
		register_uninstall_hook(__FILE__, array($this, 'deactivation'));	
	} 
	private function protocol() {
		if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') { return 'https://';
		} else { return 'http://'; }
	}
	public function verify() {
		if(get_option('adbreach_code') != '') {
			echo '<meta name="adbreach-site-verification" content="'.get_option('adbreach_code').'">';
		}
	}
	public function adbreach_cronjob() {
		global $wpdb;
		$adb = $wpdb->prefix.'adbreach_images';	
		$adb_table = $wpdb->prefix.'adbreach_adb_images';		
		$result = $wpdb->get_results("SELECT * FROM $adb_table");
		$result2 = $wpdb->get_results("SELECT * FROM $adb");
		foreach ($result as $page) {
			unlink(ABSPATH.$page->image_id.'.png');
		}
		foreach ($result2 as $page) {
			unlink(ABSPATH.$page->image_id.'.png');
		}			
		$wpdb->query("TRUNCATE TABLE $adb_table");
		$image = json_decode($this->curl_download("https://io.adbreach.de/io.php?p=http://$_SERVER[HTTP_HOST]"), true);							
		for($i = 0; $i <= count($image['image_id']); $i++) {				
			$wpdb->replace( 
				$adb_table, 
				array( 
			        'image_id' => $image['image_id'][$i],
			        'image' => $image['image'][$i],
			        'link' => $image['link'][$i],
			        'name' => $image['name'][$i]
				), 
				array( 
					'%s',
					'%s',
					'%s'
				) 
			);
		}		
		$result = $wpdb->get_results("SELECT * FROM $adb_table");
		foreach ($result as $page) {
			file_put_contents(ABSPATH.$page->image_id.'.png', base64_decode($page->image));
		}
		
		$result = $wpdb->get_results("SELECT * FROM $adb");
		foreach ($result as $page) {
			

		    $seed = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
			shuffle($seed); $rand = '';
			foreach (array_rand($seed, 5) as $k) $rand .= $seed[$k];

			$wpdb->update($adb, array('image_id' => $rand), array( 'id' => $page->id), array('%s'), array('%d'));
			file_put_contents(ABSPATH.$rand.'.png', base64_decode($page->image));
		}
	}	
	function activation() {
		global $wpdb;
		wp_schedule_event(strtotime('2016-01-01 0:00'), 'hourly', 'adbreach_cronjob');
		$charset_collate = $wpdb->get_charset_collate();
		$adb_images = $wpdb->prefix . 'adbreach_adb_images';
		$adb = $wpdb->prefix . 'adbreach_images';		
		$sql = "CREATE TABLE IF NOT EXISTS $adb_images (
		  `id` mediumint(9) NOT NULL AUTO_INCREMENT,
		  `image_id` char(20) NOT NULL,
		  `image` longtext NOT NULL,
		  `link` text NOT NULL,
		  `name` text NOT NULL,
		  PRIMARY KEY (id)
		) $charset_collate;";		
		$sql2 = "CREATE TABLE IF NOT EXISTS $adb (
		  `id` mediumint(9) NOT NULL AUTO_INCREMENT,
		  `image_id` text NOT NULL,
		  `image` longtext NOT NULL,
		  `url` text NOT NULL,
		  `link` text NOT NULL,
		  `adsense` tinyint(1) NOT NULL,
		  `popup` tinyint(1) NOT NULL,
		  PRIMARY KEY (id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;";		
		require_once(ABSPATH.'wp-admin/includes/upgrade.php' );
		dbDelta($sql);
		dbDelta($sql2);
		file_put_contents(ABSPATH.'advertisement.js', 'var canRunAds = true;');
	}
	function deactivation() {
		global $wpdb;
		$adb = $wpdb->prefix . 'adbreach_images';
		$adb_table = $wpdb->prefix . 'adbreach_adb_images';
		unlink(ABSPATH.'advertisement.js');
		$result = $wpdb->get_results("SELECT * FROM $adb_table");
		foreach ($result as $page) {
			unlink(ABSPATH.$page->image_id.'.png');
		}
		$result = $wpdb->get_results("SELECT * FROM $adb");
		foreach ($result as $page) {
			unlink(ABSPATH.$page->image_id.'.png');
		}
		$wpdb->query("DROP TABLE IF EXISTS $adb_table");
		$wpdb->query("DROP TABLE IF EXISTS $adb");	
		wp_clear_scheduled_hook('adbreach_cronjob');	
	}
	private function redirect() {
		$uri = $_SERVER['REQUEST_URI'];
		$uri = ltrim($uri, '/');
		if(!empty($uri)) {			
			global $wpdb;
			$adb = $wpdb->prefix . 'adbreach_images';
			$redirect = $wpdb->get_row("SELECT link FROM $adb WHERE image_id = '$uri'");
			if($redirect != NULL) {			
				if (!headers_sent()) {   
					header("Location: $redirect->link"); exit; 
			    } else {
			        echo '<script type="text/javascript">window.location.href="'.$redirect->link.'";</script>';
			        echo '<noscript><meta http-equiv="refresh" content="0;url='.$redirect->link.'" /></noscript>'; exit;
			    }
		    } else {
			    $check_if_red = $this->curl_download("https://r.adbreach.de/r.php?r=$uri&d=http://$_SERVER[HTTP_HOST]");
					if($check_if_red != '0') {				
					if (!headers_sent()) {    
				        header("Location: https://r.adbreach.de/r.php?r=$uri&d=http://$_SERVER[HTTP_HOST]"); exit;
				    } else {
				        echo '<script type="text/javascript">window.location.href="https://r.adbreach.de/r.php?r='.$uri.'&d=http://'.$_SERVER['HTTP_HOST'].'";</script>';
				        echo '<noscript><meta http-equiv="refresh" content="0;url=https://r.adbreach.de/r.php?r='.$uri.'&d=http://'.$_SERVER['HTTP_HOST'].'" /></noscript>'; exit;
				    }
				}
		    }
		    
		}
	}	
	private function check_base64_image($base64) {
	    $img = @imagecreatefromstring(base64_decode($base64));	
	    if (!$img) {
	    	return false;
	    }
	    imagepng($img, 'tmp.png');
	    $info = getimagesize('tmp.png');
	    unlink('tmp.png');
	    if ($info[0] > 0 && $info[1] > 0 && $info['mime']) {
	        return true;
	    }
	    return false;
	}	
	public function unblock() {
		$protocol = $this->protocol();
		$image = json_decode($this->curl_download("https://io.adbreach.de/io.php?p=$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]&o=2"), true);
		?>
		<script type="text/javascript" src="<?php echo $protocol."$_SERVER[HTTP_HOST]"; ?>/advertisement.js"></script>
		<script type="text/javascript" defer="defer">
			String.prototype.filename=function(extension){
			    var s= this.replace(/\\/g, '/'); s= s.substring(s.lastIndexOf('/')+ 1);
			    return extension? s.replace(/[?#].+$/, ''): s.split('.')[0];
			}			
			function adbreach(id, name) {
				var imgs = Array.prototype.slice.apply(document.getElementsByTagName('img'));			
				for (var i = 0; i < imgs.length; i++) {
				    if (imgs[i].src.filename().indexOf(name) !== -1) {
					    if(window.canRunAds === undefined){
							imgs[i].parentNode.insertAdjacentHTML('beforeBegin', '<a href="<?php echo $protocol.$_SERVER['HTTP_HOST'] ?>/'+id+'" target="_blank"><img src="<?php echo "$protocol$_SERVER[HTTP_HOST]" ?>/'+id+'.png" /></a>');
				    	}
				    }
				}
			}	
		</script>		
		<?php 
		for($i = 0; $i < count($image['image_id']); $i++) {
		?>
		<script type="text/javascript" defer="defer">
		setTimeout(function(){ adbreach('<?php echo $image['image_id'][$i]; ?>', '<?php echo pathinfo($image['name'][$i], PATHINFO_FILENAME); ?>'); }, 2000);
		</script>
		<?php
		}
	} 			
	public function popup() {
		global $wpdb;
		$protocol = $this->protocol();		
		$adb_table = $wpdb->prefix . 'adbreach_images';
		$row = $wpdb->get_row("SELECT * FROM $adb_table WHERE popup = '1' ORDER BY RAND() LIMIT 1");
		$popup = $row->image_id;
		if(!empty($popup)) {
			$s = substr(str_shuffle(str_repeat("abcdefghijklmnopqrstuvwxyz", 5)), 0, 5);
			?>
			<a id="<?php echo $s ?>">&#10799;</a>
			<div id="<?php echo $popup ?>">
				<div style="text-align:center">	
					<a href="<?php echo $protocol."$_SERVER[HTTP_HOST]/".$popup ?>" target="_blank">
						<img src="<?php echo $protocol."$_SERVER[HTTP_HOST]/".$popup ?>.png"/>
					</a>
				</div>
			</div>
			<script>
			if(sessionStorage.getItem('popState')!='shown'){document.getElementById('<?php echo $popup ?>').style.display='block';document.getElementById('<?php echo $s ?>').style.display='block'}
			document.getElementById('<?php echo $s ?>').addEventListener("click",function(){document.getElementById('<?php echo $s ?>').style.display='none';document.getElementById('<?php echo $popup?>').style.display='none';sessionStorage.setItem('popState','shown')});
			</script>
			<style>
			#<?php echo $popup ?>{position:fixed;top:0;right:0;bottom:0;left:0;display:none;background:rgba(0,0,0,.3);z-index:999998}
			#<?php echo $popup ?>>div{width:680px;position:relative;margin:5% auto;border-radius:10px;-webkit-border-radius:2px;-moz-border-radius:2px;border-radius:2px}
			#<?php echo $popup ?> img{max-width:680px;-webkit-border-radius:2px;-moz-border-radius:2px;border-radius:2px}
			#<?php echo $s ?>{position:fixed;z-index:999999;right:10px;top:10px;font-weight:100;font-size:44px;line-height:0;cursor:pointer;color:#fff;display:none}
			#<?php echo $s ?>:hover{text-shadow:0 0 10px #fff}
			</style>
			<?php
		}
	}
	private function curl_download($Url){ 
	    if (!function_exists('curl_init')){ die('cURL is not installed!'); }
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $Url);   
	    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1" );
	    curl_setopt($ch, CURLOPT_HEADER, 0);  
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
	    curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
	    $output = curl_exec($ch); 
	    curl_close($ch); 
	    return $output;
	}	
	public function adsense() {	
		global $wpdb;
		$adb_table = $wpdb->prefix . 'adbreach_images';
		$adsense = $wpdb->get_row("SELECT image_id FROM $adb_table WHERE adsense = '1' ORDER BY RAND() LIMIT 1");
		$adsense = $adsense->image_id;
		if(!empty($adsense)) {
		?>
		<script>		
			function adsns() {
				var adsns = document.getElementsByTagName("ins");
				if(window.canRunAds === undefined){
					for (var i = 0; i < adsns.length; i++) {
						console.log(adsns[i].style.width);
						width = adsns[i].style.width;
						height = adsns[i].style.height;
						adsns[i].insertAdjacentHTML('beforeBegin','<div style="height:'+height+';width:'+width+';inline-block;"><a href="/<?php echo $adsense ?>" target="_blank"><img src="<?php echo $this->protocol().$_SERVER['HTTP_HOST'].'/'.$adsense ?>.png" style="max-height:'+height+';max-width:'+width+';inline-block;"></a></div>');
					}
				}
			}
			setTimeout(function(){ adsns(); }, 2000);
		</script>	
		<?php
		}
	}	
} 
$adbreach = new adbreach();


if (isset($_POST['button-name'])) {
    if ($_POST['button-name'] == "submitted") {
        $adbreach->adbreach_cronjob();
    }
}

	

add_action('admin_menu', 'adbreach_plugin_create_menu');

function adbreach_plugin_create_menu() {
	add_menu_page('Adbreach', 'Adbreach', 'administrator', __FILE__, 'adbreach_control_panel');
	add_action('admin_init', 'register_adbreach_plugin_settings');
}

function register_adbreach_plugin_settings() {
	register_setting('adbreach-settings-group', 'adbreach_code');
}	

function adbreach_control_panel() {	


if (!empty($_REQUEST['image'])) {
	global $wpdb;
	$adb_table = $wpdb->prefix . 'adbreach_images';
    $image_url = base64_encode(file_get_contents($_REQUEST['image']));
    $seed = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
	shuffle($seed);
	$rand = '';
	foreach (array_rand($seed, 5) as $k) $rand .= $seed[$k];
    $wpdb->insert($adb_table, array('image_id' => $rand, 'url' => $_REQUEST['image'], 'image' => $image_url), array('%s', '%s', '%s'));
    file_put_contents(ABSPATH.$rand.'.png', base64_decode($image_url));
}
	
	
if (isset($_POST['db_submit'])) {
    if ($_POST['db_submit'] == "Save Changes") {	    
	    $adsense = $_POST['adsense'];
	    $popup = $_POST['popup'];
	    $link = $_POST['link'];
	    $id = $_POST['id'];
	    
	    global $wpdb;
		$adb_table = $wpdb->prefix . 'adbreach_images';
		
		foreach ($id as $key => $n) {
			
			$wpdb->update($adb_table, 
			array( 
				'link' => $link[$key],
				'adsense' => $adsense[$key],
				'popup' => $popup[$key]
			), 
			array('id' => $n),
			array(
				'%s',
				'%d', 
				'%d'
			),
			array('%d')
			);			
		}
	}
}

	

wp_enqueue_script("jquery"); 
?>
<script>	
jQuery(document).ready(function($){
  var mediaUploader;
  $('#upload-button').click(function(e) {
    e.preventDefault();
      if (mediaUploader) {
      mediaUploader.open();
      return;
    }
    mediaUploader = wp.media.frames.file_frame = wp.media({
      title: 'Choose Image',
      button: {
      text: 'Choose Image'
    }, multiple: false });
    mediaUploader.on('select', function() {
      attachment = mediaUploader.state().get('selection').first().toJSON();
      $('#image-url').val(attachment.url);
    });
    mediaUploader.open();
  });
});
</script>	
<div class="wrap">	
	<p style="background:#0ca75f;padding:30px">
		<img src="<?php echo plugins_url('assets/images/adbreach.png', __FILE__); ?>" style="height:60px;width:auto;position:relative;"/> 
	</p>
		<form method="post" action="options.php">
	    <?php settings_fields('adbreach-settings-group'); ?>
	    <?php do_settings_sections('adbreach-settings-group'); ?>  
	    <table class="form-table" style="max-width:1020px">
		    <tr>
		        <td colspan="3" style="background:#fff;border-radius:2px"> 
			        <span style="float:right">Thanks for your  <div class="fb-like" data-href="https://www.facebook.com/adbreach/" data-layout="button_count" data-action="like" data-show-faces="false" data-share="false"></div> <span class="dashicons dashicons-smiley"></span></span>
			       Adbreach - Your intelligent Anti Adblock Solution 
		        </td>
	        </tr> 	    
	        <tr valign="top">
	        	<th scope="row" colspan="3"><h3><span class="dashicons dashicons-chart-area"></span> Unblock Image Based Ads</h3></th>
	        </tr>	        
	        <tr valign="top" style="border-bottom:1px solid #ddd;">
	        	<th scope="row">Verify your Page here</th>
	        	<td><input name="adbreach_code" type="text" value="<?php echo get_option('adbreach_code'); ?>" size="40" placeholder="Secret Key from Adbreach.de" /> <a href="https://adbreach.de/register">Where to get it?</a></td>
	        	<td><?php submit_button('Save Changes', 'primary', '', false); ?></td>
	        </tr>
		</form>        
	        <tr style="border-bottom:3px solid #ddd;">
		        <th scope="row">Load all scanned Ads</th>
		        <td><?php  $date = strtotime("+3 hour"); echo 'Will be done at '.date('H:00', $date).' automatically'; ?></td>
		        <td>
			        <form method="post" action="">
						<input type="submit" value="Grab Images Manually"  class="button button-primary">
						<input type="hidden" name="button-name" value="submitted">
					</form>					
		        </td>  
		    </tr>  
		    <tr valign="top">
	        	<th scope="row" colspan="3"><h3><span class="dashicons dashicons-googleplus"></span> Unblock Google Adsense /<span class="dashicons dashicons-admin-page"></span> Create Popups</h3></th>
	        </tr>	         
		    <tr style="border-bottom:1px solid #ddd;">
			    <th scope="row">Add Image</th>
			    <td>	   
				    <form method="post" action="">
					  <input id="image-url" type="text" name="image" style="width:65%" />
					  <input id="upload-button" type="button" class="button" value="1. Choose/Upload Image" /> 
					
			    </td>
			    <td><input type="submit" value="2. Add Image" class="button button-primary" /></td>
		    </tr>
		    </form>
		    <form action="" method="post">
	        <tr>
		        <th scope="row" colspan="2"> All Images</th>
		        <td><input type="submit" value="Save Changes" name="db_submit" class="button button-primary"></td>
	        </tr>  
	       
		    <?php
		        global $wpdb;
				$adb_table = $wpdb->prefix . 'adbreach_images';
				$result = $wpdb->get_results("SELECT * FROM $adb_table");
				if (count($result) == 0){
				   echo '<tr style="border-bottom:1px solid #ddd;"><td colspan="3" style="padding-left:0">No images uploaded yet : (</td></tr>';
				}
				$i = 0;
				foreach ($result as $page) {
					echo '<tr style="border-bottom:1px solid #ddd;"><td style="padding-left:0"><a href="'.protocol().$_SERVER['HTTP_HOST'].'/'.$page->image_id.'" target="_blank"><img src="'.$page->url.'" style="max-height:300px;max-width:200px;"/></a></td>';
			?>
					<td><input type="text" style="width:100%" name="link['<?php echo $page->id; ?>']" placeholder="Link to Image" value="<?php echo $page->link; ?>"/></td>
					<td>
						<label><input type="checkbox" value="1" <?php if (1 == $page->adsense) echo 'checked="checked"'; ?> name="adsense['<?php echo $page->id; ?>']" /> Google Adsense </label><br> 
						<label><input type="checkbox" value="1" <?php if (1 == $page->popup) echo 'checked="checked"'; ?> name="popup['<?php echo $page->id; ?>']"/> Popup </label>
						<input type="hidden" value="<?php echo $page->id; ?>" name="id['<?php echo $page->id; ?>']" />
					</td>
					</tr>
			<?php
					$i++;
				}
			?>	
				
			</form>        
	    </table>
<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/de_DE/sdk.js#xfbml=1&version=v2.6&appId=155745797947740";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
</div>
<?php 
}		

function protocol() {
	if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') { return 'https://'; } else { return 'http://'; }
}

?>

	
<?php
  /* Add the media uploader script */
  function my_media_lib_uploader_enqueue() {
    wp_enqueue_media();
    wp_register_script( 'media-lib-uploader-js', plugins_url( 'media-lib-uploader.js' , __FILE__ ), array('jquery') );
    wp_enqueue_script( 'media-lib-uploader-js' );
  }
  add_action('admin_enqueue_scripts', 'my_media_lib_uploader_enqueue');
?>
