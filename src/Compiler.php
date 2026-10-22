<?php

namespace Tualo\Office\OAuth2;

use Tualo\Office\Basic\TualoApplication;
use Tualo\Office\ExtJSCompiler\ICompiler;
use Tualo\Office\ExtJSCompiler\CompilerHelper;

class Compiler implements ICompiler
{
    public static function getFiles()
    {

        return CompilerHelper::getFiles(__DIR__, 'oauth2', 10003);
    }
}
