<?php
defined('MOODLE_INTERNAL') || die();

class assign_submission_pde extends assign_submission_plugin {

    public function get_name() {
        return get_string('pluginname', 'assignsubmission_pde');
    }

    public function get_settings(\MoodleQuickForm $mform) {
        $config_enabled            = $this->get_config('enabled');
        $config_maxsubmissions     = $this->get_config('maxsubmissions');
        $config_feedbackvisibility = $this->get_config('feedbackvisibility');
        $config_language           = $this->get_config('language');

        // Enable/disable automated feedback
        $mform->addElement(
            'selectyesno',
            'assignsubmission_pde_enabled',
            get_string('uc_enabled', 'assignsubmission_pde')
        );
        $mform->addHelpButton('assignsubmission_pde_enabled', 'uc_enabled', 'assignsubmission_pde');
        $mform->setDefault('assignsubmission_pde_enabled', $config_enabled ?? 1);

        // Maximum submissions
        $mform->addElement(
            'text',
            'assignsubmission_pde_maxsubmissions',
            get_string('uc_maxsubmissions', 'assignsubmission_pde')
        );
        $mform->setType('assignsubmission_pde_maxsubmissions', PARAM_INT);
        $mform->addHelpButton('assignsubmission_pde_maxsubmissions', 'uc_maxsubmissions', 'assignsubmission_pde');
        $mform->setDefault('assignsubmission_pde_maxsubmissions', $config_maxsubmissions ?? 0);

        // Feedback visibility
        $mform->addElement(
            'select',
            'assignsubmission_pde_feedbackvisibility',
            get_string('uc_feedbackvisibility', 'assignsubmission_pde'),
            [
                'immediate' => get_string('uc_visibility_immediate', 'assignsubmission_pde'),
                'afterdue'  => get_string('uc_visibility_afterdue', 'assignsubmission_pde'),
            ]
        );
        $mform->addHelpButton('assignsubmission_pde_feedbackvisibility', 'uc_feedbackvisibility', 'assignsubmission_pde');
        $mform->setDefault('assignsubmission_pde_feedbackvisibility', $config_feedbackvisibility ?? 'immediate');

        // Programming language
        $mform->addElement(
            'select',
            'assignsubmission_pde_language',
            get_string('uc_language', 'assignsubmission_pde'),
            [
                'processing' => 'Processing (Java)',
                'python'     => 'Python (coming soon)',
                'java'       => 'Java (coming soon)',
                'javascript' => 'JavaScript (coming soon)',
            ]
        );
        $mform->addHelpButton('assignsubmission_pde_language', 'uc_language', 'assignsubmission_pde');
        $mform->setDefault('assignsubmission_pde_language', $config_language ?? 'processing');

        // Topic feedback availability dates
        $mform->addElement('header', 'assignsubmission_pde_topicdates',
            get_string('uc_topicdates', 'assignsubmission_pde'));

        $topics = [
            'variables'     => 'Variables',
            'conditionals'  => 'Conditionals',
            'iteration'     => 'Iteration',
            'functions'     => 'Functions',
            'classes'       => 'Classes',
            'arrays'        => 'Arrays',
            'eventhandling' => 'Event Handling',
        ];

        foreach ($topics as $key => $label) {
    $mform->addElement('html', '<div style="border:1px solid #dee2e6; border-radius:6px; padding:12px; margin-bottom:8px;"><strong>' . $label . '</strong>');

    $mform->addElement(
        'date_selector',
        'assignsubmission_pde_' . $key . '_from',
        get_string('uc_topic_from', 'assignsubmission_pde'),
        ['optional' => true, 'label' => get_string('uc_topic_from_enable', 'assignsubmission_pde')]
    );
    $mform->setDefault(
        'assignsubmission_pde_' . $key . '_from',
        $this->get_config($key . '_from') ?? 0
    );

    $mform->addElement(
        'date_selector',
        'assignsubmission_pde_' . $key . '_until',
        get_string('uc_topic_until', 'assignsubmission_pde'),
        ['optional' => true, 'label' => get_string('uc_topic_until_enable', 'assignsubmission_pde')]
    );
    $mform->setDefault(
        'assignsubmission_pde_' . $key . '_until',
        $this->get_config($key . '_until') ?? 0
    );

    $mform->addElement('html', '</div>');
    }
    }

    public function save_settings(\stdClass $formdata) {
        $this->set_config('enabled', $formdata->assignsubmission_pde_enabled ?? 1);
        $this->set_config('maxsubmissions', $formdata->assignsubmission_pde_maxsubmissions ?? 0);
        $this->set_config('feedbackvisibility', $formdata->assignsubmission_pde_feedbackvisibility ?? 'immediate');
        $this->set_config('language', $formdata->assignsubmission_pde_language ?? 'processing');

        $topics = ['variables', 'conditionals', 'iteration', 'functions', 'classes', 'arrays', 'eventhandling'];
        foreach ($topics as $key) {
            $this->set_config($key . '_from',  $formdata->{'assignsubmission_pde_' . $key . '_from'}  ?? 0);
            $this->set_config($key . '_until', $formdata->{'assignsubmission_pde_' . $key . '_until'} ?? 0);
        }
        return true;
    }

    public function view_summary(\stdClass $submission, & $showviewlink) {
        global $PAGE;
        $showviewlink = false;

        $context = $PAGE->context;
        $cmid    = $PAGE->cm->id;

        $dashboard_url = new \moodle_url('/mod/assign/submission/pde/dashboard.php', ['cmid' => $cmid]);

        if (has_capability('mod/assign:grade', $context)) {
            return '<div style="margin-top:8px;">
                <a href="' . $dashboard_url . '" class="btn btn-sm btn-outline-primary">
                    📊 View PDE Dashboard
                </a>
            </div>';
        } else {
            return '<div style="margin-top:8px;">
                <a href="' . $dashboard_url . '" class="btn btn-sm btn-outline-secondary">
                    📋 View My Submission History
                </a>
            </div>';
        }
    }

    public function is_enabled() {
        return true;
    }

    public function allow_submissions() {
        return false;
    }
}