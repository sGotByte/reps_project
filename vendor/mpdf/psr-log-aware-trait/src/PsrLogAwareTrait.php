<<<<<<< HEAD
<?php

namespace Mpdf\PsrLogAwareTrait;

use Psr\Log\LoggerInterface;

trait PsrLogAwareTrait 
{

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	public function setLogger(LoggerInterface $logger): void
	{
		$this->logger = $logger;
	}
	
}
=======
<?php

namespace Mpdf\PsrLogAwareTrait;

use Psr\Log\LoggerInterface;

trait PsrLogAwareTrait 
{

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	public function setLogger(LoggerInterface $logger): void
	{
		$this->logger = $logger;
	}
	
}
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
