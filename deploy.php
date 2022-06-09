<?php

namespace Deployer;

$env = \Dotenv\Dotenv::createImmutable(__DIR__)->load();

require 'recipe/laravel.php';
require 'contrib/php-fpm.php';
require 'contrib/npm.php';

set('application', 'SiVoteEngine');
set('repository', 'git@github.com:Institut-IP21/SiVoteEngine');
set('php_fpm_version', '7.4');

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
    'yarn',
    'yarn:production',
    'artisan:storage:link',
    'artisan:migrate',
    'artisan:evote:cache',
    'deploy:publish',
]);

task('yarn', function () {
    cd('{{release_or_current_path}}');
    run('yarn');
});

task('yarn:production', function () {
    cd('{{release_or_current_path}}');
    run('yarn production');
});

task('artisan:evote:cache', function () {
    cd('{{release_or_current_path}}');
    echo run('php artisan evote:cache');
});

after('deploy:failed', 'deploy:unlock');
