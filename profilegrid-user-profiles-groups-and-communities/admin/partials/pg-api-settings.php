<?php
/**
 * APIs / Webhooks settings screen.
 *
 * @package Profile_Magic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'APIs / Webhooks', 'profilegrid-user-profiles-groups-and-communities' ); ?></h1>

	<h2 class="nav-tab-wrapper pg-api-tab-wrapper">
		<a href="#" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>" data-tab="general"><?php esc_html_e( 'General', 'profilegrid-user-profiles-groups-and-communities' ); ?></a>
		<a href="#" class="nav-tab <?php echo 'permissions' === $active_tab ? 'nav-tab-active' : ''; ?>" data-tab="permissions"><?php esc_html_e( 'Endpoint Permissions', 'profilegrid-user-profiles-groups-and-communities' ); ?></a>
		<a href="#" class="nav-tab <?php echo 'reference' === $active_tab ? 'nav-tab-active' : ''; ?>" data-tab="reference"><?php esc_html_e( 'API Reference', 'profilegrid-user-profiles-groups-and-communities' ); ?></a>
	</h2>

	<form method="post" id="pg-api-settings-form">
		<?php wp_nonce_field( 'pg_api_settings_action', 'pg_api_settings_nonce' ); ?>
		<input type="hidden" name="pg_active_tab" id="pg_active_tab" value="<?php echo esc_attr( $active_tab ); ?>" />

		<div id="pg-tab-general" class="pg-api-tab-panel <?php echo 'general' === $active_tab ? '' : 'pg-hidden'; ?>">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="pg_enable_rest_api"><?php esc_html_e( 'Enable ProfileGrid APIs', 'profilegrid-user-profiles-groups-and-communities' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="pg_enable_rest_api" name="pg_enable_rest_api" class="pm_toggle" value="1" <?php checked( 1, $api_enabled ); ?> style="display:none;" />
						<label for="pg_enable_rest_api"></label>
						<p class="description">
							<?php esc_html_e( 'When enabled, ProfileGrid REST endpoints become available to external integrations. Disable to hide all endpoints instantly.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="pg-save-rest-api-settings-button">
				<?php submit_button( esc_html__( 'Save Changes', 'profilegrid-user-profiles-groups-and-communities' ), 'primary', 'submit', false ); ?>
				<button type="submit" name="pg_api_reset" value="1" class="button button-secondary pg-rest-api-reset-button"><?php esc_html_e( 'Reset', 'profilegrid-user-profiles-groups-and-communities' ); ?></button>
			</div>

			<?php if ( $api_enabled ) : ?>
				<hr />
				<h2><?php esc_html_e( 'Endpoint Structure', 'profilegrid-user-profiles-groups-and-communities' ); ?></h2>
				<p>
					<?php esc_html_e( 'All requests are made against the base endpoint:', 'profilegrid-user-profiles-groups-and-communities' ); ?><br />
					<code><?php echo esc_html( $endpoint_base ); ?></code>
				</p>
				<p>
					<?php esc_html_e( 'Append the query parameter', 'profilegrid-user-profiles-groups-and-communities' ); ?>
					<code>?integration=1</code>
					<?php esc_html_e( 'and specify the action you wish to execute.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<div id="pg-tab-permissions" class="pg-api-tab-panel <?php echo 'permissions' === $active_tab ? '' : 'pg-hidden'; ?>">
			<p class="description">
				<?php esc_html_e( 'Configure allowed roles for each endpoint action. Roles are loaded dynamically from WordPress, including administrator, multisite, and custom roles.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:35%;"><?php esc_html_e( 'Action', 'profilegrid-user-profiles-groups-and-communities' ); ?></th>
						<th style="width:45%;"><?php esc_html_e( 'Allowed Roles', 'profilegrid-user-profiles-groups-and-communities' ); ?></th>
						<th style="width:20%;"><?php esc_html_e( 'Risk', 'profilegrid-user-profiles-groups-and-communities' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $permission_actions as $action ) : ?>
						<?php
						$selected_roles = isset( $endpoint_permissions[ $action ] ) ? (array) $endpoint_permissions[ $action ] : array();
						$risk           = isset( $endpoint_risks[ $action ] ) ? $endpoint_risks[ $action ] : 'Low';
						?>
						<tr>
							<td><code><?php echo esc_html( $action ); ?></code></td>
							<td>
								<select name="pg_endpoint_permissions[<?php echo esc_attr( $action ); ?>][]" multiple="multiple" size="5" class="pg-endpoint-role-select">
									<?php foreach ( $available_roles as $role_slug => $role_label ) : ?>
										<option value="<?php echo esc_attr( $role_slug ); ?>" <?php selected( in_array( $role_slug, $selected_roles, true ) ); ?>>
											<?php echo esc_html( $role_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<span class="pg-risk-badge"><?php echo esc_html( $risk ); ?></span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="description"><?php esc_html_e( 'Tip: Hold Ctrl (Windows) or Cmd (macOS) to select multiple roles.', 'profilegrid-user-profiles-groups-and-communities' ); ?></p>

			<div class="pg-save-rest-api-settings-button">
				<?php submit_button( esc_html__( 'Save Changes', 'profilegrid-user-profiles-groups-and-communities' ), 'primary', 'submit', false ); ?>
				<button type="submit" name="pg_api_reset" value="1" class="button button-secondary pg-rest-api-reset-button"><?php esc_html_e( 'Reset', 'profilegrid-user-profiles-groups-and-communities' ); ?></button>
			</div>
		</div>

		<div id="pg-tab-reference" class="pg-api-tab-panel <?php echo 'reference' === $active_tab ? '' : 'pg-hidden'; ?>">
			<p class="description pg-api-reference-search-help"><?php esc_html_e( 'Search available REST endpoints and actions for integrations.', 'profilegrid-user-profiles-groups-and-communities' ); ?></p>
			<div class="pg-api-reference-toolbar">
				<input type="search" id="pg-api-reference-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search by group, method, action, or URL...', 'profilegrid-user-profiles-groups-and-communities' ); ?>" />
				<span class="pg-api-doc-tooltip-wrap">
					<a href="https://profilegrid.co/profilegrid-wordpress-rest-api-integration-overview/" class="pg-api-doc-link" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Documentation', 'profilegrid-user-profiles-groups-and-communities' ); ?>
					</a>
					<span class="pg-api-doc-tooltip">
						<?php esc_html_e( 'Open ProfileGrid WordPress REST API Integration Overview', 'profilegrid-user-profiles-groups-and-communities' ); ?>
					</span>
				</span>
			</div>
			<table class="widefat striped" id="pg-api-reference-table">
				<thead>
					<tr>
						<th style="width:20%;"><?php esc_html_e( 'Group', 'profilegrid-user-profiles-groups-and-communities' ); ?></th>
						<th style="width:10%;"><?php esc_html_e( 'Method', 'profilegrid-user-profiles-groups-and-communities' ); ?></th>
						<th style="width:25%;"><?php esc_html_e( 'Action', 'profilegrid-user-profiles-groups-and-communities' ); ?></th>
						<th style="width:45%;"><?php esc_html_e( 'URL', 'profilegrid-user-profiles-groups-and-communities' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $api_reference_rows as $row ) : ?>
						<tr data-search="<?php echo esc_attr( strtolower( $row['group'] . ' ' . $row['method'] . ' ' . $row['action'] . ' ' . $row['url'] ) ); ?>">
							<td><?php echo esc_html( $row['group'] ); ?></td>
							<td><?php echo esc_html( strtoupper( $row['method'] ) ); ?></td>
							<td><code><?php echo esc_html( $row['action'] ); ?></code></td>
							<td><code><?php echo esc_html( $row['url'] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</form>
</div>

<style>
.pg-hidden {
	display: none;
}

.pg-endpoint-role-select {
	width: 100%;
	min-height: 96px;
}

.pg-risk-badge {
	display: inline-block;
	padding: 2px 8px;
	border: 1px solid #dcdcde;
	background: #f6f7f7;
	border-radius: 10px;
	font-size: 12px;
}

.pg-save-rest-api-settings-button {
	margin: 15px 0 20px 0;
}

.pg-save-rest-api-settings-button .button-primary {
	background: #00bd48 !important;
	border-color: #00bd48 !important;
	box-shadow: none !important;
	color: #fff !important;
}

.pg-save-rest-api-settings-button .button-primary:hover {
	background: #00a63f !important;
	border-color: #009637 !important;
	color: #fff !important;
}

.pg-save-rest-api-settings-button .button-primary:focus {
	outline: 2px solid rgba(0, 182, 79, 0.6);
	outline-offset: 1px;
}

/* Local override for WP core secondary button on this screen only. */
.pg-save-rest-api-settings-button .pg-rest-api-reset-button {
	background: #f6f7f7 !important;
	border-color: #c3c4c7 !important;
	color: #2c3338 !important;
	/* box-shadow: none !important; */
	margin-left: 6px;
	height: 30px;
	line-height: 30px;
	padding: 0 14px !important;
	min-width: 82px;
	vertical-align: top;
}

