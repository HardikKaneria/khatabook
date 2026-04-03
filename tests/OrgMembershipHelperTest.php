<?php

kbs_test('org membership helper allows only known organizations', function (): void {
    $memberships = [
        ['org_id' => 11, 'role' => 'company_admin'],
        ['org_id' => 22, 'role' => 'c_manager'],
    ];

    kbs_assert_true(vy_org_membership_allows($memberships, 11), 'Accessible org should be allowed.');
    kbs_assert_true(vy_org_membership_allows($memberships, 22), 'Second accessible org should be allowed.');
    kbs_assert_false(vy_org_membership_allows($memberships, 33), 'Unknown org should be denied.');
});
