<<<<<<< HEAD
<?php

if (!function_exists('dd')) {
	function dd(...$args)
	{
		if (function_exists('dump')) {
			dump(...$args);
		} else {
			var_dump(...$args);
		}
		die;
	}
}
=======
<?php

if (!function_exists('dd')) {
	function dd(...$args)
	{
		if (function_exists('dump')) {
			dump(...$args);
		} else {
			var_dump(...$args);
		}
		die;
	}
}
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
