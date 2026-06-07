/**
 * PMP 2FA Authentication – Overlay JS
 *
 * Handles OTP form submission, resend countdown, method tab switching,
 * and Escape-key dismissal. Vanilla JS only — no jQuery dependency.
 *
 * @package PMP_2FA_Authentication
 * @since   2.0.0
 */

( function () {
	'use strict';

	// ── DOM ready ────────────────────────────────────────────────────────────────

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {

		var cfg       = window.pmp2fa_cfg || {};
		var ajaxUrl   = cfg.ajax_url   || '';
		var nonce     = cfg.nonce      || '';
		var i18n      = cfg.i18n       || {};
		var isOverlay = !! cfg.is_overlay;

		var form      = document.getElementById( 'pmp2fa-form' );
		var otpInput  = document.getElementById( 'pmp2fa-otp' );
		var submitBtn = document.getElementById( 'pmp2fa-submit' );
		var resendBtn = document.getElementById( 'pmp2fa-resend' );
		var cdSpan    = document.getElementById( 'pmp2fa-countdown' );
		var notice    = document.getElementById( 'pmp2fa-notice' );
		var cancelLink = document.getElementById( 'pmp2fa-cancel' );
		var tabs      = document.querySelectorAll( '.pmp2fa-tab' );

		if ( ! form || ! otpInput ) {
			return;
		}

		var currentMethod = 'email';
		var cdTimer;
		var activeTab = document.querySelector( '.pmp2fa-tab.is-active' );
		if ( activeTab ) {
			currentMethod = activeTab.getAttribute( 'data-method' ) || 'email';
		}

		// Lock page scroll behind modal.
		if ( isOverlay ) {
			document.body.classList.add( 'pmp2fa-locked' );
		}

		// ── Helpers ──────────────────────────────────────────────────────────────

		function showNotice( msg, type ) {
			notice.className  = 'pmp2fa-notice pmp2fa-notice--' + ( type || 'info' );
			notice.innerHTML  = msg;
			notice.style.display = 'block';
		}

		function hideNotice() {
			notice.style.display = 'none';
			notice.className     = 'pmp2fa-notice';
		}

		function setLoading( on ) {
			submitBtn.disabled = on;
			submitBtn.classList.toggle( 'is-loading', on );
			var txt = submitBtn.querySelector( '.pmp2fa-btn-text' );
			if ( txt ) {
				txt.textContent = on
					? ( i18n.verifying  || 'Verifying…' )
					: ( i18n.verify_btn || 'Verify Code' );
			}
		}

		/**
		 * Lightweight XHR POST — returns a promise-like object with done/fail/always.
		 *
		 * @param {Object} data Key-value pairs to send as application/x-www-form-urlencoded.
		 * @param {Function} cb Callback(err, parsedJSON).
		 */
		function post( data, cb ) {
			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', ajaxUrl, true );
			xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
			xhr.onload = function () {
				try {
					cb( null, JSON.parse( xhr.responseText ) );
				} catch ( e ) {
					cb( 'parse_error', null );
				}
			};
			xhr.onerror = function () { cb( 'network_error', null ); };
			var pairs = [];
			for ( var k in data ) {
				if ( Object.prototype.hasOwnProperty.call( data, k ) ) {
					pairs.push( encodeURIComponent( k ) + '=' + encodeURIComponent( data[ k ] ) );
				}
			}
			xhr.send( pairs.join( '&' ) );
		}

		// ── Countdown ────────────────────────────────────────────────────────────

		function startCountdown( secs ) {
			clearInterval( cdTimer );
			resendBtn.disabled = true;
			var left = secs;

			function tick() {
				if ( left <= 0 ) {
					clearInterval( cdTimer );
					resendBtn.disabled = false;
					if ( cdSpan ) { cdSpan.textContent = ''; }
					return;
				}
				if ( cdSpan ) { cdSpan.textContent = ' (' + left + 's)'; }
				left--;
			}
			tick();
			cdTimer = setInterval( tick, 1000 );
		}

		// ── OTP input ─────────────────────────────────────────────────────────────

		// Strip non-digits; auto-submit on full length.
		otpInput.addEventListener( 'input', function () {
			this.value = this.value.replace( /\D/g, '' );
			var maxLen = parseInt( this.getAttribute( 'maxlength' ) || '6', 10 );
			if ( this.value.length === maxLen ) {
				form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
			}
		} );

		// Handle paste — strip non-digits.
		otpInput.addEventListener( 'paste', function ( e ) {
			var raw    = ( e.clipboardData || window.clipboardData ).getData( 'text' );
			var digits = raw.replace( /\D/g, '' );
			var el     = this;
			setTimeout( function () {
				el.value = digits;
				el.dispatchEvent( new Event( 'input' ) );
			}, 0 );
			e.preventDefault();
		} );

		// Autofocus after a short delay (needed on some browsers).
		setTimeout( function () { otpInput.focus(); }, 160 );

		// ── Verify OTP ────────────────────────────────────────────────────────────

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			hideNotice();

			var otp = otpInput.value.trim();
			if ( ! otp ) {
				showNotice( i18n.enter_code || 'Please enter the verification code.', 'error' );
				otpInput.focus();
				return;
			}

			var rememberEl = form.querySelector( '[name="remember_device"]' );
			var remember   = ( rememberEl && rememberEl.checked ) ? '1' : '0';

			setLoading( true );

			post(
				{
					action          : 'pmp2fa_verify_otp',
					nonce           : nonce,
					otp             : otp,
					remember_device : remember,
				},
				function ( err, res ) {
					if ( err || ! res ) {
						showNotice( 'Connection error. Please try again.', 'error' );
						setLoading( false );
						return;
					}

					if ( res.success ) {
						showNotice( '&#10003; ' + ( i18n.verified || 'Verified! Redirecting…' ), 'success' );
						if ( isOverlay ) {
							document.body.classList.remove( 'pmp2fa-locked' );
						}
						setTimeout( function () {
							window.location.href = ( res.data && res.data.redirect ) ? res.data.redirect : '/';
						}, 700 );
					} else {
						showNotice(
							( res.data && res.data.message ) ? res.data.message : 'Verification failed.',
							'error'
						);
						otpInput.value = '';
						otpInput.focus();
						setLoading( false );
					}
				}
			);
		} );

		// ── Resend OTP ────────────────────────────────────────────────────────────

		function sendOTP( method ) {
			resendBtn.disabled = true;
			var labelSpan = resendBtn.firstChild;
			var origText  = labelSpan ? labelSpan.textContent : '';
			if ( labelSpan ) { labelSpan.textContent = i18n.sending || 'Sending…'; }
			hideNotice();

			post(
				{ action: 'pmp2fa_send_otp', nonce: nonce, method: method || currentMethod },
				function ( err, res ) {
					if ( labelSpan ) { labelSpan.textContent = origText || ( i18n.resend_btn || 'Resend Code' ); }
					if ( err || ! res ) {
						showNotice( 'Connection error.', 'error' );
					} else if ( res.success ) {
						showNotice( res.data.message || 'Code sent.', 'success' );
					} else {
						showNotice(
							( res.data && res.data.message ) ? res.data.message : 'Failed to send code.',
							'error'
						);
					}
					startCountdown( 30 );
				}
			);
		}

		resendBtn.addEventListener( 'click', function () {
			sendOTP( currentMethod );
		} );

		// ── Method tabs ───────────────────────────────────────────────────────────

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				var method = this.getAttribute( 'data-method' );
				if ( method === currentMethod ) { return; }
				currentMethod = method;

				tabs.forEach( function ( t ) {
					t.classList.remove( 'is-active' );
					t.setAttribute( 'aria-selected', 'false' );
				} );
				this.classList.add( 'is-active' );
				this.setAttribute( 'aria-selected', 'true' );

				otpInput.value = '';
				hideNotice();
				sendOTP( method );
			} );
		} );

		// ── Escape key ────────────────────────────────────────────────────────────

		if ( isOverlay && cancelLink ) {
			document.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' || e.keyCode === 27 ) {
					document.body.classList.remove( 'pmp2fa-locked' );
					window.location.href = cancelLink.href || '/';
				}
			} );

			cancelLink.addEventListener( 'click', function () {
				document.body.classList.remove( 'pmp2fa-locked' );
			} );
		}

		// ── Start initial resend countdown ────────────────────────────────────────
		startCountdown( 30 );

	} ); // ready

} )();
