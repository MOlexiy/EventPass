<?php

namespace App\Support;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

class QrCode
{
    public static function png(string $text, int $size = 280): string
    {
        return (new Writer(new GDLibRenderer($size, 2)))->writeString($text);
    }
}
