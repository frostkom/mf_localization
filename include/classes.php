<?php

class mf_localization
{
	var $id;
	var $post_type = "mf_localization";
	var $arr_languages = array( // https://tech.yandex.com/translate/doc/dg/concepts/api-overview-docpage/
		'da-DK' => 'da',
		'nb-NO' => 'no',
		'sv-SE' => 'sv',
	);
	var $blog_language;
	var $arr_po_strings;
	var $api_key;
	var $api_used;
	var $api_limit;
	var $generated_files;
	var $current_plugin;
	var $current_type;
	var $verified;
	var $translated;
	var $total;
	var $arr_texts;
	var $plugin;

	function __construct($id = 0)
	{
		if($id > 0)
		{
			$this->id = $id;
		}

		else
		{
			$this->id = check_var('intLocalizationID');
		}

		$this->blog_language = get_bloginfo('language');
	}

	function is_english()
	{
		return substr(get_bloginfo('language'), 0, 3) == 'en-';
	}

	function cron_base()
	{
		$obj_cron = new mf_cron();
		$obj_cron->start(__CLASS__);

		if($obj_cron->is_running == false)
		{
			// Delete old uploads
			#######################
			list($upload_path, $upload_url) = get_uploads_folder($this->post_type);

			get_file_info(array('path' => $upload_path, 'callback' => 'delete_files_callback', 'time_limit' => WEEK_IN_SECONDS));
			get_file_info(array('path' => $upload_path, 'folder_callback' => 'delete_empty_folder_callback'));
			#######################
		}

		$obj_cron->end();
	}

