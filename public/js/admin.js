/**
 * PMP 2FA Authentication – Admin JS
 *
 * Handles: method toggle, trust-days toggle, test email/SMS,
 * revoke-own-devices, and per-user device lookup/revoke.
 *
 * @package PMP_2FA_Authentication
 * @since   2.0.0
 */

( function ( $ ) {
	'use strict';

	var cfg  = window.pmp2fa_admin || {};
	var ajax = cfg.ajax_url || '';
	var nonce = cfg.nonce  || '';
	var i18n  = cfg.i18n   || {};

	// ── Method toggle ─────────────────────────────────────────────────────────────

	function toggleSections() {
		var m = $( '#pmp2fa-method' ).val();
		$( '#pmp2fa-email-box' ).toggle( m !== 'sms' );
		$( '#pmp2fa-sms-box'   ).toggle( m !== 'email' );
	}
	$( '#pmp2fa-method' ).on( 'change', toggleSections );
	toggleSections();

	// ── Trust days row ────────────────────────────────────────────────────────────

	$( '#pmp2fa-remember' ).on( 'change', function () {
		$( '#pmp2fa-days-row' ).toggle( this.checked );
	} );

	// ── Test email ────────────────────────────────────────────────────────────────

	$( '#pmp2fa-test-email' ).on( 'click', function () {
		var $btn = $( this );
		var $r   = $( '#pmp2fa-email-result' );
		$btn.prop( 'disabled', true ).text( i18n.sending || 'Sending…' );
		$r.text( '' ).removeClass( 'err' );

		$.post( ajax, { action: 'pmp2fa_test_email', nonce: nonce } )
			.done( function ( res ) {
				$r.toggleClass( 'err', ! res.success ).text( res.data.message );
			} )
			.fail( function () {
				$r.addClass( 'err' ).text( i18n.error || 'Request failed.' );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( '📧 Send Test Email' );
			} );
	} );

	// ── Test SMS ──────────────────────────────────────────────────────────────────

	$( '#pmp2fa-test-sms' ).on( 'click', function () {
		var $btn = $( this );
		var $r   = $( '#pmp2fa-sms-result' );
		$btn.prop( 'disabled', true ).text( i18n.sending || 'Sending…' );
		$r.text( '' ).removeClass( 'err' );

		$.post( ajax, { action: 'pmp2fa_test_sms', nonce: nonce } )
			.done( function ( res ) {
				$r.toggleClass( 'err', ! res.success ).text( res.data.message );
			} )
			.fail( function () {
				$r.addClass( 'err' ).text( i18n.error || 'Request failed.' );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( '📱 Send Test SMS' );
			} );
	} );

	// ── Revoke my devices ─────────────────────────────────────────────────────────

	$( '#pmp2fa-revoke' ).on( 'click', function () {
		if ( ! confirm( i18n.confirm_revoke || 'Revoke all your trusted devices?' ) ) {
			return;
		}
		var $btn = $( this );
		var $r   = $( '#pmp2fa-revoke-result' );
		$btn.prop( 'disabled', true );

		$.post( ajax, { action: 'pmp2fa_revoke_devices', nonce: nonce } )
			.done( function ( res ) {
				$r.css( 'color', res.success ? 'green' : 'red' ).text( res.data.message );
			} )
			.fail( function () {
				$r.css( 'color', 'red' ).text( i18n.error || 'Request failed.' );
			} )
			.always( function () { $btn.prop( 'disabled', false ); } );
	} );

	// ── Revoke user devices ───────────────────────────────────────────────────────

	var foundUserId   = 0;
	var foundUserName = '';
	var lookupTimer;

	$( '#pmp2fa-revoke-user-input' ).on( 'input', function () {
		clearTimeout( lookupTimer );
		var $r = $( '#pmp2fa-revoke-user-result' );
		$r.text( '' ).css( 'color', '' );
		$( '#pmp2fa-revoke-user' ).prop( 'disabled', true );
		foundUserId = 0;

		var val = $.trim( $( this ).val() );
		if ( ! val ) { return; }

		lookupTimer = setTimeout( function () {
			$r.css( 'color', '#666' ).text( 'Looking up user…' );

			$.post( ajax, { action: 'pmp2fa_lookup_user', nonce: nonce, search: val } )
				.done( function ( res ) {
					if ( res.success ) {
						foundUserId   = res.data.user_id;
						foundUserName = res.data.display_name;
						var n         = res.data.devices;
						var devLabel  = n === 1 ? '1 trusted device' : n + ' trusted devices';
						if ( n === 0 ) {
							$r.css( 'color', '#666' ).text(
								'Found: ' + res.data.display_name + ' (' + res.data.email + ') — no trusted devices.'
							);
							$( '#pmp2fa-revoke-user' ).prop( 'disabled', true );
						} else {
							$r.css( 'color', '#2271b1' ).text(
								'Found: ' + res.data.display_name + ' (' + res.data.email + ') — ' + devLabel + '.'
							);
							$( '#pmp2fa-revoke-user' ).prop( 'disabled', false );
						}
					} else {
						$r.css( 'color', 'red' ).text( res.data.message );
					}
				} )
				.fail( function () {
					$r.css( 'color', 'red' ).text( i18n.error || 'Lookup failed.' );
				} );
		}, 500 );
	} );

	$( '#pmp2fa-revoke-user' ).on( 'click', function () {
		if ( ! foundUserId ) { return; }
		if ( ! confirm( ( i18n.confirm_revoke_user || 'Revoke trusted devices for' ) + ' ' + foundUserName + '?' ) ) {
			return;
		}
		var $btn = $( this );
		var $r   = $( '#pmp2fa-revoke-user-result' );
		$btn.prop( 'disabled', true );
		$r.css( 'color', '#666' ).text( 'Revoking…' );

		$.post( ajax, { action: 'pmp2fa_revoke_user_devices', nonce: nonce, user_id: foundUserId } )
			.done( function ( res ) {
				$r.css( 'color', res.success ? 'green' : 'red' ).text( res.data.message );
				if ( res.success ) {
					$( '#pmp2fa-revoke-user-input' ).val( '' );
					foundUserId = 0; foundUserName = '';
				} else {
					$btn.prop( 'disabled', false );
				}
			} )
			.fail( function () {
				$r.css( 'color', 'red' ).text( i18n.error || 'Request failed.' );
				$btn.prop( 'disabled', false );
			} );
	} );

} )( jQuery );
