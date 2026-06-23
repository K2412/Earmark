<?php

namespace App\Enums;

enum HouseholdPermission: string
{
    case UpdateHousehold = 'household:update';
    case DeleteHousehold = 'household:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';
}
