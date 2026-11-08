<?php
// Plugin: assignsubmission_pde
// Hooks into Moodle assignment file submissions and forwards .pde files to Docker.

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2025051102;       // YYYYMMDDXX
$plugin->requires  = 2024100700;       // Moodle 5.0 minimum
$plugin->component = 'assignsubmission_pde';
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '1.0.0';