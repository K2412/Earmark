<?php

namespace App\Data;

readonly class HouseholdPermissions
{
    public function __construct(
        public bool $canUpdateHousehold,
        public bool $canDeleteHousehold,
        public bool $canAddMember,
        public bool $canUpdateMember,
        public bool $canRemoveMember,
        public bool $canCreateInvitation,
        public bool $canCancelInvitation,
    ) {
        //
    }
}
