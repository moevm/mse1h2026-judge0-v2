<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_qtype_judge0_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026050400) {
        upgrade_plugin_savepoint(true, 2026050400, 'qtype', 'judge0');
    }

    if ($oldversion < 2026050401) {
        $table = new xmldb_table('qtype_judge0_options');
        $field = new xmldb_field('allowed_languages', XMLDB_TYPE_TEXT, null, null, null, null, null, 'input_generator_language_id');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026050401, 'qtype', 'judge0');
    }

    if ($oldversion < 2026050402) {
        $table = new xmldb_table('qtype_judge0_options');

        $field1 = new xmldb_field('reference_solution_language_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'allowed_languages');
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }

        $field2 = new xmldb_field('compiler_options', XMLDB_TYPE_TEXT, null, null, null, null, null, 'reference_solution_language_id');
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }

        upgrade_plugin_savepoint(true, 2026050402, 'qtype', 'judge0');
    }

    return true;
}
