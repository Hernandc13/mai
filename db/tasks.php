<?php
// local/mai/db/tasks.php

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\local_mai\task\send_scheduled_reports',
        'blocking'  => 0,
        'minute'    => 'R',
        'hour'      => '*/6',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
    [
        'classname' => '\local_mai\task\check_inactivity_alerts',
        'blocking'  => 0,
        'minute'    => 'R',
        'hour'      => '*/6',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
];
