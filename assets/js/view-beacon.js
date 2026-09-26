( function () {
	var cfg = window.stwyView;
	if ( ! cfg || ! cfg.url || ! cfg.productId ) {
		return;
	}

	var body = new URLSearchParams( { product_id: String( cfg.productId ) } );

	if ( navigator.sendBeacon ) {
		navigator.sendBeacon( cfg.url, body );
	} else if ( window.fetch ) {
		window.fetch( cfg.url, { method: 'POST', body: body, keepalive: true, credentials: 'omit' } );
	}
} )();
