<?php

/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * @package     Distributed Structure
 * @copyright   2019 Podvirnyy Nikita (KRypt0n_)
 * @license     GNU GPLv3 <https://www.gnu.org/licenses/gpl-3.0.html>
 * @license     Enfesto Studio Group license <https://vk.com/topic-113350174_36400959>
 * @author      Podvirnyy Nikita (KRypt0n_)
 * 
 * Contacts:
 *
 * Email: <suimin.tu.mu.ga.mi@gmail.com>
 * VK:    vk.com/technomindlp
 *        vk.com/hphp_convertation
 * 
 */

namespace DStructure;

const DS = DIRECTORY_SEPARATOR;

class Structure
{
    protected ?string $path = null;
    protected ?string $key  = null;
    protected string $hash  = 'sha1';

    protected array $index = [];
    protected array $references = [];
    protected array $objects = [];

    /**
     * @throws \Exception
     */
    public function __construct (?string $path = null, ?string $key = null, string $hashfunc = 'sha1')
    {
        if ($path === null)
            $path = dirname (__DIR__) . DS .'.dbs';
        
        $this->path = $path;
        $this->key  = $key;
        $this->hash = $hashfunc;

        if (!in_array ($this->hash, hash_hmac_algos ()))
            throw new \Exception ('Hash algo '. $this->hash .' are not supported');

        $this->isMapped ($this->path) ?
            $this->load () :
            $this->save ();
    }

    /**
     * Получить значение из структуры
     * 
     * @param string $name
     * 
     * @return Item|null
     */
    public function get (string $name): ?Item
    {
        if (!$this->exists ($name))
            return null;

        $name   = hash_hmac ($this->hash, $name, $this->key ?? '`');
        $prefix = substr ($name, 0, 2);

        if (!isset ($this->objects[$name]))
        {
            if (!file_exists ($file = $this->path . DS .'objects'. DS . $prefix . DS . $name))
                return null;
            
            $this->objects[$name] = unserialize ($this->xor (file_get_contents ($file)));
        }
        
        return new Item ($this->objects[$name]);
    }

    /**
     * Установить значение в структуру
     * 
     * @param string $name
     * @param Item $value
     * 
     * @return Structure
     */
    public function set (string $name, Item $value): Structure
    {
        $name   = hash_hmac ($this->hash, $name, $this->key ?? '`');
        $prefix = substr ($name, 0, 2);

        if (!in_array ($prefix, $this->index['refs']))
            $this->index['refs'][] = $prefix;

        if (!isset ($this->references[$prefix]))
            $this->references[$prefix] = [$name];

        elseif (!in_array ($name, $this->references[$prefix]))
            $this->references[$prefix][] = $name;

        $this->objects[$name] = $value->getData ();

        return $this;
    }

    /**
     * Проверяет, существует ли индекс
     * 
     * @param string $name
     * 
     * @return bool
     */
    public function exists (string $name): bool
    {
        $name   = hash_hmac ($this->hash, $name, $this->key ?? '`');
        $prefix = substr ($name, 0, 2);

        return in_array ($prefix, $this->index['refs']) &&
            in_array ($name, $this->references[$prefix]);
    }

    /**
     * Удаление значения
     * 
     * @param string $name
     * 
     * @return Structure
     */
    public function remove (string $name): Structure
    {
        $name   = hash_hmac ($this->hash, $name, $this->key ?? '`');
        $prefix = substr ($name, 0, 2);

        unset ($this->objects[$name]);

        $this->references[$prefix] = array_diff ($this->references[$prefix], [$name]);

        if (sizeof ($this->references[$prefix]) == 0)
        {
            unset ($this->references[$prefix]);

            $this->index['refs'] = array_diff ($this->index['refs'], [$prefix]);
        }

        return $this;
    }

    /**
     * Пройтись по всем элементам
     * 
     * @param callable $callable
     * 
     * @return Structure
     */
    public function foreach (callable $callable): Structure
    {
        foreach ($this->index['refs'] as $reference)
            foreach ($this->references[$reference] as $item)
                $callable (new Item (unserialize ($this->xor (file_get_contents (
                    $this->path . DS .'objects'. DS . substr ($item, 0, 2) . DS . $item)))));

        return $this;
    }

    /**
     * Получить список элементов по компаратору
     * 
     * @param callable $comparator
     * 
     * @return array
     */
    public function where (callable $comparator): array
    {
        $items = [];

        foreach ($this->index['refs'] as $reference)
            foreach ($this->references[$reference] as $item)
                if ($comparator ($item = new Item (unserialize ($this->xor (file_get_contents (
                    $this->path . DS .'objects'. DS . substr ($item, 0, 2) . DS . $item))))))
                        $items[] = $item;

        return $items;
    }

    /**
     * Получить количество элементов
     * 
     * @return int
     */
    public function count (): int
    {
        $count = 0;

        foreach ($this->index['refs'] as $reference)
            $count += sizeof ($this->references[$reference]);
        
        return $count;
    }

    /**
     * Получить список всех элементов
     * 
     * @return array
     */
    public function list (): array
    {
        return $this->where (function () { return true; });
    }

    /**
     * Проверяет, является ли указанная директория размеченной под структуру
     * 
     * @param string $path
     * 
     * @return bool
     */
    public function isMapped (string $path): bool
    {
        return file_exists ($path . DS .'index.json') &&
            file_exists ($path . DS .'objects') &&
            file_exists ($path . DS .'refs');
    }

    /**
     * Загружает файл индексирования структуры
     * 
     * [@param string $path = null]
     * 
     * @return Structure
     * 
     * @throws \Exception
     */
    public function load (string $path = null): Structure
    {
        $this->index = json_decode (file_get_contents (($path ?? $this->path) . DS .'index.json'), true);

        if ($this->index['key_mask'] != hash ('sha256', $this->key ?? '`'))
            throw new \Exception ('Incorrect database key');

        if ($this->index['hash_func'] != $this->hash)
            throw new \Exception ('Incorrect hash algo');

        foreach ($this->index['refs'] as $reference)
            $this->references[$reference] = json_decode (file_get_contents (
                ($path ?? $this->path) . DS .'refs'. DS . $reference .'.json'), true);

        return $this;
    }

    /**
     * Сохраняет файл индекса структуры и рабочие объекты
     * 
     * [@param string $path = null]
     * 
     * @return Structure
     */
    public function save (string $path = null): Structure
    {
        $path ??= $this->path;

        if (!file_exists ($path))
            mkdir ($path, 0777, true);

        $this->index['refs']      ??= [];
        $this->index['key_mask']  ??= hash ('sha256', $this->key ?? '`');
        $this->index['hash_func'] ??= $this->hash;

        file_put_contents ($path . DS .'index.json', json_encode ($this->index));

        if (!file_exists ($objects = $path . DS .'objects'))
            mkdir ($objects);

        if (!file_exists ($refs = $path . DS .'refs'))
            mkdir ($refs);

        foreach ($this->references as $id => $reference)
            file_put_contents ($refs . DS . $id .'.json', json_encode ($reference));

        foreach ($this->objects as $id => $object)
        {
            if (!file_exists ($idpath = $objects . DS . substr ($id, 0, 2)))
                mkdir ($idpath);

            file_put_contents ($idpath . DS . $id, $this->xor (serialize ($object)));
        }

        return $this;
    }

    /**
     * XOR шифрование
     * 
     * @param string $data
     * 
     * @return string
     */
    protected function xor (string $data): string
    {
        return $this->key !== null ?
            $data ^ str_repeat ($this->key, strlen ($data)) : $data;
    }
}
