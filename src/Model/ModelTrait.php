<?php
namespace Lexide\Clay\Model;

/**
 * Used to load data into a model
 *
 * Where a field can accept a collection of subclasses, type-hinted by a base class, the discriminator map of that base
 * class identifies which subclass to instantiate for a given set of data
 *
 * [
 *     "discriminatorField" => [child class property to discriminate against] - e.g. "type"
 *     "subclassNamespace" => [namespace under which the subclasses are located] - defaults to the namespace of the base class
 *     "subclassSuffix" => [class name suffix to append to the value of the discriminator field] - e.g. ProductItem, ShippingItem, DiscountItem, etc...
 *     "map" => [
 *         [discriminator field value] => [partial class name or FQCN]
 *     ]
 * ]
 *
 * @property $modelDiscriminatorMap array
 *
 */
trait ModelTrait 
{
    use ClassDiscriminatorTrait;

    /**
     * @var array
     */
    protected $modelConstructorArgs = [];

    /**
     * @var bool
     */
    protected $modelCanBeUpdated = true;

    /**
     * @param array $data
     * @param bool $update
     * @param bool $replaceCollections
     */
    protected function loadData(array $data, $update = false, $replaceCollections = false)
    {
        foreach ($data as $prop => $value) {

            if (is_null($value) && !$update) {
                // nulls can be ignored if we're not updating
                continue;
            }
            // studly caps the property name
            $prop = $this->toStudlyCaps($prop);

            // look for setter first
            $setter = "set$prop";
            if (method_exists($this, $setter)) {
                if (is_array($value)) {
                    // check to see if the method requires a class
                    $param = $this->getFirstParameter($setter);
                    $isCollection = false;

                    // if the setter's first parameter is an array, we need to check if this is a collection of objects
                    // in this case there should be an "addProperty" method on the class
                    $adder = "add$prop";
                    if ($param->isArray() && method_exists($this, $adder)) {
                        $param = $this->getFirstParameter($adder);
                        $isCollection = true;
                    }
                    $value = $this->constructClasses($param, $value, $isCollection);

                    // if we have a collection and we're updating it ...
                    if (
                        $isCollection &&
                        $update &&
                        (
                            empty($replaceCollections) ||               // don't add if we're replacing all collections
                            empty($replaceCollections[$this->mbLcfirst($prop)])  // don't add if this collection has been marked for replacing
                        )
                    ) {
                        // ... add each element instead of replacing the whole set
                        foreach ($value as $element) {
                            $this->{$adder}($element);
                        }
                        continue;
                    }
                }
                $this->{$setter}($value);
            } else {
                // set the property directly
                // to camel case
                $prop = $this->mbLcfirst($prop);
                if (property_exists($this, $prop)) {
                    $this->{$prop} = $value;
                }
            }

        }
    }

    /**
     * @param array $data
     * @param bool $replaceCollections
     */
    public function updateData(array $data, $replaceCollections = false)
    {
        // only update if we're allowed
        if ($this->modelCanBeUpdated) {
            $this->loadData($data, true, $replaceCollections);
        }
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $properties = get_object_vars($this);
        $data = [];
        foreach ($properties as $prop => $blah) {
            // check for getter
            $getter = "get" . $this->mbUcfirst($prop);
            if (method_exists($this, $getter)) {
                $value = $this->{$getter}();
                $data[$prop] = $this->getValueData($value);
            }
        }
        return $data;
    }

    /**
     * @param string $method
     * @return \ReflectionParameter
     */
    private function getFirstParameter($method)
    {
        $ref = new \ReflectionMethod($this, $method);
        /** @var \ReflectionParameter $param */
        return $ref->getParameters()[0];
    }

    /**
     * @param \ReflectionParameter $param
     * @param mixed $value
     * @param bool $isCollection
     * @return object
     */
    private function constructClasses(\ReflectionParameter $param, $value, $isCollection = false)
    {
        $class = $param->getClass();
        if (!empty($class)) {

            if ($isCollection) {
                foreach ($value as $i => $subValue) {
                    $value[$i] = $this->getNewInstance($class, $subValue);
                }
            } else {
                $value = $this->getNewInstance($class, $value);
            }
        }
        return $value;
    }

    /**
     * @param \ReflectionClass $class
     * @param mixed $value
     * @return object
     */
    private function getNewInstance(\ReflectionClass $class, $value)
    {
        if (is_array($value)) {
            $class = $this->discriminateClass($class, $value);
            $value = $class->newInstanceArgs(array_merge([$value], $this->modelConstructorArgs));
        }
        return $value;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function getValueData($value)
    {
        $data = $value;
        if (is_array($value)) {
            foreach ($value as $i => $subValue) {
                if (is_object($subValue) && method_exists($subValue, "toArray")) {
                    $subValue = $subValue->toArray();
                }
                $data[$i] = $subValue;
            }
        } elseif (is_object($value) && method_exists($value, "toArray")) {
            $data = $value->toArray();
        }

        return $data;
    }

    /**
     * @param string $string
     * @return string
     */
    private function mbUcfirst($string)
    {
        return $this->mbCaseConvertFirst($string, true);
    }

    /**
     * @param string $string
     * @return string
     */
    private function mbLcfirst($string)
    {
        return $this->mbCaseConvertFirst($string, false);
    }

    /**
     * @param string $string
     * @param bool $upper
     * @return string
     */
    private function mbCaseConvertFirst($string, $upper = true)
    {
        $case = $upper? "l": "u";
        $pattern = "/^\p{L$case}/";

        return preg_replace_callback($pattern, function ($matches) use ($upper) {
            return $upper? mb_strtoupper($matches[0]): mb_strtolower($matches[0]);
        }, $string);
    }


} 
