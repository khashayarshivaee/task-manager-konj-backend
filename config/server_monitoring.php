<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Remote Server Monitoring
    |--------------------------------------------------------------------------
    |
    | Each remote server uses a dedicated,
    | read-only SSH connection.
    |
    | Disabled until SSH setup and tests pass.
    |
    */

    'remotes' => [

        'germany' => [

            'enabled' => (bool) env(
                'MONITORING_GERMANY_ENABLED',
                false,
            ),

            'host' => env(
                'MONITORING_GERMANY_SSH_HOST',
            ),

            'port' => (int) env(
                'MONITORING_GERMANY_SSH_PORT',
                22,
            ),

            'user' => env(
                'MONITORING_GERMANY_SSH_USER',
                'deploy',
            ),

            'identity_file' => env(
                'MONITORING_GERMANY_SSH_KEY_PATH',
                '/etc/task-manager/ssh/konj_monitoring_ro',
            ),

            'known_hosts_file' => env(
                'MONITORING_GERMANY_KNOWN_HOSTS',
                '/etc/task-manager/ssh/known_hosts',
            ),

            'remote_command' => 'snapshot',

            'connect_timeout' => 8,

            'process_timeout' => 20,
        ],


        'company' => [

            'enabled' => (bool) env(
                'MONITORING_COMPANY_ENABLED',
                false,
            ),

            'host' => env(
                'MONITORING_COMPANY_SSH_HOST',
            ),

            'port' => 22,

            'user' => 'konjmonitor',

            'identity_file' =>
                '/etc/task-manager/ssh/konj_almalinux_monitoring_ro',

            'known_hosts_file' =>
                '/etc/task-manager/ssh/known_hosts',

            'remote_command' => 'snapshot',

            'connect_timeout' => 8,

            'process_timeout' => 20,

        ],


    ],

];
