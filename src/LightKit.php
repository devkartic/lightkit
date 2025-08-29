<?php

namespace DevKartic\LightKit;

class LightKit
{
    public static function env(): Env\EnvManager
    {
        return new Env\EnvManager();
    }
}