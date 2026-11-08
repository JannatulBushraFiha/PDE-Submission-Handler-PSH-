<?php
// Registers event observers for assignsubmission_pde.
// Moodle fires \mod_assign\event\submission_status_updated and
// \mod_assign\event\assessable_submitted when a student submits.
// We listen to assessable_submitted which fires after files are saved.

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_assign\event\assessable_submitted',
        'callback'  => '\assignsubmission_pde\observer::on_submission',
        'priority'  => 200,
        'internal'  => false,
    ],
];