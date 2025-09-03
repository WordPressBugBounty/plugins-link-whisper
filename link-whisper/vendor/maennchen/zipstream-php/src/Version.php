<?php

declare (strict_types=1);
namespace LWVendor\ZipStream;

enum Version : int
{
    case STORE = 0xa;
    // 1.00
    case DEFLATE = 0x14;
    // 2.00
    case ZIP64 = 0x2d;
    // 4.50
}
