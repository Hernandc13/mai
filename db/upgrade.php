<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade code for local_mai.
 */
function xmldb_local_mai_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Ejemplo: versión objetivo 2025111313.
    if ($oldversion < 2025111313) {

        // Define table local_mai_notif_rules.
        $table = new xmldb_table('local_mai_notif_rules');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);

        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');

        $table->add_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('termid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_field('monitored_courses', XMLDB_TYPE_TEXT, null, null, null, null, null);

        $table->add_field('reportenabled', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('report_frequency', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'weekly');
        $table->add_field('report_format', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'pdf');
        $table->add_field('report_recipients', XMLDB_TYPE_TEXT, 'small', null, null, null, null);
        $table->add_field('report_template', XMLDB_TYPE_TEXT, 'medium', null, null, null, null);
        $table->add_field('last_report_sent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_field('alertsenabled', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('alert_days_inactive', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '7');
        $table->add_field('alert_group_inactivity', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '50');
        $table->add_field('alert_recipients', XMLDB_TYPE_TEXT, 'small', null, null, null, null);
        $table->add_field('alert_student_message', XMLDB_TYPE_TEXT, 'medium', null, null, null, null);
        $table->add_field('alert_coord_message', XMLDB_TYPE_TEXT, 'medium', null, null, null, null);
        $table->add_field('alert_channels', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'internal');
        $table->add_field('last_alerts_checked', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Indexes.
        $table->add_index('programid_idx', XMLDB_INDEX_NOTUNIQUE, ['programid']);
        $table->add_index('termid_idx', XMLDB_INDEX_NOTUNIQUE, ['termid']);
        $table->add_index('enabled_idx', XMLDB_INDEX_NOTUNIQUE, ['enabled']);

        // Create table if not exists.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Guardar savepoint.
        upgrade_plugin_savepoint(true, 2025111313, 'local', 'mai');
    }

    return true;
}
