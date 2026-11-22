<<<<<<< HEAD
<?php

namespace Mpdf\Tag;

class Ttz extends SubstituteTag
{

	public function open($attr, &$ahtml, &$ihtml)
	{
		$this->mpdf->ttz = true;
		$this->mpdf->InlineProperties['TTZ'] = $this->mpdf->saveInlineProperties();
		$this->mpdf->setCSS(['FONT-FAMILY' => 'czapfdingbats', 'FONT-WEIGHT' => 'normal', 'FONT-STYLE' => 'normal'], 'INLINE');
	}

}
=======
<?php

namespace Mpdf\Tag;

class Ttz extends SubstituteTag
{

	public function open($attr, &$ahtml, &$ihtml)
	{
		$this->mpdf->ttz = true;
		$this->mpdf->InlineProperties['TTZ'] = $this->mpdf->saveInlineProperties();
		$this->mpdf->setCSS(['FONT-FAMILY' => 'czapfdingbats', 'FONT-WEIGHT' => 'normal', 'FONT-STYLE' => 'normal'], 'INLINE');
	}

}
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
