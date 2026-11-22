<<<<<<< HEAD
<?php

namespace Mpdf\File;

class LocalContentLoader implements \Mpdf\File\LocalContentLoaderInterface
{

	public function load($path)
	{
		return file_get_contents($path);
	}

}
=======
<?php

namespace Mpdf\File;

class LocalContentLoader implements \Mpdf\File\LocalContentLoaderInterface
{

	public function load($path)
	{
		return file_get_contents($path);
	}

}
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