.pg-save-rest-api-settings-button .pg-rest-api-reset-button:hover {
	background: #f0f0f1 !important;
	border-color: #8c8f94 !important;
	color: #1d2327 !important;
}

.pg-save-rest-api-settings-button .pg-rest-api-reset-button:focus {
	outline: 2px solid rgba(34, 113, 177, 0.35);
	outline-offset: 1px;
}

.pg-api-reference-search-help {
	margin-bottom: 8px;
}

.pg-api-reference-toolbar {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 12px;
}

.pg-api-reference-toolbar #pg-api-reference-search {
	max-width: 440px;
}

.pg-api-doc-tooltip-wrap {
	position: relative;
	display: inline-flex;
	align-items: center;
}

.pg-api-doc-link {
	font-weight: 600;
	text-decoration: none;
}

.pg-api-doc-tooltip {
	position: absolute;
	left: calc(100% + 8px);
	top: 50%;
	transform: translateY(-50%);
	background: #fff;
	border: 1px solid #dcdcde;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
	border-radius: 3px;
	padding: 8px 10px;
	font-size: 12px;
	color: #1d2327;
	white-space: nowrap;
	visibility: hidden;
	opacity: 0;
	transition: opacity 0.15s ease;
	z-index: 10;
}

.pg-api-doc-tooltip-wrap:hover .pg-api-doc-tooltip,
.pg-api-doc-tooltip-wrap:focus-within .pg-api-doc-tooltip {
	visibility: visible;
	opacity: 1;
}
</style>

