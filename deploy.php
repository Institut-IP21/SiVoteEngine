<?php

namespace Deployer;

require 'recipe/laravel.php';
require 'recipe/yarn.php';
require 'contrib/php-fpm.php';
require 'contrib/npm.php';

set('application', 'SiVoteEngine');
set('repository', 'git@github.com:Institut-IP21/evote-engine.git');
set('php_fpm_version', '7.4');

host('staging')
    ->set('labels', ['stage' => 'staging'])
    ->set('hostname', function () {
        return getenv('DEPLOY_HOSTNAME_STAGING');
    })
    ->set('remote_user', function () {
        return getenv('DEPLOY_USER_STAGING');
    })
    ->set('deploy_path', function () {
        return getenv('DEPLOY_DIRECTORY_STAGING');
    })
    ->set('shared_files', ['.env', 'etc/nginx.conf'])
    ->set('shared_dirs', ['storage']);

task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'artisan:storage:link',
    'artisan:optimize',
    'artisan:model:scan',
    'artisan:route:scan',
    'artisan:migrate',
    'yarn:install',
    'yarn:production',
    'deploy:publish',
]);

task('yarn:production', function () {
    cd('{{release_or_current_path}}');
    run('yarn production');
});

task('artisan:model:scan', function () {
    cd('{{release_or_current_path}}');
    echo run('php artisan model:scan');
});

task('artisan:route:scan', function () {
    cd('{{release_or_current_path}}');
    echo run('php artisan route:scan');
});

after('deploy:failed', 'deploy:unlock');
