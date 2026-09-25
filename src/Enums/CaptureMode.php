<?php

declare(strict_types=1);

namespace Awcodes\Focus\Enums;

enum CaptureMode: string
{
    case Focus = 'focus';
    case Viewport = 'viewport';
    case FullPage = 'fullPage';
}
