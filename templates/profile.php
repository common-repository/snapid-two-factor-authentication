<table id="snapid" class="form-table">
	<tr>
		<th>
			SnapID&trade; User Setup
		</th>
		<td>
			<p>
				<img src="<?php echo plugins_url( '../images/SnapIDLogo.png', __FILE__ ); ?>" width="150" />
			</p>
			<?php if ( $options['role'] ) { ?>
			<div class="snapid-video-user">
				<iframe src="<?php echo '1' === $options['role'] ? 'https://player.vimeo.com/video/158143689' : 'https://player.vimeo.com/video/158143690' ?>" width="500" height="250" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
			</div>
			<p class="description">This user is configured to use SnapID's&trade; <strong><?php echo esc_html( $options['role_type'] ); ?> authentication</strong> for login.</p>
			<p class="description">By using SnapID&trade; you agree to these <a href="https://secure.textkey.com/snapid/termsandconditions.php" target="_blank">Terms and Conditions</a>.</p>
			<br /></br />
			<div class="snapid-display-message">
			</div>
			<div class="snapid-toggle" style="display: <?php echo $options['snapid_user'] ? 'none' : 'block' ?>;">
				<div class="spinner snapid-spinner"></div>
				<p><strong>This WordPress user is not using SnapID&trade;.</strong></p>
				<p><a href="#" id="snapid-join" class="button">Join SnapID&trade;</a></p>
				<?php echo $options['auth_modal']; ?>
			</div>

			<div class="snapid-toggle" style="display: <?php echo $options['snapid_user'] ? 'block' : 'none' ?>;">
				<div class="spinner snapid-spinner"></div>
				<p><strong>This WordPress user is using SnapID&trade;.</strong></p>
				<p><a href="#" id="snapid-remove" class="button">Remove SnapID&trade;</a></p>
			</div>
			<?php } else { ?>
				<p>This user's role is not configured to use SnapID&trade;.</p>
			<?php } ?>
		</td>
	</tr>
</table>
