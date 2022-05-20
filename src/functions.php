<?php

if ( ! function_exists("array_is_list") ) {
	/**
	 * Checks whether a given array is a list. Polyfill for the `array_is_list` function available for PHP 8.1.
	 *
	 * Determines if the given array is a list. An array is considered a list if its keys consist of consecutive
	 * numbers from 0 to `count( $array ) - 1`.
	 *
	 * @see https://www.php.net/manual/fr/function.array-is-list.php#127044
	 *
	 * @param  array $array The array being evaluated.
	 * @return bool  Returns TRUE if `$array` is a list or empty array, FALSE otherwise.
	 */
	function array_is_list(array $array) {
		$i = -1;
		foreach ( $array as $k => $v ) {
			++$i;
			if ( $k !== $i ) {
				return false;
			}
		}
		return true;
	}
}