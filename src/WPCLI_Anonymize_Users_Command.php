<?php

namespace EP4\WPCLI_Anonymizer;

use WP_CLI;
use WP_CLI_Command;

/**
 * Rewrites personally identifying information (PII) in user profiles and comments.
 *
 * @package EP4\WPCLI_Anonymizer
 */
class WPCLI_Anonymize_Users_Command extends WP_CLI_Command {
	/**
	 * Includes traits.
	 */
	use WPCLI_Anonymizer_Helpers;

	/**
	 * User ids to skip.
	 *
	 * @var array
	 */
	protected $excluded_user_ids = array();

	/**
	 * User roles to skip.
	 *
	 * @var array
	 */
	protected $excluded_user_roles = array();

	/**
	 * If we can't find a user id for a name or email, should we bail?
	 *
	 * @var boolean
	 */
	protected $skip_not_found_users = false;

	/**
	 * Site id to restrict rewrite to.
	 *
	 * @var integer
	 */
	protected $limit_to_site = null;

	/**
	 * Whether empty user fields should be updated or not. Default: FALSE.
	 *
	 * @var boolean
	 */
	protected $ignore_empty_fields = false;

	/**
	 * Whether the comment authors should be updated. Default: TRUE.
	 *
	 * @var boolean
	 */
	protected $update_comment_authors = true;

	/**
	 * Language of the fake content. Default: 'en_US'.
	 * @see https://github.com/FakerPHP/Faker/tree/main/src/Faker/Provider List of available locales.
	 *
	 * @var string
	 */
	protected $locale = 'en_US';

	/**
	 * A number used to keep the same fake generated content. Default: NULL.
	 *
	 * @var null|integer
	 */
	protected $seed = null;

	/**
	 * A list of custom domains to use for generating fake emails. Default: empty array.
	 *
	 * @var array
	 */
	protected $custom_email_domains = array();

	/**
	 * A list of custom user meta fields for which fake data must be generated. Default: empty array.
	 *
	 * @var array
	 */
	protected $custom_fields = array();

