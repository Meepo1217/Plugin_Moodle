<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_antishot_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026110401) {
        // 1. Bổ sung cột maxviolations và lockscope vào bảng local_antishot_cm
        $tablecm = new xmldb_table('local_antishot_cm');

        $fieldmax = new xmldb_field('maxviolations', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'watermark');
        if (!$dbman->field_exists($tablecm, $fieldmax)) {
            $dbman->add_field($tablecm, $fieldmax);
        }

        $fieldscope = new xmldb_field('lockscope', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'maxviolations');
        if (!$dbman->field_exists($tablecm, $fieldscope)) {
            $dbman->add_field($tablecm, $fieldscope);
        }

        // 2. Tạo bảng local_antishot_logs
        $tablelogs = new xmldb_table('local_antishot_logs');
        $tablelogs->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $tablelogs->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $tablelogs->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $tablelogs->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $tablelogs->add_field('action', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $tablelogs->add_field('userip', XMLDB_TYPE_CHAR, '45', null, XMLDB_NOTNULL, null, null);
        $tablelogs->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $tablelogs->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $tablelogs->add_index('cmid_userid', XMLDB_INDEX_NOTUNIQUE, ['cmid', 'userid']);

        if (!$dbman->table_exists($tablelogs)) {
            $dbman->create_table($tablelogs);
        }

        // 3. Tạo bảng local_antishot_locks
        $tablelocks = new xmldb_table('local_antishot_locks');
        $tablelocks->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $tablelocks->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $tablelocks->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $tablelocks->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $tablelocks->add_field('lockscope', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $tablelocks->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $tablelocks->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $tablelocks->add_index('userid_lockscope', XMLDB_INDEX_NOTUNIQUE, ['userid', 'lockscope']);
        $tablelocks->add_index('userid_cmid', XMLDB_INDEX_NOTUNIQUE, ['userid', 'cmid']);

        if (!$dbman->table_exists($tablelocks)) {
            $dbman->create_table($tablelocks);
        }

        upgrade_plugin_savepoint(true, 2026110401, 'local', 'antishot');
    }

    return true;
}