<?php

namespace Service;

class GndService
{
    /**
     * @var \Helper\Service\Gnd\GndProvider $gnd_provider
     */
    protected $gnd_provider;

    /**
     * Constructor
     *
     * @param \Helper\Service\Gnd\GndProvider $gnd_provider
     *
     */
    public function __construct($gnd_provider = null)
    {
        $this->gnd_provider = $gnd_provider;
    }

    public function lookup($gnd) {
        $result = $this->gnd_provider->lookup($gnd);
        if (!isset($result)) {
            return false;
        }

        // TODO: maybe post-process
        return $result;
    }

}
