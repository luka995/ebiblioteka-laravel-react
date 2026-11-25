<?php

namespace App\Enums;

enum ClassTransferRowStatusEnum: string
{
    case Ready = 'ready';
    case NeedsSelection = 'needs_selection';
    case NeedsTarget = 'needs_target';
    case Graduating = 'graduating';
    case Empty = 'empty';
    case Ineligible = 'ineligible';
}
