<?php

declare(strict_types=1);

namespace Awcodes\Focus\Enums;

enum FailureReason: string
{
    case WorkbenchUnavailable = 'Workbench unavailable';
    case Authentication = 'Authentication failed';
    case Navigation = 'Navigation failed';
    case SelectorNotFound = 'Selector not found';
    case SelectorAmbiguous = 'Selector matched more than one element';
    case SelectorHidden = 'Selector hidden';
    case Timeout = 'Timed out';
    case Callback = 'Callback failed';
    case Template = 'Template error';
    case Dependency = 'Screenshot failed';
    case Output = 'Could not write output';
    case Browser = 'Browser error';
}
