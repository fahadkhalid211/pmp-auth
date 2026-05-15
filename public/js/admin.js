(function($){
	'use strict';
	var cfg   = window.pmp2fa_admin || {};
	var ajax  = cfg.ajax_url;
	var nonce = cfg.nonce;

	// Toggle SMS/Email sections based on method
	function toggleSections() {
		var m = $('#pmp2fa-method').val();
		$('#pmp2fa-email-box').toggle( m !== 'sms' );
		$('#pmp2fa-sms-box').toggle( m !== 'email' );
	}
	$('#pmp2fa-method').on('change', toggleSections);
	toggleSections();

	// Toggle trust days row
	$('#pmp2fa-remember').on('change', function() {
		$('#pmp2fa-days-row').toggle( this.checked );
	});

	// Test email
	$('#pmp2fa-test-email').on('click', function() {
		var $btn = $(this), $r = $('#pmp2fa-email-result');
		$btn.prop('disabled', true).text('Sending...');
		$r.text('').css('color','');
		$.post(ajax, { action: 'pmp2fa_test_email', nonce: nonce })
			.done(function(res) { $r.css('color', res.success ? 'green':'red').text(res.data.message); })
			.fail(function() { $r.css('color','red').text('Request failed.'); })
			.always(function() { $btn.prop('disabled',false).text('Send Test Email'); });
	});

	// Test SMS
	$('#pmp2fa-test-sms').on('click', function() {
		var $btn = $(this), $r = $('#pmp2fa-sms-result');
		$btn.prop('disabled', true).text('Sending...');
		$r.text('').css('color','');
		$.post(ajax, { action: 'pmp2fa_test_sms', nonce: nonce })
			.done(function(res) { $r.css('color', res.success ? 'green':'red').text(res.data.message); })
			.fail(function() { $r.css('color','red').text('Request failed.'); })
			.always(function() { $btn.prop('disabled',false).text('Send Test SMS'); });
	});

	// Revoke MY devices
	$('#pmp2fa-revoke').on('click', function() {
		if (!confirm('Revoke all your trusted devices? You will need OTP verification on next login.')) return;
		var $btn = $(this), $r = $('#pmp2fa-revoke-result');
		$btn.prop('disabled', true);
		$.post(ajax, { action: 'pmp2fa_revoke_devices', nonce: nonce })
			.done(function(res) { $r.css('color', res.success ? 'green':'red').text(res.data.message); })
			.fail(function() { $r.css('color','red').text('Request failed.'); })
			.always(function() { $btn.prop('disabled',false); });
	});

	// Revoke devices for a SPECIFIC USER
	var foundUserId = 0, foundUserName = '';
	var lookupTimer;

	$('#pmp2fa-revoke-user-input').on('input', function() {
		clearTimeout(lookupTimer);
		$('#pmp2fa-revoke-user-result').text('').css('color','');
		$('#pmp2fa-revoke-user').prop('disabled', true);
		foundUserId = 0;
		var val = $.trim($(this).val());
		if (!val) return;

		lookupTimer = setTimeout(function() {
			$('#pmp2fa-revoke-user-result').css('color','#666').text('Looking up user...');
			$.post(ajax, { action: 'pmp2fa_lookup_user', nonce: nonce, search: val })
				.done(function(res) {
					if (res.success) {
						foundUserId   = res.data.user_id;
						foundUserName = res.data.display_name;
						var dt = res.data.devices === 1 ? '1 trusted device' : res.data.devices + ' trusted devices';
						if (res.data.devices === 0) {
							$('#pmp2fa-revoke-user-result').css('color','#666').text('Found: ' + res.data.display_name + ' (' + res.data.email + ') — no trusted devices.');
							$('#pmp2fa-revoke-user').prop('disabled', true);
						} else {
							$('#pmp2fa-revoke-user-result').css('color','#2271b1').text('Found: ' + res.data.display_name + ' (' + res.data.email + ') — ' + dt + '.');
							$('#pmp2fa-revoke-user').prop('disabled', false);
						}
					} else {
						$('#pmp2fa-revoke-user-result').css('color','red').text(res.data.message);
					}
				})
				.fail(function() { $('#pmp2fa-revoke-user-result').css('color','red').text('Lookup failed.'); });
		}, 500);
	});

	$('#pmp2fa-revoke-user').on('click', function() {
		if (!foundUserId) return;
		if (!confirm('Revoke all trusted devices for ' + foundUserName + '?')) return;
		var $btn = $(this);
		$btn.prop('disabled', true);
		$('#pmp2fa-revoke-user-result').css('color','#666').text('Revoking...');
		$.post(ajax, { action: 'pmp2fa_revoke_user_devices', nonce: nonce, user_id: foundUserId })
			.done(function(res) {
				$('#pmp2fa-revoke-user-result').css('color', res.success ? 'green':'red').text(res.data.message);
				if (res.success) {
					$('#pmp2fa-revoke-user-input').val('');
					foundUserId = 0; foundUserName = '';
				} else {
					$btn.prop('disabled', false);
				}
			})
			.fail(function() {
				$('#pmp2fa-revoke-user-result').css('color','red').text('Request failed.');
				$btn.prop('disabled', false);
			});
	});

})(jQuery);