	/**
	 * Anonymizes user profiles for development environments.
	 *
	 * Loops through all WordPress users in order to replace any existing data
	 * with fake information using the Faker library.
	 *
	 * ## OPTIONS
	 *
	 * [--keep=<user_id|user_login|email>]
	 * : User(s) to skip during replacement.
	 *
	 * [--skip-not-found]
	 * : Skip users to keep if not found, fails otherwise.
	 *
	 * [--keep-roles=<user_roles>]
	 * : User(s) with a specific role to skip during replacement.
	 *
	 * [--site=<site_id>]
	 * : Site id to limit rewrites to.
	 *
	 * [--ignore-empty-fields]
	 * : Don't update fields that are currently empty.
	 *
	 * [--ignore-comment-authors]
	 * : Don't update comment authors.
	 *
	 * [--language=<locale>]
	 * : The language of the fake content. Default: 'en_US'.
	 *
	 * [--seed=<integer>]
	 * : A number used to keep generating the same fake content. Default: NULL.
	 *
	 * [--custom-email-domains=<domains>]
	 * : A list of domains separated by comma to use for fake emails. Default: NULL.
	 *
	 * [--custom-fields=<fields>]
	 * : A list of custom user meta fields separated by comma for which fake data must be generated. Default: NULL.
	 * A custom user meta name must be associated to a faker method, by appending the method to the meta name 
	 * followed by ::. For example, to create fake data for the user_phone meta, use `user_phone::phone`. If no 
	 * method is provided, the default Faker method used will be `realTextBetween( 10, 30, 5 )`.
	 *
	 * ## EXAMPLES
	 *
	 *     # Rewrite all user profiles.
	 *     $ wp anonymize users
	 *     Success: All users have been rewritten.
	 *
	 *     # Rewrite all user profiles except for user ID 123.
	 *     $ wp anonymize users --keep=123
	 *     Success: All users have been rewritten except: 123.
	 *
	 *     # Rewrite all user profiles except ones matching user IDs 2 and 10, user login `admin`, and email `test@example.com`, and skip those if not found.
	 *     $ wp anonymize users --keep="2,10,admin,test@example.com" --skip-not-found
	 *     Success: All users have been rewritten except: 10, 456, 789.
	 *
	 *     # Rewrite all user profiles except those with the admin and editor roles.
	 *     $ wp anonymize users --keep-roles=admin,editor
	 *     Success: All users have been rewritten except: 1, 10, 11, 100.
	 *
	 *     # Rewrite only comments and users for one site on a multi-site install.
	 *     $ wp anonymize users --site=3
	 *     Success: All comments and users on site '3' rewritten.
	 *
	 *     # Rewrite all user profiles but don't update empty user fields.
	 *     $ wp anonymize users --ignore-empty-fields
	 *     Success: Rewrote all user data.
	 *
	 *     # Rewrite all user profiles using French data.
	 *     $ wp anonymize users --language=fr_FR --seed=1000
	 *     Success: Rewrote all user data.
	 *
	 *     # Rewrite all user profiles using either the 'test.com', 'test.org' or 'domain.net' domain for emails.
	 *     $ wp anonymize users --custom-email-domains=test.com,test.org,domain.net
	 *     Success: Rewrote all user data.
	 *
	 *     # Rewrite all user profiles in French along with specific user meta, but don't update empty user fields.
	 *     $ wp anonymize users --ignore-empty-fields --language=fr_FR --custom-fields=user_phone::phone,user_city::city,user_company
	 *     Success: Rewrote all user data.
	 */
	public function __invoke( $args, $assoc_args ) {
		// Prepares arguments.
		if ( ! empty( $args ) ) {
			WP_CLI::warning( 'Unknown argument' );
		}

		$this->skip_not_found_users    = false !== WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-not-found', false );
		$this->ignore_empty_fields     = false !== WP_CLI\Utils\get_flag_value( $assoc_args, 'ignore-empty-fields', false );
		$this->update_comment_authors  = false === WP_CLI\Utils\get_flag_value( $assoc_args, 'ignore-comment-authors', false );
		$this->locale                  = WP_CLI\Utils\get_flag_value( $assoc_args, 'language', 'en_US' );
		$this->seed                    = WP_CLI\Utils\get_flag_value( $assoc_args, 'seed', null );
		$this->custom_email_domains    = ! empty( $assoc_args['custom-email-domains'] ) ? explode( ',', $assoc_args['custom-email-domains'] ) : array();
		$this->excluded_user_ids       = $this->format_user_ids( WP_CLI\Utils\get_flag_value( $assoc_args, 'keep', false ) );
		$this->excluded_user_roles     = wp_parse_list( WP_CLI\Utils\get_flag_value( $assoc_args, 'keep-roles', array() ) );
		$this->limit_to_site           = $this->validate_site_id( WP_CLI\Utils\get_flag_value( $assoc_args, 'site', null ) );
		$this->custom_fields           = $this->string_to_associative_array( WP_CLI\Utils\get_flag_value( $assoc_args, 'custom-fields', null ) );

		// Adds the following hooks to prevent email from being sent when updating password and email fields.
		add_filter( 'send_password_change_email', '__return_false' );
		add_filter( 'send_email_change_email', '__return_false' );

		// Starts the process.
		WP_CLI::confirm( 'Rewrite all user data?', $assoc_args );
		$users_updated = $this->obfuscate_users();
		$items         = array(
			array(
				'Updated'  => 'Users',
				'Count'    => $users_updated,
			),
		);

		// Completed.
		WP_CLI\Utils\format_items( 'table', $items, array( 'Updated', 'Count' ) );
		if ( count( $this->excluded_user_ids ) > 0 ) {
			$ids_string = implode( ', ', $this->excluded_user_ids );
			if ( ! empty( $this->limit_to_site ) ) {
				WP_CLI::success( sprintf(
					'All users on site \'%s\' have been rewriten, except: \'%s\'.',
					$this->limit_to_site,
					$ids_string
				) );
			} else {
				WP_CLI::success( sprintf( 'All users have been rewritten except: \'%s\'.', $ids_string ) );
			}
		} else {
			if ( ! empty( $this->limit_to_site ) ) {
				WP_CLI::success( sprintf( 'All users on site \'%s\' have been rewritten.' ) );
			} else {
				WP_CLI::success( sprintf( 'All users have been rewritten.' ) );
			}
		}
	}

