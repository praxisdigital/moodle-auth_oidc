<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace auth_oidc;

use advanced_testcase;
use dml_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once $CFG->libdir . '/upgradelib.php';
require_once $CFG->dirroot . '/auth/oidc/db/upgrade.php';

final class upgrade_test extends advanced_testcase {

    /**
     * The auth_oidc_sid.sid field is expanded to store longer external session identifiers.
     */
    public function test_upgrade_expands_oidc_sid_field(): void {
        global $DB;

        $this->resetAfterTest(true);

        $database_manager = $DB->get_manager();
        $table = new \xmldb_table('auth_oidc_sid');
        $oldfield = new \xmldb_field('sid', XMLDB_TYPE_CHAR, '36', null, XMLDB_NOTNULL, null, null, 'userid');

        $database_manager->change_field_precision($table, $oldfield);

        $user = self::getDataGenerator()->create_user();
        $sid = str_repeat('a', 128);
        $record = [
            'userid' => $user->id,
            'sid' => $sid,
            'timecreated' => time(),
        ];

        try
        {
            $DB->insert_record('auth_oidc_sid', $record);
            $this->fail('Expected inserting a 128-character SID into the old 36-character field to fail.');
        } catch (dml_exception $e){}

        set_config('version', 2025040830, 'auth_oidc');
        xmldb_auth_oidc_upgrade(2025040830);

        $id = $DB->insert_record('auth_oidc_sid', $record);
        $stored_sid = $DB->get_field('auth_oidc_sid', 'sid', ['id' => $id]);

        $this->assertSame($sid, $stored_sid);
        $this->assertSame(128, strlen($stored_sid));
    }
}
