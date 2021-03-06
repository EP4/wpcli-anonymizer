<?php

namespace EP4\WPCLI_Anonymizer;

use WP_CLI;

if ( ! class_exists( '\WP_CLI' ) ) {
	return;
}

$wpcli_anonymizer_autoloader = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $wpcli_anonymizer_autoloader ) ) {
	require_once $wpcli_anonymizer_autoloader;
}

WP_CLI::add_command( 'anonymize users', WPCLI_Anonymize_Users_Command::class );
WP_CLI::add_command( 'anonymize comments', WPCLI_Anonymize_Comments_Command::class );
