<<<<<<< HEAD
<?php

namespace Mpdf\Barcode;

interface BarcodeInterface
{

	/**
	 * @return string
	 */
	public function getType();

	/**
	 * @return mixed[]
	 */
	public function getData();

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function getKey($key);

	/**
	 * @return string
	 */
	public function getChecksum();

}
=======
<?php

namespace Mpdf\Barcode;

interface BarcodeInterface
{

	/**
	 * @return string
	 */
	public function getType();

	/**
	 * @return mixed[]
	 */
	public function getData();

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function getKey($key);

	/**
	 * @return string
	 */
	public function getChecksum();

}
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
