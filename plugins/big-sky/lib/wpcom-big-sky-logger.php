<?php
declare( strict_types = 1 );

require_once __DIR__ . '/big-sky-logger.php';

/**
 * A logger which log natively on WPCOM to the big_sky_sessions and big_sky_actions tables.
 */
class WPCOM_Big_Sky_Logger extends Big_Sky_Logger {
	private function get_session_id( $session_uuid ) {
		// TODO: memcached
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				'SELECT big_sky_session_id FROM big_sky_sessions WHERE session_uuid = %s',
				$session_uuid
			)
		);
	}

	/**
	 * Log a session to the big_sky_sessions table. If run twice, it updates the name.
	 *
	 * @param string $session_uuid The session UUID.
	 * @param string $name The session name.
	 * @param string $date The session date.
	 * @param bool   $is_test Whether the session is a test session.
	 */
	public function log_session( $session_uuid, $name, $date, $is_test = false ) {
		global $wpdb;

		if ( ! $session_uuid ) {
			$this->log_generic_logger_error_to_logstash( 'Error logging session! No session_uuid provided.', $wpdb->last_error );
			return false;
		}

		$_blog_id = get_current_blog_id();
		$_user_id = get_current_user_id();

		// find the session
		$session_id = $this->get_session_id( $session_uuid );

		// if no session found, insert a new one
		if ( ! $session_id ) {
			if ( ! $name || ! $date ) {
				$this->log_generic_logger_error_to_logstash( 'Error logging session! Missing required fields.', $wpdb->last_error );
				return;
			}
			$result = $wpdb->insert(
				'big_sky_sessions',
				[
					'session_uuid' => $session_uuid,
					'name'         => $name,
					'date'         => $date,
					'user_id'      => $_user_id,
					'blog_id'      => $_blog_id,
					'is_test'      => $is_test,
				]
			);

			if ( false === $result ) {
				$this->log_generic_logger_error_to_logstash( 'Error logging session! ' . $session_uuid . ' ' . $name, $wpdb->last_error );
			}

			// return the ID of the inserted row for later association
			return $wpdb->insert_id;
		} else {
			$result = $wpdb->update(
				'big_sky_sessions',
				[
					'name'    => $name,
					'is_test' => $is_test,
				],
				[
					'big_sky_session_id' => $session_id,
				]
			);

			if ( false === $result ) {
				$this->log_generic_logger_error_to_logstash( 'Error updating session! ' . $session_uuid . ' ' . $name, $wpdb->last_error );
			}

			return $session_id;
		}
	}

	/**
	 * Log an action to the big_sky_actions table.
	 *
	 * @param string       $session_uuid The session UUID.
	 * @param string       $message_role The message type.
	 * @param string       $message_id The message ID.
	 * @param string|array $payload The content of the message.
	 * @param string       $date The date of the message.
	 * @param string       $parent_message_id The parent message ID.
	 */
	public function log_action( $session_uuid, $message_role, $message_id, $payload, $date, $parent_message_id, $is_test = false ) {
		global $wpdb;

		if ( ! $session_uuid ) {
			$this->log_generic_logger_error_to_logstash( 'Error logging session! No session_uuid provided.', $wpdb->last_error );
			return new WP_Error( 'big_sky_session_missing', 'Session UUID not provided' );
		}

		$_blog_id = get_current_blog_id();
		$_user_id = get_current_user_id();

		// find the session
		$session_id = $this->get_session_id( $session_uuid );

		if ( ! $session_id ) {
			$session_id = $this->log_session( $session_uuid, 'New Session', current_time( 'mysql' ), $is_test );

			if ( ! $session_id ) {
				$this->log_generic_logger_error_to_logstash( 'Error creating session for session_uuid ' . $session_uuid, $wpdb->last_error );
				return new WP_Error( 'big_sky_session_not_found', 'Session not found' );
			}
		}

		$content = is_string( $payload ) ? $payload : json_encode( $payload );

		// These are app errors, aka errors that users encounter.
		if ( 'error' === $message_role ) {
			$user_agent = $this->parse_user_agent( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );

			$logstash_params = array(
				'feature'         => 'big-sky-action',
				'message'         => $content,
				'browser_name'    => $user_agent->browser,
				'browser_version' => $user_agent->browser_version,
				'properties'      => array(
					'platform'          => $user_agent->platform,
					'session_id'        => $session_uuid,
					'message_id'        => $message_id,
					'message_role'      => $message_role,
					'parent_message_id' => $parent_message_id,
				),
				'severity'        => $is_test ? 'warning' : 'error',
				'blog_id'         => $_blog_id,
				'user_id'         => $_user_id,
			);
			$this->log_to_logstash( $logstash_params );
		}

		$parent_action_id = $parent_message_id ?
			$wpdb->get_var(
				$wpdb->prepare(
					'SELECT big_sky_action_id FROM big_sky_actions WHERE message_id = %s',
					$parent_message_id
				)
			) : 0;

		$result = $wpdb->insert(
			'big_sky_actions',
			[
				'blog_id'            => $_blog_id,
				'user_id'            => $_user_id,
				'big_sky_session_id' => $session_id,
				'parent_id'          => $parent_action_id,
				'date'               => $date,
				'content'            => $content,
				'message_type'       => $message_role,
				'message_id'         => strval( $message_id ),
			]
		);

		if ( false === $result ) {
			$this->log_generic_logger_error_to_logstash( 'Error logging action! ' . $message_id . ' ' . $content, $wpdb->last_error );
		}

		// return the ID of the inserted row for later association
		return $wpdb->insert_id;
	}

	/**
	 * Copied from https://github.a8c.com/Automattic/wpcom/blob/8d58c99c00d329a70ffc5955096ca4921954ccb3/wp-content/admin-plugins/js-errors/js-errors.php#L85
	 */
	private function parse_user_agent( $agent ) {
		// phpcs:disable
		$meta = new stdClass();
		$meta->browser = null;
		$meta->browser_version = null;
		$meta->platform = null;

		if ( false !== stripos( $agent, 'msie' ) ) {
			$meta->browser = 'IE';
			if ( preg_match( '!msie ([\d\.]+);!i', $agent, $matches ) )
				$meta->browser_version = $matches[1];

		} elseif ( false !== stripos( $agent, 'chrome' ) ) {
			$meta->browser = 'Chrome';
			if ( preg_match( '!chrome/([\d\.]+)!i', $agent, $matches ) )
				$meta->browser_version = $matches[1];

		} elseif ( false !== stripos( $agent, 'safari' ) ) {
			$meta->browser = 'Safari';
			if ( preg_match( '!version([ /])([\d\.]+)!i', $agent, $matches ) || preg_match( '!safari/([\d\.]+)!i', $agent, $matches ) )
				$meta->browser_version = $matches[1];

		} elseif ( false !== stripos( $agent, 'firefox' ) ) {
			$meta->browser = 'Firefox';
			if ( preg_match( '!firefox/([\d\.]+)!i', $agent, $matches ) )
				$meta->browser_version = $matches[1];

		} elseif ( false !== stripos( $agent, 'opera' ) ) {
			$meta->browser = 'Opera';
			if ( preg_match( '!version([ /])([\d\.]+)!i', $agent, $matches ) || preg_match( '!opera([ /])([\d\.]+)!i', $agent, $matches ) )
				$meta->browser_version = $matches[2];
		} else {
			$meta->browser = $agent;
		}

		if ( false !== stripos( $agent, 'windows' ) ) {
			$meta->platform = 'Win';
		} elseif ( false !== stripos( $agent, 'mac' ) ) {
			$meta->platform = 'Mac';
		}
		// phpcs:enable

		return $meta;
	}
	/**
	 * Log metadata to the big_sky_site_metadata table.
	 *
	 * @param string $session_uuid The session UUID.
	 * @param array  $metadata The metadata to log.
	 */
	public function log_metadata( $session_uuid, $metadata ) {
		global $wpdb;

		if ( ! $session_uuid ) {
			$this->log_generic_logger_error_to_logstash( 'Error logging metadata! No session_uuid provided.', $wpdb->last_error );
			return new WP_Error( 'big_sky_session_missing', 'Session UUID not provided' );
		}

		$_blog_id = get_current_blog_id();

		// find the session
		$session_id = $this->get_session_id( $session_uuid );

		if ( ! $session_id ) {
			// Create a new session
			$session_id = $this->log_session( $session_uuid, 'New Session', current_time( 'mysql' ), false );

			if ( ! $session_id ) {
				$this->log_generic_logger_error_to_logstash( 'Error creating session for session_uuid ' . $session_uuid, $wpdb->last_error );
				return new WP_Error( 'big_sky_session_not_found', 'Session not found' );
			}
		}

		$result = $wpdb->insert(
			'big_sky_site_metadata',
			[
				'big_sky_session_id' => $session_id,
				'blog_id'            => $_blog_id,
				'date'               => current_time( 'mysql' ),
				'content'            => json_encode( $metadata ),
			]
		);

		if ( false === $result ) {
			$this->log_generic_logger_error_to_logstash( 'Error logging metadata!', $wpdb->last_error );
		}

		return $wpdb->insert_id;
	}

	private function log_generic_logger_error_to_logstash( $message = '', $extra = '' ) {
		$params = array(
			'feature'  => 'big-sky-session-logger',
			'message'  => $message,
			'extra'    => $extra,
			'severity' => 'error',
		);
		$this->log_to_logstash( $params );
	}

	private function log_to_logstash( $logstash_params ) {
		if ( defined( 'IS_WPCOM' ) && IS_WPCOM ) { // Shouldn't be needed, but just in case
			require_lib( 'log2logstash' );
			log2logstash( $logstash_params );
		}
	}
}
