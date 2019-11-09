<?php

namespace DStructure;

class Item implements \ArrayAccess
{
    protected array $data = [];

    public function __construct (array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Возвращает данные объекта
     * 
     * @return array
     */
    public function getData (): array
    {
        return $this->data;
    }

    # __get / __set

    /**
     * @throws \Exception
     */
    public function __get (string $name)
    {
        if (isset ($this->data[$name]))
            return $this->data[$name];

        throw new \Exception ('Section '. $name .' not founded');
    }

    public function __set (string $name, $value): void
    {
        $this->data[$name] = $value;
    }

    # ArrayAccess

    public function offsetExists ($offset): bool
    {
        return isset ($this->data[$offset]);
    }

    public function offsetGet ($offset)
    {
        return $this->data[$offset];
    }

    public function offsetSet ($offset, $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset ($offset): void
    {
        unset ($this->data[$offset]);
    }
}
