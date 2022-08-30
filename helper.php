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
 * Do not forget to rename 'your_namespace' to whatever you want!
 */
class Helper {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// private
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	private static $admin_notices = [], $cf7_posted_data = [], $custom_login_logo = [], $enqueue_stylesheet = false, $hide_recaptcha_badge = false, $zoom_jwt = '';

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
		if(!has_action('admin_notices', [__CLASS__, 'admin_notices'])){
			add_action('admin_notices', [__CLASS__, 'admin_notices']);
		}
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
	 * @return bool
	 */
   	public static function array_keys_exist($keys = [], $array = []){
		if(!is_array($keys) or !is_array($array)){
            return false;
        }
        foreach($keys as $key){
            if(!array_key_exists($key, $array)){
                return false;
            }
        }
        return true;
	}

	/**
	 * @return false|WP_User|WP_error
	 */
	public static function authenticate($user, $username_or_email){
		if(!is_null($user)){
			return $user;
		}
		if(is_email($username_or_email)){
			$user = get_user_by('email', $username_or_email);
		}
		if(!$user){
			$user = get_user_by('login', $username_or_email);
		}
		return $user;
	}

	/**
	 * @return string
	 */
   	public static function base64_urldecode($data = '', $strict = false){
        return base64_decode(strtr($data, '-_', '+/'), $strict);
    }