	/* Admin */
	function settings_localization()
	{
		if(IS_SUPER_ADMIN && $this->is_english() == false)
		{
			$options_area = __FUNCTION__;

			add_settings_section($options_area, "", array($this, $options_area."_callback"), BASE_OPTIONS_PAGE);

			$arr_settings = array(
				'setting_localization_api_key' => __("API Key", 'lang_localization'),
			);

			show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));
		}
	}

	function settings_localization_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);

		echo settings_header($setting_key, __("Localization", 'lang_localization'));
	}

	function setting_localization_api_key_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		settings_save_site_wide($setting_key);
		$option = get_site_option($setting_key, get_option($setting_key));

		$suffix = ($option == '' ? "<a href='//translate.yandex.com/developers/keys'>".__("Get yours here", 'lang_localization')."</a>" : "");

		echo show_textfield(array('name' => $setting_key, 'value' => $option, 'suffix' => $suffix));
	}

	function admin_init()
	{
		global $pagenow;

		if($pagenow == 'tools.php' && check_var('page') == 'mf_localization/list/index.php')
		{
			$plugin_include_url = plugin_dir_url(__FILE__);
			$plugin_version = get_plugin_version(__FILE__);

			mf_enqueue_style('style_localization_wp', $plugin_include_url."style_wp.css", $plugin_version);
			mf_enqueue_script('script_localization_wp', $plugin_include_url."script_wp.js", array('plugins_url' => plugins_url(), 'confirm_question' => __("Are you sure?", 'lang_localization')), $plugin_version);
		}
	}

	function get_count_message($data = array())
	{
		$out = "";

		if(IS_ADMINISTRATOR)
		{
			$option_localization_updated_files = get_option('option_localization_updated_files');

			if($option_localization_updated_files > 0)
			{
				$out = "&nbsp;<span class='update-plugins' title='".__("Files to Download", 'lang_localization')."'>
					<span>".$option_localization_updated_files."</span>
				</span>";
			}
		}

		return $out;
	}

	function admin_menu()
	{
		global $wpdb;

		if($this->is_english() == false && does_table_exist($wpdb->prefix."localization"))
		{
			$menu_root = $this->post_type."/";
			$menu_start = $menu_root.'list/index.php';
			$menu_capability = override_capability(array('page' => $menu_start, 'default' => 'edit_posts'));

			$count_message = $this->get_count_message();

			if($count_message != '' && current_user_can($menu_capability))
			{
				global $menu;

				if(!preg_match("/update-plugins/i", $menu[75][0]))
				{
					$menu[75][0] .= $count_message; // tools.php
				}
			}

			$menu_title = __("Localization", 'lang_localization');
			add_submenu_page("tools.php", $menu_title, $menu_title.$count_message, $menu_capability, $menu_start);
		}
	}

	/* Get localizations from PHP code */
	function init_po_strings()
	{
		$this->arr_po_strings = array();
	}

	function get_po_strings($data)
	{
		if(preg_match("/mf_.*\/(.*?)\.php/", $data['file']))
		{
			$file_content = get_file_content(array('file' => $data['file']));

			$arr_strings = get_match_all("/__\([\"\'](.*?)[\"\'],\s?[\"\'](.*?)[\"\']\)/", $file_content, false);

			if(count($arr_strings[0]) > 0)
			{
				$type_temp = '';
				$name_temp = '';

				if($name_temp == '' && preg_match("/\/plugins\//", $data['file']))
				{
					$type_temp = 'plugin';

					$plugin_dir = plugin_dir_path($data['file']."index.php");
					$plugin_dir_clean = trim(str_replace(WP_PLUGIN_DIR, "", $plugin_dir), "/");
					$arr_dir = explode("/", $plugin_dir_clean);

					if(substr($arr_dir[0], 0, 3) == "mf_")
					{
						$plugin_dir = WP_PLUGIN_DIR."/".$arr_dir[0]."/index.php";

						if(file_exists($plugin_dir))
						{
							$arr_plugin_data = get_plugin_data($plugin_dir);

							if(isset($arr_plugin_data['Name']) && $arr_plugin_data['Name'] != '')
							{
								$name_temp = $arr_plugin_data['Name'];
							}

							else
							{
								do_log("No name was returned when trying for ".$plugin_dir);
							}
						}

						else
						{
							do_log("File does not exist: ".$plugin_dir);
						}
					}

					else
					{
						do_log("mf_ does not exist in ".$data['file']." -> ".$plugin_dir." -> ".$plugin_dir_clean." -> ".var_export($arr_dir, true));
					}
				}

				if($name_temp == '' && preg_match("/\/themes\//", $data['file']))
				{
					$type_temp = 'theme';

					$arr_theme_data = wp_get_theme();
					$name_temp = $arr_theme_data['Name'];
				}

				if($name_temp == '')
				{
					$name_temp = $data['file'];
				}

				foreach($arr_strings[0] as $key => $value)
				{
					$plugin_temp = $arr_strings[1][$key];

					if(isset($this->arr_po_strings[$name_temp]))
					{
						$this->arr_po_strings[$name_temp]['strings'][md5($value.$plugin_temp)] = array($value, $plugin_temp);
					}

					else
					{
						$this->arr_po_strings[$name_temp] = array(
							'type' => $type_temp,
							'lang_id' => $plugin_temp,
							'strings' => array(
								md5($value.$plugin_temp) => array($value, $plugin_temp),
							),
						);
					}
				}
			}
		}
	}

	/* List */
	/*function fetch_request()
	{
		$this->translated = check_var('strLocalizationTranslated');
	}

	function save_data()
	{
		global $error_text, $done_text;

		$out = "";

		return $out;
	}*/

	function api_used_lately()
	{
		global $wpdb;

		return $wpdb->get_var("SELECT COUNT(localizationID) FROM ".$wpdb->prefix."localization WHERE localizationCreated > DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 0, ".$this->api_limit);
	}

	function get_translated_text($original_text, $plugin_temp)
	{
		global $wpdb;

		$log_message = __("I got no answer from the translation API", 'lang_localization');

		if(isset($this->arr_languages[$this->blog_language]))
		{
			$translation_url = "https://translate.yandex.net/api/v1.5/tr.json/translate?key=".$this->api_key."&text=".urlencode(html_entity_decode($original_text))."&lang=en-".$this->arr_languages[$this->blog_language]."&format=plain";

			list($content, $headers) = get_url_content(array('url' => $translation_url, 'catch_head' => true));

			if(isset($headers['http_code']))
			{
				switch($headers['http_code'])
				{
					case 200:
						$json = json_decode($content, true);

						if(isset($json['text']))
						{
							$translation = $json['text'][0];

							if($translation != '')
							{
								$wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->prefix."localization SET localizationString = %s, localizationTranslated = %s, localizationPlugin = %s, localizationCreated = NOW(), userID = '%d'", $original_text, $translation, $plugin_temp, get_current_user_id()));

								return "<i class='fas fa-cloud-download-alt'></i> ".$translation;
							}
						}

						$this->api_used++;
					break;

					default:
						do_log(sprintf("Something went wrong (%s)", $headers['http_code']." - ".$translation_url));

						$this->api_limit = 0;
					break;

					/*401 	Invalid API key
					402 	Blocked API key
					404 	Exceeded the daily limit on the amount of translated text
					413 	Exceeded the maximum text size
					422 	The text cannot be translated
					501		The specified translation direction is not supported*/
				}

				do_log($log_message, 'trash');
			}

			else
			{
				do_log($log_message);
			}
		}

		else
		{
			do_log(sprintf("The language %s is not recognizable", $this->blog_language));
		}
	}

	function generate_files()
	{
		$out = "";

		if($this->verified > 0 && count($this->arr_texts) > 0)
		{
			list($upload_path, $upload_url) = get_uploads_folder($this->post_type);

			$file = ($this->current_type == 'plugin' ? $this->current_plugin."-" : '').str_replace("-", "_", $this->blog_language).".po";

			$content = "msgid \"\"\n"
			."msgstr \"\"\n"
			."\"Project-Id-Version: \\n\"\n"
			."\"Report-Msgid-Bugs-To: \\n\"\n"
			."\"POT-Creation-Date: ".date("Y-m-d H:i")."+0100\\n\"\n"
			."\"PO-Revision-Date: \\n\"\n"
			."\"Last-Translator: ".get_user_info(array('id' => get_current_user_id(), 'type' => 'name'))."\\n\"\n"
			."\"Language-Team: \\n\"\n"
			."\"MIME-Version: 1.0\\n\"\n"
			."\"Content-Type: text/plain; charset=UTF-8\\n\"\n"
			."\"Content-Transfer-Encoding: 8bit\\n\"\n"
			."\"X-Poedit-Language: ".str_replace("-", "_", $this->blog_language)."\\n\"\n"
			."\"X-Poedit-SourceCharset: utf-8\\n\"\n"
			."\"X-Poedit-KeywordsList: esc_html__;esc_html_e;esc_attr__;esc_attr_e;__;_e\\n\"\n"
			."\"X-Poedit-Basepath: .\\n\"\n"
			."\"X-Poedit-SearchPath-0: ..\\n\"\n";

			foreach($this->arr_texts as $key => $value)
			{
				$content .= "\n\nmsgid \"".$key."\"\n"
				."msgstr \"".$value."\"";
			}

			$success = set_file_content(array('file' => $upload_path.$file, 'mode' => 'w', 'content' => $content));

			if($success)
			{
				$translated_percent = mf_format_number((($this->translated / $this->total) * 100), 0);

				$translated_button_class = 'is_disabled';
				$translated_span_class = '';

				if($translated_percent == 100)
				{
					$translated_button_class = '';
					$translated_span_class = 'color_green';
				}

				else if($translated_percent > 75)
				{
					$translated_button_class = '';
					$translated_span_class = 'color_yellow';
				}

				/*else if($translated_percent < 5)
				{
					$translated_span_class = 'color_red';
				}*/

				$out .= "<a href='".$upload_url.$file."' class='button".($translated_button_class != '' ? " ".$translated_button_class : '')."'>"
					.$file." ("
						.$this->verified." ".__("new", 'lang_localization')
						.", "
						."<span".($translated_span_class != '' ? " class='".$translated_span_class."'" : '').">".$translated_percent."% ".__("translated", 'lang_localization')."</span>"
						.")"
				."</a> ";

				$this->generated_files++;
			}
		}

		return $out;
	}
}