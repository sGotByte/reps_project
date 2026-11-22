<<<<<<< HEAD
<?php

namespace Mpdf\Tag;

class WatermarkText extends Tag
{

	public function open($attr, &$ahtml, &$ihtml)
	{
		$txt = '';
		if (!empty($attr['CONTENT'])) {
			$txt = htmlspecialchars_decode($attr['CONTENT'], ENT_QUOTES);
		}

		$alpha = -1;
		if (isset($attr['ALPHA']) && $attr['ALPHA'] > 0) {
			$alpha = $attr['ALPHA'];
		}
		$this->mpdf->SetWatermarkText($txt, $alpha);
	}

	public function close(&$ahtml, &$ihtml)
	{
	}
}
=======
<?php

namespace Mpdf\Tag;

class WatermarkText extends Tag
{

	public function open($attr, &$ahtml, &$ihtml)
	{
		$txt = '';
		if (!empty($attr['CONTENT'])) {
			$txt = htmlspecialchars_decode($attr['CONTENT'], ENT_QUOTES);
		}

		$alpha = -1;
		if (isset($attr['ALPHA']) && $attr['ALPHA'] > 0) {
			$alpha = $attr['ALPHA'];
		}
		$this->mpdf->SetWatermarkText($txt, $alpha);
	}

	public function close(&$ahtml, &$ihtml)
	{
	}
}
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
