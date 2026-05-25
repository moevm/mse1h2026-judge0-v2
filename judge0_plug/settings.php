<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'qtype_judge0/server_url',
        get_string('server_url', 'qtype_judge0'),
        get_string('server_url_desc', 'qtype_judge0'),
        'http://server:2358',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'qtype_judge0/auth_header',
        get_string('auth_header', 'qtype_judge0'),
        get_string('auth_header_desc', 'qtype_judge0'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'qtype_judge0/auth_token',
        get_string('auth_token', 'qtype_judge0'),
        get_string('auth_token_desc', 'qtype_judge0'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'qtype_judge0/poll_timeout',
        get_string('poll_timeout', 'qtype_judge0'),
        get_string('poll_timeout_desc', 'qtype_judge0'),
        45,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'qtype_judge0/monaco_base_url',
        get_string('monaco_base_url', 'qtype_judge0'),
        get_string('monaco_base_url_desc', 'qtype_judge0'),
        '',
        PARAM_URL
    ));
}
