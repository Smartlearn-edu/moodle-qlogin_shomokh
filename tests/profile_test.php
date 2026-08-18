<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Profile test functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qlogin_shomokh;

/**
 * Tests for the self-service verification section on the Moodle profile.
 */
final class profile_test extends \advanced_testcase {
    public function test_current_eligible_user_gets_verification_profile_action(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_qlogin_shomokh');
        set_config('requireemail', 1, 'local_qlogin_shomokh');
        set_config('requirephone', 1, 'local_qlogin_shomokh');
        set_config('authtypes', 'manual', 'local_qlogin_shomokh');
        set_config('graceperioddays', 30, 'local_qlogin_shomokh');

        $user = $this->getDataGenerator()->create_user([
            'username' => '966501234567',
            'email' => 'profile-test@example.com',
            'auth' => 'manual',
        ]);
        $this->setUser($user);
        $tree = new \core_user\output\myprofile\tree();

        \local_qlogin_shomokh_myprofile_navigation($tree, $user, true);

        $this->assertArrayHasKey('qloginshomokhverification', $tree->categories);
        $this->assertArrayHasKey('qloginshomokhverificationstatus', $tree->nodes);
        $content = $tree->nodes['qloginshomokhverificationstatus']->content;
        $this->assertStringContainsString('/local/qlogin_shomokh/verify.php', $content);
        $this->assertStringContainsString('btn btn-primary', $content);
    }

    public function test_profile_action_is_not_added_when_viewing_another_user(): void {
        $this->resetAfterTest();
        $viewer = $this->getDataGenerator()->create_user();
        $profileowner = $this->getDataGenerator()->create_user([
            'username' => '966501234568',
            'auth' => 'manual',
        ]);
        $this->setUser($viewer);
        $tree = new \core_user\output\myprofile\tree();

        \local_qlogin_shomokh_myprofile_navigation($tree, $profileowner, false);

        $this->assertArrayNotHasKey('qloginshomokhverification', $tree->categories);
    }

    public function test_current_legacy_user_without_phone_gets_linking_action(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_qlogin_shomokh');
        set_config('requirephone', 1, 'local_qlogin_shomokh');
        set_config('authtypes', 'manual', 'local_qlogin_shomokh');
        set_config('selfclaimenabled', 1, 'local_qlogin_shomokh');
        $user = $this->getDataGenerator()->create_user([
            'username' => 'legacy.profile.student',
            'email' => 'legacy-profile@example.test',
            'auth' => 'manual',
        ]);
        $this->setUser($user);
        $tree = new \core_user\output\myprofile\tree();

        \local_qlogin_shomokh_myprofile_navigation($tree, $user, true);

        $this->assertArrayHasKey('qloginshomokhverification', $tree->categories);
        $this->assertArrayHasKey('qloginshomokhlinkphone', $tree->nodes);
        $content = $tree->nodes['qloginshomokhlinkphone']->content;
        $this->assertStringContainsString('/local/qlogin_shomokh/link_existing.php', $content);
    }
}
