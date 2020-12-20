<?php

if(!defined('ABSPATH'))
{
	header('Content-Type: application/json');

	$folder = str_replace("/wp-content/plugins/mf_localization/include/api", "/", dirname(__FILE__));

	require_once($folder."wp-load.php");
}

$json_output = array();

$type = check_var('type', 'char');
$arr_input = explode("/", $type);

$type_action = $arr_input[0];
$type_value_1 = isset($arr_input[1]) ? $arr_input[1] : "";
$type_value_2 = isset($arr_input[2]) ? $arr_input[2] : "";

if(is_user_logged_in())
{
	switch($type_action)
	{
		case 'verify':
			$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."localization SET localizationVerified = '1', localizationCreated = NOW(), userID = '%d' WHERE localizationID = '%d'", get_current_user_id(), $type_value_1));

			if($wpdb->rows_affected > 0)
			{
				$json_output['success'] = true;
				$done_text = __("The translation was verified", 'lang_localization');
				$json_output['message'] = get_notification();
			}

			else
			{
				$error_text = __("I could not verify the translation for you", 'lang_localization');
				$json_output['error'] = get_notification();
			}
		break;

		case 'add':
			$wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->prefix."localization SET localizationString = %s, localizationTranslated = %s, localizationPlugin = %s, localizationCreated = NOW(), userID = '%d'", $type_value_2, '', $type_value_1, get_current_user_id()));

			$intLocalizationID = $wpdb->insert_id;

			$result = $wpdb->get_results($wpdb->prepare("SELECT localizationString, localizationTranslated FROM ".$wpdb->prefix."localization WHERE localizationID = '%d'", $intLocalizationID));

			foreach($result as $r)
			{
				$json_output['success'] = true;
				$json_output['message'] = "<form action='' method='post' class='mf_form localization_change' rel='update/".$type_value_1."'>"
					.show_textfield(array('name' => 'strLocalizationTranslated', 'text' => $r->localizationString, 'value' => $r->localizationTranslated, 'required' => true))
					.show_button(array('name' => 'btnLocalizationUpdate', 'text' => __("Update", 'lang_localization')))
					.input_hidden(array('name' => 'intLocalizationID', 'value' => $intLocalizationID))
					//.wp_nonce_field('update_localization_'.$intLocalizationID, '_wpnonce_update_localization', true, false)
				."</form>";
			}
		break;

		case 'change':
			$result = $wpdb->get_results($wpdb->prepare("SELECT localizationString, localizationTranslated FROM ".$wpdb->prefix."localization WHERE localizationID = '%d'", $type_value_1));

			foreach($result as $r)
			{
				$json_output['success'] = true;
				$json_output['message'] = "<form action='' method='post' class='mf_form localization_change' rel='update/".$type_value_1."'>"
					.show_textfield(array('name' => 'strLocalizationTranslated', 'text' => $r->localizationString, 'value' => $r->localizationTranslated)) //, 'required' => true
					.show_button(array('name' => 'btnLocalizationUpdate', 'text' => __("Update", 'lang_localization')))
					.input_hidden(array('name' => 'intLocalizationID', 'value' => $type_value_1))
					//.wp_nonce_field('update_localization_'.$type_value_1, '_wpnonce_update_localization', true, false)
				."</form>";
			}
		break;

		case 'update':
			$intLocalizationID = check_var('intLocalizationID');
			$strLocalizationTranslated = check_var('strLocalizationTranslated');

			if($intLocalizationID > 0)
			{
				$strLocalizationString = $wpdb->get_var($wpdb->prepare("SELECT localizationString FROM ".$wpdb->prefix."localization WHERE localizationID = '%d'", $intLocalizationID));

				if($strLocalizationTranslated == $strLocalizationString || $strLocalizationTranslated == '')
				{
					$wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix."localization WHERE localizationID = '%d'", $intLocalizationID));

					if($wpdb->rows_affected > 0)
					{
						$json_output['success'] = true;

						if($strLocalizationTranslated == $strLocalizationString)
						{
							$done_text = __("A translation is only necessary if it differs from the original, so it was not saved", 'lang_localization');
						}

						else
						{
							$done_text = __("The translation was deleted", 'lang_localization');
						}

						$json_output['message'] = get_notification();
					}

					else
					{
						$error_text = __("I could not delete the translation for you", 'lang_localization');
						$json_output['error'] = get_notification();
					}
				}

				else
				{
					$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."localization SET localizationTranslated = %s, localizationVerified = '1', localizationCreated = NOW(), userID = '%d' WHERE localizationID = '%d'", $strLocalizationTranslated, get_current_user_id(), $intLocalizationID));

					if($wpdb->rows_affected > 0)
					{
						$json_output['success'] = true;
						$done_text = __("The translation was updated", 'lang_localization');
						$json_output['message'] = get_notification();
					}

					else
					{
						$error_text = __("I could not update the translation for you", 'lang_localization');
						$json_output['error'] = get_notification();
					}
				}
			}

			else
			{
				$error_text = __("I could not update the translation for you because the request lacked information", 'lang_localization');
				$json_output['error'] = get_notification();
			}
		break;
	}
}

echo json_encode($json_output);