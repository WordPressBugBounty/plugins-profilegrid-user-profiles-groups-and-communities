<?php

/**
 * REST API support for ProfileGrid.
 *
 * @package Profile_Magic
 */
class Profile_Magic_Rest_API {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected $profile_magic;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'profilegrid/v1';

	/**
	 * Parent slug for submenu entries.
	 *
	 * @var string
	 */
	protected $parent_slug = 'pm_manage_groups_hide';

	/**
	 * Roles allowed to interact with API.
	 *
	 * @var array
	 */
	protected $allowed_roles = array( 'administrator', 'editor', 'author' );

	/**
	 * Option name that stores API toggle.
	 *
	 * @var string
	 */
	protected $option_name = 'pg_enable_rest_api';

	/**
	 * Option name for endpoint permissions map.
	 *
	 * @var string
	 */
	protected $endpoint_permissions_option_name = 'pg_rest_endpoint_permissions';

	/**
	 * Stores the authenticated user during a REST request.
	 *
	 * @var WP_User|null
	 */
	protected $current_user = null;

	/**
	 * Lazily instantiated PM_request helper.
	 *
	 * @var PM_request|null
	 */
	protected $request_helper = null;

	/**
	 * Cached flag for SECTION table description support.
	 *
	 * @var bool|null
	 */
	protected $section_desc_column_present = null;

	/**
	 * Profile_Magic_Rest_API constructor.
	 *
	 * @param string $profile_magic Plugin slug.
	 * @param string $version       Plugin version.
	 */
	public function __construct( $profile_magic, $version ) {
		$this->profile_magic = $profile_magic;
		$this->version       = $version;
	}

	/**
	 * Registers REST endpoints.
	 */
	public function register_routes() {
		// Main integration endpoint that handles all actions via parameters.
		// NOTE: An empty route triggers the "_rest_route was called incorrectly" warning.
		// Keeping this commented for reference; use '/' or '/integration' instead.
		/*
		register_rest_route(
			$this->namespace,
			'',
			array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => array( $this, 'handle_integration_request' ),
				'permission_callback' => array( $this, 'integration_permission_check' ),
			)
		);
		*/
		// Cover namespace root requests (with trailing slash).
		register_rest_route(
			$this->namespace,
			'/',
			array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => array( $this, 'handle_integration_request' ),
				'permission_callback' => array( $this, 'integration_permission_check' ),
			)
		);

