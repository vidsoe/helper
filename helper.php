<?php namespace your_namespace;
/**
 * Vidsoe Helper
 *
 * A collection of useful static methods for your WordPress plugins and theme's functions.php
 *
 * @author Vidsoe
 * @copyright Vidsoe
 * @license GPL2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link https://github.com/vidsoe/helper
 * @version 0.8.27
 *
 * Do not forget to rename the namespace to whatever you want!
 */
class Helper {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// private
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	private static $admin_notices = [], $cf7_posted_data = [];

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// public
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
   	public static function add_admin_notice($message = '', $class = 'warning', $is_dismissible = false){
		$html = self::admin_notice_html($message, $class, $is_dismissible);
        $md5 = md5($html);
        self::$admin_notices[$md5] = $html;
		if(has_action('admin_notices', [__CLASS__, 'admin_notices'])){
			return;
		}
		add_action('admin_notices', [__CLASS__, 'admin_notices']);
	}

	/**
	 * @return string
	 */
   	public static function admin_notice_html($message = '', $class = 'warning', $is_dismissible = false){
		if(!in_array($class, ['error', 'info', 'success', 'warning'])){
            $class = 'warning';
        }
        if($is_dismissible){
            $class .= ' is-dismissible';
        }
        return '<div class="notice notice-' . $class . '"><p>' . $message . '</p></div>';
	}

	/**
	 * @return void
	 */
   	public static function admin_notices(){
		foreach(self::$admin_notices as $admin_notice){
			echo $admin_notice;
		}
	}

