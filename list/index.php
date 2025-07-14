<?php

$obj_localization = new mf_localization();

echo "<div class='wrap'>
	<h2>"
		.__("Localization", 'lang_localization')
		."<a href='#' class='add-new-h2 toggle_tables'>".__("Toggle Tables", 'lang_localization')."</a>"
		."<a href='#' class='add-new-h2 toggle_translated'>".__("Toggle Translated", 'lang_localization')."</a>"
		."<a href='#' class='add-new-h2 toggle_empty'>".__("Toggle Empty", 'lang_localization')."</a>"
	."</h2>";

	$obj_localization->init_po_strings();

	get_file_info(array('path' => WP_PLUGIN_DIR, 'callback' => array($obj_localization, 'get_po_strings')));
	get_file_info(array('path' => get_theme_root(), 'callback' => array($obj_localization, 'get_po_strings')));

	$obj_localization->api_key = get_site_option('setting_localization_api_key');
	$obj_localization->api_used = 0;
	$obj_localization->api_limit = 10;

	$out_files = $out = "";
	$obj_localization->generated_files = 0;

	foreach($obj_localization->arr_po_strings as $file => $array)
	{
		$arr_strings = $array['strings'];

		$obj_localization->current_plugin = $array['lang_id'];
		$obj_localization->current_type = $array['type'];

		$obj_localization->total = $obj_localization->translated = $obj_localization->verified = 0;
		$obj_localization->arr_texts = [];

		$out_body = "";

		foreach($arr_strings as $array)
		{
			$original_text = $array[0];
			$obj_localization->plugin = $array[1];
			$translated_text = __($original_text, $obj_localization->plugin);

			$is_translated = ($translated_text != $original_text);
			$has_suggestion = true;

			$translated_out = $row_actions = "";
			$intLocalizationID = 0;

			$result = $wpdb->get_results($wpdb->prepare("SELECT localizationID, localizationString, localizationTranslated, localizationVerified, localizationCreated, userID FROM ".$wpdb->prefix."localization WHERE localizationString = %s AND (localizationPlugin = %s OR localizationPlugin IS NULL)", $original_text, $obj_localization->plugin)); // AND localizationTranslated != localizationString

			if($wpdb->num_rows > 0)
			{
				$i = 0;

				foreach($result as $r)
				{
					$intLocalizationID = $r->localizationID;
					$strLocalizationString = $r->localizationString;
					$strLocalizationTranslated = $r->localizationTranslated;
					$intLocalizationVerified = $r->localizationVerified;
					$dteLocalizationCreated = $r->localizationCreated;
					$intUserID = $r->userID;

					if($i > 0 || ($is_translated && $strLocalizationTranslated == $translated_text) || $strLocalizationTranslated == $strLocalizationString || $strLocalizationTranslated == '')
					{
						$wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix."localization WHERE localizationID = '%d'", $intLocalizationID));
					}

					else
					{
						if($intLocalizationVerified == 1 && $strLocalizationTranslated != '')
						{
							$is_translated = true;
							$translated_text = $strLocalizationTranslated;
							$obj_localization->verified++;

							$translated_out .= "<i class='fas fa-user-check green'></i> ";

							if($strLocalizationString == $strLocalizationTranslated && $dteLocalizationCreated < date("Y-m-d H:i:s", strtotime("-1 year")))
							{
								$wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix."localization WHERE localizationID = '%d'", $intLocalizationID));
							}
						}

						else
						{
							$translated_out .= "<i class='fa fa-question-circle'></i> ";

							$obj_localization->api_used++;
						}

						if($strLocalizationTranslated != '') // && $strLocalizationTranslated != $strLocalizationString
						{
							$translated_out .= $strLocalizationTranslated;

							if($intLocalizationVerified == 1)
							{
								if($intUserID > 0)
								{
									$translated_out .= " <span class='grey'>".get_user_info(array('id' => $intUserID, 'type' => 'short_name'))."</span>";
								}
							}

							else
							{
								$row_actions .= ($row_actions != '' ? " | " : '')."<a href='#verify/".$intLocalizationID."' class='ajax_link confirm_link'>".__("Verify", 'lang_localization')."</a>";
							}
						}

						$row_actions .= ($row_actions != '' ? " | " : '')."<a href='#change/".$intLocalizationID."' class='ajax_link'>".__("Change", 'lang_localization')."</a>";
					}

					$i++;
				}
			}

			else if($is_translated)
			{
				$translated_out = $translated_text;

				$row_actions .= ($row_actions != '' ? " | " : '')."<a href='#add/".$obj_localization->plugin."/".$original_text."' class='ajax_link'>".__("Add", 'lang_localization')."</a>";
			}

			else if($obj_localization->api_key != '' && $obj_localization->api_used < $obj_localization->api_limit) // $obj_localization->api_used_lately() < $obj_localization->api_limit
			{
				$translated_out .= $obj_localization->get_translated_text($original_text, $obj_localization->plugin);
			}

			else
			{
				$result = $wpdb->get_results($wpdb->prepare("SELECT localizationTranslated FROM ".$wpdb->prefix."localization WHERE localizationString = %s AND localizationPlugin != %s AND localizationTranslated != '' LIMIT 0, 1", $original_text, $obj_localization->plugin));

				if($wpdb->num_rows > 0)
				{
					foreach($result as $r)
					{
						$strLocalizationTranslated = $r->localizationTranslated;

						if($strLocalizationTranslated != $original_text)
						{
							$wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->prefix."localization SET localizationString = %s, localizationTranslated = %s, localizationPlugin = %s, localizationCreated = NOW(), userID = '%d'", $original_text, $strLocalizationTranslated, $obj_localization->plugin, get_current_user_id()));

							$intLocalizationID = $wpdb->insert_id;

							$translated_out .= $strLocalizationTranslated;

							$row_actions .= ($row_actions != '' ? " | " : '')."<a href='#change/".$intLocalizationID."' class='ajax_link'>".__("Change", 'lang_localization')."</a>";
						}
					}
				}

				else
				{
					$translated_out .= "<span class='grey'>(".__("none", 'lang_localization').")</span>";

					$row_actions .= ($row_actions != '' ? " | " : '')."<a href='#add/".$obj_localization->plugin."/".$original_text."' class='ajax_link'>".__("Add", 'lang_localization')."</a>";

					$has_suggestion = false;
				}
			}

			$out_body .= "<tr".($intLocalizationID > 0 ? " id='location_".$intLocalizationID."'" : "").">
				<td>";

					if($obj_localization->plugin != $obj_localization->current_plugin)
					{
						$out_body .= "<i class='fa fa-times red'></i>
						<div class='row-actions'>".$obj_localization->plugin."</div>
						<i class='set_tr_color' rel='red has_errors'></i>";
					}

					else if($is_translated)
					{
						$out_body .= "<i class='fa fa-check green'></i>
						<i class='set_tr_color' rel='green is_translated hide'></i>";
					}

					else
					{
						$out_body .= "<i class='fa fa-times red'></i>
						<i class='set_tr_color' rel='yellow ".($has_suggestion ? "has_suggestion" : "is_empty")."'></i>";
					}

				$out_body .= "</td>
				<td>".$original_text."</td>
				<td>"
					.$translated_out;

					if($row_actions != '')
					{
						$out_body .= "<div class='row-actions'>".$row_actions."</div>";
					}

				$out_body .= "</td>
			</tr>";

			if($is_translated)
			{
				$obj_localization->arr_texts[$original_text] = $translated_text;

				$obj_localization->translated++;
			}

			$obj_localization->total++;
		}

		$out .= "<h3 class='toggle_table'>".$file." (".$obj_localization->current_plugin.", ".sprintf(__("%d left", 'lang_localization'), ($obj_localization->total - $obj_localization->translated)).")</h3>
		<table class='wp-list-table widefat striped'>
			<thead>
				<tr>
					<th class='column-primary'></th>
					<th>".__("English", 'lang_localization')."</th>
					<th>".__("Translated", 'lang_localization')."</th>
				</tr>
			</thead>
			<tbody>".$out_body."</tbody>
		</table>";

		$out_files .= $obj_localization->generate_files();
	}

	update_option('option_localization_updated_files', $obj_localization->generated_files, false);

	if($out != '')
	{
		if(IS_SUPER_ADMIN && $out_files != '')
		{
			echo "<div id='poststuff' class='postbox'>
				<h3 class='hndle'>".__("Download", 'lang_localization')."</h3>
				<div class='inside'>".$out_files."</div>
			</div>";
		}

		echo $out;
	}

echo "</div>";