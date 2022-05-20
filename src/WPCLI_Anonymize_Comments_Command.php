<?php

namespace EP4\WPCLI_Anonymizer;

use WP_CLI;
use WP_CLI_Command;

/**
 * Rewrites personally identifying information (PII) in user profiles and comments.
 *
 * @package EP4\WPCLI_Anonymizer
 */
class WPCLI_Anonymize_Comments_Command extends WP_CLI_Command {
	/**
	 * Includes traits.
	 */
	use WPCLI_Anonymizer_Helpers;

	/**
	 * User ids for which comments should be updated. Default: empty array (all users).
	 *
	 * @var array
	 */
	protected $user_ids = array();

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
	 * Should the comment author fields be updated, but the comment text and custom fields be ignored. Default: FALSE.
	 *
	 * @var boolean
	 */
	protected $only_author_fields = false;

	/**
	 * Should the comment text and custom fields be updated, but the comment author fields be ignored. Default: FALSE.
	 *
	 * @var boolean
	 */
	protected $except_author_fields = false;

	/**
	 * Should the comment author fields be filled using the data from an existing user. Default: FALSE.
	 *
	 * @var boolean
	 */
	protected $use_existing_users_data = false;

	/**
	 * Whether empty comment fields should be updated or not. Default: FALSE.
	 *
	 * @var boolean
	 */
	protected $ignore_empty_fields = false;

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
	 * Anonymizes comments for development environments.
	 *
	 * Loops through all WordPress comments in order to replace any existing data
	 * with fake information using the Faker library.
	 *
	 * ## OPTIONS
	 *
	 * [--users=<user_id|user_login|email>]
	 * : Comments from a specific user to update. If set to 0, only comments from logged-out users will be updated.
	 *
	 * [--only-author-fields]
	 * : Only update fields related to the comment author; ignore the comment text and custom fields.
	 * This option will be ignored if the `--except-author-fields` option is also used.
	 *
	 * [--except-author-fields]
	 * : Only update the comment text and custom fields; ignore fields relative to the comment author.
	 * This option has priority over the `--only-author-fields`, if both options are used.
	 *
	 * [--use-existing-user-data]
	 * : Most likely used in conjunction with `--users=<user_id>`. If this option is used, and the comment to anonymize
	 * was published by a logged-in user, then the comment author details will be rewritten with existing user profile
	 * values. This option only makes sense if user profiles have been anonymized before anonymizing user comments.
	 *
	 * [--skip-not-found]
	 * : Skip user(s) comments to update if not found, fails otherwise.
	 *
	 * [--site=<site_id>]
	 * : Site id to limit rewrites to.
	 *
	 * [--ignore-empty-fields]
	 * : Don't update fields that are currently empty.
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
	 *     # Rewrite all user profiles and comments.
	 *     $ wp anonymize users
	 *     Success: Rewrote all user data.
	 *
	 *     # Rewrite all user profiles except for user_id 123.
	 *     $ wp anonymize users --keep=1
	 *     Success: All comments and users except: '1' rewritten.
	 *
	 *     # Rewrite all user profiles except ones matching user id 123, user login admin, and/or test@example.com and skip those if not found.
	 *     $ wp anonymize users --keep="2,admin,test@example.com" --skip-not-found
	 *     Success: All comments and users except: '2,1,3' rewritten.
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

		$this->user_ids                = $this->format_user_ids( WP_CLI\Utils\get_flag_value( $assoc_args, 'users', false ) );
		$this->limit_to_site           = WP_CLI\Utils\get_flag_value( $assoc_args, 'site', null );
		$this->skip_not_found_users    = false !== WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-not-found', false );
		$this->ignore_empty_fields     = false !== WP_CLI\Utils\get_flag_value( $assoc_args, 'ignore-empty-fields', false );
		$this->locale                  = WP_CLI\Utils\get_flag_value( $assoc_args, 'language', 'en_US' );
		$this->seed                    = WP_CLI\Utils\get_flag_value( $assoc_args, 'seed', null );
		$this->custom_email_domains    = ! empty( $assoc_args['custom-email-domains'] ) ? explode( ',', $assoc_args['custom-email-domains'] ) : array();
		$this->use_existing_users_data = false !== WP_CLI\Utils\get_flag_value( $assoc_args, 'use-existing-user-data', false );
		$this->except_author_fields    = false !== WP_CLI\Utils\get_flag_value( $assoc_args, 'except-author-fields', false );
		$this->only_author_fields      = ( false === $this->except_author_fields ) && ( false !== WP_CLI\Utils\get_flag_value( $assoc_args, 'only-author-fields', false ) );
		$this->limit_to_site           = $this->validate_site_id( WP_CLI\Utils\get_flag_value( $assoc_args, 'site', null ) );
		$this->custom_fields           = $this->string_to_associative_array( WP_CLI\Utils\get_flag_value( $assoc_args, 'custom-fields', null ) );

		// Starts the process.
		WP_CLI::confirm( 'Rewrites all comment data?', $assoc_args );
		$comments_updated = $this->obfuscate_comments();
		$items            = array(
			array(
				'Updated' => 'Comments',
				'Count'   => $comments_updated,
			),
		);

		WP_CLI\Utils\format_items( 'table', $items, array( 'Updated', 'Count' ) );

