(function($) {
	var SnapID_Admin = {

		init: function() {
			this.events();
		},

		events: function() {
			var self = this;

			$('#snapid-register').click(function(e) {
				e.preventDefault();
				var $terms = $('#snapid-terms-agree');
				if (!$terms.is(':checked')) {
					setTimeout(function() {
						console.log($terms.parent('label'));
						$terms.parent('label').addClass('snapid-ease-red');
					}, 500);
				} else {
					self.register_site($(this).parents('td'));
				}
			});

			$('#snapid-join').click(function(e) {
				e.preventDefault();
				self.register_user($(this).parents('td'), false);
			});

			$('#snapid-remove').click(function(e) {
				self.remove(e, $(this).parents('td'));
			});

			$('.snapid-radio input').change(function(e) {
				$(this).parents('td').find('.snapid-roles-wrap').toggle();
			});

			$('.snapid-roles-wrap input').change(function(e) {
				var name = $(this).attr('name');
				if ($(this).is(':checked')) {
					$('input[name="'+name+'"]').not($(this)).attr('checked', false);
				}
			});

			$('#snapid-uninstall-form').submit(function(e) {
				if ($('#snapid-delete-settings').is(':checked') || $('#snapid-delete-users').is(':checked')) {
					if (!confirm('Deleting SnapID data cannot be undone. Proceed?')) {
						e.preventDefault();
					}
				}
			});

			$('.snapid-toggle-videos').click(function(e) {
				var $this = $(this);

				$(document.body).toggleClass('snapid-hide-videos');

				var hide_videos = $(document.body).hasClass('snapid-hide-videos') ? 1 : 0;

				var data = {
					action: 'snapid_toggle_videos_site',
					nonce: $('#_wpnonce').val(),
					hide_videos: hide_videos
				};

				SnapID.ajax(data, 'POST', function(response) {
					// Tell server to hide videos.
				});

				$('input#snapid-hide-videos').val(hide_videos);
			});
		},

		message: function(str) {
			var $msg = $('.snapid-display-message');

			clearTimeout(SnapID.countdown); // clear last closed modal
			clearTimeout(SnapID.polling); // clear last closed modal

			$msg.html(str).fadeIn(500, function() {
				$msg.delay(5000).fadeOut(500);
			});
		},

		register_site: function($parent) {
			$parent.find('.snapid-spinner').show();
			var self = this,
				data = {
					action: 'snapid_register_site',
					nonce: $('#_wpnonce').val()
				};

			SnapID.ajax(data, 'POST', function(response) {
				$parent.find('.snapid-spinner').hide();
				if (response && response.success) {
					self.register_user($parent, true);
				} else if (response && ! response.success && response.data) {
					self.message(response.data.errordescr);
				} else {
					self.message('Sorry, something went wrong...');
				}
			});
		},

		register_user: function($parent, refresh) {
			var self = this,
				$snapid_auth = $('#snapid-auth'),
				data = {
					action: 'snapid_register_user',
					nonce: $('#snapid-nonce').val(),
					user_id: $('#snapid-user-id').val()
				};

			$snapid_auth.find('#snapid-tocode').text('*****');
			$snapid_auth.find('#snapid-key').text('*******');

			SnapID.auth_modal($snapid_auth);

			SnapID.ajax(data, 'POST', function(response) {
				if (response && response.success && !response.data.errordescr) {
					$snapid_auth.find('#snapid-tocode').text(response.data.tocode);
					$snapid_auth.find('#snapid-key').text(response.data.joincode);
					SnapID.update_time( $snapid_auth, 90 );
					self.join_check(response, $parent, refresh);
				} else if (response && response.error && response.data.errordescr) {
					SnapID.add_message($snapid_auth, response.data.errordescr, true);
				} else {
					SnapID.add_message($snapid_auth, 'Sorry, something went wrong...', true);
				}
			});
		},

		join_check: function(response, $parent, refresh) {
			var self = this,
			$snapid_auth = $('#snapid-auth'),
			data = {
				action: 'snapid_join_check',
				nonce: $('#snapid-nonce').val(),
				response: response
			};

			SnapID.ajax(data, 'POST', function(response) {
				if (!response) {
					self.message('Sorry, something went wrong...');
					return;
				}
				if (response.data.keyreceived) {
					$.snapid_modal.close();
					self.message(response.data.errordescr);
					if (response.success) {
						$parent.find('.snapid-toggle').toggle();
					}
					$('#snapid-profile-nag').remove();
					if (refresh) {
						setTimeout(function() {
							location.reload();
						}, 2000);
					}
					return;
				}
				if (response.error) {
					$.snapid_modal.close();
					response.errordescr = response.errordescr ? response.errordescr : 'Sorry, something went wrong...';
					self.message(response.errordescr);
					return;
				}
				SnapID.polling = setTimeout(function() {
					self.join_check(response, $parent, refresh);
				}, 3000);
			});
		},

		remove: function(e, $parent) {
			e.preventDefault();
			var self = this,
			data = {
				action: 'snapid_remove',
				nonce: $('#snapid-nonce').val(),
				user_id: $('#snapid-user-id').val()
			};

			$parent.find('.snapid-spinner').show();

			SnapID.ajax(data, 'POST', function(response) {
				$parent.find('.snapid-spinner').hide();
				if (!response) {
					self.message('Sorry, something went wrong.');
				} else if (response.data.errordescr !== ''){
					self.message(response.data.errordescr);
				} else {
					self.message('SnapID has been removed from this account.');
					$parent.find('.snapid-toggle').toggle();
				}
			});
		}
	};

	SnapID_Admin.init();

})(jQuery);
