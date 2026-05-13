jQuery(function($){ // use jQuery code inside this to avoid "$ is not defined" error
	$('.misha_loadmore').click(function() {
 
		var button = $(this),
		    data = {
			'action': 'loadmore',
			'query': misha_loadmore_params.posts, // that's how we get params from wp_localize_script() function
			'page' : misha_loadmore_params.current_page,
			'topids': misha_loadmore_params.ids || [],
			'category': misha_loadmore_params.tax_query || [],
		};
 
		$.ajax({ // you can also use $.post here
			url : misha_loadmore_params.ajaxurl, // AJAX handler
			data : data,
			type : 'POST',
			beforeSend : function ( xhr ) {
				button.text('Loading...'); // change the button text, you can also add a preloader image
			},
			success : function( data ){
				if( data ) { 
					button.text( 'View More' ); // .prev().before(data); // insert new posts
					$('.lm-container').append(data);
					misha_loadmore_params.current_page++;
 
					if ( misha_loadmore_params.current_page == misha_loadmore_params.max_page ) 
						button.remove(); // if last page, remove the button
 
					// you can also fire the "post-load" event here if you use a plugin that requires it
					// $( document.body ).trigger( 'post-load' );
				} else {
					button.remove(); // if no data, remove the button as well
				}
			}
		});
	});
	
	//if( cookieBar.isShow ) {
		$.cookieBar({
			//language: 'en',
			style: 'bottom',
			infoLink: cookieBar.link || 'javascript:;',
			message: cookieBar.message,
			acceptText: cookieBar.text,
			//privacy: 'popup',
			//privacyContent: 'That the quick brown',
			//infoText:	'Mehr sss',
			//privacyText:	'Datenschutz'
		});
	//}
	
});