		if ( ! empty( $this->limit_to_site ) ) {
			WP_CLI::success( sprintf( 'All comments and users on site \'%s\' rewritten.' ) );
		} else {
			WP_CLI::success( sprintf( 'All comments and users rewritten.' ) );
		}
	}

	/**
	 * Rewrite the PII found in standard WordPress comments.
	 *
	 * @return integer Number of comments updated.
	 */
	protected function obfuscate_comments() {
		$faker        = $this->get_faker();
		$count        = 0;
		$all_comments = array();

		if ( ! is_multisite() ) {
			$data                        = $this->gather_comments( null );
			$count                       = count( $data );
			$all_comments['single_site'] = $data;
		} else {
			if ( ! empty( $this->limit_to_site ) ) {
				$site = get_site( $this->limit_to_site );
				if ( ! empty( $site ) ) {
					$data                           = $this->gather_comments( $site->blog_id );
					$count                          = $count + count( $data );
					$all_comments[ $site->blog_id ] = $data;
				} else {
					WP_CLI::error( 'Site not found.' );
				}
			} else {
				$sites = get_sites();
				foreach ( $sites as $site ) {
					$data                           = $this->gather_comments( $site->blog_id );
					$count                          = $count + count( $data );
					$all_comments[ $site->blog_id ] = $data;
				}
			}
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Rewriting comments...', $count );

		foreach ( $all_comments as $blog_id => $comments ) {
			foreach ( $comments as $comment ) {
				if ( 'single_site' !== $blog_id && is_multisite() ) {
					switch_to_blog( $blog_id );
				}

				$commentarr = $comment->to_array();

				// Maybe anonymizes the comment author fields.
				if ( ! $this->except_author_fields ) {
					$commentarr = $this->obfuscate_comment_author( $commentarr );
				}

				// Maybe anonymizes the comment text and custom fields too!
				if ( ! $this->only_author_fields ) {
					
				}

				/**
				 * Filters the fake generated comment data before updating the comment.
				 *
				 * Triggered before a single comment is updated with fake information. Allows you to modify custom meta fields when the plugin is triggered.
				 *
				 * @since 1.0.0
				 *
				 * @param array      $commentarr New comment data about to be written to the database.
				 * @param WP_Comment $comment    The original WP_Comment object.
				 * @param Factory    $faker      The faker object, made available for you to generate fake data for meta fields, etc.
				 */
				$commentarr = apply_filters( 'ep4_wpcli_anonymizer_comment_data', $commentarr, $comment, $faker );

				wp_update_comment( $commentarr );

				/**
				 * Post update comment.
				 *
				 * Triggered after a single comment is updated with fake information.
				 *
				 * @since 1.0.0
				 *
				 * @param array      $commentarr New comment data written to the database.
				 * @param WP_Comment $comment    The original WP_Comment object.
				 * @param Factory    $faker      The faker object, made available for you to generate fake data for meta fields, etc.
				 */
				do_action( 'ep4_wpcli_anonymizer_comment_updated', $commentarr, $comment, $faker );

				$progress->tick();

				if ( is_multisite() ) {
					restore_current_blog();
				}
			}
		}

		$progress->finish();

		return $count;
	}

	protected function obfuscate_comment_author( $commentarr ) {
		$faker = $this->get_faker();

		if ( ! empty( $commentarr['user_id'] ) ) {
			$user_info = get_userdata( $commentarr['user_id'] );
		}

		if ( ! empty( $user_info ) ) {
			if ( ! empty( $user_info->display_name ) ) {
				$author_name = $user_info->display_name;
			} else {
				$author_name = $user_info->user_login;
			}

			$user_email = $user_info->user_email;
			$user_url   = ! empty( $user_info->user_url ) ? $user_info->user_url : '';
		} else {
			$author_name = $faker->name;

			if ( ! empty( $this->custom_email_domains ) ) {
				$domains = $this->custom_email_domains;
				shuffle( $domains ); // Randomizes the order of domains.
	
				$domain       = reset( $domains );
				$domain       = false === strpos( $domain, '.', 1 ) ? $domain . '.' . $faker->tld() : $domain;
				$email_domain = $domain;
			} else {
				$email_domain = $faker->safeEmailDomain();
			}

			$user_email = str_replace( '-', '.', sanitize_title( $author_name ) ) . '@' . $email_domain;
			$user_url   = ! empty( $this->ignore_empty_fields ) && ! empty( $commentarr['comment_author_url'] ) ? $faker->url : '';
		}

		$commentarr['comment_author']       = $author_name;
		$commentarr['comment_author_email'] = $user_email;
		$commentarr['comment_author_url']   = $user_url;
		$commentarr['comment_author_IP']    = $faker->ipv4;
		$commentarr['comment_agent']        = $faker->userAgent;

		return $commentarr;
	}

	/**
	 * Gather comments for a single blog.
	 *
	 * @param integer $blog_id blog id.
	 *
	 * @return array
	 */
	protected function gather_comments( $blog_id ) {
		if ( is_int( $blog_id ) && is_multisite() ) {
			switch_to_blog( $blog_id );
		}

		$query_args = array(
			'status'  => array( 'any' ),
			'user_id' => ! empty( $this->user_ids ) ? $this->user_ids : '',
		);
		$comments   = get_comments( $query_args );

		if ( is_multisite() ) {
			restore_current_blog();
		}

		return $comments;
	}

}
