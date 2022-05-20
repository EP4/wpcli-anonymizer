<?php

namespace EP4\WPCLI_Anonymizer;

use WP_CLI;
use Faker\Factory;

/**
 * Helpers methods for Anonymizer commands.
 *
 * @package EP4\WPCLI_Anonymizer
 */
trait WPCLI_Anonymizer_Helpers {
	/**
	 * Get the faker object.
	 *
	 * @return object $faker The faked object.
	 */
	protected function get_faker() {
		$locale = isset( $this->locale ) ? $this->locale : 'en_US';

		$faker = Factory::create( $locale );
		if ( isset( $this->seed ) && null !== $this->seed ) {
			$faker->seed( $this->seed );
		}

		return $faker;
	}

	/**
	 * Converts a string of comma-separated user IDs, logins and emails to an array of user IDs.
	 *
	 * @param string $arg_string The --keep argument value.
	 *
	 * @return integer Number of user ids excluded.
	 */
	private function format_user_ids( $arg_string ) {
		if ( empty( $arg_string ) ) {
			return array();
		}

		$user_ids = array_map(
			array( $this, 'get_user_id' ),
			wp_parse_list( $arg_string )
		);

		$user_ids = array_filter( $user_ids );
		return $user_ids;
	}

	/**
	 * Returns a user id from an email, user login, or string id.
	 *
	 * @param string $string A single segment of the --keep argument.
	 *
	 * @return integer|boolean User id or false if not found but skipping is ok.
	 */
	private function get_user_id( $string ) {
		$skip_not_found_users = isset( $this->skip_not_found_users ) ? $this->skip_not_found_users : false;

		if ( is_numeric( $string ) ) {
			$user_id = (int) $string;
			$user    = get_user_by( 'ID', $user_id );

			if ( $user ) {
				return $user_id;
			} else {
				if ( $skip_not_found_users ) {
					WP_CLI::warning( sprintf( 'The user ID \'%s\' doesn\'t seem to exist. Skipping...', $user_id ) );
				} else {
					WP_CLI::error( sprintf( 'The user ID \'%s\' doesn\'t seem to exist. Consider using the `--skip-not-found` flag. Aborting...', $user_id ) );
				}
			}
		}
		if ( stristr( $string, '@' ) ) {
			$user = ! is_multisite() ? get_user_by( 'email', $string ) : $this->ms_get_user_by( 'email', $string );
			if ( $user ) {
				return $user->ID;
			} else {
				if ( $skip_not_found_users ) {
					WP_CLI::warning( sprintf( 'The user email \'%s\' doesn\'t seem to exist. Skipping...', $string ) );
				} else {
					WP_CLI::error( sprintf( 'The user email \'%s\' doesn\'t seem to exist. Consider using the `--skip-not-found` flag. Aborting...', $string ) );
				}
			}
		}

		$user = ! is_multisite() ? get_user_by( 'login', $string ) : $this->ms_get_user_by( 'login', $string );

		if ( $user ) {
			return $user->ID;
		}

		if ( $skip_not_found_users ) {
			WP_CLI::warning( sprintf( 'The username \'%s\' doesn\'t seem to exist. Skipping...', $string ) );
		} else {
			WP_CLI::error( sprintf( 'The username \'%s\' doesn\'t seem to exist. Consider using the `--skip-not-found` flag. Aborting...', $string ) );
		}

		return false;
	}

	/**
	 * Get a user by field for multisite.
	 *
	 * @param string $field field name.
	 * @param string $string string to search by.
	 */
	protected function ms_get_user_by( $field, $string ) {
		global $wpdb;
		if ( 'login' === $field ) {
			$user_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT ID FROM $wpdb->users WHERE `user_login` = %s LIMIT 1",
				$string
			) );
		} elseif ( 'email' ) {
			$user_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT ID FROM $wpdb->users WHERE `user_email` = %s LIMIT 1",
				$string
			) );
		} else {
			WP_CLI::error( 'Unrecognized user search field.' );
		}
		if ( ! empty( $user_id ) ) {
			$user     = new stdClass();
			$user->ID = $user_id;
			return $user;
		}
		return false;
	}

	private function validate_site_id( $arg_string ) {
		if ( empty( $arg_string ) ) {
			return null;
		}

		if ( ! is_multisite() ) {
			WP_CLI::error( 'The \'--site=<id>\' option is only valid for multisite installs. Aborting...' );
		}

		if ( ! is_numeric( $arg_string ) ) {
			WP_CLI::error( sprintf( 'The \'--site=<id>\' value must be a number, but it is currently set to \'%s\'. Aborting...', $arg_string ) );
		}

		$site_id = (int) $arg_string;

		if ( empty( get_site( $site_id ) ) ) {
			WP_CLI::error( sprintf( 'There is no site corresponding to the ID \'%s\'. Aborting...', $arg_string ) );
		}

		return $site_id;
	}

	private function string_to_associative_array( string $string, $item_delimiter = ',', $assoc_delimiter = '::' ) {
		if ( empty( $string ) ) {
			return array();
		}

		$items = explode( $item_delimiter, $string );
		$array = array();

		foreach ( $items as $item ) {
			$item  = explode( $assoc_delimiter, $item );
			$key   = $item[0];
			$value = isset( $item[1] ) ? $item[1] : null;

			$array[ $key ] = $value;
		}

		return $array;
	}

	private function associative_array_to_string( array $array, $item_delimiter = ',', $assoc_delimiter = '::' ) {
		$string = '';
		if ( empty( $array ) ) {
			return $string;
		}

		foreach ( $array as $key => $value ) {
			$string .= "{$key}{$assoc_delimiter}{$value}{$item_delimiter}";
		}

		$string = rtrim( $string, $item_delimiter );

		return $string;
	}

	/**
	 * Converts an array to a command line string.
	 *
	 * @param  array  $array An associative array to convert into a command line string.
	 *
	 * @return string $string The command line.
	 */
	public function array_to_cmd( array $array ) {
		$string = '';

		// Makes sure numerical index are first in the array.
		ksort( $array );

		// FALSE and NULL values are deliberately ignored.
		foreach ( $array as $key => $value ) {
			if ( is_int( $key ) && ! empty( $value ) ) {
				// Numerical key means the value is the command itself.
				$string .= "{$value} ";
			} elseif ( is_string( $value ) || is_numeric( $value ) ) {
				// A string or numerical value can be added without issue.
				$string .= "--{$key}={$value} ";
			} elseif ( true === $value ) {
				// A boolean set to TRUE means no value is expected, so only the key is needed.
				$string .= "--{$key} ";
			} elseif ( is_array( $value ) ) {
				if ( array_is_list( $value ) ) {
					// An indexed array must be converted back to a string with items separated by commas.
					$string .= "--{$key}=" . implode( ',', $value ) . ' ';
				} else {
					// An associative array must be parsed based on the format of the `string_to_associative_array()` method.
					$string .= "--{$key}=" . $this->associative_array_to_string( $value ) . ' ';
				}
			}
		}

		return $string;
	}
}
