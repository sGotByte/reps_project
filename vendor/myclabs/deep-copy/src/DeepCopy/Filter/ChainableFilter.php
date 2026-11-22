<<<<<<< HEAD
<?php

namespace DeepCopy\Filter;

/**
 * Defines a decorator filter that will not stop the chain of filters.
 */
class ChainableFilter implements Filter
{
    /**
     * @var Filter
     */
    protected $filter;

    public function __construct(Filter $filter)
    {
        $this->filter = $filter;
    }

    public function apply($object, $property, $objectCopier)
    {
        $this->filter->apply($object, $property, $objectCopier);
    }
}
=======
<?php

namespace DeepCopy\Filter;

/**
 * Defines a decorator filter that will not stop the chain of filters.
 */
class ChainableFilter implements Filter
{
    /**
     * @var Filter
     */
    protected $filter;

    public function __construct(Filter $filter)
    {
        $this->filter = $filter;
    }

    public function apply($object, $property, $objectCopier)
    {
        $this->filter->apply($object, $property, $objectCopier);
    }
}
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
