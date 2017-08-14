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
				update_checkout();
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

	$(document).on( 'change', '.recipient_email', function() {

		if ( $( 'form.checkout' ).length === 0 ) {
			return;
		}

		// Update the checkout so recurring carts are updated
		if ( $( this ).hasClass( 'wcsg_needs_update' ) ) {
			update_checkout();
		}
	});

	$(document).on( 'keyup', '.recipient_email', function( e ) {
		var code = e.keyCode || e.which || 0;

		if ( $( 'form.checkout' ).length === 0 || code === 9 ) {
			return true;
		}

		var current_recipient  = $( this ).val();
		var original_recipient = $( this ).attr( 'data-recipient' );
		reset_checkout_update_timer();

		// If the recipient has changed since last load, mark the element as needing an update
		if ( current_recipient !== original_recipient ) {
			$( this ).addClass( 'wcsg_needs_update' );
			update_timer = setTimeout( update_checkout, '1500' );
		} else {
			$( this ).removeClass( 'wcsg_needs_update' );
		}
	});

	function update_checkout() {
		reset_checkout_update_timer();
		$( '.recipient_email' ).removeClass( 'wcsg_needs_update' );
		$( document.body ).trigger( 'update_checkout' );
	}

	function reset_checkout_update_timer() {
		clearTimeout( update_timer );
	}
});
