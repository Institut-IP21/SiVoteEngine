<?php

namespace Deployer;

task('artisan:model:scan', function () {
    run("php {{release_path}}/artisan model:scan");
});

task('artisan:route:scan', function () {
    run("php {{release_path}}/artisan route:scan");
});

task('horizon:restart-staging', function () {
    run("sudo supervisorctl restart horizon-staging");
})->onStage('staging');

task('horizon:restart-prod', function () {
    run("sudo supervisorctl restart horizon-prod");
})->onStage('prod');