		// Alias /integration to the same handler for clarity.
		register_rest_route(
			$this->namespace,
			'/integration',
			array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => array( $this, 'handle_integration_request' ),
				'permission_callback' => array( $this, 'integration_permission_check' ),
			)
		);

		// Token generation endpoint
		register_rest_route(
			$this->namespace,
			'/token',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_access_token' ),
				'permission_callback' => array( $this, 'token_permission_check' ),
				'args'                => array(
					'username'              => array(
						'type'        => 'string',
						'required'    => true,
						'description' => __( 'WordPress username', 'profilegrid-user-profiles-groups-and-communities' ),
					),
					'application_password' => array(
						'type'        => 'string',
						'required'    => true,
						'description' => __( 'Application password generated in WordPress', 'profilegrid-user-profiles-groups-and-communities' ),
					),
				),
			)
		);

		// Groups endpoints
		register_rest_route(
			$this->namespace,
			'/groups',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_groups' ),
					'permission_callback' => array( $this, 'authenticate_request' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'search'   => array(
							'type' => 'string',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_group' ),
					'permission_callback' => array( $this, 'authenticate_request' ),
					'args'                => array(
						'group_name' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/groups/(?P<group_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_single_group' ),
					'permission_callback' => array( $this, 'authenticate_request' ),
					'args'                => array(
						'group_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Permission callback for integration routes.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return true|WP_Error
	 */
	public function integration_permission_check( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		$action = $request->get_param( 'action' );
		if ( empty( $action ) || 'get_access_token' === $action ) {
			return true;
		}

		$integration = $request->get_param( 'integration' );
		if ( ! $integration ) {
			return true;
		}

		return $this->authenticate_request( $request );
	}

	/**
	 * Permission callback for token requests.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return true|WP_Error
	 */
	public function token_permission_check( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		return true;
	}

	/**
	 * Registers API settings submenu under ProfileGrid.
	 */
	public function register_settings_page() {
		$parent_slug = apply_filters( 'profilegrid_api_menu_parent_slug', $this->parent_slug );

		add_submenu_page(
			$parent_slug,
			__( 'APIs / Webhooks', 'profilegrid-user-profiles-groups-and-communities' ),
			__( 'APIs / Webhooks', 'profilegrid-user-profiles-groups-and-communities' ),
			'manage_options',
			'pm_api_settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Renders the API settings page and handles option save.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['pg_api_settings_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['pg_api_settings_nonce'] ) );
			if ( wp_verify_nonce( $nonce, 'pg_api_settings_action' ) ) {
				if ( isset( $_POST['pg_api_reset'] ) ) {
					update_option( $this->option_name, 0 );
					update_option( $this->endpoint_permissions_option_name, $this->get_default_endpoint_permissions() );
					add_settings_error( 'pg_api_settings', 'pg_api_settings', esc_html__( 'Settings reset.', 'profilegrid-user-profiles-groups-and-communities' ), 'updated' );
				} else {
					$enabled = isset( $_POST['pg_enable_rest_api'] ) ? 1 : 0;
					update_option( $this->option_name, $enabled );

					$available_roles      = $this->get_settings_available_roles();
					$raw_permissions      = isset( $_POST['pg_endpoint_permissions'] ) ? wp_unslash( $_POST['pg_endpoint_permissions'] ) : array();
					$sanitized_permissions = $this->sanitize_endpoint_permissions_input( $raw_permissions, $available_roles );
					update_option( $this->endpoint_permissions_option_name, $sanitized_permissions );

					add_settings_error( 'pg_api_settings', 'pg_api_settings', esc_html__( 'Settings saved.', 'profilegrid-user-profiles-groups-and-communities' ), 'updated' );
				}
			}
		}

		$api_enabled          = (int) get_option( $this->option_name, 0 );
		$endpoint_base        = rest_url( $this->namespace );
		$permission_actions   = $this->get_permission_actions();
		$endpoint_risks       = $this->get_permission_risk_map();
		$available_roles      = $this->get_settings_available_roles();
		$endpoint_permissions = $this->get_endpoint_permissions();
		$api_reference_rows   = $this->get_api_reference_rows();
		$active_tab           = isset( $_POST['pg_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['pg_active_tab'] ) ) : ( isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general' );
		$allowed_tabs         = array( 'general', 'permissions', 'reference' );
		if ( ! in_array( $active_tab, $allowed_tabs, true ) ) {
			$active_tab = 'general';
		}

		settings_errors( 'pg_api_settings' );

		include plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/pg-api-settings.php';
	}

	/**
	 * Generates an access token using username and application password.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate_access_token( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		$username         = sanitize_user( $request->get_param( 'username' ) );
		$application_pass = $request->get_param( 'application_password' );
		$application_pass = is_string( $application_pass ) ? trim( $application_pass ) : '';
		$error_status     = rest_authorization_required_code();

		if ( empty( $username ) || empty( $application_pass ) ) {
			return new WP_Error(
				'pg_rest_missing_credentials',
				__( 'Username and application password are required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => $error_status )
			);
		}

		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			return new WP_Error(
				'pg_rest_invalid_user',
				__( 'The provided username does not exist.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => $error_status )
			);
		}

		if ( ! $this->user_has_access( $user ) ) {
			return new WP_Error(
				'pg_rest_forbidden_role',
				__( 'You are not allowed to request API access.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		// Use WordPress core function for application password verification
		$password_valid = $this->verify_application_password( $user, $application_pass );

		if ( is_wp_error( $password_valid ) ) {
			return $password_valid;
		}

		if ( ! $password_valid ) {
			return new WP_Error(
				'pg_rest_invalid_application_password',
				__( 'Invalid application password.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => $error_status )
			);
		}

		$token_data = $this->issue_token( $user->ID );

		return rest_ensure_response(
			array(
				'token'      => $token_data['token'],
				'expires_in' => $token_data['expires_in'],
				'user_id'    => $user->ID,
			)
		);
	}

	/**
	 * Verify application password using WordPress core functions.
	 *
	 * @param WP_User $user             WordPress user object.
	 * @param string  $application_pass Application password to verify.
	 *
	 * @return bool|WP_Error
	 */
	protected function verify_application_password( $user, $application_pass ) {
		// Method 1: Use wp_authenticate_application_password (WordPress 5.6+)
		if ( function_exists( 'wp_authenticate_application_password' ) ) {
			$authenticated_user = wp_authenticate_application_password( null, $user->user_login, $application_pass );
			return ! is_wp_error( $authenticated_user ) && $authenticated_user->ID === $user->ID;
		}

		// Method 2: Use WP_Application_Passwords class directly (WordPress 5.6+)
		if ( class_exists( 'WP_Application_Passwords' ) ) {
			// Check if application password exists and is valid for this user
			$passwords = WP_Application_Passwords::get_user_application_passwords( $user->ID );
			if ( ! empty( $passwords ) ) {
				foreach ( $passwords as $password ) {
					if ( WP_Application_Passwords::chunk_password( $application_pass ) === $password['password'] ) {
						return true;
					}
				}
			}
			return false;
		}

		// Method 3: Fallback for older WordPress versions or if Application Passwords not available.
		// This path must remain application-password-only (never validate regular account password).
		return $this->fallback_password_verification( $user, $application_pass );
	}

	/**
	 * Fallback password verification for environments without Application Passwords.
	 *
	 * @param WP_User $user     WordPress user object.
	 * @param string  $password Password to verify.
	 *
	 * @return bool|WP_Error
	 */
	protected function fallback_password_verification( $user, $password ) {
		// Legacy fallback: check ProfileGrid-scoped stored application passwords only.
		$stored_passwords = get_user_meta( $user->ID, 'profilegrid_application_passwords', true );
		if ( is_array( $stored_passwords ) ) {
			foreach ( $stored_passwords as $stored_password ) {
				if ( is_array( $stored_password ) && ! empty( $stored_password['password_hash'] ) && wp_check_password( $password, $stored_password['password_hash'] ) ) {
					return true;
				}
			}
		}

		return new WP_Error(
			'pg_rest_app_password_unsupported',
			__( 'Application password authentication is not available on this site.', 'profilegrid-user-profiles-groups-and-communities' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Returns list of groups using ProfileGrid functions instead of direct SQL.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_groups( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		if ( ! $this->current_user || ! $this->user_has_access( $this->current_user ) ) {
			return new WP_Error(
				'pg_rest_forbidden',
				__( 'You are not allowed to list groups.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$page     = (int) $request->get_param( 'page' );
		$page     = $page > 0 ? $page : 1;

		// Support returning all groups when `all=1` is provided or when `per_page=0`.
		$raw_per_page = $request->get_param( 'per_page' );
		$all_flag     = $request->get_param( 'all' );

		if ( $all_flag || ( isset( $raw_per_page ) && $raw_per_page === 'all' ) || ( isset( $raw_per_page ) && (int) $raw_per_page === 0 ) ) {
			$per_page = false; // indicate no limit to DB handler
			$offset   = 0;
		} else {
			$per_page = (int) $raw_per_page;
			$per_page = $per_page > 0 ? min( $per_page, 100 ) : 20;
			$offset   = ( $page - 1 ) * $per_page;
		}
		$search   = $request->get_param( 'search' );

		// Use ProfileGrid's DBhandler instead of direct SQL
		$dbhandler = new PM_DBhandler();

		// Build where conditions
		$where      = 1;
		$additional = '';

		if ( ! empty( $search ) ) {
			global $wpdb;
			$search     = sanitize_text_field( $search );
			$like       = '%' . $wpdb->esc_like( $search ) . '%';
			$additional = $wpdb->prepare( 'group_name LIKE %s OR group_desc LIKE %s', $like, $like );
		}

		// Get total count
		$total_result = $dbhandler->get_all_result( 'GROUPS', 'COUNT(id) as total', $where, 'var', 0, false, null, false, $additional );
		if ( is_numeric( $total_result ) ) {
			$total = (int) $total_result;
		} elseif ( is_object( $total_result ) && isset( $total_result->total ) ) {
			$total = (int) $total_result->total;
		} elseif ( is_array( $total_result ) && isset( $total_result[0] ) ) {
			$item  = $total_result[0];
			$total = is_object( $item ) && isset( $item->total ) ? (int) $item->total : (int) $item;
		} else {
			$total = 0;
		}

		// Get groups with pagination
		$results = $dbhandler->get_all_result( 'GROUPS', '*', $where, 'results', $offset, $per_page, 'id', 'DESC', $additional );

		$groups = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $group ) {
				$groups[] = $this->prepare_group_response( $group );
			}
		}

		// Normalize pagination values for response
		$per_page_val = $per_page === false ? (int) $total : (int) $per_page;
		$total_pages  = $per_page === false || $per_page_val === 0 ? 1 : (int) ceil( $total / $per_page_val );

		return rest_ensure_response(
			array(
				'data'       => $groups,
				'pagination' => array(
					'page'       => (int) $page,
					'per_page'   => $per_page_val,
					'total'      => (int) $total,
					'total_page' => $total_pages,
				),
			)
		);
	}

	/**
	 * Creates a new user group.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_group( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		if ( ! $this->current_user || ! $this->user_has_access( $this->current_user ) ) {
			return new WP_Error(
				'pg_rest_forbidden',
				__( 'You are not allowed to create groups.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$params     = $this->get_request_payload( $request );
		$group_name = isset( $params['group_name'] ) ? sanitize_text_field( wp_unslash( $params['group_name'] ) ) : '';

		if ( empty( $group_name ) ) {
			return new WP_Error(
				'pg_rest_missing_group_name',
				__( 'Group name is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}
		$associate_role = isset( $params['associate_role'] ) ? sanitize_text_field( wp_unslash( $params['associate_role'] ) ) : 'subscriber';
		$group_slug     = isset( $params['group_slug'] ) ? sanitize_title( wp_unslash( $params['group_slug'] ) ) : sanitize_title( $group_name );

		$data = array(
			'group_name'           => $group_name,
			'associate_role'       => $associate_role,
			'group_slug'           => $group_slug,
			'is_group_limit'       => isset( $params['is_group_limit'] ) ? $this->normalize_boolean_flag( $params['is_group_limit'] ) : 0,
			'is_group_leader'      => isset( $params['is_group_leader'] ) ? $this->normalize_boolean_flag( $params['is_group_leader'] ) : 0,
			'show_success_message' => isset( $params['show_success_message'] ) ? $this->normalize_boolean_flag( $params['show_success_message'] ) : 0,
		);

		$maybe_html = array(
			'group_desc',
			'group_limit_message',
			'success_message',
		);

		foreach ( $maybe_html as $field ) {
			if ( array_key_exists( $field, $params ) ) {
				$data[ $field ] = wp_kses_post( $params[ $field ] );
			}
		}

		if ( array_key_exists( 'group_icon', $params ) ) {
			$icon_id = $this->sanitize_attachment_id( $params['group_icon'] );
			if ( $icon_id > 0 ) {
				$data['group_icon'] = $icon_id;
			}
		}

		$data['group_limit'] = array_key_exists( 'group_limit', $params ) ? (int) $params['group_limit'] : 0;

		if ( array_key_exists( 'leader_username', $params ) ) {
			$data['leader_username'] = sanitize_user( wp_unslash( $params['leader_username'] ) );
		}

		if ( array_key_exists( 'group_leaders', $params ) ) {
			$leaders = $this->normalize_complex_payload( $params['group_leaders'] );
			if ( ! is_array( $leaders ) ) {
				$leaders = array( $leaders );
			}
			$leaders = array_filter(
				array_map(
					function( $leader ) {
						return is_string( $leader ) ? sanitize_text_field( wp_unslash( $leader ) ) : '';
					},
					$leaders
				)
			);
			$data['group_leaders'] = maybe_serialize( array_values( $leaders ) );
		}

		if ( array_key_exists( 'leader_rights', $params ) ) {
			$leader_rights = $this->normalize_complex_payload( $params['leader_rights'] );
			$leader_rights = $this->sanitize_recursive_payload( $leader_rights );
			$data['leader_rights'] = maybe_serialize( $leader_rights );
		}

		if ( array_key_exists( 'group_options', $params ) ) {
			$options = $this->normalize_complex_payload( $params['group_options'] );
			$options = $this->sanitize_recursive_payload( $options );
			$data['group_options'] = maybe_serialize( $options );
		}

		$dbhandler = new PM_DBhandler();
		$group_id  = $dbhandler->insert_row( 'GROUPS', $data );

		if ( false === $group_id ) {
			return new WP_Error(
				'pg_rest_group_creation_failed',
				__( 'Unable to create group.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 500 )
			);
		}

		$group = $dbhandler->get_row( 'GROUPS', $group_id );

		return rest_ensure_response(
			array(
				'message' => __( 'Group created successfully.', 'profilegrid-user-profiles-groups-and-communities' ),
				'group'   => $this->prepare_group_response( $group ),
			)
		);
	}

	/**
	 * Creates a custom section assigned to a group.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_group_section_endpoint( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		if ( ! $this->current_user || ! $this->user_has_access( $this->current_user ) ) {
			return new WP_Error(
				'pg_rest_forbidden',
				__( 'You are not allowed to manage sections.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$params   = $this->get_request_payload( $request );
		$group_id = isset( $params['group_id'] ) ? (int) $params['group_id'] : (int) $request->get_param( 'group_id' );

		if ( $group_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_group_id',
				__( 'A valid group_id parameter is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$group = $this->get_group_by_id( $group_id );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$section_name = isset( $params['section_name'] ) ? sanitize_text_field( wp_unslash( $params['section_name'] ) ) : '';
		if ( '' === $section_name ) {
			return new WP_Error(
				'pg_rest_missing_section_name',
				__( 'Section name is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$ordering = isset( $params['ordering'] ) ? (int) $params['ordering'] : 0;
		if ( $ordering <= 0 ) {
			$ordering = $this->get_next_section_ordering( $group_id );
		}

		$section_desc = array_key_exists( 'section_desc', $params ) ? wp_kses_post( $params['section_desc'] ) : '';

		$section_options = array();
		if ( array_key_exists( 'section_options', $params ) ) {
			$options_payload = $this->normalize_complex_payload( $params['section_options'] );
			$options_payload = $this->sanitize_recursive_payload( $options_payload );

			if ( is_array( $options_payload ) ) {
				$section_options = $options_payload;
			} elseif ( ! empty( $options_payload ) ) {
				$section_options = array(
					'value' => $options_payload,
				);
			}
		}

		$data = array(
			'gid'          => $group_id,
			'section_name' => $section_name,
			'ordering'     => $ordering,
		);

		if ( '' !== $section_desc ) {
			if ( $this->section_supports_description() ) {
				$data['section_desc'] = $section_desc;
			} else {
				if ( ! is_array( $section_options ) ) {
					$section_options = array();
				}
				$section_options['section_desc'] = $section_desc;
			}
		}

		if ( ! empty( $section_options ) ) {
			$data['section_options'] = maybe_serialize( $section_options );
		}

		$dbhandler  = new PM_DBhandler();
		$section_id = $dbhandler->insert_row( 'SECTION', $data );

		if ( false === $section_id ) {
			return new WP_Error(
				'pg_rest_section_creation_failed',
				__( 'Unable to create section.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 500 )
			);
		}

		$section = $dbhandler->get_row( 'SECTION', $section_id );

		return rest_ensure_response(
			array(
				'message' => __( 'Section created successfully.', 'profilegrid-user-profiles-groups-and-communities' ),
				'section' => $this->prepare_section_response( $section ),
			)
		);
	}

	/**
	 * Retrieves all sections for a group.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_group_section_endpoint( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		$params   = $this->get_request_payload( $request );
		$group_id = isset( $params['group_id'] ) ? (int) $params['group_id'] : (int) $request->get_param( 'group_id' );

		if ( $group_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_group_id',
				__( 'A valid group_id parameter is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$group = $this->get_group_by_id( $group_id );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$dbhandler = new PM_DBhandler();
		$sections  = $dbhandler->get_all_result(
			'SECTION',
			'*',
			array( 'gid' => $group_id ),
			'results',
			0,
			false,
			'ordering'
		);

		$sections = $sections ? $sections : array();

		return rest_ensure_response(
			array(
				'group_id' => $group_id,
				'sections' => $this->map_sections( $sections, $group_id ),
			)
		);
	}

	/**
	 * Updates an existing section belonging to a group.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_group_section_endpoint( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		$params     = $this->get_request_payload( $request );
		$group_id   = isset( $params['group_id'] ) ? (int) $params['group_id'] : (int) $request->get_param( 'group_id' );
		$section_id = isset( $params['section_id'] ) ? (int) $params['section_id'] : (int) $request->get_param( 'section_id' );

		if ( $group_id <= 0 || $section_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_parameters',
				__( 'Both group_id and section_id are required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$group = $this->get_group_by_id( $group_id );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$dbhandler = new PM_DBhandler();
		$section   = $dbhandler->get_row( 'SECTION', $section_id );

		if ( empty( $section ) || (int) $section->gid !== $group_id ) {
			return new WP_Error(
				'pg_rest_section_not_found',
				__( 'Section not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 404 )
			);
		}

		$data            = array();
		$options_changed = false;
		$section_options = array();

		if ( isset( $section->section_options ) && '' !== $section->section_options ) {
			$section_options = maybe_unserialize( $section->section_options );
		}
		if ( ! is_array( $section_options ) ) {
			$section_options = array();
		}

		if ( array_key_exists( 'section_name', $params ) ) {
			$data['section_name'] = sanitize_text_field( wp_unslash( $params['section_name'] ) );
		}

		if ( array_key_exists( 'ordering', $params ) ) {
			$data['ordering'] = (int) $params['ordering'];
		}

		if ( array_key_exists( 'section_desc', $params ) ) {
			$section_desc = wp_kses_post( $params['section_desc'] );
			if ( $this->section_supports_description() ) {
				$data['section_desc'] = $section_desc;
			} else {
				$section_options['section_desc'] = $section_desc;
				$options_changed                 = true;
			}
		}

		if ( array_key_exists( 'section_options', $params ) ) {
			$options_payload = $this->normalize_complex_payload( $params['section_options'] );
			$options_payload = $this->sanitize_recursive_payload( $options_payload );
			if ( ! is_array( $options_payload ) ) {
				$options_payload = array();
			}
			$section_options = $options_payload;
			$options_changed = true;
		}

		if ( $options_changed ) {
			$data['section_options'] = maybe_serialize( $section_options );
		}

		if ( empty( $data ) ) {
			return new WP_Error(
				'pg_rest_no_changes',
				__( 'No updatable fields were provided.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$updated = $dbhandler->update_row( 'SECTION', 'id', $section_id, $data );

		if ( false === $updated ) {
			return new WP_Error(
				'pg_rest_section_update_failed',
				__( 'Unable to update the section.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 500 )
			);
		}

		$section = $dbhandler->get_row( 'SECTION', $section_id );

		return rest_ensure_response(
			array(
				'message' => __( 'Section updated successfully.', 'profilegrid-user-profiles-groups-and-communities' ),
				'section' => $this->prepare_section_response( $section ),
			)
		);
	}

	/**
	 * Deletes a section and its fields.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_group_section_endpoint( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		$params     = $this->get_request_payload( $request );
		$group_id   = isset( $params['group_id'] ) ? (int) $params['group_id'] : (int) $request->get_param( 'group_id' );
		$section_id = isset( $params['section_id'] ) ? (int) $params['section_id'] : (int) $request->get_param( 'section_id' );

		if ( $group_id <= 0 || $section_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_parameters',
				__( 'Both group_id and section_id are required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$group = $this->get_group_by_id( $group_id );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$section = $this->get_section_by_id( $section_id );
		if ( is_wp_error( $section ) ) {
			return $section;
		}

		if ( (int) $section->gid !== $group_id ) {
			return new WP_Error(
				'pg_rest_section_not_found',
				__( 'Section not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 404 )
			);
		}

		$dbhandler = new PM_DBhandler();

		$fields = $dbhandler->get_all_result(
			'FIELDS',
			array( 'field_id' ),
			array(
				'associate_section' => $section_id,
			),
			'results',
			0,
			false,
			'field_id'
		);

		if ( ! empty( $fields ) ) {
			foreach ( $fields as $field ) {
				$dbhandler->remove_row( 'FIELDS', 'field_id', (int) $field->field_id, '%d' );
			}
		}

		$removed = $dbhandler->remove_row( 'SECTION', 'id', $section_id, '%d' );
		if ( false === $removed ) {
			return new WP_Error(
				'pg_rest_section_delete_failed',
				__( 'Unable to delete the section.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'group_id'   => $group_id,
				'section_id' => $section_id,
				'status'     => 'deleted',
			)
		);
	}

	/**
	 * Creates a custom field assigned to a group section.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_group_field_endpoint( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		if ( ! $this->current_user || ! $this->user_has_access( $this->current_user ) ) {
			return new WP_Error(
				'pg_rest_forbidden',
				__( 'You are not allowed to manage fields.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$params     = $this->get_request_payload( $request );
		$group_id   = isset( $params['group_id'] ) ? (int) $params['group_id'] : (int) $request->get_param( 'group_id' );
		$section_id = isset( $params['section_id'] ) ? (int) $params['section_id'] : (int) $request->get_param( 'section_id' );

		if ( $group_id <= 0 || $section_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_parameters',
				__( 'Both group_id and section_id are required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$group = $this->get_group_by_id( $group_id );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$section = $this->get_section_by_id( $section_id );
		if ( is_wp_error( $section ) ) {
			return $section;
		}

		if ( (int) $section->gid !== $group_id ) {
			return new WP_Error(
				'pg_rest_section_group_mismatch',
				__( 'Section does not belong to the provided group.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$field_label = isset( $params['field_label'] ) ? sanitize_text_field( wp_unslash( $params['field_label'] ) ) : '';
		if ( '' === $field_label ) {
			return new WP_Error(
				'pg_rest_missing_field_label',
				__( 'Field label is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$field_type = isset( $params['field_type'] ) ? sanitize_key( wp_unslash( $params['field_type'] ) ) : '';
		if ( '' === $field_type ) {
			return new WP_Error(
				'pg_rest_missing_field_type',
				__( 'Field type is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$field_desc = array_key_exists( 'field_desc', $params ) ? wp_kses_post( $params['field_desc'] ) : '';
		$ordering   = isset( $params['ordering'] ) ? (int) $params['ordering'] : 0;
		if ( $ordering <= 0 ) {
			$ordering = $this->get_next_field_ordering( $group_id, $section_id );
		}

		$is_required         = array_key_exists( 'required', $params ) ? $this->normalize_boolean_flag( $params['required'] ) : 0;
		$show_in_signup_form = array_key_exists( 'show_in_signup_form', $params ) ? $this->normalize_boolean_flag( $params['show_in_signup_form'] ) : 1;
		$display_on_profile  = array_key_exists( 'display_on_profile', $params ) ? $this->normalize_boolean_flag( $params['display_on_profile'] ) : 1;
		$display_on_group    = array_key_exists( 'display_on_group', $params ) ? $this->normalize_boolean_flag( $params['display_on_group'] ) : 0;
		$is_editable         = array_key_exists( 'is_editable', $params ) ? $this->normalize_boolean_flag( $params['is_editable'] ) : 1;
		$visibility          = array_key_exists( 'visibility', $params ) ? (int) $params['visibility'] : 1;

		$field_options = array();
		if ( array_key_exists( 'field_options', $params ) ) {
			$field_options = $this->normalize_complex_payload( $params['field_options'] );
			$field_options = $this->sanitize_recursive_payload( $field_options );
		}

		$field_key = '';
		if ( array_key_exists( 'field_key', $params ) ) {
			$field_key = sanitize_key( wp_unslash( $params['field_key'] ) );
		}

		$field_key_source = $ordering > 0 ? $ordering : time();
		$pm_request       = $this->get_request_helper();

		if ( empty( $field_key ) || $pm_request->get_default_key_type( $field_type ) ) {
			$field_key = $pm_request->get_field_key( $field_type, $field_key_source );
		}

		$data = array(
			'field_name'          => $field_label,
			'field_desc'          => $field_desc,
			'field_type'          => $field_type,
			'field_key'           => $field_key,
			'associate_group'     => $group_id,
			'associate_section'   => $section_id,
			'show_in_signup_form' => $show_in_signup_form,
			'is_required'         => $is_required,
			'is_editable'         => $is_editable,
			'display_on_profile'  => $display_on_profile,
			'display_on_group'    => $display_on_group,
			'visibility'          => $visibility,
			'ordering'            => $ordering,
		);

		if ( ! empty( $field_options ) ) {
			$data['field_options'] = maybe_serialize( $field_options );
		}

		$dbhandler = new PM_DBhandler();
		$field_id  = $dbhandler->insert_row( 'FIELDS', $data );

		if ( false === $field_id ) {
			return new WP_Error(
				'pg_rest_field_creation_failed',
				__( 'Unable to create field.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 500 )
			);
		}

		$field = $dbhandler->get_row( 'FIELDS', $field_id );

		return rest_ensure_response(
			array(
				'message' => __( 'Field created successfully.', 'profilegrid-user-profiles-groups-and-communities' ),
				'field'   => $this->prepare_field_response( $field ),
			)
		);
	}

	/**
	 * Retrieves a single group.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_single_group( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		if ( ! $this->current_user || ! $this->user_has_access( $this->current_user ) ) {
			return new WP_Error(
				'pg_rest_forbidden',
				__( 'You are not allowed to view this group.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$group_id = (int) $request->get_param( 'group_id' );

		if ( $group_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_group_id',
				__( 'A valid group_id parameter is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$dbhandler = new PM_DBhandler();
		$group     = $dbhandler->get_row( 'GROUPS', $group_id );

		if ( empty( $group ) ) {
			return new WP_Error(
				'pg_rest_group_not_found',
				__( 'Group not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $this->prepare_group_response( $group ) );
	}

	/**
	 * Returns members of a group with pagination.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_group_members_endpoint( WP_REST_Request $request ) {
		$gid = (int) $request->get_param( 'group_id' );

		if ( $gid <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_group_id',
				__( 'A valid group_id parameter is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$group = $this->get_group_by_id( $gid );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$page    = max( 1, (int) $request->get_param( 'page' ) );
		$search  = $request->get_param( 'search' );
		$role    = $request->get_param( 'role' );
		$status  = $request->get_param( 'status' );
		$order   = strtoupper( $request->get_param( 'order' ) );
		$orderby = $request->get_param( 'orderby' );
		$order   = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'ASC';

		$allowed_orderby = array( 'display_name', 'user_login', 'registered', 'first_name', 'last_name', 'ID' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'display_name';
		}

		$raw_per_page = $request->get_param( 'per_page' );
		if ( $raw_per_page === 'all' || (string) $raw_per_page === '0' || $request->get_param( 'all' ) ) {
			$per_page = false;
			$offset   = 0;
		} else {
			$per_page = (int) $raw_per_page;
			$per_page = $per_page > 0 ? min( $per_page, 200 ) : 20;
			$offset   = ( $page - 1 ) * $per_page;
		}

		$pm_request = $this->get_request_helper();
		$filters    = array(
			'gid' => (string) $gid,
		);

		if ( null !== $status && $status !== '' ) {
			$filters['status'] = $status;
		}

		$meta_query = $pm_request->pm_get_user_meta_query( $filters );
		$dbhandler  = new PM_DBhandler();

		$limit        = ( false === $per_page ) ? '' : $per_page;
		$offset_value = ( false === $per_page ) ? '' : $offset;

		$user_query = $dbhandler->pm_get_all_users_ajax(
			$search,
			$meta_query,
			$role,
			$offset_value,
			$limit,
			$order,
			$orderby
		);

		$total   = (int) $user_query->get_total();
		$users   = $user_query->get_results();
		$members = array();

		if ( ! empty( $users ) ) {
			foreach ( $users as $user ) {
				$members[] = $this->map_group_member_response( $user, $gid );
			}
		}

		$per_page_val = ( false === $per_page ) ? $total : (int) $per_page;
		$total_pages  = ( false === $per_page || 0 === $per_page_val ) ? 1 : (int) ceil( $total / $per_page_val );

		return rest_ensure_response(
			array(
				'group_id'   => $gid,
				'data'       => $members,
				'pagination' => array(
					'page'       => (int) $page,
					'per_page'   => $per_page_val,
					'total'      => $total,
					'total_page' => $total_pages,
				),
			)
		);
	}

	/**
	 * Returns list of users with optional filters.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_users_endpoint( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		$page    = max( 1, (int) $request->get_param( 'page' ) );
		$search  = $request->get_param( 'search' );
		$role    = $request->get_param( 'role' );
		$status  = $request->get_param( 'status' );
		$group   = $request->get_param( 'group_id' );
		$order   = strtoupper( $request->get_param( 'order' ) );
		$orderby = $request->get_param( 'orderby' );
		$order   = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'ASC';

		$allowed_orderby = array( 'display_name', 'user_login', 'registered', 'first_name', 'last_name', 'ID' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'display_name';
		}

		$raw_per_page = $request->get_param( 'per_page' );
		if ( $raw_per_page === 'all' || (string) $raw_per_page === '0' || $request->get_param( 'all' ) ) {
			$per_page = false;
			$offset   = 0;
		} else {
			$per_page = (int) $raw_per_page;
			$per_page = $per_page > 0 ? min( $per_page, 200 ) : 20;
			$offset   = ( $page - 1 ) * $per_page;
		}

		$pm_request = $this->get_request_helper();
		$filters    = array();

		if ( null !== $status && $status !== '' ) {
			$filters['status'] = $status;
		}

		if ( null !== $group && '' !== $group ) {
			$filters['gid'] = (string) absint( $group );
		}

		$meta_query = $pm_request->pm_get_user_meta_query( $filters );
		$dbhandler  = new PM_DBhandler();

		$limit        = ( false === $per_page ) ? '' : $per_page;
		$offset_value = ( false === $per_page ) ? '' : $offset;

		$user_query = $dbhandler->pm_get_all_users_ajax(
			$search,
			$meta_query,
			$role,
			$offset_value,
			$limit,
			$order,
			$orderby
		);

		$total = (int) $user_query->get_total();
		$users = $user_query->get_results();
		$data  = array();

		if ( ! empty( $users ) ) {
			foreach ( $users as $user ) {
				$data[] = $this->map_user_response( $user );
			}
		}

		$per_page_val = ( false === $per_page ) ? $total : (int) $per_page;
		$total_pages  = ( false === $per_page || 0 === $per_page_val ) ? 1 : (int) ceil( $total / $per_page_val );

		return rest_ensure_response(
			array(
				'data'       => $data,
				'pagination' => array(
					'page'       => (int) $page,
					'per_page'   => $per_page_val,
					'total'      => $total,
					'total_page' => $total_pages,
				),
			)
		);
	}

	/**
	 * Returns details for a single user.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_user_details_endpoint( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		$user_id = (int) $request->get_param( 'user_id' );

		if ( $user_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_user_id',
				__( 'A valid user_id parameter is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'pg_rest_user_not_found',
				__( 'User not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 404 )
			);
		}

		$pm_request   = $this->get_request_helper();
		$status       = get_user_meta( $user_id, 'rm_user_status', true );
		$status       = ( '' === $status ) ? '0' : (string) $status;
		$groups_meta  = get_user_meta( $user_id, 'pm_group', true );
		$group_ids    = $pm_request->pg_filter_users_group_ids( $groups_meta );
		$group_ids    = is_array( $group_ids ) ? array_values( array_unique( array_map( 'absint', $group_ids ) ) ) : array();
		$joining_meta = get_user_meta( $user_id, 'pm_joining_date', true );
		$joining_dates = array();

		if ( is_array( $joining_meta ) ) {
			foreach ( $joining_meta as $gid => $date ) {
				$gid = absint( $gid );
				if ( $gid > 0 ) {
					$joining_dates[ $gid ] = $date;
				}
			}
		}

		$profile_url = method_exists( $pm_request, 'pm_get_user_profile_url' ) ? $pm_request->pm_get_user_profile_url( $user_id ) : '';

		return rest_ensure_response(
			array(
				'id'            => $user_id,
				'user_login'    => $user->user_login,
				'email'         => $user->user_email,
				'display_name'  => $user->display_name,
				'roles'         => (array) $user->roles,
				'status'        => $status,
				'groups'        => $group_ids,
				'joining_dates' => $joining_dates,
				'profile_url'   => $profile_url,
				'avatar'        => get_avatar_url( $user_id ),
				'user_registered' => $user->user_registered,
			)
		);
	}

	/**
	 * Adds users to a group.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_group_members_endpoint( WP_REST_Request $request ) {
		$params      = $this->get_request_payload( $request );
		$gid         = isset( $params['group_id'] ) ? (int) $params['group_id'] : (int) $request->get_param( 'group_id' );
		$user_ids    = isset( $params['user_ids'] ) ? $this->normalize_id_list( $params['user_ids'] ) : array();
		$user_emails = isset( $params['user_emails'] ) ? (array) $params['user_emails'] : array();

		if ( $gid <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_group_id',
				__( 'A valid group_id parameter is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$group = $this->get_group_by_id( $gid );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		if ( empty( $user_ids ) && empty( $user_emails ) ) {
			return new WP_Error(
				'pg_rest_missing_members',
				__( 'Provide at least one user_id or user_email to add.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$added          = array();
		$already_member = array();
		$not_found      = array();
		$errors         = array();

		foreach ( $user_ids as $uid ) {
			$result = $this->add_user_to_group( $uid, $gid );
			if ( is_wp_error( $result ) ) {
				$errors[] = array(
					'user_id' => $uid,
					'error'   => $result->get_error_message(),
				);
			} elseif ( 'exists' === $result ) {
				$already_member[] = $uid;
			} else {
				$added[] = $uid;
			}
		}

		foreach ( $user_emails as $email ) {
			$sanitized = sanitize_email( $email );
			if ( empty( $sanitized ) ) {
				$not_found[] = array(
					'email'  => $email,
					'reason' => __( 'Invalid email address.', 'profilegrid-user-profiles-groups-and-communities' ),
				);
				continue;
			}

			$user = get_user_by( 'email', $sanitized );
			if ( ! $user ) {
				$not_found[] = array(
					'email'  => $sanitized,
					'reason' => __( 'User not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				);
				continue;
			}

			$result = $this->add_user_to_group( $user->ID, $gid );
			if ( is_wp_error( $result ) ) {
				$errors[] = array(
					'email' => $sanitized,
					'error' => $result->get_error_message(),
				);
			} elseif ( 'exists' === $result ) {
				$already_member[] = $user->ID;
			} else {
				$added[] = $user->ID;
			}
		}

		return rest_ensure_response(
			array(
				'group_id'        => $gid,
				'added'           => array_values( array_unique( $added ) ),
				'already_members' => array_values( array_unique( $already_member ) ),
				'not_found'       => $not_found,
				'errors'          => $errors,
			)
		);
	}

	/**
	 * Removes a specific member from a group.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function remove_group_member_endpoint( WP_REST_Request $request ) {
		$params = $this->get_request_payload( $request );
		$gid    = isset( $params['group_id'] ) ? (int) $params['group_id'] : (int) $request->get_param( 'group_id' );
		$uid    = isset( $params['user_id'] ) ? (int) $params['user_id'] : (int) $request->get_param( 'user_id' );

		if ( $gid <= 0 || $uid <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_parameters',
				__( 'Both group_id and user_id are required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$group = $this->get_group_by_id( $gid );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$result = $this->remove_user_from_group( $uid, $gid );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'group_id' => $gid,
				'user_id'  => $uid,
				'removed'  => (bool) $result,
			)
		);
	}

	/**
	 * Partially updates a group.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_group_endpoint( WP_REST_Request $request ) {
		$params = $this->get_request_payload( $request );
		$gid    = isset( $params['group_id'] ) ? (int) $params['group_id'] : (int) $request->get_param( 'group_id' );

		if ( $gid <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_group_id',
				__( 'A valid group_id parameter is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$group = $this->get_group_by_id( $gid );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$data = $this->map_group_update_payload( $params );
		if ( empty( $data ) ) {
			return new WP_Error(
				'pg_rest_no_changes',
				__( 'No updatable fields were provided.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$dbhandler = new PM_DBhandler();
		$updated   = $dbhandler->update_row( 'GROUPS', 'id', $gid, $data );

		if ( false === $updated ) {
			return new WP_Error(
				'pg_rest_group_update_failed',
				__( 'Unable to update the group.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 500 )
			);
		}

		$group = $dbhandler->get_row( 'GROUPS', $gid );

		return rest_ensure_response(
			array(
				'message' => __( 'Group updated successfully.', 'profilegrid-user-profiles-groups-and-communities' ),
				'group'   => $this->prepare_group_response( $group ),
			)
		);
	}

	/**
	 * Deletes (soft or hard) a group.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_group_endpoint( WP_REST_Request $request ) {
		$params = $this->get_request_payload( $request );
		$gid    = isset( $params['group_id'] ) ? (int) $params['group_id'] : (int) $request->get_param( 'group_id' );
		$force  = isset( $params['force'] ) ? $this->normalize_boolean_flag( $params['force'] ) : $this->normalize_boolean_flag( $request->get_param( 'force' ) );

		if ( $gid <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_group_id',
				__( 'A valid group_id parameter is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$group = $this->get_group_by_id( $gid );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$dbhandler = new PM_DBhandler();

		if ( 1 === $force ) {
			$this->detach_group_from_members( $gid );
			$deleted = $dbhandler->remove_row( 'GROUPS', 'id', $gid );
			if ( false === $deleted ) {
				return new WP_Error(
					'pg_rest_group_delete_failed',
					__( 'Unable to delete the group.', 'profilegrid-user-profiles-groups-and-communities' ),
					array( 'status' => 500 )
				);
			}

			return rest_ensure_response(
				array(
					'group_id' => $gid,
					'status'   => 'deleted',
				)
			);
		}

		$options = isset( $group->group_options ) ? maybe_unserialize( $group->group_options ) : array();
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$options['pg_deleted']    = 1;
		$options['pg_deleted_at'] = current_time( 'mysql' );

		$dbhandler->update_row(
			'GROUPS',
			'id',
			$gid,
			array(
				'group_options' => maybe_serialize( $options ),
			)
		);

		return rest_ensure_response(
			array(
				'group_id' => $gid,
				'status'   => 'soft_deleted',
			)
		);
	}

	/**
	 * Lists membership requests with pagination and filters.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_membership_requests_endpoint( WP_REST_Request $request ) {
		$page    = max( 1, (int) $request->get_param( 'page' ) );
		$raw_per = $request->get_param( 'per_page' );

		if ( $raw_per === 'all' || (string) $raw_per === '0' || $request->get_param( 'all' ) ) {
			$per_page = false;
			$offset   = 0;
		} else {
			$per_page = (int) $raw_per;
			$per_page = $per_page > 0 ? min( $per_page, 200 ) : 20;
			$offset   = ( $page - 1 ) * $per_page;
		}

		$gid    = (int) $request->get_param( 'group_id' );
		$uid    = (int) $request->get_param( 'user_id' );
		$status = $request->get_param( 'status' );
		$search = $request->get_param( 'search' );

		$conditions = array();
		if ( $gid > 0 ) {
			$conditions['gid'] = $gid;
		}
		if ( $uid > 0 ) {
			$conditions['uid'] = $uid;
		}
		if ( null !== $status && $status !== '' ) {
			$conditions['status'] = $status;
		} else {
			$conditions['status'] = '1';
		}

		$where = empty( $conditions ) ? 1 : $conditions;

		$search_ids = array();
		if ( ! empty( $search ) ) {
			$user_query = new WP_User_Query(
				array(
					'search'         => '*' . esc_attr( $search ) . '*',
					'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
					'fields'         => array( 'ID' ),
				)
			);
			$results = $user_query->get_results();
			if ( ! empty( $results ) ) {
				foreach ( $results as $user_row ) {
					$search_ids[] = (int) $user_row->ID;
				}
			}
		}

		if ( ! empty( $search ) && empty( $search_ids ) ) {
			$additional = 'AND uid IN(0)';
		} elseif ( ! empty( $search_ids ) ) {
			$additional = 'AND uid IN(' . implode( ',', array_map( 'intval', $search_ids ) ) . ')';
		} else {
			$additional = '';
		}

		$dbhandler = new PM_DBhandler();

		$total_result = $dbhandler->get_all_result( 'REQUESTS', 'COUNT(id)', $where, 'var', 0, false, null, false, $additional );
		$total        = is_numeric( $total_result ) ? (int) $total_result : 0;

		$results = $dbhandler->get_all_result( 'REQUESTS', '*', $where, 'results', $offset, $per_page, 'id', 'DESC', $additional );
		$data    = array();

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$data[] = $this->map_membership_request_row( $row );
			}
		}

		$per_page_val = ( false === $per_page ) ? $total : (int) $per_page;
		$total_pages  = ( false === $per_page || 0 === $per_page_val ) ? 1 : (int) ceil( $total / $per_page_val );

		return rest_ensure_response(
			array(
				'data'       => $data,
				'pagination' => array(
					'page'       => (int) $page,
					'per_page'   => $per_page_val,
					'total'      => $total,
					'total_page' => $total_pages,
				),
			)
		);
	}

	/**
	 * Creates a membership request (pending approval).
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_membership_request_endpoint( WP_REST_Request $request ) {
		$params  = $this->get_request_payload( $request );
		$gid     = isset( $params['group_id'] ) ? (int) $params['group_id'] : (int) $request->get_param( 'group_id' );
		$uid     = isset( $params['user_id'] ) ? (int) $params['user_id'] : (int) $request->get_param( 'user_id' );
		$message = isset( $params['message'] ) ? wp_kses_post( $params['message'] ) : '';

		if ( $gid <= 0 || $uid <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_parameters',
				__( 'Both group_id and user_id are required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$group = $this->get_group_by_id( $gid );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$user = get_user_by( 'ID', $uid );
		if ( ! $user ) {
			return new WP_Error(
				'pg_rest_user_not_found',
				__( 'User not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 404 )
			);
		}

		$pm_request = $this->get_request_helper();
		if ( $pm_request->profile_magic_check_is_group_member( $gid, $uid ) ) {
			return new WP_Error(
				'pg_rest_user_already_member',
				__( 'User is already a member of this group.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 409 )
			);
		}

		$dbhandler = new PM_DBhandler();
		$where     = array(
			'gid' => $gid,
			'uid' => $uid,
		);

		$existing = $dbhandler->get_all_result( 'REQUESTS', '*', $where );
		if ( ! empty( $existing ) ) {
			return new WP_Error(
				'pg_rest_request_exists',
				__( 'A membership request already exists for this user.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 409 )
			);
		}

		$options = array(
			'request_date' => gmdate( 'Y-m-d H:i:s' ),
		);
		if ( ! empty( $message ) ) {
			$options['message'] = $message;
		}

		$data = array(
			'gid'     => $gid,
			'uid'     => $uid,
			'status'  => '1',
			'options' => maybe_serialize( $options ),
		);

		$request_id = $dbhandler->insert_row( 'REQUESTS', $data, array( '%d', '%d', '%d', '%s' ) );
		if ( false === $request_id ) {
			return new WP_Error(
				'pg_rest_request_create_failed',
				__( 'Unable to create membership request.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 500 )
			);
		}

		$this->notify_group_leaders_pending_request( $gid, $uid );
		do_action( 'profilegrid_join_group_request', $gid, $uid );

		$request = $dbhandler->get_row( 'REQUESTS', $request_id, 'id' );

		return rest_ensure_response(
			array(
				'message' => __( 'Membership request created.', 'profilegrid-user-profiles-groups-and-communities' ),
				'request' => $this->map_membership_request_row( $request ),
			)
		);
	}

	/**
	 * Approves a membership request and adds user to the group.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve_membership_request_endpoint( WP_REST_Request $request ) {
		$request_id = (int) $request->get_param( 'request_id' );
		if ( $request_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_request_id',
				__( 'A valid request_id is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$dbhandler = new PM_DBhandler();
		$record    = $dbhandler->get_row( 'REQUESTS', $request_id, 'id' );

		if ( empty( $record ) ) {
			return new WP_Error(
				'pg_rest_request_not_found',
				__( 'Membership request not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 404 )
			);
		}

		$pm_request = $this->get_request_helper();
		if ( ! $pm_request->pg_check_group_limit_available( $record->gid ) ) {
			$message = $dbhandler->get_value( 'GROUPS', 'group_limit_message', $record->gid );
			if ( empty( $message ) ) {
				$message = __( 'Group limit reached.', 'profilegrid-user-profiles-groups-and-communities' );
			}
			return new WP_Error(
				'pg_rest_group_limit_reached',
				$message,
				array( 'status' => 409 )
			);
		}

		$joined = $pm_request->profile_magic_join_group_fun( $record->uid, $record->gid, 'open' );
		if ( false === $joined ) {
			return new WP_Error(
				'pg_rest_join_failed',
				__( 'Unable to add user to the group.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 500 )
			);
		}

		$dbhandler->remove_row( 'REQUESTS', 'id', $request_id );
		do_action( 'pm_user_membership_request_approve', $record->gid, $record->uid );

		return rest_ensure_response(
			array(
				'message'    => __( 'Membership request approved.', 'profilegrid-user-profiles-groups-and-communities' ),
				'group_id'   => (int) $record->gid,
				'user_id'    => (int) $record->uid,
				'request_id' => $request_id,
			)
		);
	}

	/**
	 * Denies a membership request.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function deny_membership_request_endpoint( WP_REST_Request $request ) {
		$request_id = (int) $request->get_param( 'request_id' );
		$reason     = $request->get_param( 'reason' );

		if ( $request_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_request_id',
				__( 'A valid request_id is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$dbhandler = new PM_DBhandler();
		$record    = $dbhandler->get_row( 'REQUESTS', $request_id, 'id' );

		if ( empty( $record ) ) {
			return new WP_Error(
				'pg_rest_request_not_found',
				__( 'Membership request not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 404 )
			);
		}

		$dbhandler->remove_row( 'REQUESTS', 'id', $request_id );

		$pmemails = new PM_Emails();
		$pmemails->pm_send_group_based_notification( $record->gid, $record->uid, 'on_request_denied' );
		do_action( 'pm_user_membership_request_denied', $record->gid, $record->uid );

		return rest_ensure_response(
			array(
				'message'    => __( 'Membership request denied.', 'profilegrid-user-profiles-groups-and-communities' ),
				'group_id'   => (int) $record->gid,
				'user_id'    => (int) $record->uid,
				'request_id' => $request_id,
				'reason'     => $reason,
			)
		);
	}

	/**
	 * Bulk denies all pending membership requests for a group.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk_deny_all_membership_requests_endpoint( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		$params   = $this->get_request_payload( $request );
		$group_id = isset( $params['group_id'] ) ? (int) $params['group_id'] : (int) $request->get_param( 'group_id' );
		$reason   = isset( $params['reason'] ) ? sanitize_text_field( $params['reason'] ) : sanitize_text_field( $request->get_param( 'reason' ) );

		if ( $group_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_group_id',
				__( 'A valid group_id parameter is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$group = $this->get_group_by_id( $group_id );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$dbhandler = new PM_DBhandler();
		$requests  = $dbhandler->get_all_result(
			'REQUESTS',
			'*',
			array(
				'gid'    => $group_id,
				'status' => '1',
			),
			'results'
		);

		$denied = array();
		$errors = array();

		if ( ! empty( $requests ) ) {
			foreach ( $requests as $record ) {
				$result = $this->deny_membership_request_record( $record, $reason );
				if ( is_wp_error( $result ) ) {
					$errors[] = array(
						'request_id' => isset( $record->id ) ? (int) $record->id : 0,
						'error'      => $result->get_error_message(),
					);
				} else {
					$denied[] = isset( $record->id ) ? (int) $record->id : 0;
				}
			}
		}

		return rest_ensure_response(
			array(
				'group_id' => $group_id,
				'denied'   => $denied,
				'errors'   => $errors,
				'reason'   => $reason,
			)
		);
	}

	/**
	 * Bulk approves all pending membership requests for a group.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk_approve_all_membership_requests_endpoint( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		$params   = $this->get_request_payload( $request );
		$group_id = isset( $params['group_id'] ) ? (int) $params['group_id'] : (int) $request->get_param( 'group_id' );

		if ( $group_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_group_id',
				__( 'A valid group_id parameter is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$group = $this->get_group_by_id( $group_id );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$dbhandler = new PM_DBhandler();
		$requests  = $dbhandler->get_all_result(
			'REQUESTS',
			'*',
			array(
				'gid'    => $group_id,
				'status' => '1',
			),
			'results'
		);

		$approved = array();
		$errors   = array();

		if ( ! empty( $requests ) ) {
			foreach ( $requests as $record ) {
				$result = $this->approve_membership_request_record( $record );
				if ( is_wp_error( $result ) ) {
					$errors[] = array(
						'request_id' => isset( $record->id ) ? (int) $record->id : 0,
						'error'      => $result->get_error_message(),
					);
				} else {
					$approved[] = isset( $record->id ) ? (int) $record->id : 0;
				}
			}
		}

		return rest_ensure_response(
			array(
				'group_id' => $group_id,
				'approved' => $approved,
				'errors'   => $errors,
			)
		);
	}


	/**
	 * Activates a user account globally.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function activate_user_account_endpoint( WP_REST_Request $request ) {
		$params  = $this->get_request_payload( $request );
		$user_id = isset( $params['user_id'] ) ? (int) $params['user_id'] : (int) $request->get_param( 'user_id' );

		if ( $user_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_user_id',
				__( 'A valid user_id parameter is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'pg_rest_user_not_found',
				__( 'User not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 404 )
			);
		}

		update_user_meta( $user_id, 'rm_user_status', '0' );
		$this->notify_groups_about_user_status( $user_id, 'activate' );

		return rest_ensure_response(
			array(
				'message' => __( 'User account activated.', 'profilegrid-user-profiles-groups-and-communities' ),
				'user_id' => $user_id,
				'status'  => 'activated',
			)
		);
	}

	/**
	 * Deactivates (suspends) a user account globally.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function deactivate_user_account_endpoint( WP_REST_Request $request ) {
		$params  = $this->get_request_payload( $request );
		$user_id = isset( $params['user_id'] ) ? (int) $params['user_id'] : (int) $request->get_param( 'user_id' );

		if ( $user_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_user_id',
				__( 'A valid user_id parameter is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'pg_rest_user_not_found',
				__( 'User not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 404 )
			);
		}

		update_user_meta( $user_id, 'rm_user_status', '1' );
		do_action( 'pg_user_suspended', $user_id );
		$this->notify_groups_about_user_status( $user_id, 'deactivate' );

		return rest_ensure_response(
			array(
				'message' => __( 'User account deactivated.', 'profilegrid-user-profiles-groups-and-communities' ),
				'user_id' => $user_id,
				'status'  => 'deactivated',
			)
		);
	}

	/**
	 * Activates all eligible non-admin users.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function activate_all_user_endpoint( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		if ( ! $this->current_user || ! $this->user_has_access( $this->current_user ) ) {
			return new WP_Error(
				'pg_rest_forbidden_role',
				__( 'You are not allowed to use ProfileGrid APIs.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$query  = new WP_User_Query(
			array(
				'role__not_in' => array( 'administrator' ),
				'fields'       => array( 'ID' ),
				'number'       => -1,
			)
		);
		$users  = $query->get_results();
		$total  = is_array( $users ) ? count( $users ) : 0;
		$activated = array();
		$skipped   = array();
		$errors    = array();

		if ( ! empty( $users ) ) {
			foreach ( $users as $user ) {
				$uid    = (int) $user->ID;
				$status = get_user_meta( $uid, 'rm_user_status', true );

				if ( '0' === (string) $status ) {
					$skipped[] = $uid;
					continue;
				}

				$updated = update_user_meta( $uid, 'rm_user_status', '0' );
				if ( false === $updated ) {
					$errors[] = array(
						'user_id' => $uid,
						'error'   => __( 'Failed to activate user.', 'profilegrid-user-profiles-groups-and-communities' ),
					);
					continue;
				}

				$this->notify_groups_about_user_status( $uid, 'activate' );
				$activated[] = $uid;
			}
		}

		return rest_ensure_response(
			array(
				'message'   => __( 'All eligible users activated.', 'profilegrid-user-profiles-groups-and-communities' ),
				'processed' => array(
					'total'     => $total,
					'activated' => count( $activated ),
					'skipped'   => count( $skipped ),
					'errors'    => $errors,
				),
				'activated' => $activated,
				'skipped'   => $skipped,
			)
		);
	}

	/**
	 * Deactivates all eligible non-admin users.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function deactivate_all_user_endpoint( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		if ( ! $this->current_user || ! $this->user_has_access( $this->current_user ) ) {
			return new WP_Error(
				'pg_rest_forbidden_role',
				__( 'You are not allowed to use ProfileGrid APIs.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$query  = new WP_User_Query(
			array(
				'role__not_in' => array( 'administrator' ),
				'fields'       => array( 'ID' ),
				'number'       => -1,
			)
		);
		$users  = $query->get_results();
		$total  = is_array( $users ) ? count( $users ) : 0;
		$deactivated = array();
		$skipped     = array();
		$errors      = array();

		if ( ! empty( $users ) ) {
			foreach ( $users as $user ) {
				$uid    = (int) $user->ID;
				$status = get_user_meta( $uid, 'rm_user_status', true );

				if ( '1' === (string) $status ) {
					$skipped[] = $uid;
					continue;
				}

				$updated = update_user_meta( $uid, 'rm_user_status', '1' );
				if ( false === $updated ) {
					$errors[] = array(
						'user_id' => $uid,
						'error'   => __( 'Failed to deactivate user.', 'profilegrid-user-profiles-groups-and-communities' ),
					);
					continue;
				}

				do_action( 'pg_user_suspended', $uid );
				$this->notify_groups_about_user_status( $uid, 'deactivate' );
				$deactivated[] = $uid;
			}
		}

		return rest_ensure_response(
			array(
				'message'   => __( 'All eligible users deactivated.', 'profilegrid-user-profiles-groups-and-communities' ),
				'processed' => array(
					'total'       => $total,
					'deactivated' => count( $deactivated ),
					'skipped'     => count( $skipped ),
					'errors'      => $errors,
				),
				'deactivated' => $deactivated,
				'skipped'     => $skipped,
			)
		);
	}

	/**
	 * Assigns a user to a group (single user_id/group_id).
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function assign_group_endpoint( WP_REST_Request $request ) {
		$params  = $this->get_request_payload( $request );
		$gid     = isset( $params['group_id'] ) ? (int) $params['group_id'] : (int) $request->get_param( 'group_id' );
		$user_id = isset( $params['user_id'] ) ? (int) $params['user_id'] : (int) $request->get_param( 'user_id' );

		if ( $gid <= 0 || $user_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_parameters',
				__( 'Both group_id and user_id are required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$group = $this->get_group_by_id( $gid );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$result = $this->add_user_to_group( $user_id, $gid );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$status = ( 'exists' === $result ) ? 'already_member' : 'assigned';

		return rest_ensure_response(
			array(
				'group_id' => $gid,
				'user_id'  => $user_id,
				'status'   => $status,
			)
		);
	}

	/**
	 * Updates profile data for a user (user_id only).
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_user_profile_endpoint( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		$params  = $this->get_request_payload( $request );
		$user_id = isset( $params['user_id'] ) ? (int) $params['user_id'] : (int) $request->get_param( 'user_id' );

		if ( $user_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_user_id',
				__( 'A valid user_id parameter is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'pg_rest_user_not_found',
				__( 'User not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 404 )
			);
		}

		$raw_fields = isset( $params['fields'] ) ? $params['fields'] : array();
		$raw_fields = $this->normalize_complex_payload( $raw_fields );

		if ( empty( $raw_fields ) || ! is_array( $raw_fields ) ) {
			return new WP_Error(
				'pg_rest_missing_parameters',
				__( 'Fields payload is required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$pm_request = $this->get_request_helper();
		$groups     = $this->resolve_user_group_ids( $user_id );
		$updated    = false;

		if ( ! empty( $groups ) ) {
			foreach ( $groups as $gid ) {
				$fields = $pm_request->pm_get_backend_user_meta( $user_id, $gid, array() );
				if ( empty( $fields ) ) {
					continue;
				}

				$post_data        = array();
				$fields_to_update = array();

				foreach ( $fields as $field ) {
					$field_key = isset( $field->field_key ) ? $field->field_key : '';
					$field_id  = isset( $field->field_id ) ? (string) $field->field_id : '';

					if ( '' !== $field_key && array_key_exists( $field_key, $raw_fields ) ) {
						$post_data[ $field_key ] = $raw_fields[ $field_key ];
						$fields_to_update[]      = $field;
						continue;
					}

					if ( '' !== $field_id && array_key_exists( $field_id, $raw_fields ) ) {
						$post_data[ $field_key ] = $raw_fields[ $field_id ];
						$fields_to_update[]      = $field;
					}
				}

				if ( empty( $fields_to_update ) ) {
					continue;
				}

				$pm_request->pm_update_user_custom_fields_data( $post_data, array(), $_SERVER, $gid, $fields_to_update, $user_id );
				$updated = true;
			}
		}

		if ( ! $updated ) {
			return new WP_Error(
				'pg_rest_no_changes',
				__( 'No updatable fields were provided.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'message' => __( 'User profile updated successfully', 'profilegrid-user-profiles-groups-and-communities' ),
				'user_id' => $user_id,
			)
		);
	}

	/**
	 * Removes a user from a group (single user_id/group_id).
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function remove_from_group_endpoint( WP_REST_Request $request ) {
		$params  = $this->get_request_payload( $request );
		$gid     = isset( $params['group_id'] ) ? (int) $params['group_id'] : (int) $request->get_param( 'group_id' );
		$user_id = isset( $params['user_id'] ) ? (int) $params['user_id'] : (int) $request->get_param( 'user_id' );

		if ( $gid <= 0 || $user_id <= 0 ) {
			return new WP_Error(
				'pg_rest_missing_parameters',
				__( 'Both group_id and user_id are required.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$group = $this->get_group_by_id( $gid );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$result = $this->remove_user_from_group( $user_id, $gid );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'group_id' => $gid,
				'user_id'  => $user_id,
				'status'   => 'removed',
			)
		);
	}

	/**
	 * Handles the integration endpoint using action parameter.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_integration_request( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		// Check if this is an integration request
		$integration = $request->get_param( 'integration' );
		if ( ! $integration ) {
			return new WP_Error(
				'pg_rest_missing_integration',
				__( 'Integration flag is missing. Append ?integration=1 to your request.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$action = $request->get_param( 'action' );

		if ( 'get_access_token' !== $action ) {
			$auth = $this->authenticate_request( $request );
			if ( is_wp_error( $auth ) ) {
				return $auth;
			}

			$authorization = $this->authorize_integration_action( $action, $request );
			if ( is_wp_error( $authorization ) ) {
				return $authorization;
			}
		}

		switch ( $action ) {
			case 'get_access_token':
				return $this->generate_access_token( $request );

			case 'get_all_groups':
				return $this->get_groups( $request );

			case 'get_single_group':
				return $this->get_single_group( $request );
			case 'get_group_members':
				return $this->get_group_members_endpoint( $request );
			case 'add_group_members':
				return $this->add_group_members_endpoint( $request );
			case 'remove_group_member':
				return $this->remove_group_member_endpoint( $request );
			case 'update_group':
				return $this->update_group_endpoint( $request );
			case 'delete_group':
				return $this->delete_group_endpoint( $request );
			case 'get_membership_requests':
				return $this->get_membership_requests_endpoint( $request );
			case 'create_membership_request':
				return $this->create_membership_request_endpoint( $request );
			case 'approve_membership_request':
				return $this->approve_membership_request_endpoint( $request );
			case 'deny_membership_request':
				return $this->deny_membership_request_endpoint( $request );
			case 'assign_group':
				return $this->assign_group_endpoint( $request );
			case 'remove_from_group':
				return $this->remove_from_group_endpoint( $request );
			case 'activate_user_account':
				return $this->activate_user_account_endpoint( $request );
			case 'deactivate_user_account':
				return $this->deactivate_user_account_endpoint( $request );
			case 'activate_all_user':
				return $this->activate_all_user_endpoint( $request );
			case 'deactivate_all_user':
				return $this->deactivate_all_user_endpoint( $request );
			case 'update_user_profile':
				return $this->update_user_profile_endpoint( $request );
			case 'bulk_approve_all_membership_requests':
				return $this->bulk_approve_all_membership_requests_endpoint( $request );
			case 'bulk_deny_all_membership_requests':
				return $this->bulk_deny_all_membership_requests_endpoint( $request );
			case 'create_group':
				return $this->create_group( $request );
			case 'get_users':
				return $this->get_users_endpoint( $request );
			case 'get_user_details':
				return $this->get_user_details_endpoint( $request );
			case 'get_group_section':
				return $this->get_group_section_endpoint( $request );
			case 'update_group_section':
				return $this->update_group_section_endpoint( $request );
			case 'delete_section':
				return $this->delete_group_section_endpoint( $request );
			case 'create_group_section':
				return $this->create_group_section_endpoint( $request );
			case 'create_group_field':
				return $this->create_group_field_endpoint( $request );

			default:
				return new WP_Error(
					'pg_rest_unknown_action',
					__( 'Unknown or missing action parameter.', 'profilegrid-user-profiles-groups-and-communities' ),
					array( 'status' => 400 )
				);
		}
	}

	/**
	 * Permission callback wrapper to validate bearer token.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return true|WP_Error
	 */
	public function authenticate_request( WP_REST_Request $request ) {
		$enabled = $this->assert_api_enabled();
		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		$token = $this->extract_token( $request );
		if ( empty( $token ) ) {
			return new WP_Error(
				'pg_rest_missing_token',
				__( 'API token missing. Send it via the PG-Token header or Authorization: Bearer.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$user = $this->validate_token( $token );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$this->current_user = $user;
		wp_set_current_user( $user->ID );

		if ( ! $this->user_has_access( $user ) ) {
			return new WP_Error(
				'pg_rest_forbidden_role',
				__( 'You are not allowed to use ProfileGrid APIs.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		/*
		 * Enforce endpoint-level saved role permissions for both:
		 * - integration action requests (?integration=1&action=...)
		 * - direct REST routes (e.g. /groups, /groups/{id})
		 */
		$scoped_action = $this->resolve_permission_action_from_request( $request );
		if ( ! empty( $scoped_action ) ) {
			$authorization = $this->authorize_integration_action( $scoped_action, $request );
			if ( is_wp_error( $authorization ) ) {
				return $authorization;
			}
		}

		return true;
	}

	/**
	 * Issues a new token stored as transient.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array
	 */
	protected function issue_token( $user_id ) {
		$token       = wp_generate_password( 64, false, false );
		$expires_in  = (int) apply_filters( 'pg_rest_api_token_expiration', DAY_IN_SECONDS );
		$transient   = 'pg_api_token_' . $token;
		$token_value = array(
			'user_id' => $user_id,
			'issued'  => time(),
		);

		set_transient( $transient, $token_value, $expires_in );

		return array(
			'token'      => $token,
			'expires_in' => $expires_in,
		);
	}

	/**
	 * Extracts the token from headers.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return string
	 */
	protected function extract_token( WP_REST_Request $request ) {
		$token = $request->get_header( 'pg-token' );

		if ( empty( $token ) ) {
			$auth_header = $request->get_header( 'authorization' );
			if ( ! empty( $auth_header ) && preg_match( '/Bearer\\s+(.*)$/i', $auth_header, $matches ) ) {
				$token = trim( $matches[1] );
			}
		}

		if ( empty( $token ) ) {
			$allow_query_token = apply_filters( 'pg_rest_api_allow_query_token', false, $request );
			if ( $allow_query_token ) {
				$token = $request->get_param( 'token' );
			}
		}

		return $token;
	}

	/**
	 * Validates token and returns user.
	 *
	 * @param string $token Provided token.
	 *
	 * @return WP_User|WP_Error
	 */
	protected function validate_token( $token ) {
		$cached = get_transient( 'pg_api_token_' . $token );
		if ( false === $cached || empty( $cached['user_id'] ) ) {
			return new WP_Error(
				'pg_rest_invalid_token',
				__( 'Invalid or expired token.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$user = get_user_by( 'ID', (int) $cached['user_id'] );

		if ( ! $user ) {
			return new WP_Error(
				'pg_rest_invalid_token',
				__( 'The token is linked to a user that no longer exists.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return $user;
	}

	/**
	 * Normalizes a group object for API responses.
	 *
	 * @param object|array $group Group data.
	 *
	 * @return array
	 */
	protected function prepare_group_response( $group ) {
		if ( empty( $group ) ) {
			return array();
		}

		$data = (array) $group;

		if ( isset( $data['group_options'] ) ) {
			$data['group_options'] = maybe_unserialize( $data['group_options'] );
		}

		if ( isset( $data['group_leaders'] ) ) {
			$data['group_leaders'] = maybe_unserialize( $data['group_leaders'] );
		}

		if ( isset( $data['leader_rights'] ) ) {
			$data['leader_rights'] = maybe_unserialize( $data['leader_rights'] );
		}

		$dbhandler = new PM_DBhandler();
		$gid       = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		// Keep the default group payload minimal and schema-oriented.
		if ( $gid > 0 ) {
			$group_requests = $dbhandler->get_all_result( 'GROUP_REQUESTS', '*', array( 'gid' => $gid ), 'results' );
			if ( ! empty( $group_requests ) ) {
				$data['group_requests'] = array_map( function( $r ) { return (array) $r; }, $group_requests );
			} else {
				$data['group_requests'] = array();
			}

			// Fetch members: users whose usermeta 'pm_group' contains this gid (stored as serialized array)
			$members = array();
			$serialized_fragment = sprintf(':"%s";', $gid);
			$user_query_args = array(
				'meta_query' => array(
					array(
						'key'     => 'pm_group',
						'value'   => $serialized_fragment,
						'compare' => 'LIKE',
					),
				),
				'fields' => array( 'ID', 'user_login', 'display_name' ),
			);

			$wp_users = get_users( $user_query_args );
			if ( ! empty( $wp_users ) ) {
				foreach ( $wp_users as $u ) {
					$members[] = array(
						'id'           => (int) $u->ID,
						'user_login'   => sanitize_user( $u->user_login, true ),
						'display_name' => sanitize_text_field( $u->display_name ),
						'avatar'       => esc_url_raw( get_avatar_url( $u->ID ) ),
					);
				}
			}

			$data['members'] = $members;
			$data['members_count'] = count( $members );
		}

		// Include leader details (id, login, display_name) for readability
		if ( ! empty( $data['group_leaders'] ) && is_array( $data['group_leaders'] ) ) {
			$leader_details = array();
			foreach ( $data['group_leaders'] as $leader_login ) {
				$leader = get_user_by( 'login', $leader_login );
				if ( $leader ) {
					$leader_details[] = array(
						'id'           => (int) $leader->ID,
						'user_login'   => sanitize_user( $leader->user_login, true ),
						'display_name' => sanitize_text_field( $leader->display_name ),
					);
				}
			}
			$data['group_leaders_details'] = $leader_details;
		}

		// Add group icon URL when present
		if ( ! empty( $data['group_icon'] ) ) {
			$icon_id = absint( $data['group_icon'] );
			$icon_url = wp_get_attachment_url( $icon_id );
			$data['group_icon_url'] = $icon_url ? esc_url_raw( $icon_url ) : '';
		}

		if ( $gid > 0 ) {
			$sections          = $dbhandler->get_all_result( 'SECTION', '*', array( 'gid' => $gid ), 'results', 0, false, 'ordering' );
			$fields            = $dbhandler->get_all_result( 'FIELDS', '*', array( 'associate_group' => $gid ), 'results', 0, false, 'ordering' );
			$data['sections']  = $sections ? $this->map_sections( $sections, $gid ) : array();
			$data['fields']    = $fields ? $this->map_fields( $fields ) : array();
		}

		return $data;
	}

	/**
	 * Maps sections to include their fields.
	 *
	 * @param array $sections Sections.
	 * @param int   $gid      Group ID.
	 *
	 * @return array
	 */
	protected function map_sections( $sections, $gid ) {
		$dbhandler = new PM_DBhandler();
		$mapped    = array();

		foreach ( $sections as $section ) {
			$section_data = (array) $section;

			$section_fields = $dbhandler->get_all_result(
				'FIELDS',
				'*',
				array(
					'associate_group'   => $gid,
					'associate_section' => $section->id,
				),
				'results',
				0,
				false,
				'ordering'
			);

			$section_data['fields'] = $this->map_fields( $section_fields );
			$mapped[]               = $section_data;
		}

		return $mapped;
	}

	/**
	 * Normalizes field objects for API responses.
	 *
	 * @param array $fields Fields.
	 *
	 * @return array
	 */
	protected function map_fields( $fields ) {
		$mapped = array();

		if ( empty( $fields ) ) {
			return $mapped;
		}

		foreach ( $fields as $field ) {
			$field_data = (array) $field;

			if ( isset( $field_data['field_options'] ) ) {
				$field_data['field_options'] = maybe_unserialize( $field_data['field_options'] );
			}

			$mapped[] = $field_data;
		}

		return $mapped;
	}

	/**
	 * Converts WP_User into group member payload.
	 *
	 * @param WP_User|object $user User object.
	 * @param int            $gid  Group ID.
	 *
	 * @return array
	 */
	protected function map_group_member_response( $user, $gid ) {
		$user_id = (int) $user->ID;
		$status  = get_user_meta( $user_id, 'rm_user_status', true );
		$status  = ( '' === $status ) ? '0' : (string) $status;

		$joining_dates = get_user_meta( $user_id, 'pm_joining_date', true );
		$joining_dates = is_array( $joining_dates ) ? $joining_dates : array();
		$joined_at     = isset( $joining_dates[ $gid ] ) ? $joining_dates[ $gid ] : $user->user_registered;

		$pm_request  = $this->get_request_helper();
		$profile_url = method_exists( $pm_request, 'pm_get_user_profile_url' ) ? $pm_request->pm_get_user_profile_url( $user_id ) : '';

		return array(
			'id'           => $user_id,
			'user_login'   => sanitize_user( $user->user_login, true ),
			'display_name' => sanitize_text_field( $user->display_name ),
			'email'        => isset( $user->user_email ) ? sanitize_email( $user->user_email ) : '',
			'avatar'       => esc_url_raw( get_avatar_url( $user_id ) ),
			'roles'        => $user instanceof WP_User ? (array) $user->roles : array(),
			'status'       => $status,
			'joined_at'    => $joined_at,
			'profile_url'  => esc_url_raw( $profile_url ),
		);
	}

	/**
	 * Maps a WP_User into a generic user payload.
	 *
	 * @param WP_User|object $user User object.
	 *
	 * @return array
	 */
	protected function map_user_response( $user ) {
		if ( empty( $user ) || ! isset( $user->ID ) ) {
			return array();
		}

		$user_id   = (int) $user->ID;
		$status    = get_user_meta( $user_id, 'rm_user_status', true );
		$status    = ( '' === $status ) ? '0' : (string) $status;
		$pm_request = $this->get_request_helper();
		$groups     = get_user_meta( $user_id, 'pm_group', true );
		$groups     = $pm_request->pg_filter_users_group_ids( $groups );
		$groups     = is_array( $groups ) ? array_values( array_unique( array_map( 'absint', $groups ) ) ) : array();
		$profile_url = method_exists( $pm_request, 'pm_get_user_profile_url' ) ? $pm_request->pm_get_user_profile_url( $user_id ) : '';

		return array(
			'id'           => $user_id,
			'user_login'   => sanitize_user( $user->user_login, true ),
			'display_name' => isset( $user->display_name ) ? sanitize_text_field( $user->display_name ) : '',
			'email'        => isset( $user->user_email ) ? sanitize_email( $user->user_email ) : '',
			'roles'        => $user instanceof WP_User ? (array) $user->roles : array(),
			'status'       => $status,
			'groups'       => $groups,
			'avatar'       => esc_url_raw( get_avatar_url( $user_id ) ),
			'profile_url'  => esc_url_raw( $profile_url ),
		);
	}

	/**
	 * Resolves group IDs for a user.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array
	 */
	protected function resolve_user_group_ids( $user_id ) {
		$pm_request = $this->get_request_helper();
		$groups     = get_user_meta( $user_id, 'pm_group', true );
		$groups     = $pm_request->pg_filter_users_group_ids( $groups );

		return is_array( $groups ) ? array_values( array_unique( array_map( 'absint', $groups ) ) ) : array();
	}

	/**
	 * Approves a membership request record.
	 *
	 * @param object $record Request record.
	 *
	 * @return true|WP_Error
	 */
	protected function approve_membership_request_record( $record ) {
		if ( empty( $record ) || ! isset( $record->gid, $record->uid ) ) {
			return new WP_Error(
				'pg_rest_request_not_found',
				__( 'Membership request not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 404 )
			);
		}

		$dbhandler  = new PM_DBhandler();
		$pm_request = $this->get_request_helper();

		if ( ! $pm_request->pg_check_group_limit_available( $record->gid ) ) {
			$message = $dbhandler->get_value( 'GROUPS', 'group_limit_message', $record->gid );
			if ( empty( $message ) ) {
				$message = __( 'Group limit reached.', 'profilegrid-user-profiles-groups-and-communities' );
			}
			return new WP_Error(
				'pg_rest_group_limit_reached',
				$message,
				array( 'status' => 409 )
			);
		}

		$joined = $pm_request->profile_magic_join_group_fun( $record->uid, $record->gid, 'open' );
		if ( false === $joined ) {
			return new WP_Error(
				'pg_rest_join_failed',
				__( 'Unable to add user to the group.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 500 )
			);
		}

		if ( isset( $record->id ) ) {
			$dbhandler->remove_row( 'REQUESTS', 'id', $record->id );
		}

		do_action( 'pm_user_membership_request_approve', $record->gid, $record->uid );

		return true;
	}

	/**
	 * Denies a membership request record.
	 *
	 * @param object $record Request record.
	 * @param string $reason Reason.
	 *
	 * @return true|WP_Error
	 */
	protected function deny_membership_request_record( $record, $reason = '' ) {
		if ( empty( $record ) || ! isset( $record->gid, $record->uid ) ) {
			return new WP_Error(
				'pg_rest_request_not_found',
				__( 'Membership request not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 404 )
			);
		}

		$dbhandler = new PM_DBhandler();

		if ( isset( $record->id ) ) {
			$dbhandler->remove_row( 'REQUESTS', 'id', $record->id );
		}

		$pmemails = new PM_Emails();
		$pmemails->pm_send_group_based_notification( $record->gid, $record->uid, 'on_request_denied' );
		do_action( 'pm_user_membership_request_denied', $record->gid, $record->uid );

		return true;
	}

	/**
	 * Notifies user groups about activation/deactivation events.
	 *
	 * @param int    $user_id User ID.
	 * @param string $action  Either 'activate' or 'deactivate'.
	 *
	 * @return void
	 */
	protected function notify_groups_about_user_status( $user_id, $action ) {
		$pm_request = $this->get_request_helper();
		$groups     = get_user_meta( $user_id, 'pm_group', true );
		$gids       = $pm_request->pg_filter_users_group_ids( $groups );

		if ( empty( $gids ) || ! is_array( $gids ) ) {
			return;
		}

		$pmemails = new PM_Emails();
		$template = 'activate' === $action ? 'on_user_activate' : 'on_user_deactivate';

		foreach ( $gids as $gid ) {
			$pmemails->pm_send_group_based_notification( $gid, $user_id, $template );
		}
	}

	/**
	 * Maps a REQUESTS row to API response.
	 *
	 * @param object $row Request row.
	 *
	 * @return array
	 */
	protected function map_membership_request_row( $row ) {
		if ( empty( $row ) ) {
			return array();
		}

		$row_array         = (array) $row;
		$row_array['id']   = isset( $row->id ) ? (int) $row->id : 0;
		$row_array['gid']  = isset( $row->gid ) ? (int) $row->gid : 0;
		$row_array['uid']  = isset( $row->uid ) ? (int) $row->uid : 0;
		$row_array['options'] = isset( $row->options ) ? maybe_unserialize( $row->options ) : array();

		$user = get_user_by( 'ID', $row_array['uid'] );
		if ( $user ) {
			$row_array['user'] = array(
				'id'           => (int) $user->ID,
				'user_login'   => sanitize_user( $user->user_login, true ),
				'display_name' => sanitize_text_field( $user->display_name ),
				'email'        => sanitize_email( $user->user_email ),
				'avatar'       => esc_url_raw( get_avatar_url( $user->ID ) ),
			);
		} else {
			$row_array['user'] = array();
		}

		return $row_array;
	}

	/**
	 * Notifies leaders/admin about pending membership request.
	 *
	 * @param int $gid Group ID.
	 * @param int $uid Requesting user ID.
	 *
	 * @return void
	 */
	protected function notify_group_leaders_pending_request( $gid, $uid ) {
		$pm_request   = $this->get_request_helper();
		$pmemails     = new PM_Emails();
		$groupleaders = $pm_request->pg_get_group_leaders( $gid );

		if ( empty( $groupleaders ) ) {
			$email_address = get_option( 'admin_email' );
			$leader_info   = get_user_by( 'email', $email_address );
			if ( ! empty( $leader_info ) && isset( $leader_info->ID ) ) {
				$groupleaders[] = $leader_info->ID;
			}
		}

		if ( ! empty( $groupleaders ) ) {
			foreach ( $groupleaders as $leader ) {
				$pmemails->pm_send_group_based_notification_to_group_admin( $gid, $uid, 'on_membership_request', $leader );
			}
		}
	}

	/**
	 * Consolidates request body parameters for JSON/form/integration submissions.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return array
	 */
	protected function get_request_payload( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		if ( ! is_array( $params ) ) {
			$params = array();
		}

		foreach ( array( 'rest_route', 'integration', 'action' ) as $ignored ) {
			if ( isset( $params[ $ignored ] ) ) {
				unset( $params[ $ignored ] );
			}
		}

		return $params;
	}

	/**
	 * Normalizes boolean-like values to 1/0 integers.
	 *
	 * @param mixed $value Incoming value.
	 *
	 * @return int
	 */
	protected function normalize_boolean_flag( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 1 : 0;
		}
		if ( is_numeric( $value ) ) {
			return (int) ( (int) $value !== 0 );
		}
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			if ( in_array( $value, array( '1', 'true', 'yes', 'on' ), true ) ) {
				return 1;
			}
			if ( in_array( $value, array( '0', 'false', 'no', 'off' ), true ) ) {
				return 0;
			}
		}

		return ! empty( $value ) ? 1 : 0;
	}

	/**
	 * Attempts to decode JSON/serialized payloads before storing them.
	 *
	 * @param mixed $value Incoming value.
	 *
	 * @return mixed
	 */
	protected function normalize_complex_payload( $value ) {
		if ( is_object( $value ) ) {
			$value = json_decode( wp_json_encode( $value ), true );
		}

		if ( is_string( $value ) ) {
			$raw = wp_unslash( $value );
			$json = json_decode( $raw, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $json;
			}

			$maybe_unserialized = maybe_unserialize( $raw );
			if ( false !== $maybe_unserialized || $raw === 'b:0;' ) {
				return $maybe_unserialized;
			}
		}

		return $value;
	}

	/**
	 * Recursively sanitizes array structures.
	 *
	 * @param mixed $value Incoming value.
	 *
	 * @return mixed
	 */
	protected function sanitize_recursive_payload( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->sanitize_recursive_payload( $item );
			}
			return $value;
		}

		if ( is_string( $value ) ) {
			return wp_kses_post( $value );
		}

		return $value;
	}

	/**
	 * Validates attachment IDs provided by API payloads.
	 *
	 * @param mixed $attachment_id Attachment identifier.
	 *
	 * @return int
	 */
	protected function sanitize_attachment_id( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id <= 0 ) {
			return 0;
		}

		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return 0;
		}

		return $attachment_id;
	}

	/**
	 * Retrieves a PM_request helper instance.
	 *
	 * @return PM_request
	 */
	protected function get_request_helper() {
		if ( null === $this->request_helper ) {
			$this->request_helper = new PM_request();
		}

		return $this->request_helper;
	}

	/**
	 * Converts a mixed list into array of user IDs.
	 *
	 * @param mixed $list List.
	 *
	 * @return array
	 */
	protected function normalize_id_list( $list ) {
		if ( empty( $list ) ) {
			return array();
		}

		if ( is_string( $list ) ) {
			$list = preg_split( '/[,|\s]+/', $list );
		}

		if ( ! is_array( $list ) ) {
			$list = array( $list );
		}

		$ids = array();
		foreach ( $list as $value ) {
			$value = (int) $value;
			if ( $value > 0 ) {
				$ids[] = $value;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Adds an existing user to group metadata.
	 *
	 * @param int $user_id User ID.
	 * @param int $gid     Group ID.
	 *
	 * @return true|WP_Error|string 'exists' when already in group.
	 */
	protected function add_user_to_group( $user_id, $gid ) {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'pg_rest_user_not_found',
				__( 'User not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 404 )
			);
		}

		$groups = get_user_meta( $user_id, 'pm_group', true );
		$groups = maybe_unserialize( $groups );
		if ( empty( $groups ) ) {
			$groups = array();
		}
		if ( ! is_array( $groups ) ) {
			$groups = array( $groups );
		}

		$groups = array_map( 'absint', $groups );
		if ( in_array( $gid, $groups, true ) ) {
			return 'exists';
		}

		$groups[] = $gid;
		update_user_meta( $user_id, 'pm_group', array_values( array_unique( $groups ) ) );

		$joining_dates = get_user_meta( $user_id, 'pm_joining_date', true );
		if ( ! is_array( $joining_dates ) ) {
			$joining_dates = array();
		}
		$joining_dates[ $gid ] = gmdate( 'Y-m-d' );
		update_user_meta( $user_id, 'pm_joining_date', $joining_dates );

		do_action( 'profile_magic_join_group_additional_process', $gid, $user_id );

		return true;
	}

	/**
	 * Removes a user from the given group.
	 *
	 * @param int $user_id User ID.
	 * @param int $gid     Group ID.
	 *
	 * @return bool|WP_Error
	 */
	protected function remove_user_from_group( $user_id, $gid ) {
	$user_id = absint( $user_id );
	$gid     = absint( $gid );

	// Basic sanity checks.
	if ( $user_id <= 0 || $gid <= 0 ) {
		return new WP_Error(
			'pg_rest_missing_parameters',
			__( 'Both group_id and user_id are required.', 'profilegrid-user-profiles-groups-and-communities' ),
			array( 'status' => 400 )
		);
	}

	$user = get_user_by( 'ID', $user_id );
	if ( ! $user ) {
		return new WP_Error(
			'pg_rest_user_not_found',
			__( 'User not found.', 'profilegrid-user-profiles-groups-and-communities' ),
			array( 'status' => 404 )
		);
	}

	// Load groups meta and normalize it aggressively.
	$groups = get_user_meta( $user_id, 'pm_group', true );
	$groups = maybe_unserialize( $groups );

	// Handle legacy / weird formats: single value, CSV string, etc.
	if ( is_string( $groups ) ) {
		// If it looks like a CSV, split it; otherwise treat as single value.
		if ( strpos( $groups, ',' ) !== false || strpos( $groups, '|' ) !== false ) {
			$groups = preg_split( '/[,|\s]+/', $groups );
		} elseif ( $groups === '' ) {
			$groups = array();
		} else {
			$groups = array( $groups );
		}
	} elseif ( empty( $groups ) ) {
		$groups = array();
	} elseif ( ! is_array( $groups ) ) {
		$groups = array( $groups );
	}

	// Normalize everything to integers and drop garbage.
	$groups = array_values(
		array_unique(
			array_filter(
				array_map(
					static function( $val ) {
						return absint( $val );
					},
					$groups
				)
			)
		)
	);

	// Now do a strict search with fully-normalized data.
	$key = array_search( $gid, $groups, true );
	if ( false === $key ) {
		return new WP_Error(
			'pg_rest_user_not_in_group',
			__( 'User is not a member of this group.', 'profilegrid-user-profiles-groups-and-communities' ),
			array( 'status' => 404 )
		);
	}

	// Remove the group and persist updated list.
	unset( $groups[ $key ] );
	update_user_meta( $user_id, 'pm_group', array_values( $groups ) );

	// Clean up joining dates for this group if present.
	$joining_dates = get_user_meta( $user_id, 'pm_joining_date', true );
	if ( is_array( $joining_dates ) ) {
		// Keys might be stored as string or int; normalize both.
		$normalized_dates = array();
		foreach ( $joining_dates as $k => $v ) {
			$normalized_key = absint( $k );
			if ( $normalized_key > 0 ) {
				$normalized_dates[ $normalized_key ] = $v;
			}
		}

		if ( isset( $normalized_dates[ $gid ] ) ) {
			unset( $normalized_dates[ $gid ] );
		}

		update_user_meta( $user_id, 'pm_joining_date', $normalized_dates );
	}

	do_action( 'profile_magic_user_removed_from_group', $gid, $user_id );

	return true;
}
	/**
	 * Removes group reference from all members (used before hard delete).
	 *
	 * @param int $gid Group ID.
	 *
	 * @return void
	 */
	protected function detach_group_from_members( $gid ) {
		$pm_request = $this->get_request_helper();
		$dbhandler  = new PM_DBhandler();

		$meta_query = $pm_request->pm_get_user_meta_query(
			array(
				'gid' => (string) $gid,
			)
		);

		$user_query = $dbhandler->pm_get_all_users_ajax( '', $meta_query, '', '', '', 'ASC', 'ID' );
		$members    = $user_query->get_results();

		if ( empty( $members ) ) {
			return;
		}

		foreach ( $members as $member ) {
			$this->remove_user_from_group( $member->ID, $gid );
		}
	}
	/**
	 * Maps payload into DB fields for update.
	 *
	 * @param array $params Payload.
	 *
	 * @return array
	 */
	protected function map_group_update_payload( $params ) {
		$data = array();

		if ( array_key_exists( 'group_name', $params ) ) {
			$data['group_name'] = sanitize_text_field( wp_unslash( $params['group_name'] ) );
		}
		if ( array_key_exists( 'associate_role', $params ) ) {
			$data['associate_role'] = sanitize_text_field( wp_unslash( $params['associate_role'] ) );
		}
		if ( array_key_exists( 'group_slug', $params ) ) {
			$data['group_slug'] = sanitize_title( wp_unslash( $params['group_slug'] ) );
		}
		if ( array_key_exists( 'group_desc', $params ) ) {
			$data['group_desc'] = wp_kses_post( $params['group_desc'] );
		}
		if ( array_key_exists( 'group_limit_message', $params ) ) {
			$data['group_limit_message'] = wp_kses_post( $params['group_limit_message'] );
		}
		if ( array_key_exists( 'success_message', $params ) ) {
			$data['success_message'] = wp_kses_post( $params['success_message'] );
		}
		if ( array_key_exists( 'group_icon', $params ) ) {
			$icon_id = absint( $params['group_icon'] );
			if ( 0 === $icon_id ) {
				$data['group_icon'] = 0;
			} else {
				$icon_id = $this->sanitize_attachment_id( $icon_id );
				if ( $icon_id > 0 ) {
					$data['group_icon'] = $icon_id;
				}
			}
		}
		if ( array_key_exists( 'group_limit', $params ) ) {
			$data['group_limit'] = (int) $params['group_limit'];
		}
		if ( array_key_exists( 'leader_username', $params ) ) {
			$data['leader_username'] = sanitize_user( wp_unslash( $params['leader_username'] ) );
		}
		if ( array_key_exists( 'is_group_limit', $params ) ) {
			$data['is_group_limit'] = $this->normalize_boolean_flag( $params['is_group_limit'] );
		}
		if ( array_key_exists( 'is_group_leader', $params ) ) {
			$data['is_group_leader'] = $this->normalize_boolean_flag( $params['is_group_leader'] );
		}
		if ( array_key_exists( 'show_success_message', $params ) ) {
			$data['show_success_message'] = $this->normalize_boolean_flag( $params['show_success_message'] );
		}
		if ( array_key_exists( 'group_leaders', $params ) ) {
			$leaders = $this->normalize_complex_payload( $params['group_leaders'] );
			if ( ! is_array( $leaders ) ) {
				$leaders = array( $leaders );
			}
			$leaders = array_filter(
				array_map(
					function( $leader ) {
						return is_string( $leader ) ? sanitize_text_field( wp_unslash( $leader ) ) : '';
					},
					$leaders
				)
			);
			$data['group_leaders'] = maybe_serialize( array_values( $leaders ) );
		}
		if ( array_key_exists( 'leader_rights', $params ) ) {
			$rights = $this->normalize_complex_payload( $params['leader_rights'] );
			$rights = $this->sanitize_recursive_payload( $rights );
			$data['leader_rights'] = maybe_serialize( $rights );
		}
		if ( array_key_exists( 'group_options', $params ) ) {
			$options = $this->normalize_complex_payload( $params['group_options'] );
			$options = $this->sanitize_recursive_payload( $options );
			$data['group_options'] = maybe_serialize( $options );
		}

		return $data;
	}

	/**
	 * Retrieves a group row or WP_Error when missing.
	 *
	 * @param int $gid Group ID.
	 *
	 * @return object|WP_Error
	 */
	protected function get_group_by_id( $gid ) {
		$dbhandler = new PM_DBhandler();
		$group     = $dbhandler->get_row( 'GROUPS', $gid );

		if ( empty( $group ) ) {
			return new WP_Error(
				'pg_rest_group_not_found',
				__( 'Group not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 404 )
			);
		}

		return $group;
	}

	/**
	 * Retrieves a section row or WP_Error when missing.
	 *
	 * @param int $section_id Section ID.
	 *
	 * @return object|WP_Error
	 */
	protected function get_section_by_id( $section_id ) {
		$dbhandler = new PM_DBhandler();
		$section   = $dbhandler->get_row( 'SECTION', $section_id );

		if ( empty( $section ) ) {
			return new WP_Error(
				'pg_rest_section_not_found',
				__( 'Section not found.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 404 )
			);
		}

		return $section;
	}

	/**
	 * Returns the next ordering value for sections belonging to a group.
	 *
	 * @param int $gid Group ID.
	 *
	 * @return int
	 */
	protected function get_next_section_ordering( $gid ) {
		$dbhandler = new PM_DBhandler();
		$where     = array( 'gid' => $gid );
		$last      = $dbhandler->get_all_result( 'SECTION', 'ordering', $where, 'var', 0, 1, 'ordering', 'DESC' );

		if ( is_numeric( $last ) ) {
			return (int) $last + 1;
		}

		return 1;
	}

	/**
	 * Returns the next ordering value for fields in a section.
	 *
	 * @param int $gid        Group ID.
	 * @param int $section_id Section ID.
	 *
	 * @return int
	 */
	protected function get_next_field_ordering( $gid, $section_id ) {
		$dbhandler = new PM_DBhandler();
		$where     = array(
			'associate_group'   => $gid,
			'associate_section' => $section_id,
		);

		$last = $dbhandler->get_all_result( 'FIELDS', 'ordering', $where, 'var', 0, 1, 'ordering', 'DESC' );

		if ( is_numeric( $last ) ) {
			return (int) $last + 1;
		}

		return 1;
	}

	/**
	 * Normalizes a section object for API responses.
	 *
	 * @param object|array $section Section data.
	 *
	 * @return array
	 */
	protected function prepare_section_response( $section ) {
		if ( empty( $section ) ) {
			return array();
		}

		$data = (array) $section;

		if ( isset( $data['section_options'] ) && '' !== $data['section_options'] ) {
			$options                  = maybe_unserialize( $data['section_options'] );
			$data['section_options']  = $options;
			if ( ! isset( $data['section_desc'] ) && is_array( $options ) && isset( $options['section_desc'] ) ) {
				$data['section_desc'] = $options['section_desc'];
			}
		}

		return $data;
	}

	/**
	 * Normalizes a field object for API responses.
	 *
	 * @param object|array $field Field data.
	 *
	 * @return array
	 */
	protected function prepare_field_response( $field ) {
		if ( empty( $field ) ) {
			return array();
		}

		$mapped = $this->map_fields( array( $field ) );
		if ( ! empty( $mapped ) && isset( $mapped[0] ) ) {
			return $mapped[0];
		}

		return (array) $field;
	}

	/**
	 * Determines if SECTION table has a description column.
	 *
	 * @return bool
	 */
	protected function section_supports_description() {
		if ( null !== $this->section_desc_column_present ) {
			return $this->section_desc_column_present;
		}

		global $wpdb;

		$pm_activator = new Profile_Magic_Activator();
		$table        = $pm_activator->get_db_table_name( 'SECTION' );
		$table        = esc_sql( $table );

		$column = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `$table` LIKE %s",
				'section_desc'
			)
		);

		$this->section_desc_column_present = ! empty( $column );

		return $this->section_desc_column_present;
	}

	/**
	 * Returns scoped endpoint permission actions.
	 *
	 * @return array
	 */
	protected function get_permission_actions() {
		return array(
			'get_all_groups',
			'get_single_group',
			'get_users',
			'get_user_details',
			'get_group_members',
			'get_group_section',
			'get_membership_requests',
			'create_group',
			'update_user_profile',
			'activate_user_account',
			'deactivate_user_account',
			'activate_all_user',
			'deactivate_all_user',
			'assign_group',
			'remove_from_group',
			'add_group_members',
			'remove_group_member',
			'update_group',
			'delete_group',
			'create_group_section',
			'update_group_section',
			'delete_section',
			'create_group_field',
			'create_membership_request',
			'approve_membership_request',
			'deny_membership_request',
			'bulk_approve_all_membership_requests',
			'bulk_deny_all_membership_requests',
		);
	}

	/**
	 * Returns risk labels for permission actions.
	 *
	 * @return array
	 */
	protected function get_permission_risk_map() {
		return array(
			'get_all_groups'                      => 'Low',
			'get_single_group'                   => 'Low',
			'get_group_section'                    => 'Low',
			'create_membership_request'            => 'Medium',
			'get_users'                            => 'Medium',
			'get_user_details'                     => 'Medium',
			'get_group_members'                    => 'Medium',
			'get_membership_requests'              => 'Medium',
			'create_group'                         => 'High',
			'update_user_profile'                  => 'High',
			'activate_user_account'                => 'High',
			'deactivate_user_account'              => 'High',
			'activate_all_user'                    => 'High',
			'deactivate_all_user'                  => 'High',
			'assign_group'                         => 'High',
			'remove_from_group'                    => 'High',
			'add_group_members'                    => 'High',
			'remove_group_member'                  => 'High',
			'update_group'                         => 'High',
			'delete_group'                         => 'High',
			'create_group_section'                 => 'High',
			'update_group_section'                 => 'High',
			'delete_section'                       => 'High',
			'create_group_field'                   => 'High',
			'approve_membership_request'           => 'High',
			'deny_membership_request'              => 'High',
			'bulk_approve_all_membership_requests' => 'High',
			'bulk_deny_all_membership_requests'    => 'High',
		);
	}

	/**
	 * Default endpoint permission map.
	 *
	 * @return array
	 */
	protected function get_default_endpoint_permissions() {
		$defaults = array();
		foreach ( $this->get_permission_actions() as $action ) {
			$defaults[ $action ] = array( 'administrator', 'editor', 'author' );
		}

		return $defaults;
	}

	/**
	 * Collect available roles for endpoint permission settings.
	 *
	 * @return array
	 */
	protected function get_settings_available_roles() {
		$available_roles = array();
		$wp_roles        = wp_roles();

		if ( ! empty( $wp_roles->roles ) && is_array( $wp_roles->roles ) ) {
			foreach ( $wp_roles->roles as $role_slug => $role_data ) {
				$available_roles[ $role_slug ] = isset( $role_data['name'] ) ? $role_data['name'] : ucfirst( str_replace( '_', ' ', $role_slug ) );
			}
		}

		$uid = get_current_user_id();
		if ( $uid > 0 && $this->is_profilegrid_group_leader_user( $uid ) ) {
				$available_roles['pm_group_leader']  = esc_html__( 'ProfileGrid Group Leader', 'profilegrid-user-profiles-groups-and-communities' );
				$available_roles['pm_group_manager'] = esc_html__( 'ProfileGrid Group Manager', 'profilegrid-user-profiles-groups-and-communities' );
		}

		return (array) apply_filters( 'profilegrid_api_settings_available_roles', $available_roles );
	}

	/**
	 * Sanitizes endpoint permissions input.
	 *
	 * @param mixed $raw_permissions Raw permissions.
	 * @param array $available_roles Available roles list.
	 *
	 * @return array
	 */
	protected function sanitize_endpoint_permissions_input( $raw_permissions, $available_roles ) {
		$raw_permissions = is_array( $raw_permissions ) ? $raw_permissions : array();
		$allowed_actions = $this->get_permission_actions();
		$role_slugs      = array_keys( (array) $available_roles );
		$sanitized       = array();

		foreach ( $allowed_actions as $action ) {
			$roles = isset( $raw_permissions[ $action ] ) ? (array) $raw_permissions[ $action ] : array();
			$roles = array_map( 'sanitize_key', $roles );
			$roles = array_values( array_intersect( array_unique( $roles ), $role_slugs ) );
			$sanitized[ $action ] = $roles;
		}

		return $sanitized;
	}

	/**
	 * Returns saved endpoint permissions map with legacy default upgrade.
	 *
	 * @return array
	 */
	protected function get_saved_endpoint_permissions_map() {
		$defaults = $this->get_default_endpoint_permissions();
		$saved    = get_option( $this->endpoint_permissions_option_name, null );

		if ( ! is_array( $saved ) ) {
			return $defaults;
		}

		/*
		 * Legacy builds used editor+author defaults.
		 * Upgrade that exact legacy map to include administrator by default.
		 */
		if ( $this->is_legacy_endpoint_permissions_default_map( $saved ) ) {
			update_option( $this->endpoint_permissions_option_name, $defaults );
			return $defaults;
		}

		return $saved;
	}

	/**
	 * Checks if saved permissions still match the legacy default map.
	 *
	 * @param array $permissions Saved endpoint permissions map.
	 *
	 * @return bool
	 */
	protected function is_legacy_endpoint_permissions_default_map( $permissions ) {
		if ( ! is_array( $permissions ) ) {
			return false;
		}

		$legacy_roles = array( 'author', 'editor' );
		$actions      = $this->get_permission_actions();
		$has_match    = false;

		foreach ( $actions as $action ) {
			if ( ! array_key_exists( $action, $permissions ) ) {
				continue;
			}

			$action_roles = array_map( 'sanitize_key', (array) $permissions[ $action ] );
			$action_roles = array_values( array_unique( $action_roles ) );
			sort( $action_roles );

			if ( $action_roles !== $legacy_roles ) {
				return false;
			}

			$has_match = true;
		}

		return $has_match;
	}

	/**
	 * Returns saved endpoint permission map.
	 *
	 * @return array
	 */
	protected function get_endpoint_permissions() {
		$defaults        = $this->get_default_endpoint_permissions();
		$available_roles = $this->get_settings_available_roles();
		$saved_raw       = $this->get_saved_endpoint_permissions_map();
		$saved_keys      = array_keys( $saved_raw );
		$saved           = $this->sanitize_endpoint_permissions_input( $saved_raw, $available_roles );
		$role_slugs      = array_keys( (array) $available_roles );

		// Keep defaults only for truly missing actions.
		foreach ( $defaults as $action => $roles ) {
			$default_roles = array_values( array_intersect( array_map( 'sanitize_key', (array) $roles ), $role_slugs ) );
			if ( ! in_array( $action, $saved_keys, true ) ) {
				$saved[ $action ] = $default_roles;
			}
		}

		return $saved;
	}

	/**
	 * Returns API reference rows shown in settings.
	 *
	 * @return array
	 */
	protected function get_api_reference_rows() {
		$base = rest_url( $this->namespace );

		return array(
			array( 'group' => 'Auth', 'method' => 'POST', 'action' => 'token', 'url' => $base . '/token' ),
			array( 'group' => 'Users', 'method' => 'GET', 'action' => 'get_users', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'get_users' ), $base ) ),
			array( 'group' => 'Users', 'method' => 'GET', 'action' => 'get_user_details', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'get_user_details', 'user_id' => 123 ), $base ) ),
			array( 'group' => 'Users', 'method' => 'POST', 'action' => 'update_user_profile', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'update_user_profile' ), $base ) ),
			array( 'group' => 'Users', 'method' => 'POST', 'action' => 'activate_user_account', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'activate_user_account' ), $base ) ),
			array( 'group' => 'Users', 'method' => 'POST', 'action' => 'deactivate_user_account', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'deactivate_user_account' ), $base ) ),
			array( 'group' => 'Users', 'method' => 'POST', 'action' => 'activate_all_user', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'activate_all_user' ), $base ) ),
			array( 'group' => 'Users', 'method' => 'POST', 'action' => 'deactivate_all_user', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'deactivate_all_user' ), $base ) ),
			array( 'group' => 'Groups', 'method' => 'GET', 'action' => 'get_all_groups', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'get_all_groups' ), $base ) ),
			array( 'group' => 'Groups', 'method' => 'GET', 'action' => 'get_single_group', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'get_single_group', 'group_id' => 123 ), $base ) ),
			array( 'group' => 'Groups', 'method' => 'POST', 'action' => 'create_group', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'create_group' ), $base ) ),
			array( 'group' => 'Groups', 'method' => 'POST', 'action' => 'update_group', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'update_group', 'group_id' => 123 ), $base ) ),
			array( 'group' => 'Groups', 'method' => 'POST', 'action' => 'delete_group', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'delete_group', 'group_id' => 123 ), $base ) ),
			array( 'group' => 'Group Members', 'method' => 'GET', 'action' => 'get_group_members', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'get_group_members', 'group_id' => 123 ), $base ) ),
			array( 'group' => 'Group Members', 'method' => 'POST', 'action' => 'add_group_members', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'add_group_members' ), $base ) ),
			array( 'group' => 'Group Members', 'method' => 'POST', 'action' => 'remove_group_member', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'remove_group_member' ), $base ) ),
			array( 'group' => 'Group Members', 'method' => 'POST', 'action' => 'assign_group', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'assign_group' ), $base ) ),
			array( 'group' => 'Group Members', 'method' => 'POST', 'action' => 'remove_from_group', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'remove_from_group' ), $base ) ),
			array( 'group' => 'Group Sections', 'method' => 'GET', 'action' => 'get_group_section', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'get_group_section', 'group_id' => 123 ), $base ) ),
			array( 'group' => 'Group Sections', 'method' => 'POST', 'action' => 'create_group_section', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'create_group_section' ), $base ) ),
			array( 'group' => 'Group Sections', 'method' => 'POST', 'action' => 'update_group_section', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'update_group_section' ), $base ) ),
			array( 'group' => 'Group Sections', 'method' => 'POST', 'action' => 'delete_section', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'delete_section' ), $base ) ),
			array( 'group' => 'Group Fields', 'method' => 'POST', 'action' => 'create_group_field', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'create_group_field' ), $base ) ),
			array( 'group' => 'Membership', 'method' => 'GET', 'action' => 'get_membership_requests', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'get_membership_requests' ), $base ) ),
			array( 'group' => 'Membership', 'method' => 'POST', 'action' => 'create_membership_request', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'create_membership_request' ), $base ) ),
			array( 'group' => 'Membership', 'method' => 'POST', 'action' => 'approve_membership_request', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'approve_membership_request', 'request_id' => 456 ), $base ) ),
			array( 'group' => 'Membership', 'method' => 'POST', 'action' => 'deny_membership_request', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'deny_membership_request', 'request_id' => 456 ), $base ) ),
			array( 'group' => 'Membership', 'method' => 'POST', 'action' => 'bulk_approve_all_membership_requests', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'bulk_approve_all_membership_requests' ), $base ) ),
			array( 'group' => 'Membership', 'method' => 'POST', 'action' => 'bulk_deny_all_membership_requests', 'url' => add_query_arg( array( 'integration' => 1, 'action' => 'bulk_deny_all_membership_requests' ), $base ) ),
		);
	}

	/**
	 * Returns permissions map sanitized for runtime checks.
	 *
	 * @return array
	 */
	protected function get_endpoint_permissions_for_runtime() {
		$defaults = $this->get_default_endpoint_permissions();
		$saved    = $this->get_saved_endpoint_permissions_map();
		$keys     = array_keys( $saved );
		$pool     = $this->get_runtime_role_pool();
		$map      = array();

		foreach ( $this->get_permission_actions() as $action ) {
			$raw_roles = isset( $saved[ $action ] ) ? (array) $saved[ $action ] : array();
			$raw_roles = array_map( 'sanitize_key', $raw_roles );
			$roles     = array_values( array_intersect( array_unique( $raw_roles ), $pool ) );

			if ( ! in_array( $action, $keys, true ) && isset( $defaults[ $action ] ) ) {
				$fallback = array_map( 'sanitize_key', (array) $defaults[ $action ] );
				$roles    = array_values( array_intersect( array_unique( $fallback ), $pool ) );
			}

			$map[ $action ] = $roles;
		}

		return $map;
	}

	/**
	 * Returns normalized role pool used by runtime authorization.
	 *
	 * @return array
	 */
	protected function get_runtime_role_pool() {
		$pool     = array( 'administrator', 'pm_group_leader', 'pm_group_manager' );
		$wp_roles = wp_roles();

		if ( ! empty( $wp_roles->roles ) && is_array( $wp_roles->roles ) ) {
			$pool = array_merge( $pool, array_keys( $wp_roles->roles ) );
		}

		return array_values( array_unique( array_map( 'sanitize_key', $pool ) ) );
	}

	/**
	 * Returns authorization role context for a user.
	 *
	 * @param WP_User $user User object.
	 *
	 * @return array
	 */
	protected function get_user_authorization_roles( $user ) {
		if ( ! $user instanceof WP_User ) {
			return array();
		}

		$roles = array_map( 'sanitize_key', (array) $user->roles );

		// Multisite: network super admins may not have per-site administrator role assigned.
		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			$roles[] = 'administrator';
		}

		if ( $this->is_profilegrid_group_leader_user( (int) $user->ID ) ) {
			$roles[] = 'pm_group_leader';
			$roles[] = 'pm_group_manager';
		}

		return array_values( array_unique( $roles ) );
	}

	/**
	 * Checks whether a user is a ProfileGrid group leader.
	 *
	 * @param int $uid User ID.
	 *
	 * @return bool
	 */
	protected function is_profilegrid_group_leader_user( $uid ) {
		$uid = (int) $uid;
		if ( $uid <= 0 || ! class_exists( 'PM_request' ) ) {
			return false;
		}

		$pm_request = new PM_request();
		if ( method_exists( $pm_request, 'profile_magic_check_is_group_leader' ) && $pm_request->profile_magic_check_is_group_leader( $uid ) ) {
			return true;
		}

		$gids = get_user_meta( $uid, 'pm_group', true );
		$gids = is_array( $gids ) ? $gids : ( empty( $gids ) ? array() : array( $gids ) );

		if ( method_exists( $pm_request, 'pg_check_in_single_group_is_user_group_leader' ) ) {
			foreach ( $gids as $gid ) {
				if ( $pm_request->pg_check_in_single_group_is_user_group_leader( $uid, (int) $gid ) ) {
					return true;
				}
			}
		}

		if ( method_exists( $pm_request, 'pg_get_group_leaders' ) ) {
			foreach ( $gids as $gid ) {
				$leaders = (array) $pm_request->pg_get_group_leaders( (int) $gid );
				if ( in_array( $uid, array_map( 'intval', $leaders ), true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Centralized endpoint authorization based on saved permissions map.
	 *
	 * @param string          $action  Integration action.
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return true|WP_Error
	 */
	protected function authorize_integration_action( $action, WP_REST_Request $request ) {
		$action = sanitize_key( $action );
		if ( empty( $action ) ) {
			return new WP_Error(
				'pg_rest_unknown_action',
				esc_html__( 'Unknown or missing action parameter.', 'profilegrid-user-profiles-groups-and-communities' ),
				array( 'status' => 400 )
			);
		}

		$scope = $this->get_permission_actions();
		if ( ! in_array( $action, $scope, true ) ) {
			return true;
		}

		if ( ! $this->current_user instanceof WP_User ) {
			return $this->forbidden_action_error( $action );
		}

		$user_roles = $this->get_user_authorization_roles( $this->current_user );

		$map           = $this->get_endpoint_permissions_for_runtime();
		$allowed_roles = isset( $map[ $action ] ) ? (array) $map[ $action ] : array();
		if ( empty( $allowed_roles ) ) {
			return $this->forbidden_action_error( $action );
		}

		if ( empty( array_intersect( $allowed_roles, $user_roles ) ) ) {
			return $this->forbidden_action_error( $action );
		}

		return true;
	}

	/**
	 * Resolves permission-scoped action from integration and direct REST requests.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return string
	 */
	protected function resolve_permission_action_from_request( WP_REST_Request $request ) {
		$action = sanitize_key( (string) $request->get_param( 'action' ) );
		if ( ! empty( $action ) ) {
			return $action;
		}

		$route            = (string) $request->get_route();
		$method           = strtoupper( (string) $request->get_method() );
		$namespace_prefix = '/' . trim( $this->namespace, '/' );

		if ( strpos( $route, $namespace_prefix ) === 0 ) {
			$route = substr( $route, strlen( $namespace_prefix ) );
		}

		if ( '' === $route ) {
			$route = '/';
		}

		if ( '/groups' === $route ) {
			if ( 'GET' === $method ) {
				return 'get_all_groups';
			}

			if ( 'POST' === $method ) {
				return 'create_group';
			}
		}

		if ( preg_match( '#^/groups/\d+$#', $route ) && 'GET' === $method ) {
			return 'get_single_group';
		}

		return '';
	}

	/**
	 * Builds a structured forbidden error for action-level authorization failures.
	 *
	 * @param string $action Integration action.
	 *
	 * @return WP_Error
	 */
	protected function forbidden_action_error( $action ) {
		$action = sanitize_key( $action );

		return new WP_Error(
			'pg_rest_forbidden_action',
			sprintf(
				/* translators: %s: integration action name. */
				esc_html__( 'Action "%s" is not permitted for your role.', 'profilegrid-user-profiles-groups-and-communities' ),
				$action
			),
			array(
				'status' => rest_authorization_required_code(),
				'action' => $action,
				'hint'   => esc_html__( 'Configure Endpoint Permissions in ProfileGrid > APIs / Webhooks.', 'profilegrid-user-profiles-groups-and-communities' ),
			)
		);
	}


	/**
	 * Checks whether APIs are enabled and returns error when disabled.
	 *
	 * @return true|WP_Error
	 */
	protected function assert_api_enabled() {
		if ( $this->api_enabled() ) {
			return true;
		}

		return new WP_Error(
			'pg_rest_disabled',
			__( 'ProfileGrid APIs are currently disabled.', 'profilegrid-user-profiles-groups-and-communities' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Determines API toggle state.
	 *
	 * @return bool
	 */
	protected function api_enabled() {
		return (bool) get_option( $this->option_name, 0 );
	}

	/**
	 * Determines if a user has permission to hit API endpoints.
	 *
	 * @param WP_User $user User object.
	 *
	 * @return bool
	 */
	protected function user_has_access( $user ) {
		if ( ! $user instanceof WP_User ) {
			return false;
		}

		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			return true;
		}

		$endpoint_permissions = $this->get_endpoint_permissions_for_runtime();
		$dynamic_roles        = array();
		foreach ( $endpoint_permissions as $action_roles ) {
			$dynamic_roles = array_merge( $dynamic_roles, (array) $action_roles );
		}
		$dynamic_roles = array_values( array_unique( array_map( 'sanitize_key', $dynamic_roles ) ) );

		$allowed_roles = array_values( array_unique( array_merge( array( 'administrator' ), (array) $this->allowed_roles, $dynamic_roles ) ) );
		$allowed_roles = apply_filters( 'profilegrid_api_allowed_roles', $allowed_roles, $user );
		$user_roles    = (array) $user->roles;

		return ! empty( array_intersect( $allowed_roles, $user_roles ) );
	}
}