<script>
(function() {
	var tabs = document.querySelectorAll('.pg-api-tab-wrapper .nav-tab');
	var tabField = document.getElementById('pg_active_tab');
	var search = document.getElementById('pg-api-reference-search');

	function setActiveTab(tab) {
		var panels = document.querySelectorAll('.pg-api-tab-panel');
		for (var i = 0; i < panels.length; i++) {
			panels[i].classList.add('pg-hidden');
		}

		var activePanel = document.getElementById('pg-tab-' + tab);
		if (activePanel) {
			activePanel.classList.remove('pg-hidden');
		}

		for (var j = 0; j < tabs.length; j++) {
			tabs[j].classList.remove('nav-tab-active');
			if (tabs[j].getAttribute('data-tab') === tab) {
				tabs[j].classList.add('nav-tab-active');
			}
		}

		if (tabField) {
			tabField.value = tab;
		}
	}

	for (var i = 0; i < tabs.length; i++) {
		tabs[i].addEventListener('click', function(e) {
			e.preventDefault();
			setActiveTab(this.getAttribute('data-tab'));
		});
	}

	if (search) {
		search.addEventListener('input', function() {
			var query = (this.value || '').toLowerCase();
			var rows = document.querySelectorAll('#pg-api-reference-table tbody tr');
			for (var i = 0; i < rows.length; i++) {
				var haystack = rows[i].getAttribute('data-search') || '';
				rows[i].style.display = haystack.indexOf(query) !== -1 ? '' : 'none';
			}
		});
	}
})();
</script>
