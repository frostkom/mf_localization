<?php
/*
Plugin Name: MF Localization
Plugin URI: 
Description: 
Version: 2.1.5
Licence: GPLv2 or later
Author: Martin Fors
Author URI: https://frostkom.se
Text Domain: lang_localization
Domain Path: /lang

Depends: MF Base
*/

if(is_plugin_active("mf_base/index.php"))
{
	include_once("include/classes.php");

	$obj_localization = new mf_localization();

	add_action('cron_base', 'activate_localization', mt_rand(1, 10));

	if(is_admin())
	{
		register_activation_hook(__FILE__, 'activate_localization');
		register_deactivation_hook(__FILE__, 'deactivate_localization');
		register_uninstall_hook(__FILE__, 'uninstall_localization');

		add_action('admin_init', array($obj_localization, 'settings_localization'), 0);
		add_action('admin_init', array($obj_localization, 'admin_init'), 0);
		add_action('admin_menu', array($obj_localization, 'admin_menu'));
	}

	load_plugin_textdomain('lang_localization', false, dirname(plugin_basename(__FILE__))."/lang/");

	function activate_localization()
	{
		global $wpdb;

		$default_charset = DB_CHARSET != '' ? DB_CHARSET : "utf8";

		$arr_add_column = $arr_update_column = $arr_add_index = array();

		$wpdb->query("CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."localization (
			localizationID INT UNSIGNED NOT NULL AUTO_INCREMENT,
			localizationString TEXT,
			localizationTranslated TEXT,
			localizationPlugin VARCHAR(60) DEFAULT NULL,
			localizationVerified ENUM('0', '1') NOT NULL DEFAULT '0',
			localizationCreated DATETIME DEFAULT NULL,
			userID INT UNSIGNED DEFAULT NULL,
			PRIMARY KEY (localizationID)
		) DEFAULT CHARSET=".$default_charset);

		$arr_add_column[$wpdb->prefix."localization"] = array(
			'localizationPlugin' => "ALTER TABLE [table] ADD [column] VARCHAR(60) DEFAULT NULL AFTER localizationTranslated",
		);

		$arr_add_column[$wpdb->prefix."localization"] = array(
			'localizationCreated' => "ALTER TABLE [table] ADD [column] DATETIME DEFAULT NULL AFTER localizationVerified",
		);

		$arr_update_column[$wpdb->prefix."localization"] = array(
			//'' => "ALTER TABLE [table] CHANGE [column] [column] ",
		);

		$arr_add_index[$wpdb->prefix."localization"] = array(
			//'' => "ALTER TABLE [table] ADD INDEX [column] ([column])",
		);

		update_columns($arr_update_column);
		add_columns($arr_add_column);
		add_index($arr_add_index);
	}

	function deactivate_localization()
	{
		mf_uninstall_plugin(array(
			'options' => array('setting_localization_language'),
		));
	}

	function uninstall_localization()
	{
		mf_uninstall_plugin(array(
			'uploads' => 'mf_localization',
			'options' => array('setting_localization_api_key', 'option_localization_updated_files'),
			'tables' => array('localization'),
		));
	}
}