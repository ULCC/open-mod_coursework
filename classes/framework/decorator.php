<?php

namespace mod_coursework\framework;


/**
 * Class decorator
 *
 * Acts as a decorator around a class. Remember to add the '@mixin' property so that PHPStorm will
 * provide autocompletion of methods and properties.
 */
class decorator {

    /**
     * @var
     */
    protected $wrapped_object;

    /**
     * @param $wrapped_object
     */
    public function __construct($wrapped_object) {
        $this->wrapped_object = $wrapped_object;
    }

    /**
     * Delegate everything to the wrapped object by default.
     *
     * @param $method
     * @param $args
     * @return mixed
     */
    public function __call($method, $args) {
        return call_user_func_array(array($this->wrapped_object,
                                          $method),
                                    $args);
    }

    /**
     * Delegate everything to the wrapped object by default.
     *
     * @param $name
     * @return mixed
     */
    public function __get($name) {
        return $this->wrapped_object->$name;
    }

    /**
     * Delegate everything to the wrapped object by default.
     *
     * @param $name
     * @param $value
     * @return mixed
     */
    public function __set($name, $value) {
        return $this->wrapped_object->$name = $value;
    }

    /**
     * @return mixed
     */
    public function wrapped_object() {
        return $this->wrapped_object;
    }
}