	/**
	 * @return string
	 */
   	public static function basename($path = '', $suffix = ''){
		return wp_basename(preg_replace('/\?.*/', '', $path), $suffix);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// <!-- Contact Form 7
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	public static function cf7_additional_setting($name = '', $contact_form = null){
		$contact_form = self::cf7_contact_form($contact_form);
		if(is_null($contact_form)){
			return [];
		}
		return $contact_form->additional_setting($name, false);
	}

	/**
	 * @return WPCF7_ContactForm|null
	 */
	public static function cf7_contact_form($contact_form = null){
		$current_contact_form = wpcf7_get_current_contact_form();
		if(empty($contact_form)){ // null, false, 0 and other PHP falsey values
			return $current_contact_form;
		}
		if($contact_form instanceof \WPCF7_ContactForm){
			return $contact_form;
		}
		if(is_numeric($contact_form) or $contact_form instanceof \WP_Post){
			$contact_form = wpcf7_contact_form($contact_form); // replace the current contact form
			if(!is_null($current_contact_form)){
				wpcf7_contact_form($current_contact_form->id()); // restore the current contact form
			}
			return $contact_form; // null or WPCF7_ContactForm
		}
		if(is_string($contact_form)){
			$contact_form = wpcf7_get_contact_form_by_title($contact_form); // replace the current contact form
			if(!is_null($current_contact_form)){
				wpcf7_contact_form($current_contact_form->id()); // restore the current contact form
			}
			return $contact_form; // null or WPCF7_ContactForm
		}
		return null;
	}

	/**
	 * @return bool
	 */
	public static function cf7_fake_mail($contact_form = null, $submission = null){
		if(!did_action('wpcf7_before_send_mail')){
			return false; // too early
		}
		if(did_action('wpcf7_mail_failed') or did_action('wpcf7_mail_sent')){
			return false; // too late
		}
		$contact_form = self::cf7_contact_form($contact_form);
		if(is_null($contact_form)){
			return false;
		}
		$submission = self::cf7_submission($submission);
		if(is_null($submission)){
			return false;
		}
		if(!$submission->is('init')){
			return false; // try to prevent conflicts with other statuses
		}
		if(self::cf7_skip_mail($contact_form) or self::cf7_send_mail($contact_form)){ // skip or send
			$message = $contact_form->message('mail_sent_ok');
			$message = wp_strip_all_tags($message);
			$submission->set_response($message);
			$submission->set_status('mail_sent');
			return true;
		}
		$message = $contact_form->message('mail_sent_ng');
		$message = wp_strip_all_tags($message);
		$submission->set_response($message);
		$submission->set_status('mail_failed');
		return false;
	}

	/**
	 * @return array
	 */
	public static function cf7_invalid_fields($fields = [], $contact_form = null){
		$contact_form = self::cf7_contact_form($contact_form);
		if(is_null($contact_form)){
			return [];
		}
		if(!self::is_array_assoc($fields)){
			return [];
		}
		$invalid = [];
		$tags = wp_list_pluck($contact_form->scan_form_tags(), 'type', 'name');
		foreach($fields as $name => $type){
			if(!empty($tags[$name])){
				if(!in_array($tags[$name], (array) $type)){
					$invalid[] = $name;
				}
			}
		}
		return $invalid;
	}

	/**
	 * @return bool
	 */
	public static function cf7_is_true($name = '', $contact_form = null){
		$contact_form = self::cf7_contact_form($contact_form);
		if(is_null($contact_form)){
			return false;
		}
		return $contact_form->is_true($name);
	}

	/**
	 * @return array
	 */
	public static function cf7_missing_fields($fields = [], $contact_form = null){
		$contact_form = self::cf7_contact_form($contact_form);
		if(is_null($contact_form)){
			return [];
		}
		if(!self::is_array_assoc($fields)){
			return [];
		}
		$missing = [];
		$tags = wp_list_pluck($contact_form->scan_form_tags(), 'type', 'name');
		foreach(array_keys($fields) as $name){
			if(empty($tags[$name])){
				$missing[] = $name;
			}
		}
		return $missing;
	}

	/**
	 * @return string
	 */
	public static function cf7_pref($name = '', $contact_form = null){
		$contact_form = self::cf7_contact_form($contact_form);
		if(is_null($contact_form)){
			return '';
		}
		$pref = $contact_form->pref($name);
		if(is_null($pref)){
			return '';
		}
		return $pref;
	}

	/**
	 * @return array|null|string
	 */
	public static function cf7_raw_posted_data($key = ''){
		if(empty(self::$cf7_posted_data)){
			$posted_data = array_filter((array) $_POST, function($key){
				return '_' !== substr($key, 0, 1);
			}, ARRAY_FILTER_USE_KEY);
			self::$cf7_posted_data = self::cf7_sanitize_posted_data($posted_data);
		}
		if('' === $key){
			return self::$cf7_posted_data;
		}
		if(isset($key, self::$cf7_posted_data)){
			return self::$cf7_posted_data[$key];
		}
		return null;
	}

	/**
	 * @return string
	 */
	public static function cf7_sanitize_posted_data($value = []){
		if(!empty($value)){
			if(is_array($value)){
				$value = array_map([__CLASS__, 'cf7_sanitize_posted_data'], $value);
			} elseif(is_string($value)){
				$value = wp_check_invalid_utf8($value);
				$value = wp_kses_no_null($value);
			}
		}
		return $value;
	}

	/**
	 * @return bool
	 */
	public static function cf7_send_mail($contact_form = null){
		$contact_form = self::cf7_contact_form($contact_form);
		if(is_null($contact_form)){
			return false;
		}
		$skip_mail = self::cf7_skip_mail($contact_form);
		if($skip_mail){
			return true;
		}
		$result = \WPCF7_Mail::send($contact_form->prop('mail'), 'mail');
		if(!$result){
			return false;
		}
		$additional_mail = [];
		if($mail_2 = $contact_form->prop('mail_2') and $mail_2['active']){
			$additional_mail['mail_2'] = $mail_2;
		}
		$additional_mail = apply_filters('wpcf7_additional_mail', $additional_mail, $contact_form);
		foreach($additional_mail as $name => $template){
			\WPCF7_Mail::send($template, $name);
		}
		return true;
	}

	/**
	 * @return bool
	 */
	public static function cf7_skip_mail($contact_form = null){
		$contact_form = self::cf7_contact_form($contact_form);
		if(is_null($contact_form)){
			return false;
		}
		$skip_mail = ($contact_form->in_demo_mode() or $contact_form->is_true('skip_mail') or !empty($contact_form->skip_mail));
		$skip_mail = apply_filters('wpcf7_skip_mail', $skip_mail, $contact_form);
		return boolval($skip_mail);
	}

	/**
	 * @return WPCF7_Submission|null
	 */
	public static function cf7_submission($submission = null){
		$current_submission = \WPCF7_Submission::get_instance();
		if(empty($submission)){ // null, false, 0 and other PHP falsey values
			return $current_submission;
		}
		if($submission instanceof \WPCF7_Submission){
			return $submission;
		}
		return null;
	}

	/**
	 * @return bool
	 */
	public static function cf7_tag_has_data_option($tag = null){
		if(!$tag instanceof \WPCF7_FormTag){
			return false;
		}
		return ($tag->get_data_option() ? true : false);
	}

	/**
	 * @return bool
	 */
	public static function cf7_tag_has_free_text($tag = null){
		if(!$tag instanceof \WPCF7_FormTag){
			return false;
		}
		return $tag->has_option('free_text');
	}

	/**
	 * @return bool
	 */
	public static function cf7_tag_has_pipes($tag = null){
		if(!$tag instanceof \WPCF7_FormTag){
			return false;
		}
		if(WPCF7_USE_PIPE and $tag->pipes instanceof \WPCF7_Pipes and !$tag->pipes->zero()){
			$pipes = $tag->pipes->to_array();
			foreach($pipes as $pipe){
				if($pipe[0] !== $pipe[1]){
					return true;
				}
			}
		}
		return false;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// Contact Form 7 -->
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string|WP_Error
	 */
	public static function dir_to_url($path = ''){
		return str_replace(wp_normalize_path(ABSPATH), site_url('/'), wp_normalize_path($path));
	}

	/**
	 * @return void
	 */
	public static function enqueue($handle = '', $src = '', $deps = [], $ver = false, $in_footer = true){
		$mimes = [
			'css' => 'text/css',
			'js' => 'application/javascript',
		];
		$filetype = wp_check_filetype(self::basename($src), $mimes);
		switch($filetype['type']){
			case 'application/javascript':
				wp_enqueue_script($handle, $src, $deps, $ver, $in_footer);
				break;
			case 'text/css':
				wp_enqueue_style($handle, $src, $deps, $ver);
				break;
		}
	}

	/**
	 * @return WP_Error
	 */
	public static function error($message = '', $data = ''){
		if(is_wp_error($message)){
			$data = $message->get_error_data();
			$message = $message->get_error_message();
		}
		if(empty($message)){
			$message = __('Something went wrong.');
		}
		return new \WP_Error('error', $message, $data);
	}

	/**
	 * @return string
	 */
	public static function implode_and($array = [], $and = '&'){
		if(empty($array)){
			return '';
		}
		if(1 === count($array)){
			return $array[0];
		}
		$last = array_pop($array);
		return implode(', ', $array) . ' ' . trim($and) . ' ' . $last;
	}

	/**
	 * @return bool
	 */
	public static function is_array_assoc($array = []){
		if(!is_array($array)){
			return false;
		}
		$end = count($array) - 1;
		return (array_keys($array) !== range(0, $end));
	}

	/**
	 * @return bool
	 */
	public static function is_plugin_active($plugin = ''){
        if(!function_exists('is_plugin_active')){
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active($plugin);
    }

	/**
	 * @return void
	 */
	public static function local_enqueue($handle = '', $file = '', $deps = []){
		if(!file_exists($file)){
			return;
		}
		$src = self::dir_to_url($file);
		$ver = filemtime($file);
		self::enqueue($handle, $src, $deps, $ver, true);
	}

	/**
	 * @return string|WP_Error
	 */
	static public function sanitize_upload_path($path = ''){
		$path = wp_normalize_path($path);
		$basename = basename($path);
		$dirname = dirname($path);
		$upload_dir = wp_get_upload_dir();
		if($upload_dir['error']){
			return self::error($upload_dir['error']);
		}
		$basedir = wp_normalize_path($upload_dir['basedir']);
		if(0 !== strpos($dirname, $basedir)){
			$error_msg = sprintf(__('Unable to locate needed folder (%s).'), __('The uploads directory'));
			return self::error($error_msg);
		}
		return trailingslashit($dirname) . $basename;
	}

	/**
	 * @return string|WP_Error
	 */
	public static function upload_dir_to_url($path = ''){
		$path = self::sanitize_upload_path($path);
		if(is_wp_error($path)){
			return $path;
		}
		$upload_dir = wp_get_upload_dir();
		$basedir = wp_normalize_path($upload_dir['basedir']);
		return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $path);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
