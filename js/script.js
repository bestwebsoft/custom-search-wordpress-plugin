( function( $ ) {
	$( document ).ready( function() {
		$( '.cstmsrch_select_all_block input' ).show();
		$( '#cstmsrch_settings_form' ).on( 'change click select', 'input', function() {
			var $select_all = $( this ).closest('.cstmsrch-checkbox-section').find('.cstmsrch_cb_select_all'),
				$checkboxes = $( this ).closest('.cstmsrch-checkbox-section').find('.cstmsrch_cb_select'),
				checkboxes_size = $checkboxes.size(),
				checkboxes_selected_size = $checkboxes.filter( ':checked' ).size();
			if ( $( this ).hasClass( 'cstmsrch_cb_select_all' ) ) {
				if ( $( this ).is( ':checked' ) ) {
					$checkboxes.attr( 'checked', true );
				} else {
					$checkboxes.attr( 'checked', false );
				}
			} else {
				if ( checkboxes_size == checkboxes_selected_size ) {
					$select_all.attr( 'checked', true );
				} else {
					$select_all.attr( 'checked', false );
				}
			}
		} );
	} );
} )( jQuery );