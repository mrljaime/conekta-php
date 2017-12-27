<?php

namespace Conekta;

/**
 * Class ConektaResource
 * @package Conekta
 */
abstract class ConektaResource extends ConektaObject
{

    /**
     * @param $class
     * @return string
     */
    public static function className($class)
    {
        // Useful for namespaces: Foo\Charge
        if ($postfix = strrchr($class, '\\')) {
            $class = substr($postfix, 1);
        }

        if (substr($class, 0, strlen('Conekta')) == 'Conekta') {
            $class = substr($class, strlen('Conekta'));
        }

        $class = str_replace('_', '', $class);
        $name = urlencode($class);
        $name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));

        return $name;
    }

    /**
     * @param $class
     * @param $method
     * @return mixed
     */
    protected static function _getBase($class, $method)
    {
        $args = array_slice(func_get_args(), 2);

        return call_user_func_array(array($class, $method), $args);
    }

    /**
     * @param null $class
     * @return string
     * @throws NoConnectionError
     */
    public static function classUrl($class = null)
    {
        if (empty($class)) {

            throw new NoConnectionError(
            Lang::translate('error.resource.id', Lang::EN, array('RESOURCE' => "NULL")),
            Lang::translate('error.resource.id_purchaser', Conekta::$locale)
            );
        }

        $base = self::_getBase($class, 'className', $class);

        return "/{$base}s";
    }

    /**
     * @param $class
     * @param $params
     * @return ConektaList|ConektaObject
     */
    protected static function _scpWhere($class, $params)
    {
        if (Conekta::$apiVersion == "2.0.0") {
            $path = explode('\\', $class);
            $instance = new ConektaList(array_pop($path));

        } else {
            $instance = new ConektaObject();
        }

        $requestor = new Requestor();
        $url = self::classUrl($class);
        $response = $requestor->request('get', $url, $params);
        $instance->loadFromArray($response);

        return $instance;
    }

    /**
     * @param $class
     * @param $id
     * @return mixed
     */
    protected static function _scpFind($class, $id)
    {
        $instance = new $class($id);
        $requestor = new Requestor();
        $url = $instance->instanceUrl();
        $response = $requestor->request('get', $url);
        $instance->loadFromArray($response);

        return $instance;
    }

    /**
     * @param $class
     * @param $params
     * @return mixed
     */
    protected static function _scpCreate($class, $params)
    {
        $requestor = new Requestor();
        $url = self::classUrl($class);
        $response = $requestor->request('post', $url, $params);
        $instance = new $class();
        $instance->loadFromArray($response);

        return $instance;
    }

    /**
     * @return string
     */
    public function instanceUrl()
    {
        $id = $this->id;
        $this->idValidator($id);
        $class = get_class($this);
        $base = $this->classUrl($class);
        $extn = urlencode($id);

        return "{$base}/{$extn}";
    }

    /**
     * @param $id
     * @throws ParameterValidationError
     */
    protected function idValidator($id)
    {
        if (!$id) {
            $error = new ParameterValidationError(
                Lang::translate('error.resource.id', Lang::EN, array('RESOURCE' => get_class())),
                Lang::translate('error.resource.id_purchaser', Conekta::$locale)
            );

            if (Conekta::$apiVersion == "2.0.0") {
                $handler = new Handler();
                $handler = $error;

                throw $handler;
            } // Endif

            throw $error;
        } // Endif
    }

    protected function _delete($parent = null, $member = null)
    {
        self::_customAction("delete", null, null);

        if (isset($parent) && isset($member)) {
            $obj = $this->$parent->$member;

            if ($obj instanceof ConektaObject) {
                foreach ($this->$parent->$member as $k => $v) {
                    if (strpos($v->id, $this->id) !== false) {
                        $this->$parent->$member->_values = Util::shiftArray($this->$parent->$member->_values, $k);
                        $this->$parent->$member->loadFromArray($this->$parent->$member->_values);
                        $this->$parent->$member->offsetUnset(count($this->$parent->$member) - 1);
                        break;
                    } // Endif
                } // End foreach
            } // Endif
        } // Endif

        return $this;
    }

    /**
     * @param $params
     * @return $this
     */
    protected function _update($params)
    {
        $requestor = new Requestor();
        $url = $this->instanceUrl();
        $response = $requestor->request("put", $url, $params);

        return $this;
    }

    /**
     * @param $member
     * @param $params
     * @return mixed
     */
    protected function _createMember($member, $params)
    {
        $requestor = new Requestor();
        $url = $this->instanceUrl() . "/" . $member;
        $response = $requestor->request("post", $url, $params);

        if ($this->$member instanceof ConektaList ||
            $this->$member instanceof ConektaObject ||
            strpos($member, "cards") !== false ||
            strpos($member, "payment_methods") !== false
        ) {

            if (empty($this->$member)) {
                if (Conekta::$apiVersion == "2.0.0") {
                    $this->$member = new ConektaList($member);

                } else {
                    $this->$member = new ConektaObject();
                } // End else
            } // Endif

            if ($this->$member instanceof ConektaList) {
                $this->$member->addElement($response);

            } else {
                $this->$member->loadFromArray(array_merge(
                    $this->$member->toArray(),
                    [$response]
                ));

                $this->loadFromArray();
            } // Endif

            $instances = $this->$member;
            $instance = end($instances);

        } else {
            $class = '\\Conekta\\' . ucfirst($member);

            $instance = new $class();
            $instance->loadFromArray($response);
            $this->$member = $instance;
            $this->_setVal($member, $instance);
            $this->loadFromArray();
        }

        return $instance;
    }

    /**
     * @param string $method
     * @param null $action
     * @param null $params
     * @return $this
     */
    protected function _customAction($method = "post", $action = null, $params = null)
    {
        $requestor = new Requestor();
        if (!is_null($action)) {
            $url = $this->instanceUrl() . "/{$action}";

        } else {
            $url = $this->instanceUrl();
        }

        $response = $requestor->request($method, $url, $params);
        $this->loadFromArray($response);

        return $this;
    }

    /**
     * @param $member
     * @param $param
     * @param $parent
     * @return mixed
     */
    protected function _createMemberWithRelation($member, $param, $parent)
    {
        $parentClass = strtolower((new \ReflectionClass($parent))->getShortName());
        $child = self::_createMember($member, $param);
        $child->$parentClass = $parent;

        return $child;
    }

}