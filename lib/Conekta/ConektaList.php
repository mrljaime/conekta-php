<?php

namespace Conekta;


/**
 * Class ConektaList
 * @package Conekta
 */
class ConektaList extends ConektaObject
{

    const LIMIT = 5;

    /**
     * ConektaList constructor.
     * @param null $elements_type
     * @param array $params
     */
    public function __construct($elements_type, $params = array())
    {
        parent::__construct();
        $this->elements_type = $elements_type;
        $this->params = $params;
        $this->total = 0;
    }

    /**
     * @param $element
     * @return $this
     */
    public function addElement($element)
    {
        $element = Util::convertToConektaObject($element);
        $this[$this->total] = $element;
        $this->_values[$this->total] = $element;
        $this->total = $this->total + 1;

        return $this;
    }

    /**
     * @param null $values
     */
    public function loadFromArray($values = null)
    {
        if (!is_null($values)) {
            $this->has_more = $values["has_more"];
            $this->total = $values["total"];

            foreach ($this as $key => $value) {
                $this->_unsetKey($key);
            }
        }

        if (isset($values["data"])) {

            return parent::loadFromArray($values["data"]);
        }

    }

    /**
     * @param array $options
     */
    public function next($options = array('limit' => self::LIMIT))
    {
        if (0 < count($this)) {
            $this->params["next"] = end($this)->id;
        }

        $this->params["previous"] = null;

        return $this->_moveCursor($options["limit"]);
    }

    /**
     * @param array $options
     */
    public function previous($options = array('limit' => self::LIMIT))
    {
        if (0 < count($this)) {
            $this->params["previous"] = $this[0]->id;
        }

        $this->params["next"] = null;

        return $this->_moveCursor($options["limit"]);
    }

    /**
     * @param $limit
     */
    protected function _moveCursor($limit)
    {

        $this->params["limit"] = $limit;
        $class = Util::$types[strtolower($this->elements_type)];
        $url = ConektaResource::classUrl($class);

        /*
         * Calling WS
         */
        $requestor = new Requestor();
        $response = $requestor->request("get", $url, $this->params);

        return $this->loadFromArray($response);
    }
}
