<?php

/**
 * Plugin Name: Big Sky
 * Plugin URI: https://automattic.com/
 * Update URI: https://github.com/Automattic/big-sky-plugin
 * Description: The Big Sky AI Site Builder.
 * Version: 4.2.1
 * Author: Automattic, Inc.
 * Author URI: https://automattic.com/
 * Text Domain: big-sky
 * Domain Path: /languages
 * License: GPL2
 */

require_once __DIR__ . '/lib/wpcom-rest-api-v2-endpoints.php';

if ( ! class_exists( 'Big_Sky' ) ) {

	class Big_Sky {
		public const ENABLE_OPTION_NAME = 'big_sky_enable';
		public const SUPPORTED_LOCALES  = [
			// Keys are WP.org and WP.com locale slugs.
			// Values are the English name of the language.
			'ar'    => 'Arabic',
			'de'    => 'German',
			'de_DE' => 'German',
			'es'    => 'Spanish (Spain)',
			'es_ES' => 'Spanish (Spain)',
			'fr'    => 'French (France)',
			'fr_FR' => 'French (France)',
			'he'    => 'Hebrew',
			'he_IL' => 'Hebrew',
			'id'    => 'Indonesian',
			'id_ID' => 'Indonesian',
			'it'    => 'Italian',
			'it_IT' => 'Italian',
			'ja'    => 'Japanese',
			'ko'    => 'Korean',
			'ko_KR' => 'Korean',
			'nl'    => 'Dutch',
			'nl_NL' => 'Dutch',
			'pt-br' => 'Portuguese (Brazil)',
			'pt_BR' => 'Portuguese (Brazil)',
			'ru'    => 'Russian',
			'ru_RU' => 'Russian',
			'sv'    => 'Swedish',
			'sv_SE' => 'Swedish',
			'tr'    => 'Turkish',
			'tr_TR' => 'Turkish',
			'zh-cn' => 'Chinese (China)',
			'zh_CN' => 'Chinese (China)',
			'zh-tw' => 'Chinese (Taiwan)',
			'zh_TW' => 'Chinese (Taiwan)',
		];
		public static $enabled          = '1';

		public static function init() {
			self::$enabled = get_option( self::ENABLE_OPTION_NAME, '1' );

			add_action( 'init', array( 'Big_Sky', 'load_textdomain' ) );

			add_action( 'enqueue_block_editor_assets', array( 'Big_Sky', 'enqueue_assets' ) );
			add_action( 'admin_init', array( 'Big_Sky', 'register_big_sky_enable' ) );
			add_action( 'admin_init', array( 'Big_Sky', 'register_big_sky_metadata_setting' ) );
			add_action( 'rest_api_init', array( 'Big_Sky', 'register_big_sky_metadata_setting' ) );
			add_action( 'rest_api_init', array( 'Big_Sky', 'register_big_sky_rest_fields' ) );
			add_action( 'delete_post', array( 'Big_Sky', 'handle_post_deletion' ) );
			add_action( 'wp_trash_post', array( 'Big_Sky', 'handle_post_deletion' ) );

			if ( self::is_dev_mode() ) {
				add_action( 'admin_notices', array( 'Big_Sky', 'admin_notices' ) );

				add_filter( 'jetpack_sync_error_idc_validation', '__return_false' );
			}

			// use the filter jetpack_options_whitelist to allow the big_sky_site_metadata option to be synced
			add_filter(
				'jetpack_options_whitelist',
				function ( $whitelist ) {
					$whitelist[] = 'big_sky_site_metadata';
					return $whitelist;
				}
			);

			// When an AI generated logo is edited, mark the new image as an AI generated logo as well.
			add_filter( 'wp_edited_image_metadata', array( 'Big_Sky', 'set_big_sky_generated_logo_for_edited_images' ), 10, 3 );

			$logger = self::get_logger();

			// Register the endpoints.
			new WPCOM_REST_API_V2_Endpoint_Big_Sky_Plugin( $logger );

			self::init_site_health();
		}

		/**
		 * Enables "Development" features that should be accessible only for admins.
		 */
		public static function is_dev_mode() {
			// Known local environments.
			$domain = parse_url( get_site_url(), PHP_URL_HOST );
			if (
				$domain === 'localhost' ||
				'.jurassic.tube' === stristr( $domain, '.jurassic.tube' )
			) {
				return true;
			}

			// A8C development.
			if ( self::is_wpcom() && is_proxied_automattician() ) {
				return true;
			}
			if ( defined( 'AT_PROXIED_REQUEST' ) && AT_PROXIED_REQUEST && defined( 'ATOMIC_CLIENT_ID' ) ) {
				switch ( ATOMIC_CLIENT_ID ) {
					case 1:
					case 2:
					case 32:
						return true;
						break;
				}
			}

			return false;
		}

		private static function get_logger() {
			if ( self::is_wpcom() ) {
				require_once __DIR__ . '/lib/wpcom-big-sky-logger.php';
				return new WPCOM_Big_Sky_Logger();
			} else {
				require_once __DIR__ . '/lib/jetpack-big-sky-logger.php';
				return new Jetpack_Big_Sky_Logger();
			}
		}

		public static function is_wpcom() {
			return defined( 'IS_WPCOM' ) && IS_WPCOM;
		}

		public static function load_textdomain() {
			load_plugin_textdomain( 'big-sky', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		public static function enable_setting_html() {
			?>
			<label for="<?php echo esc_attr( self::ENABLE_OPTION_NAME ); ?>">
				<input name="<?php echo esc_attr( self::ENABLE_OPTION_NAME ); ?>" id="<?php echo esc_attr( self::ENABLE_OPTION_NAME ); ?>" <?php echo checked( self::$enabled, true, false ); ?> type="checkbox" value="1" />
				<?php esc_html_e( 'Enable AI-powered site building experience', 'big-sky' ); ?>
			</label>
			<?php
		}

		public static function register_big_sky_enable() {
			add_settings_field(
				self::ENABLE_OPTION_NAME,
				'<span>' . __( 'AI Features', 'big-sky' ) . '</span>',
				array( 'Big_Sky', 'enable_setting_html' ),
				'writing'
			);
			register_setting(
				'writing',
				self::ENABLE_OPTION_NAME,
				'intval'
			);
		}

		public static function register_big_sky_metadata_setting() {
			register_post_meta(
				'attachment',
				'big_sky_generated_logo',
				[
					'type'          => 'integer',
					'description'   => 'If this attachment is a logo generated by Big Sky, the ID of the base/uncolorized generated logo or -1 if the attachment is the base/uncolorized generated logo. 0 if this attachment is not a logo generated by Big Sky.',
					'single'        => true,
					'default'       => 0,
					'show_in_rest'  => current_user_can( 'edit_theme_options' ),
					'auth_callback' => fn () => current_user_can( 'edit_theme_options' ),
				]
			);

			$args = array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => 'Settings for the Big Sky assembler',
				'show_in_rest'      => [
					'schema' => [
						'title' => __( 'Site Design Settings' ),
					],
				],
			);
			register_setting( 'options', 'big_sky_site_metadata', $args );
			register_post_meta(
				'page',
				'big_sky_page_metadata',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => 'Page level settings for the Big Sky assembler',
				)
			);
			if ( self::is_dev_mode() ) {
				register_setting(
					'options',
					'big_sky_last_site_design_payload',
					[
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => 'Last site design payload for debugging.',
						'show_in_rest'      => true,
					]
				);
			}
		}

		public static function register_big_sky_rest_fields() {
			add_filter( 'rest_request_after_callbacks', [ 'Big_Sky', 'add_big_sky_meta_to_global_styles' ], 10, 3 );
		}

		public static function add_big_sky_meta_to_global_styles( $response, $handler, WP_REST_Request $request ) {
			if ( ! $response instanceof WP_REST_Response ) {
				return $response;
			}

			if ( ! class_exists( 'WP_Theme_JSON_Resolver_Gutenberg' ) ) {
				return $response;
			}

			$theme_slug = get_option( 'stylesheet' );

			if ( ! str_contains( $request->get_route(), sprintf( 'global-styles/themes/%s/variations', $theme_slug ) ) ) {
				return $response;
			}

			if ( $response->is_error() ) {
				return $response;
			}

			$data = $response->get_data();

			// This is ugly.
			$raw_files = new ReflectionProperty( 'WP_Theme_JSON_Resolver_Gutenberg', 'theme_json_file_cache' );
			$raw_data  = [];
			foreach ( $raw_files->getValue() as $file => $file_data ) {
				$title              = $file_data['title'] ?? basename( $file, '.json' );
				$raw_data[ $title ] = $file_data;
			}

			foreach ( $data as &$variation ) {
				$title                       = $variation['title'];
				$variation['x-big-sky-meta'] = [
					'keywords' => $raw_data[ $title ]['keywords'] ?? [],
				];
			}

			$response->set_data( $data );

			return $response;
		}

		public static function init_site_health() {
			add_filter(
				'site_status_tests',
				function ( $tests ) {
					$tests['direct']['big_sky_checks'] = array(
						'name'  => __( 'Big Sky checks', 'big-sky' ),
						'label' => __( 'Big Sky checks', 'big-sky' ),
						'group' => 'direct',
						'test'  => array( __CLASS__, 'do_checks' ),
					);
					return $tests;
				}
			);
		}

		/**
		 * Do site-health page checks
		 *
		 * @access public
		 * @return array
		 */
		public static function do_checks() {
			$failures    = [];
			$passes      = [];
			$critical    = false;
			$is_e2e_test = ! empty( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] === '8889'; // e2e run on 8889 port, less checking for that.
			/**
			 * Default, no issues found
			 */
			$result = array(
				'label'       => __( 'Big Sky Checks', 'big-sky' ),
				'status'      => 'good',
				'badge'       => array(
					'label' => __( 'Big Sky', 'big-sky' ),
					'color' => 'blue',
				),
				'description' => sprintf(
					'<p>%s</p>',
					__( 'Big Sky did not find any known issues with your site.', 'big-sky' )
				),
				'actions'     => '',
				'test'        => 'big_sky_checks',
			);

			// check that the assembler theme is installed and active
			if ( 'Assembler' !== wp_get_theme()->get( 'Name' ) ) {
				$critical   = true;
				$failures[] = sprintf(
					'<p>%s</p>',
					__( 'The Assembler theme is not active. Big Sky requires the Assembler theme to be active.', 'big-sky' )
				);
			} else {
				$passes[] = sprintf(
					'<p>%s</p>',
					__( 'The Assembler theme is active.', 'big-sky' )
				);
			}

			// check that Jetpack is installed and active
			if ( ! self::is_wpcom() && ! class_exists( 'Jetpack' ) ) {
				$critical   = true;
				$failures[] = sprintf(
					'<p>%s</p>',
					__( 'Jetpack is not installed. Big Sky requires Jetpack to be installed and active.', 'big-sky' )
				);
			} else {
				$passes[] = sprintf(
					'<p>%s</p>',
					__( 'Jetpack is installed.', 'big-sky' )
				);
			}

			// check that Jetpack is connected
			if ( ! self::is_wpcom() && class_exists( 'Jetpack' ) && ! Jetpack::connection()->is_connected() ) {
				$critical   = $is_e2e_test ? false : true; // not critical for e2e tests
				$failures[] = sprintf(
					'<p>%s</p>',
					__( 'Jetpack is not connected. Big Sky requires Jetpack to be connected.', 'big-sky' )
				);
			} else {
				$passes[] = sprintf(
					'<p>%s</p>',
					__( 'Jetpack is connected.', 'big-sky' )
				);
			}

			// do the same for Jetpack::connection()->has_connected_admin()
			if ( ! self::is_wpcom() && class_exists( 'Jetpack' ) && ! Jetpack::connection()->has_connected_admin() ) {
				$critical   = $is_e2e_test ? false : true; // not critical for e2e tests
				$failures[] = sprintf(
					'<p>%s</p>',
					__( 'Jetpack does not have a connected admin. Big Sky requires Jetpack to be connected to an admin.', 'big-sky' )
				);
			} else {
				$passes[] = sprintf(
					'<p>%s</p>',
					__( 'Jetpack is connected to an admin.', 'big-sky' )
				);
			}

			// check that Jetpack_Options::get_option('mapbox_api_key') is set
			if ( ! self::is_wpcom() && class_exists( 'Jetpack_Mapbox_Helper' ) && ! Jetpack_Mapbox_Helper::get_access_token() ) {
				$failures[] = sprintf(
					'<p>%s</p>',
					__( 'Mapbox API key is not set. Big Sky requires a Mapbox API key to be set.', 'big-sky' )
				);
			} else {
				$passes[] = sprintf(
					'<p>%s</p>',
					__( 'Mapbox API key is set.', 'big-sky' )
				);
			}

			// check that page on front is a static page
			if ( 'page' !== get_option( 'show_on_front' ) ) {
				$critical              = true;
				$reading_settings_link = sprintf(
					'<a href="%s">%s</a>',
					admin_url( 'options-reading.php' ),
					__( 'Change it.', 'big-sky' )
				);
				$failures[]            = sprintf(
					'<p>%s %s</p>',
					__( 'The home page is not set to a static page. Big Sky requires a static page to be set as the home page.', 'big-sky' ),
					$reading_settings_link,
				);
			} else {
				$passes[] = sprintf(
					'<p>%s</p>',
					__( 'The home page is set to a static page.', 'big-sky' )
				);
			}

			/**
			 * If issues found.
			 */
			if ( count( $failures ) > 0 ) {
				$result['status'] = $critical ? 'critical' : 'red';
				/* translators: $d is the number of performance issues found. */
				$result['label']       = sprintf( _n( 'Big Sky is affected by %d issue', 'Big Sky is affected by %d issues', count( $failures ), 'big-sky' ), count( $failures ) );
				$result['description'] = __( 'Big Sky detected the following issues with your site:', 'big-sky' );

				foreach ( $failures as $issue ) {
					$result['description'] .= '<p>';
					$result['description'] .= "<span class='dashicons dashicons-warning' style='color: crimson;'></span> &nbsp;";
					$result['description'] .= wp_kses( $issue, array( 'a' => array( 'href' => array() ) ) ); // Only allow a href HTML tags.
					$result['description'] .= '</p>';
				}
			}

			/**
			 * Add passes
			 */
			if ( count( $passes ) > 0 ) {
				$result['description'] .= __( 'These checks passed:', 'big-sky' );

				foreach ( $passes as $pass ) {
					$result['description'] .= '<p>';
					$result['description'] .= "<span class='dashicons dashicons-yes' style='color: green;'></span> &nbsp;";
					$result['description'] .= wp_kses( $pass, array( 'a' => array( 'href' => array() ) ) ); // Only allow a href HTML tags.
					$result['description'] .= '</p>';
				}
			}

			return $result;
		}

		public static function enqueue_assets() {
			if ( ! self::$enabled ) {
				return;
			}

			$current_screen = get_current_screen();
			if ( ! ( $current_screen instanceof \WP_Screen ) || 'site-editor' !== $current_screen->base ) {
				return;
			}

			$checks = self::do_checks();
			if ( 'critical' === $checks['status'] ) {
				return;
			}

			wp_enqueue_script(
				'big-sky-assembler',
				plugins_url( 'build/index.js', __FILE__ ),
				[ 'wp-edit-site' ],
				filemtime( plugin_dir_path( __FILE__ ) . 'build/index.js' )
			);

			wp_set_script_translations(
				'big-sky-assembler',
				'big-sky',
				plugin_dir_path( __FILE__ ) . 'languages'
			);

			wp_enqueue_style(
				'big-sky-assembler',
				plugins_url( 'build/style-index.css', __FILE__ ),
				[ 'wp-edit-site' ],
				filemtime( plugin_dir_path( __FILE__ ) . 'build/style-index.css' )
			);

			$user_locale = get_user_locale();
			if ( isset( static::SUPPORTED_LOCALES[ $user_locale ] ) ) {
				$user_language = static::SUPPORTED_LOCALES[ $user_locale ];
			} else {
				$user_locale   = 'en_US';
				$user_language = 'English (United States)';
			}

			wp_localize_script(
				'big-sky-assembler',
				'bigSkyInitialState',
				[
					'isDevMode'     => self::is_dev_mode(),
					'isLocalGraph'  => false, // Change this to use local graph
					'launchStatus'  => get_option( 'launch-status' ),
					'userLocale'    => $user_locale,
					'userLanguage'  => $user_language,
					'isComingSoon'  => self::is_coming_soon(),
					'isBlogPrivate' => self::is_blog_private(),
					'siteMetadata'  => json_decode( get_option( 'big_sky_site_metadata' ), true ),
					'siteIntent'    => get_option( 'site_intent', '' ),
					'siteGoals'     => get_option( 'site_goals', [] ),
				]
			);
		}

		/**
		 * Whether the site is currently unlaunched or not.
		 * On WordPress.com and WoA, sites can be marked as "coming soon", aka unlaunched.
		 *
		 * See Jetpack_Status::is_coming_soon()
		 * https://github.com/Automattic/jetpack/blob/trunk/projects/packages/status/src/class-status.php
		 */
		public static function is_coming_soon() {
			return ( new \Automattic\Jetpack\Status() )->is_coming_soon();
		}

		/**
		 * See Jetpack_Status::is_private_site()
		 * https://github.com/Automattic/jetpack/blob/trunk/projects/packages/status/src/class-status.php
		 */
		public static function is_blog_private() {
			return ( new \Automattic\Jetpack\Status() )->is_private_site();
		}

		/**
		 * Displays admin notices.
		 */
		public static function admin_notices() {
			$checks = self::do_checks();

			if ( 'good' === $checks['status'] ) {
				return;
			}

			wp_admin_notice(
				$checks['description'],
				array(
					'type'        => 'error',
					'dismissible' => true,
				)
			);
		}

		/**
		 * If the original image was marked as an AI generated image, mark the new one as one as well.
		 */
		public static function set_big_sky_generated_logo_for_edited_images( $new_image_meta, $new_attachment_id, $attachment_id ) {
			$original = get_post_meta( $attachment_id, 'big_sky_generated_logo', true );
			if ( $original ) {
				add_post_meta( $new_attachment_id, 'big_sky_generated_logo', -1, true );
			}
			return $new_image_meta;
		}

		/**
		 * Handle post deletion by removing associated patterns from site metadata
		 *
		 * @param int $post_id The ID of the post being deleted
		 */
		public static function handle_post_deletion( $post_id ) {
			try {
				// Only process pages
				if ( 'page' !== get_post_type( $post_id ) ) {
					return;
				}

				// Get current site metadata
				$site_metadata = json_decode( get_option( 'big_sky_site_metadata' ), true );

				// If no patterns exist or patterns is not an object, return early
				if ( empty( $site_metadata['patterns'] ) || ! is_array( $site_metadata['patterns'] ) ) {
					return;
				}

				if ( ! isset( $site_metadata['patterns'][ $post_id ] ) ) {
					return;
				}

				// Remove patterns for the deleted post
				unset( $site_metadata['patterns'][ $post_id ] );

				// Update the site metadata
				update_option( 'big_sky_site_metadata', json_encode( $site_metadata ) );
			} catch ( Exception $e ) {
				// no big deal
				return;
			}
		}
	}
} // close class_exists check

Big_Sky::init();
