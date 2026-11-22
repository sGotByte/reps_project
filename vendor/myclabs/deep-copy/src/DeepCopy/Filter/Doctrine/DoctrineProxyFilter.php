<<<<<<< HEAD
<?php

namespace DeepCopy\Filter\Doctrine;

use DeepCopy\Filter\Filter;

/**
 * @final
 */
class DoctrineProxyFilter implements Filter
{
    /**
     * Triggers the magic method __load() on a Doctrine Proxy class to load the
     * actual entity from the database.
     *
     * {@inheritdoc}
     */
    public function apply($object, $property, $objectCopier)
    {
        $object->__load();
    }
}
=======
<?php

namespace DeepCopy\Filter\Doctrine;

use DeepCopy\Filter\Filter;

/**
 * @final
 */
class DoctrineProxyFilter implements Filter
{
    /**
     * Triggers the magic method __load() on a Doctrine Proxy class to load the
     * actual entity from the database.
     *
     * {@inheritdoc}
     */
    public function apply($object, $property, $objectCopier)
    {
        $object->__load();
    }
}
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
