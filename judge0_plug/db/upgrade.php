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

    if ($oldversion < 2026050506) {
        $table = new xmldb_table('qtype_judge0_options');

        $field1 = new xmldb_field('cpu_time_limit', XMLDB_TYPE_NUMBER, '10, 3', null, null, null, null, 'compiler_options');
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }

        $field2 = new xmldb_field('memory_limit', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'cpu_time_limit');
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }

        upgrade_plugin_savepoint(true, 2026050506, 'qtype', 'judge0');
    }

    if ($oldversion < 2026050507) {
        $table = new xmldb_table('qtype_judge0_queue');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('token', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('slot', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('languageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('answerhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('answer', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'queued');
        $table->add_field('fraction', XMLDB_TYPE_NUMBER, '10, 7', null, null, null, null);
        $table->add_field('state', XMLDB_TYPE_CHAR, '32', null, null, null, null);
        $table->add_field('resultjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('error', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timestarted', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('questionid', XMLDB_KEY_FOREIGN, ['questionid'], 'question', ['id']);

        $table->add_index('token', XMLDB_INDEX_UNIQUE, ['token']);
        $table->add_index('ownerhash', XMLDB_INDEX_NOTUNIQUE, ['userid', 'attemptid', 'slot', 'questionid', 'answerhash']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026050507, 'qtype', 'judge0');
    }

    return true;
}
