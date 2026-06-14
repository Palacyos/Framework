<?php

namespace App\Core;

final class App
{
    public static function createBuilder(): WebApplicationBuilder
    {
        return new WebApplicationBuilder();
    }
}
