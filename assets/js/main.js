/*
	Verti by HTML5 UP
	html5up.net | @ajlkn
	Free for personal and commercial use under the CCA 3.0 license (html5up.net/license)
*/

(function ($) {

	var $window = $(window),
		$body = $('body');

	// Breakpoints.
	breakpoints({
		xlarge: ['1281px', '1680px'],
		large: ['981px', '1280px'],
		medium: ['737px', '980px'],
		small: [null, '736px']
	});

	// Play initial animations on page load.
	$window.on('load', function () {
		window.setTimeout(function () {
			$body.removeClass('is-preload');
		}, 100);
	});

	// Dropdowns.
	$('#nav > ul').dropotron({
		mode: 'fade',
		noOpenerFade: true,
		speed: 300
	});

	// Nav.

	// Toggle.
	$(
		'<div id="navToggle">' +
		'<a href="#navPanel" class="toggle"></a>' +
		'</div>'
	)
		.appendTo($body);

	// Panel.
	$(
		'<div id="navPanel">' +
		'<nav>' +
		$('#nav').navList() +
		'</nav>' +
		'</div>'
	)
		.appendTo($body)
		.panel({
			delay: 500,
			hideOnClick: true,
			hideOnSwipe: true,
			resetScroll: true,
			resetForms: true,
			side: 'left',
			target: $body,
			visibleClass: 'navPanel-visible'
		});

		$("#user_document_url_container").hide();
	$("#document_location").on( "change" , function(){
		
		$(".file-field-group").hide();
		if( $(this).val() == "local_file" )
		{
			$("#user_document_location_container").show();
		}

		if( $(this).val() == "url" )
		{
			$("#user_document_url_container").show();
		}
	});

	$(".search_field").on( "keyup" , function(){
		filter_users( $("#txt_filter_name").val() , $("#txt_filter_faculty").val() );
	});

})(jQuery);


function filter_users( name , faculty )
{
    let filters = { name: name , faculty: faculty };

    if( name == "" && faculty == "" )
    {
        $("tbody tr").show();
    }
    else
    {
        $("tbody tr").hide();
    }
    
    $.each(filters, function (key, search_term) {
        $("tbody [data-search-key='" + key + "']").each(function( index , cell ){
            if( search_term != "" && $(cell).html().toUpperCase().indexOf(search_term.toUpperCase()) >= 0 )
            {
                $(cell).parents("tr").show();
            }
        });
    });
}