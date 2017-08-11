jQuery(document).ready(function($){
	$(document).on('click', '.woocommerce_subscription_gifting_checkbox',function() {
		if ($(this).is(':checked')) {
			$(this).siblings('.woocommerce_subscriptions_gifting_recipient_email').slideDown( 250 );
		} else {
			$(this).siblings('.woocommerce_subscriptions_gifting_recipient_email').slideUp( 250 );

			var recipient_email_element = $(this).parent().find('.recipient_email');
			recipient_email_element.val('');

			if ( $( 'form.checkout' ).length !== 0 ) {
				// Trigger the event to update the checkout after the recipient field has been cleared
				recipient_email_element.trigger( 'focusout' );
			}
		}
	});

	$(document).on('submit', 'div.woocommerce > form', function(evt) {

		var $form = $( evt.target );
		var $submit = $( document.activeElement );

		// if we're not on the cart page exit
		if ( 0 === $form.find( 'table.shop_table.cart' ).length ) {
			return;
		}

		// if the recipient email element is the active element, the clicked button is the update cart button
		if ( $submit.is( 'input.recipient_email' ) ) {
			 $( 'input[type=submit][name=update_cart]').attr( 'clicked', 'true' );
		}
	});

	/*******************************************
	 * Update checkout on input changed events *
	 *******************************************/
	var update_timer;

	$(document).on( 'focusout', '.recipient_email', function() {

		if ( $( 'form.checkout' ).length === 0 ) {
			return;
		}

		var new_recipient_email      = $( this ).val();
		var existing_recipient_email = $( this ).prop( "defaultValue" );

		// If the recipient has changed, update the checkout so recurring carts are updated
		if ( new_recipient_email !== existing_recipient_email ) {
			update_checkout();
		}
	});

	$(document).on( 'keydown', '.recipient_email', function( e ) {
		var code = e.keyCode || e.which || 0;

		if ( code === 9 ) {
			return true;
		}

		reset_checkout_update_timer();
		update_timer = setTimeout( update_checkout, '1500' );
	});

	function update_checkout() {
		$( document.body ).trigger( 'update_checkout' );
	}

	function reset_checkout_update_timer() {
		clearTimeout( update_timer );
	}
});
