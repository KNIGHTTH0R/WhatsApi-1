<?php

namespace WhatsApi\events;

/**
 * Class EventsManager
 */
class EventsManager
{
    /**
     * @var array
     */
    private $listeners = [];

    /**
     * @param $event
     * @param $callback
     */
    public function bind($event, $callback)
    {
        $this->listeners[$event][] = $callback;
    }

    /**
     * @param $event
     * @param array $parameters
     */
    public function fire($event, array $parameters)
    {
        if (!empty($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $listener) {
                call_user_func_array($listener, $parameters);
            }
        }
    }
}
