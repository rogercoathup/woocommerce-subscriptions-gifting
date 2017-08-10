jQuery(document).ready(function($){
	$(document).on('click', '.woocommerce_subscription_gifting_checkbox',function() {
		if ($(this).is(':checked')) {
			$(this).siblings('.woocommerce_subscriptions_gifting_recipient_email').slideDown( 250 );
		} else {
			$(this).siblings('.woocommerce_subscriptions_gifting_recipient_email').slideUp( 250 );
			$(this).parent().find('.recipient_email').val('');
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

	$(document).on( 'focusout', '.recipient_email',function() {

		if ( $( 'form.checkout' ).length === 0 ) {
			return;
		}

		var new_recipient_email      = $( this ).val();
		var existing_recipient_email = $( this ).prop( "defaultValue" );

		// if the recipient has changed, update the checkout so recurring carts are updated
		if ( new_recipient_email !== existing_recipient_email ) {
			$( document.body ).trigger( 'update_checkout' );
		}
	});
});
