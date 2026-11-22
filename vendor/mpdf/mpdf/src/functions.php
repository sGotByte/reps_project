<<<<<<< HEAD
<?php

if (!function_exists('str_starts_with')) {
	function str_starts_with($haystack, $needle)
	{
		return 0 === strncmp($haystack, $needle, \strlen($needle));
	}
}

if (!function_exists('str_ends_with')) {
	function str_ends_with($haystack, $needle)
	{
		if ('' === $needle || $needle === $haystack) {
			return true;
		}

		if ('' === $haystack) {
			return false;
		}

		$needleLength = \strlen($needle);

		return $needleLength <= \strlen($haystack) && 0 === substr_compare($haystack, $needle, -$needleLength);
	}
}
=======
<?php

if (!function_exists('str_starts_with')) {
	function str_starts_with($haystack, $needle)
	{
		return 0 === strncmp($haystack, $needle, \strlen($needle));
	}
}

if (!function_exists('str_ends_with')) {
	function str_ends_with($haystack, $needle)
	{
		if ('' === $needle || $needle === $haystack) {
			return true;
		}

		if ('' === $haystack) {
			return false;
		}

		$needleLength = \strlen($needle);

		return $needleLength <= \strlen($haystack) && 0 === substr_compare($haystack, $needle, -$needleLength);
	}
}
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
