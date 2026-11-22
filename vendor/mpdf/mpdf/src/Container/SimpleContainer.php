<<<<<<< HEAD
<?php

namespace Mpdf\Container;

class SimpleContainer implements \Mpdf\Container\ContainerInterface
{

	private $services;

	public function __construct(array $services)
	{
		$this->services = $services;
	}

	public function get($id)
	{
		if (!$this->has($id)) {
			throw new \Mpdf\Container\NotFoundException(sprintf('Unable to find service of key "%s"', $id));
		}

		return $this->services[$id];
	}

	public function has($id)
	{
		return isset($this->services[$id]);
	}

	public function getServices()
	{
		return $this->services;
	}

}
=======
<?php

namespace Mpdf\Container;

class SimpleContainer implements \Mpdf\Container\ContainerInterface
{

	private $services;

	public function __construct(array $services)
	{
		$this->services = $services;
	}

	public function get($id)
	{
		if (!$this->has($id)) {
			throw new \Mpdf\Container\NotFoundException(sprintf('Unable to find service of key "%s"', $id));
		}

		return $this->services[$id];
	}

	public function has($id)
	{
		return isset($this->services[$id]);
	}

	public function getServices()
	{
		return $this->services;
	}

}
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
