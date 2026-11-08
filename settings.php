<?php
// Settings for assignsubmission_pde — System Administrator level
// Accessible via Site Administration → Plugins → Assignment → PDE Submission Handler

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // ── Docker Configuration ───────────────────────────────────────────────────

    $settings->add(new admin_setting_heading(
        'assignsubmission_pde/docker_heading',
        get_string('docker_heading', 'assignsubmission_pde'),
        get_string('docker_heading_desc', 'assignsubmission_pde')
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_pde/docker_url',
        get_string('docker_url', 'assignsubmission_pde'),
        get_string('docker_url_desc', 'assignsubmission_pde'),
        'http://localhost:5001/process',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_pde/docker_timeout',
        get_string('docker_timeout', 'assignsubmission_pde'),
        get_string('docker_timeout_desc', 'assignsubmission_pde'),
        '30',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_pde/max_file_size',
        get_string('max_file_size', 'assignsubmission_pde'),
        get_string('max_file_size_desc', 'assignsubmission_pde'),
        '10',
        PARAM_INT
    ));

    // ── AI Configuration ───────────────────────────────────────────────────────

    $settings->add(new admin_setting_heading(
        'assignsubmission_pde/ai_heading',
        get_string('ai_heading', 'assignsubmission_pde'),
        get_string('ai_heading_desc', 'assignsubmission_pde')
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'assignsubmission_pde/gemini_api_key',
        get_string('gemini_api_key', 'assignsubmission_pde'),
        get_string('gemini_api_key_desc', 'assignsubmission_pde'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'assignsubmission_pde/ai_model',
        get_string('ai_model', 'assignsubmission_pde'),
        get_string('ai_model_desc', 'assignsubmission_pde'),
        'gemini-2.0-flash',
        [
            'gemini-2.0-flash'    => 'Gemini 2.0 Flash (Recommended)',
            'gemini-1.5-pro'      => 'Gemini 1.5 Pro',
            'gemini-1.5-flash'    => 'Gemini 1.5 Flash',
        ]
    ));

    // ── Plugin Behaviour ───────────────────────────────────────────────────────

    $settings->add(new admin_setting_heading(
        'assignsubmission_pde/behaviour_heading',
        get_string('behaviour_heading', 'assignsubmission_pde'),
        get_string('behaviour_heading_desc', 'assignsubmission_pde')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'assignsubmission_pde/enabled',
        get_string('plugin_enabled', 'assignsubmission_pde'),
        get_string('plugin_enabled_desc', 'assignsubmission_pde'),
        '1'
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_pde/max_violations',
        get_string('max_violations', 'assignsubmission_pde'),
        get_string('max_violations_desc', 'assignsubmission_pde'),
        '15',
        PARAM_INT
    ));

}