	/**
	 * Loop over all the users found and replace their personal data.
	 *
	 * @return integer Number of users updated.
	 */
	protected function obfuscate_users() {
		$users = array();
		if ( is_multisite() ) {
			if ( ! empty( $this->limit_to_site ) ) {
				$sites[] = get_site( $this->limit_to_site );
			} else {
				$sites = get_sites();
			}
			foreach ( $sites as $site ) {
				$site_users = get_users(
					array(
						'blog_id'      => $site->blog_id,
						'exclude'      => $this->excluded_user_ids,
						'role__not_in' => $this->excluded_user_roles,
						'fields'       => 'all_with_meta',
					)
				);
				$users      = array_merge( $users, $site_users );
			}
		} else {
			$users = get_users(
				array(
					'exclude'      => $this->excluded_user_ids,
					'role__not_in' => $this->excluded_user_roles,
					'fields'       => 'all_with_meta',
				)
			);
		}
		if ( count( $users ) <= 0 ) {
			WP_CLI::warning( 'No users changed (did you exclude them all?)' );
			return 0;
		}
		$count    = count( $users );
		$progress = \WP_CLI\Utils\make_progress_bar( 'Rewriting users...', $count );
		foreach ( $users as $user ) {
			if ( null !== $this->seed ) {
				$this->seed++; // Increases the seed or else the data will be the same for all users.
			}

			$this->obfuscate_user( $user );

			if ( $this->update_comment_authors ) {
				$command_args = array(
					'anonymize comments',
					'users'                  => $user->ID,
					'language'               => $this->locale,
					'seed'                   => $this->seed,
					'custom-email-domains'   => $this->custom_email_domains,
					'site'                   => $this->limit_to_site,
					'skip-not-found'         => $this->skip_not_found_users,
					'ignore-empty-fields'    => $this->ignore_empty_fields,
					'only-author-fields'     => true, // TRUE means no value is expected for this flag.
					'use-existing-user-data' => true,
					'yes'                    => true, // Used for bypassing dialogs.
				);

				WP_CLI::runcommand( $this->array_to_cmd( $command_args ), array( 'exit_error' => false ) );
			}

			$progress->tick();
		}
		$progress->finish();

		return $count;
	}

	/**
	 * Replace a single user's data.
	 *
	 * @param WP_User $user WordPress user object.
	 * @return void
	 */
	private function obfuscate_user( $user ) {
		$faker          = $this->get_faker();
		$original_user  = $user;
		$user_data      = $user->to_array();
		$fake_data      = $this->get_fake_user_profile_data();
		$default_fields = array_merge( 
			array( 'user_login', 'user_pass', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'user_activation_key', 'display_name' ),
			_get_additional_user_keys( $user )
		);

		foreach ( $fake_data as $key => $value ) {
			if ( $user->has_prop( $key ) ) {
				if ( ! $this->ignore_empty_fields || ! empty( $user->get( $key ) ) ) {
					if ( in_array( $key, $default_fields, true ) ) {
						$user_data[ $key ] = $value;
					} else {
						$user_data['meta_input'][ $key ] = $value;
					}
				}
			}
		}

		/**
		 * Filters the fake generated user data before updating the user.
		 *
		 * Triggered before a single user is updated with fake information.
		 *
		 * @since 1.0.0
		 *
		 * @param array   $user_data     New user data about to be written to the database.
		 * @param WP_User $original_user The original WP_User object.
		 * @param Factory $faker         The faker object, made available for you to generate fake data for meta fields etc.
		 */
		$user_data = apply_filters( 'ep4_wpcli_anonymizer_user_data', $user_data, $original_user, $faker );

		wp_update_user( $user_data );
		$this->update_user_login( $user->ID, $fake_data['user_login'] );

		/**
		 * Post update user.
		 *
		 * Triggered after a single user is updated with fake information.
		 *
		 * @since 1.0.0
		 *
		 * @param array   $user_data     New user data written to the database.
		 * @param WP_User $original_user The original WP_User object.
		 * @param Factory $faker         The faker object, made available for you to generate fake data for meta fields etc.
		 */
		do_action( 'ep4_wpcli_anonymizer_user_updated', $user_data, $original_user, $faker );
	}

