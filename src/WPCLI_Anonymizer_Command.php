<?php

namespace EP4\WPCLI_Anonymizer;

use WP_CLI;
use WP_CLI_Command;

class WPCLI_Anonymizer_Command extends WP_CLI_Command {

	/**
	 * Greets the world.
	 *
	 * ## EXAMPLES
	 *
	 *     # Greet the world.
	 *     $ wp hello-world
	 *     Success: Hello World!
	 *
	 * @when before_wp_load
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		WP_CLI::success( 'Hello World!' );
	}
}
