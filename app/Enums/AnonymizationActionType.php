<?php

namespace App\Enums;

enum AnonymizationActionType: string
{
    case ForgottenManual = 'forgotten_manual';
    case AutoAnonymizeGuest = 'auto_anonymize_guest';
    case AutoAnonymizeRegistered = 'auto_anonymize_registered';
}