	/**
	 * Gets fake user profile data.
	 *
	 * @return array $profile_data An array of fake profile data ready to be used.
	 */
	protected function get_fake_user_profile_data() {
		$faker = $this->get_faker();

		$first_name    = $faker->firstName();
		$last_name     = $faker->lastName();
		$display_name  = $first_name . ' ' . $last_name;
		$user_login    = str_replace( '-', '.', sanitize_title( $last_name . '.' . $first_name ) );
		$user_login    = $this->generate_unused_user_login( $user_login );
		$user_nicename = mb_strimwidth( sanitize_user( $user_login, true ), 0, 50 );

		if ( ! empty( $this->custom_email_domains ) ) {
			$domains = $this->custom_email_domains;
			shuffle( $domains ); // Randomizes the order of domains.

			$domain     = reset( $domains );
			$domain     = false === strpos( $domain, '.', 1 ) ? $domain . '.' . $faker->tld() : $domain;
			$user_email = $user_login . '@' . $domain;
		} else {
			$user_email = $user_login . '@' . $faker->safeEmailDomain();
		}

		$profile_fields = array(
			// Fields from the users table.
			'user_pass'       => wp_hash_password( $faker->password ),
			'user_nicename'   => $user_nicename,
			'user_email'      => $user_email,
			'user_url'        => $faker->url,
			'display_name'    => $display_name,
			'user_login'      => $user_login,
			'user_registered' => $faker->dateTimeThisDecade()->format( 'Y-m-d H:i:s' ),

			// Other fields from the usermeta table.
			'nickname'    => $user_login,
			'first_name'  => $first_name,
			'last_name'   => $last_name,
			'description' => $faker->realTextBetween( 100, 200, 3 ),
		);

		$contact_methods = wp_get_user_contact_methods();
		foreach ( $contact_methods as $contact_method_key => $contact_method_label ) {
			$profile_fields[ $contact_method_key ] = $faker->numerify( strtolower( $first_name ) . '_#####' );
		}

		foreach ( $this->custom_fields as $user_meta => $faker_method ) {
			if ( ! empty( $faker_method ) ) {
				$faker_method = str_replace( array( '(', ')' ), '', $faker_method ); // Avoids duplicate parenthesis issues.
				$fake_data    = $faker->$faker_method();
			} else {
				$fake_data = rtrim( $faker->realTextBetween( 10, 30, 5 ), '.' );
			}

			$profile_fields[ $user_meta ] = $fake_data;
		}

		return $profile_fields;
	}

	/**
	 * Return a fake login name that doesn't exist yet.
	 *
	 * @return string
	 */
	private function generate_unused_user_login( $user_login_to_check = '' ) {
		$faker          = $this->get_faker();
		$new_user_login = false;
		$sanity_check   = 0;

		while ( ! $new_user_login ) {
			$user_login_to_check = $sanity_check > 5 || empty( $user_login_to_check ) ? mb_strimwidth( sanitize_user( $faker->userName() ), 0, 60 ) : mb_strimwidth( sanitize_user( $user_login_to_check ), 0, 60 );
			$user                = get_user_by( 'user_login', $user_login_to_check );
			if ( ! $user ) {
				$new_user_login = $user_login_to_check;
			} elseif ( $sanity_check > 0 ) { // it would be crazy to get here, but lets try adding some random numbers.
				$user_login_to_check = $faker->numerify( mb_strimwidth( $user_login_to_check, 0, 60, '#####' ) );
				$user                = get_user_by( 'user_login', $user_login_to_check );
				if ( ! $user ) {
					$new_user_login = $user_login_to_check;
				}
			}

			$sanity_check ++;
			// it should be impossible to get here.
			if ( $sanity_check > 30 ) {
				WP_CLI::error( 'Unable to find a fake username that was not already in use. Consider running the script once again. Aborting...' );
			}
		}

		return $new_user_login;
	}

	/**
	 * WordPress does not update user names via the wp_update_user function, so we need to do that manually.
	 *
	 * @param int    $user_id WP user id.
	 * @param string $new_login New user login.
	 *
	 * @return void
	 */
	private function update_user_login( $user_id, $new_login ) {
		global $wpdb;
		$wpdb->update( $wpdb->users, array( 'user_login' => $new_login ), array( 'ID' => $user_id ) );
	}

}
