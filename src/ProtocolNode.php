<?php

namespace WhatsApi;

/**
 * Class ProtocolNode
 */
class ProtocolNode
{
    /**
     * @var
     */
    private static $cli;
    /**
     * @var string
     */
    private $tag;
    /**
     * @var array
     */
    private $attributeHash;
    /**
     * @var array
     */
    private $children;
    /**
     * @var
     */
    private $data;

    /**
     * @param $tag
     * @param $attributeHash
     * @param $children
     * @param $data
     */
    public function __construct($tag, $attributeHash = null, $children = null, $data = null)
    {
        $this->tag = $tag;
        $this->attributeHash = $attributeHash;
        $this->children = $children;
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * @return string[]
     */
    public function getAttributes()
    {
        return $this->attributeHash;
    }

    /**
     * @return ProtocolNode[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param string $indent
     * @param bool $isChild
     * @return string
     */
    public function nodeString($indent = '', $isChild = false)
    {
        //formatters
        $lt = '<';
        $gt = '>';
        $nl = '\n';
        if (!self::isCli()) {
            $lt = '&lt;';
            $gt = '&gt;';
            $nl = '<br />';
            $indent = str_replace(' ', '&nbsp;', $indent);
        }

        $ret = $indent . $lt . $this->tag;
        if ($this->attributeHash != null) {
            foreach ($this->attributeHash as $key => $value) {
                $ret .= ' ' . $key . '=\'' . $value . '\'';
            }
        }

        $ret .= $gt;
        $dataLen = strlen($this->data);

        if ($dataLen > 0) {
            $ret .= ($dataLen <= 1024) ? $this->data : ' ' . $dataLen . ' byte data';
        }

        if ($this->children) {
            $ret .= $nl;
            $foo = [];
            foreach ($this->children as $child) {
                $foo[] = $child->nodeString($indent . ' ', true);
            }
            $ret .= implode($nl, $foo);
            $ret .= $nl . $indent;
        }
        $ret .= $lt . '/' . $this->tag . $gt;

        if (!$isChild) {
            $ret .= $nl;
            if (!self::isCli()) {
                $ret .= $nl;
            }
        }

        return $ret;
    }

    /**
     * check if call is from command line
     * @return bool
     */
    private static function isCli()
    {
        if (self::$cli === null) {
            //initial setter
            self::$cli = php_sapi_name() == 'cli';
        }

        return self::$cli;
    }

    /**
     * @param string $needle
     * @return boolean
     */
    public function nodeIdContains($needle)
    {
        return (strpos($this->getAttribute('id'), $needle) !== false);
    }

    /**
     * @param $attribute
     * @return string
     */
    public function getAttribute($attribute)
    {
        $ret = '';
        if (isset($this->attributeHash[$attribute])) {
            $ret = $this->attributeHash[$attribute];
        }

        return $ret;
    }

    /**
     * @param $tag
     * @return bool
     */
    public function hasChild($tag)
    {
        return $this->getChild($tag) != null;
    }

    /**
     * @param $tag
     * @return ProtocolNode
     */
    public function getChild($tag)
    {
        $ret = null;
        if ($this->children) {
            if (is_int($tag)) {
                if (isset($this->children[$tag])) {
                    return $this->children[$tag];
                } else {
                    return null;
                }
            }
            foreach ($this->children as $child) {
                if (strcmp($child->tag, $tag) == 0) {
                    return $child;
                }
                $ret = $child->getChild($tag);
                if ($ret) {
                    return $ret;
                }
            }
        }

        return null;
    }

    /**
     * @param int $offset
     */
    public function refreshTimes($offset = 0)
    {
        if (isset($this->attributeHash['id'])) {
            $id = $this->attributeHash['id'];
            $parts = explode('-', $id);
            $parts[0] = time() + $offset;
            $this->attributeHash['id'] = implode('-', $parts);
        }
        if (isset($this->attributeHash['t'])) {
            $this->attributeHash['t'] = time();
        }
    }

    /**
     * Print human readable ProtocolNode object
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode([
            'tag' => $this->tag,
            'attributeHash' => $this->attributeHash,
            'children' => $this->children,
            'data' => $this->data
        ]);
    }
}