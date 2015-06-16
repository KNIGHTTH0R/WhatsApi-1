<?php
/**
 * Created by PhpStorm.
 * User: askar
 * Date: 15.06.2015
 * Time: 18:39
 */

namespace WhatsApi;


abstract class BinTreeNode
{
    /**
     * @var KeyStream
     */
    protected $key;

    /**
     *
     */
    public function resetKey()
    {
        $this->key = null;
    }

    /**
     * @param KeyStream $key
     */
    public function setKey(KeyStream $key)
    {
        $this->key = $key;
    }
}