<?php

namespace Deployer;

use Dotenv;

require './vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__)->load();

require 'recipe/laravel.php';
require 'contrib/php-fpm.php';
require 'contrib/npm.php';

set('application', 'SiVoteEngine');
set('repository', 'git@github.com:Institut-IP21/SiVoteEngine');
set('php_fpm_version', '8.0');

host('staging')
    ->set('labels', ['stage' => 'staging'])
    ->set('hostname', function () {
        return env('DEPLOY_HOSTNAME_STAGING');
    })
    ->set('remote_user', function () {
        return env('DEPLOY_USER_STAGING');
    })
    ->set('deploy_path', function () {
        return env('DEPLOY_DIRECTORY_STAGING');
    })
    ->set('shared_files', ['.env', 'etc/nginx.conf'])
    ->set('shared_dirs', ['storage']);

host('production')
    ->set('labels', ['stage' => 'production'])
    ->set('hostname', function () {
        return env('DEPLOY_HOSTNAME_PRODUCTION');
    })
    ->set('remote_user', function () {
        return env('DEPLOY_USER_PRODUCTION');
    })
    ->set('deploy_path', function () {
        return env('DEPLOY_DIRECTORY_PRODUCTION');
    })
    ->set('shared_files', ['.env', 'etc/nginx.conf'])
    ->set('shared_dirs', ['storage']);

task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'bun:install',
    'bun:production',
    'artisan:storage:link',
    'artisan:migrate',
    'artisan:evote:cache',
    'deploy:publish',
]);

task('bun:install', function () {
    cd('{{release_or_current_path}}');
    run('bun install');
});

task('bun:production', function () {
    cd('{{release_or_current_path}}');
    run('bun run production');
});

task('artisan:evote:cache', function () {
    cd('{{release_or_current_path}}');
    echo run('php artisan evote:cache');
});

after('deploy:failed', 'deploy:unlock');
