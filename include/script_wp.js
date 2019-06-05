jQuery(function($)
{
	function show_hide_tables()
	{
		$(".toggle_table").next("table").each(function()
		{
			if($(this).find("tbody tr:not(.hide)").length > 0)
			{
				$(this).removeClass('hide').prev(".toggle_table").removeClass('hide');
			}

			else
			{
				$(this).addClass('hide').prev(".toggle_table").addClass('hide');
			}
		});
	}

	show_hide_tables();

	$(document).on('click', ".toggle_tables", function()
	{
		$(".toggle_table:not(.hide)").next("table").toggleClass('hide');
	});

	$(document).on('click', ".toggle_translated", function()
	{
		$(".wp-list-table .is_translated").toggleClass('hide');

		show_hide_tables();
	});

	$(document).on('click', ".toggle_empty", function()
	{
		$(".wp-list-table .is_empty").toggleClass('hide');

		show_hide_tables();
	});

	$(document).on('click', ".toggle_table", function()
	{
		$(this).next("table").toggleClass('hide');
	});

	$(document).on('click', ".ajax_link", function()
	{
		var dom_obj = $(this),
			type = dom_obj.attr('href').substring(1);

		if($(this).hasClass("confirm_link") && !confirm(script_localization_wp.confirm_question))
		{
			return false;
		}

		$.ajax(
		{
			url: script_localization_wp.plugins_url + '/mf_localization/include/api/?type=' + type,
			type: 'get',
			dataType: 'json',
			success: function(data)
			{
				if(data.success)
				{
					dom_obj.parents("td").html(data.message);
				}

				else
				{
					dom_obj.parents("td").append(data.error);
				}
			}
		});

		return false;
	});

	$(document).on('submit', ".localization_change", function()
	{
		var dom_obj = $(this),
			type = dom_obj.attr('rel'),
			form_data = dom_obj.serialize();

		$.ajax(
		{
			url: script_localization_wp.plugins_url + '/mf_localization/include/api/?type=' + type,
			type: 'post',
			dataType: 'json',
			data: form_data,
			success: function(data)
			{
				if(data.success)
				{
					dom_obj.parents("td").html(data.message);
				}

				else
				{
					dom_obj.parents("td").append(data.error);
				}
			}
		});

		return false;
	});
});