	/**
	 * @return string
	 */
   	public static function base64_urlencode($data = ''){
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

	/**
	 * @return string
	 */
   	public static function basename($path = '', $suffix = ''){
		return wp_basename(preg_replace('/\?.*/', '', $path), $suffix);
	}

	/**
	 * @return string
	 */
   	public static function canonicalize($key = ''){
        $key = sanitize_title($key);
        return \WP_REST_Request::canonicalize_header_name($key);
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
	public static function cf7_is_false($name = '', $contact_form = null){
		$contact_form = self::cf7_contact_form($contact_form);
		if(is_null($contact_form)){
			return false;
		}
		return self::is_false(self::cf7_pref($name, $contact_form));
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
	 * @return WP_Role|null
	 */
   	public static function clone_role($source = '', $destination = '', $display_name = ''){
        $role = get_role($source);
        if(is_null($role)){
            return null;
        }
        $destination = self::canonicalize($destination);
        return add_role($destination, $display_name, $role->capabilities);
    }

	/**
	 * @return bool
	 */
   	public static function current_screen_in($ids = []){
        global $current_screen;
        if(!is_array($ids)){
            return false;
        }
        if(!isset($current_screen)){
            return false;
        }
        return in_array($current_screen->id, $ids);
    }

	/**
	 * @return bool
	 */
   	public static function current_screen_is($id = ''){
        global $current_screen;
        if(!is_string($id)){
            return false;
        }
        if(!isset($current_screen)){
            return false;
        }
        return ($current_screen->id === $id);
    }

	/**
	 * @return string
	 */
	public static function current_time($type = 'U', $offset_or_tz = ''){ // If $offset_or_tz is an empty string, the output is adjusted with the GMT offset in the WordPress option.
        if('timestamp' === $type){
            $type = 'U';
        }
        if('mysql' === $type){
            $type = 'Y-m-d H:i:s';
        }
        $timezone = $offset_or_tz ? self::timezone($offset_or_tz) : wp_timezone();
        $datetime = new \DateTime('now', $timezone);
        return $datetime->format($type);
    }

	/**
	 * @return bool|WP_Error
	 */
	public static function custom_login_logo($attachment_id = 0, $half = true){
        if(!wp_attachment_is_image($attachment_id)){
            return self::error(__('File is not an image.'));
        }
		$custom_logo = wp_get_attachment_image_src($attachment_id, 'medium');
		$height = $custom_logo[2];
		$width = $custom_logo[1];
		if($half){
			$height = $height / 2;
			$width = $width / 2;
		}
		self::$custom_login_logo = [$custom_logo[0], $width, $height];
		if(!has_action('login_enqueue_scripts', [__CLASS__, 'login_enqueue_scripts'])){
			add_action('login_enqueue_scripts', [__CLASS__, 'login_enqueue_scripts']);
		}
        return true;
    }

	/**
	 * @return string
	 */
	public static function date_convert($string = '', $fromtz = '', $totz = '', $format = 'Y-m-d H:i:s'){
        $datetime = date_create($string, self::timezone($fromtz));
        if($datetime === false){
            return gmdate($format, 0);
        }
        return $datetime->setTimezone(self::timezone($totz))->format($format);
    }

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
	 * @return void
	 */
	public static function enqueue_stylesheet(){
		self::$enqueue_stylesheet = true;
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
	public static function fa_file_type($post = null){
        if('attachment' !== get_post_status($post)){
			return '';
		}
		if(wp_attachment_is('audio', $post)){
			return 'audio';
		}
		if(wp_attachment_is('image', $post)){
			return 'image';
		}
		if(wp_attachment_is('video', $post)){
			return 'video';
		}
		$type = get_post_mime_type($post);
		switch($type){
			case 'application/zip':
			case 'application/x-rar-compressed':
			case 'application/x-7z-compressed':
			case 'application/x-tar':
				return 'archive';
				break;
			case 'application/vnd.ms-excel':
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
				return 'excel';
				break;
			case 'application/pdf':
				return 'pdf';
				break;
			case 'application/vnd.ms-powerpoint':
			case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
				return 'powerpoint';
				break;
			case 'application/msword':
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				return 'word';
				break;
			default:
				return 'file';
		}
    }

	/**
	 * @return string
	 */
	public static function first_p($text = '', $dot = true){
        return self::one_p($text, $dot, 'first');
    }

	/**
	 * @return string
	 */
	public static function format_function($function_name = '', $args = []){
        $str = '<div style="color: #24831d; font-family: monospace; font-weight: 400;">' . $function_name . '(';
        $function_args = [];
        foreach($args as $arg){
            $arg = shortcode_atts([
                'default' => 'null',
                'name' => '',
                'type' => '',
            ], $arg);
            if($arg['default'] and $arg['name'] and $arg['type']){
                $function_args[] = '<span style="color: #cd2f23; font-family: monospace; font-style: italic; font-weight: 400;">' . $arg['type'] . '</span> <span style="color: #0f55c8; font-family: monospace; font-weight: 400;">$' . $arg['name'] . '</span> = <span style="color: #000; font-family: monospace; font-weight: 400;">' . $arg['default'] . '</span>';
            }
        }
        if($function_args){
            $str .= ' ' . implode(', ', $function_args) . ' ';
        }
        $str .= ')</div>';
		return $str;
    }

	/**
	 * @return int
	 */
	public static function get_memory_size(){
        if(!function_exists('exec')){
			$current_limit = ini_get('memory_limit');
			$current_limit_int = wp_convert_hr_to_bytes($current_limit);
            return $current_limit_int;
        }
        exec('free -b', $output);
        $output = sanitize_text_field($output[1]);
        $output = explode(' ', $output);
        return (int) $output[1];
    }

	/**
	 * @return array
	 */
	public static function get_posts_query($args = null){
        $defaults = [
			'category' => 0,
			'exclude' => [],
			'include' => [],
			'meta_key' => '',
			'meta_value' => '',
			'numberposts' => 5,
			'order' => 'DESC',
			'orderby' => 'date',
			'post_type' => 'post',
			'suppress_filters' => true,
		];
		$parsed_args = wp_parse_args($args, $defaults);
		if(empty($parsed_args['post_status'])){
			$parsed_args['post_status'] = ('attachment' === $parsed_args['post_type']) ? 'inherit' : 'publish';
		}
		if(!empty($parsed_args['numberposts']) and empty($parsed_args['posts_per_page'])){
			$parsed_args['posts_per_page'] = $parsed_args['numberposts'];
		}
		if(!empty($parsed_args['category'])){
			$parsed_args['cat'] = $parsed_args['category'];
		}
		if(!empty($parsed_args['include'])){
			$incposts = wp_parse_id_list($parsed_args['include']);
			$parsed_args['posts_per_page'] = count($incposts);  // Only the number of posts included.
			$parsed_args['post__in'] = $incposts;
		} elseif(!empty($parsed_args['exclude'])){
			$parsed_args['post__not_in'] = wp_parse_id_list($parsed_args['exclude']);
		}
		$parsed_args['ignore_sticky_posts'] = true;
		$parsed_args['no_found_rows'] = true;
		$query = new WP_Query;
		$query->query($parsed_args);
		return $query;
    }

	/**
	 * @return void
	 */
	public static function hide_recaptcha_badge(){
		if(!has_action('wp_head', [__CLASS__, 'wp_head'])){
			add_action('wp_head', [__CLASS__, 'wp_head']);
		}
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
	public static function is_cloudflare(){
        return !empty($_SERVER['CF-ray']);
    }

	/**
	 * @return bool
	 */
	public static function is_doing_heartbeat(){
        return (wp_doing_ajax() and isset($_POST['action']) and 'heartbeat' === $_POST['action']);
    }

	/**
	 * @return bool
	 */
	public static function is_false($data = ''){
        return in_array((string) $data, ['0', 'false', 'off'], true);
    }

	/**
	 * @return bool
	 */
	public static function is_google_workspace($email = ''){
		if(!is_email($email)){
			return false;
		}
		list($local, $domain) = explode('@', $email, 2);
		if('gmail.com' === strtolower($domain)){
			return true;
		}
		if(!getmxrr($domain, $mxhosts)){
			return false;
		}
		return in_array('aspmx.l.google.com', $mxhosts);
    }

	/**
	 * @return bool
	 */
	public static function is_mysql_date($subject = ''){
		$pattern = '/^\d{4}\-(0[1-9]|1[0-2])\-(0[1-9]|[12]\d|3[01]) ([01]\d|2[0-3]):([0-5]\d):([0-5]\d)$/';
        return preg_match($pattern, $subject);
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
	 * @return bool
	 */
	public static function is_post_revision_or_auto_draft($post = null){
        return (wp_is_post_revision($post) or 'auto-draft' === get_post_status($post));
    }

	/**
	 * @return bool
	 */
	public static function is_true($data = ''){
        return in_array((string) $data, ['1', 'on', 'true'], true);
    }

	/**
	 * @return array
	 */
	public static function ksort_deep($array = []){
        if(!self::is_array_assoc($array)){
            return [];
        }
        ksort($array);
        foreach($array as $key => $value){
            $array[$key] = self::ksort_deep($value);
        }
        return $array;
    }

	/**
	 * @return string
	 */
	public static function last_p($text = '', $dot = true){
        return self::one_p($text, $dot, 'last');
    }

	/**
	 * @return array
	 */
	public static function list_pluck($list = [], $index_key = ''){
		$newlist = [];
		foreach($list as $value){
			if(is_object($value)){
				if(isset($value->$index_key)){
					$newlist[$value->$index_key] = $value;
				} else {
					$newlist[] = $value;
				}
			} else {
				if(isset($value[$index_key])){
					$newlist[$value[$index_key]] = $value;
				} else {
					$newlist[] = $value;
				}
			}
		}
		return $newlist;
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
	 * @return void
	 */
	public static function local_login_header(){
		if(!has_action('login_headertext', [__CLASS__, 'login_headertext'])){
			add_action('login_headertext', [__CLASS__, 'login_headertext']);
		}
		if(!has_action('login_headerurl', [__CLASS__, 'login_headerurl'])){
			add_action('login_headerurl', [__CLASS__, 'login_headerurl']);
		}
    }

	/**
	 * @return void
	 */
   	public static function login_enqueue_scripts(){
        if(empty(self::$custom_login_logo)){
			return;
		} ?>
		<style type="text/css">
			#login h1 a,
			.login h1 a {
				background-image: url(<?php echo self::$custom_login_logo[0]; ?>);
				background-size: <?php echo self::$custom_login_logo[1]; ?>px <?php echo self::$custom_login_logo[2]; ?>px;
				height: <?php echo self::$custom_login_logo[2]; ?>px;
				width: <?php echo self::$custom_login_logo[1]; ?>px;
			}
		</style><?php
    }

	/**
	 * @return string
	 */
	public static function login_headertext($login_header_text){
		return get_option('blogname');
	}

	/**
	 * @return string
	 */
	public static function login_headerurl($login_header_url){
		return home_url();
	}

	/**
	 * @return array
	 */
	public static function offset_or_tz($offset_or_tz = ''){ // Default GMT offset or timezone string. Must be either a valid offset (-12 to 14) or a valid timezone string.
        if(is_numeric($offset_or_tz)){
            return [
                'gmt_offset' => $offset_or_tz,
                'timezone_string' => '',
            ];
        } else {
            if(preg_match('/^UTC[+-]/', $offset_or_tz)){ // Map UTC+- timezones to gmt_offsets and set timezone_string to empty.
                return [
                    'gmt_offset' => intval(preg_replace('/UTC\+?/', '', $offset_or_tz)),
                    'timezone_string' => '',
                ];
            } else {
                if(in_array($offset_or_tz, timezone_identifiers_list())){
                    return [
                        'gmt_offset' => 0,
                        'timezone_string' => $offset_or_tz,
                    ];
                } else {
                    return [
                        'gmt_offset' => 0,
                        'timezone_string' => 'UTC',
                    ];
                }
            }
        }
    }

	/**
	 * @return string
	 */
	public static function one_p($text = '', $dot = true, $p = 'first'){
        if(false === strpos($text, '.')){
            if($dot){
                $text .= '.';
            }
            return $text;
        } else {
            $text = sanitize_text_field($text);
            $text = explode('.', $text);
			$text = array_map('trim', $text);
            $text = array_filter($text);
            switch($p){
                case 'first':
                    $text = array_shift($text);
                    break;
                case 'last':
                    $text = array_pop($text);
                    break;
                default:
                    $p = absint($p);
                    if(count($text) >= $p){
                        $p --;
                        $text = $text[$p];
                    } else {
                        $text = __('Error');
                    }
            }
            if($dot){
                $text .= '.';
            }
            return $text;
        }
    }

	/**
	 * @return string
	 */
	public static function prepare($str = '', ...$args){
        global $wpdb;
		if(!$args){
			return $str;
		}
		if(false === strpos($str, '%')){
			return $str;
		} else {
			return str_replace("'", '', $wpdb->remove_placeholder_escape($wpdb->prepare($str, ...$args)));
		}
    }

	/**
	 * @return array
	 */
	public static function post_type_labels($singular = '', $plural = '', $all = true){
        if(empty($singular)){
            return [];
        }
        if(empty($plural)){
            $plural = $singular;
        }
		return [
            'name' => $plural,
            'singular_name' => $singular,
            'add_new' => 'Add New',
            'add_new_item' => 'Add New ' . $singular,
            'edit_item' => 'Edit ' . $singular,
            'new_item' => 'New ' . $singular,
            'view_item' => 'View ' . $singular,
            'view_items' => 'View ' . $plural,
            'search_items' => 'Search ' . $plural,
            'not_found' => 'No ' . strtolower($plural) . ' found.',
            'not_found_in_trash' => 'No ' . strtolower($plural) . ' found in Trash.',
            'parent_item_colon' => 'Parent ' . $singular . ':',
            'all_items' => ($all ? 'All ' : '') . $plural,
            'archives' => $singular . ' Archives',
            'attributes' => $singular . ' Attributes',
            'insert_into_item' => 'Insert into ' . strtolower($singular),
            'uploaded_to_this_item' => 'Uploaded to this ' . strtolower($singular),
            'featured_image' => 'Featured image',
            'set_featured_image' => 'Set featured image',
            'remove_featured_image' => 'Remove featured image',
            'use_featured_image' => 'Use as featured image',
            'filter_items_list' => 'Filter ' . strtolower($plural) . ' list',
            'items_list_navigation' => $plural . ' list navigation',
            'items_list' => $plural . ' list',
            'item_published' => $singular . ' published.',
            'item_published_privately' => $singular . ' published privately.',
            'item_reverted_to_draft' => $singular . ' reverted to draft.',
            'item_scheduled' => $singular . ' scheduled.',
            'item_updated' => $singular . ' updated.',
        ];
    }

	/**
	 * @return string
	 */
	public static function recaptcha_branding(){
        return 'This site is protected by reCAPTCHA and the Google <a href="https://policies.google.com/privacy" target="_blank">Privacy Policy</a> and <a href="https://policies.google.com/terms" target="_blank">Terms of Service</a> apply.';
    }

	/**
	 * @return string
	 */
	public static function remote_country(){
		switch(true){
			case !empty($_SERVER['HTTP_CF_IPCOUNTRY']):
				$country = $_SERVER['HTTP_CF_IPCOUNTRY'];
				break;
			case is_callable(['wfUtils', 'IP2Country']):
				$country = \wfUtils::IP2Country(self::remote_ip());
				break;
			default:
				$country = '';
		}
		return strtoupper($country);
    }

	/**
	 * @return array|string|WP_Error
	 */
	public static function remote_delete($url = '', $args = []){
        return self::remote_request('DELETE', $url, $args);
    }

	/**
	 * @return array|string|WP_Error
	 */
	public static function remote_get($url = '', $args = []){
        return self::remote_request('GET', $url, $args);
    }

	/**
	 * @return array|string|WP_Error
	 */
	public static function remote_head($url = '', $args = []){
        return self::remote_request('HEAD', $url, $args);
    }

	/**
	 * @return string
	 */
	public static function remote_ip(){
		switch(true){
			case !empty($_SERVER['HTTP_CF_CONNECTING_IP']):
				$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
				break;
			case is_callable(['wfUtils', 'getIP']):
				$ip = \wfUtils::getIP();
				break;
			case !empty($_SERVER['HTTP_X_FORWARDED_FOR']):
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
				break;
			case !empty($_SERVER['HTTP_X_REAL_IP']):
				$ip = $_SERVER['HTTP_X_REAL_IP'];
				break;
			case !empty($_SERVER['REMOTE_ADDR']):
				$ip = $_SERVER['REMOTE_ADDR'];
				break;
			default:
				return '';
		}
		if(false === strpos($ip, ',')){
			$ip = trim($ip);
		} else {
			$ip = explode(',', $ip);
			$ip = array_map('trim', $ip);
			$ip = array_filter($ip);
			if(!$ip){
				return '';
			}
			$ip = $ip[0];
		}
		if(!\WP_Http::is_ip_address($ip)){
			return '';
		}
		return $ip;
	}

	/**
	 * @return array|string|WP_Error
	 */
	public static function remote_options($url = '', $args = []){
        return self::remote_request('OPTIONS', $url, $args);
    }

	/**
	 * @return array|string|WP_Error
	 */
	public static function remote_patch($url = '', $args = []){
        return self::remote_request('PATCH', $url, $args);
    }

	/**
	 * @return array|string|WP_Error
	 */
	public static function remote_post($url = '', $args = []){
        return self::remote_request('POST', $url, $args);
    }

	/**
	 * @return array|string|WP_Error
	 */
	public static function remote_put($url = '', $args = []){
        return self::remote_request('PUT', $url, $args);
    }

	/**
	 * @return array|string|WP_Error
	 */
	public static function remote_request($method = '', $url = '', $args = []){
		$args = self::sanitize_remote_args($args);
		$args['method'] = strtoupper($method);
		if(!self::seems_wp_http_request($args, true)){
			return self::error(__('Invalid request method.'));
		}
		if(empty($args['user-agent']) and !empty($_SERVER['HTTP_USER_AGENT'])){
			$args['user-agent'] = $_SERVER['HTTP_USER_AGENT'];
		}
		$response = wp_remote_request($url, $args);
		if(is_wp_error($response)){
			return $response;
		}
		$body = wp_remote_retrieve_body($response);
		$code = wp_remote_retrieve_response_code($response);
		$headers = wp_remote_retrieve_headers($response);
		$request = new \WP_REST_Request($method);
		$request->set_body($body);
		$request->set_headers($headers);
		$is_valid = $request->has_valid_params();
		if(is_wp_error($is_valid)){
			return $is_valid; // regardless of the response code
		}
		$is_json = $request->is_json_content_type();
		$json_params = [];
		if($is_json){
			$json_params = $request->get_json_params();
			$error = self::seems_wp_error($json_params);
			if(is_wp_error($error)){
				return $error; // regardless of the response code
			}
		}
		if($code >= 200 and $code < 300){
			if($is_json){
				return $json_params;
			}
			return $body;
		}
		$message = wp_remote_retrieve_response_message($response);
		if(empty($message)){
			$message = get_status_header_desc($code);
		}
		if(empty($message)){
			$message = __('Something went wrong.');
		}
		return self::error($message, [
			'body' => $body,
			'headers' => $headers,
			'status' => $code,
		]);
	}

	/**
	 * @return array|string|WP_Error
	 */
	public static function remote_trace($url = '', $args = []){
        return self::remote_request('TRACE', $url, $args);
    }

	/**
	 * @return string
	 */
	public static function remove_whitespaces($str = ''){
		return trim(preg_replace('/[\n\r\s\t]+/', ' ', $str));
    }

	/**
	 * @return array
	 */
	static public function sanitize_remote_args($args = []){
        if(!is_array($args)){
    		$args = wp_parse_args($args);
    	}
        if(self::seems_wp_http_request($args)){
            return $args;
        }
    	return [
    		'body' => $args,
    	];
    }

	/**
	 * @return int
	 */
	public static function sanitize_timeout($timeout = 0){
        $timeout = (int) $timeout;
        if($timeout < 0){
            $timeout = 0;
        }
        $max_execution_time = (int) ini_get('max_execution_time');
        if(0 !== $max_execution_time){
            if(0 === $timeout or $timeout > $max_execution_time){
                $timeout = $max_execution_time - 1;
            }
        }
        // TODO: check for Cloudflare Enterprise
        if(self::is_cloudflare()){
            if(0 === $timeout or $timeout > 99){
                $timeout = 99;
            }
        }
        return $timeout;
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
	 * @return bool
	 */
	static public function seems_wp_http_request($args = [], $method_verification = false){
        if(!is_array($args)){
    		return false;
    	}
    	$wp_http_request_args = ['method', 'timeout', 'redirection', 'httpversion', 'user-agent', 'reject_unsafe_urls', 'blocking', 'headers', 'cookies', 'body', 'compress', 'decompress', 'sslverify', 'sslcertificates', 'stream', 'filename', 'limit_response_size'];
    	$seems_wp_http_request = true;
    	foreach(array_keys($args) as $arg){
    		if(!in_array($arg, $wp_http_request_args)){
    			$seems_wp_http_request = false;
    			break;
    		}
    	}
    	if(!$method_verification){
    		return $seems_wp_http_request;
    	}
    	if(empty($args['method'])){
    		return false;
    	}
    	return in_array($args['method'], ['DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST', 'PUT', 'TRACE']);
    }

	/**
	 * @return bool|WP_Error
	 */
	static public function seems_wp_error($error = []){
		if(!self::array_keys_exist(['code', 'data', 'message'], $error)){
			return false;
		}
		if(4 === count($error)){
			if(!array_key_exists('additional_errors', $error)){
				return false;
			}
		} else {
			if(3 !== count($error)){
				return false;
			}
		}
		if(!$error['code'] or !$error['message']){
			return false;
		}
		return new \WP_Error($error['code'], $error['message'], $error['data']);
    }

	/**
	 * @return WP_Error|WP_User
	 */
	static public function signon($username_or_email = '', $password = '', $remember = false){
        if(is_user_logged_in()){
            return wp_get_current_user();
        } else {
			add_filter('wordfence_ls_require_captcha', [__CLASS__, 'wordfence_ls_require_captcha']);
            $user = wp_signon([
                'remember' => $remember,
                'user_login' => $username_or_email,
                'user_password' => $password,
            ]);
			remove_filter('wordfence_ls_require_captcha', [__CLASS__, 'wordfence_ls_require_captcha']);
			if(is_wp_error($user)){
				return $user;
			}
            return wp_set_current_user($user->ID);
        }
    }

	/**
	 * @return WP_Error|WP_User
	 */
	static public function signon_without_password($username_or_email = '', $remember = false){
        if(is_user_logged_in()){
            return wp_get_current_user();
        } else {
			add_filter('authenticate', [__CLASS__, 'authenticate'], 10, 2);
			add_filter('wordfence_ls_require_captcha', [__CLASS__, 'wordfence_ls_require_captcha']);
            $user = wp_signon([
                'remember' => $remember,
                'user_login' => $username_or_email,
                'user_password' => '',
            ]);
			remove_filter('wordfence_ls_require_captcha', [__CLASS__, 'wordfence_ls_require_captcha']);
			remove_filter('authenticate', [__CLASS__, 'authenticate']);
			if(is_wp_error($user)){
				return $user;
			}
            return wp_set_current_user($user->ID);
        }
    }

	/**
	 * @return string
	 */
	public static function str_split($str = '', $line_length = 55){
        $str = sanitize_text_field($str);
        $lines = ceil(strlen($str) / $line_length);
        $words = explode(' ', $str);
		if(count($words) <= $lines){
			return $words;
		}
		$length = 0;
		$index = 0;
		$oputput = [];
		foreach($words as $word){
			$word_length = strlen($word);
			if((($length + $word_length) <= $line_length) or empty($oputput[$index])){
				$oputput[$index][] = $word;
				$length += ($word_length + 1);
			} else {
				if($index < ($lines - 1)){
					$index ++;
				}
				$length = $word_length;
				$oputput[$index][] = $word;
			}
		}
		foreach($oputput as $index => $words){
			$oputput[$index] = implode(' ', $words);
		}
		return $oputput;
    }

	/**
	 * @return string
	 */
	public static function str_split_lines($str = '', $lines = 2){
        $str = sanitize_text_field($str);
        $words = explode(' ', $str);
		if(count($words) <= $lines){
			return $words;
		}
		$line_length = ceil(strlen($str) / $lines);
		$length = 0;
		$index = 0;
		$oputput = [];
		foreach($words as $word){
			$word_length = strlen($word);
			if((($length + $word_length) <= $line_length) or empty($oputput[$index])){
				$oputput[$index][] = $word;
				$length += ($word_length + 1);
			} else {
				if($index < ($lines - 1)){
					$index ++;
				}
				$length = $word_length;
				$oputput[$index][] = $word;
			}
		}
		foreach($oputput as $index => $words){
			$oputput[$index] = implode(' ', $words);
		}
		return $oputput;
    }

	/**
	 * @return DateTimeZone
	 */
	public static function timezone($offset_or_tz = ''){
        return new \DateTimeZone(self::timezone_string($offset_or_tz));
    }

	/**
	 * @return string
	 */
	public static function timezone_string($offset_or_tz = ''){
        $offset_or_tz = self::offset_or_tz($offset_or_tz);
        $timezone_string = $offset_or_tz['timezone_string'];
        if($timezone_string){
            return $timezone_string;
        }
        $offset = floatval($offset_or_tz['gmt_offset']);
        $hours = intval($offset);
        $minutes = ($offset - $hours);
        $sign = ($offset < 0) ? '-' : '+';
        $abs_hour = abs($hours);
        $abs_mins = abs($minutes * 60);
        $tz_offset = sprintf('%s%02d:%02d', $sign, $abs_hour, $abs_mins);
        return $tz_offset;
    }

	/**
	 * @return void
	 */
	public static function wp_enqueue_scripts(){
		if(self::$enqueue_stylesheet){
			wp_enqueue_style(get_stylesheet(), get_stylesheet_uri(), [], filemtime(get_stylesheet_directory() . '/style.css'));
		}
	}

	/**
	 * @return void
	 */
	public static function wp_head(){
		if(self::$hide_recaptcha_badge){ ?>
			<style type="text/css">
				.grecaptcha-badge {
					visibility: hidden !important;
				}
			</style><?php
		}
	}

	/**
	 * @return bool
	 */
	static public function wordfence_ls_require_captcha($required){
		return false;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// <!-- Zoom
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// Zoom -->
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
