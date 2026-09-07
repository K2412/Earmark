<?php

namespace App\Enums;

enum PositionPurpose: string
{
    case Investable = 'investable';
    case PrimaryResidence = 'primary_residence';
    case HomePurchase = 'home_purchase';
    case Other = 'other';
}
