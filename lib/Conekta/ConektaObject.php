<?php

namespace Conekta;


/**
 * Class ConektaObject
 * @package Conekta
 */
class ConektaObject extends \ArrayObject
{
    protected $_values;

    /**
     * ConektaObject constructor.
     * @param null $id
     */
    public function __construct($id = null)
    {
        $this->_values = array();
        $this->id = $id;
    }

    /**
     * @param $object
     * @param $val
     */
    public function _setVal($object, $val)
    {
        $this->_values[$object] = $val;
        $this[$object] = $val;
    }

    /**
     * @param $object
     */
    public function _unsetKey($object)
    {
        unset($this->_values[$object]);
        unset($object);
    }

    /**
     * @param $values
     */
    public function loadFromArray($values)
    {
        foreach ($values as $object => $val) {
            if (is_array($val)) {
                $val = Util::convertToConektaObject($val);
            }

            if (strpos(get_class($this), 'ConektaObject') !== false) {
                $this[$object] = $val;

            } else {

                if (false !== strpos($object, "url") && false !== strpos(get_class($this), 'Webhook')) {
                    $object = "webhook_url";
                }

                $this->$object = $val;

                if ($object == "metadata") {
                    $this->metadata = new ConektaObject();

                    if (is_array($val) || is_object($val)) {

                        foreach ($val as $iObject => $iValue) {
                            $this->metadata->$iObject = $iValue;
                            $this->metadata->_setVal($iObject, $iValue);
                        } // End foreach
                    } // End if
                } // End if
            } // End else

            $this->_setVal($object, $val);
        }
    }

    /**
     * @return string
     */
    public function __toJSON()
    {
        if (defined("JSON_PRETTY_PRINT")) {

            return json_encode($this->_toArray(), JSON_PRETTY_PRINT);
        }

        return json_encode($this->_toArray());
    }

    protected function _toArray()
    {
        $array = [];

        /**
         * @var string $object
         * @var array|\ArrayObject $val
         */
        foreach ($this->_values as $object => $val) {
            if (is_object($val) && 0 !== strlen(trim(get_class($val)))) {

                if (!empty($val)) {
                    $array[$object] = $val->_toArray();
                }

                continue;
            }

            $array[$object] = $val;
        }

        return $array;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->__toJSON();
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return isset($this->_values[$offset]) ? $this->_values[$offset] : null;
    }
}
