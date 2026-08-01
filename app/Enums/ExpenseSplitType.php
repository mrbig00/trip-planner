<?php

namespace App\Enums;

enum ExpenseSplitType: string
{
    case Equal = 'equal';
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
