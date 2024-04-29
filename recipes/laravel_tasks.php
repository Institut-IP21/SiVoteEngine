<?php

namespace Deployer;

task('horizon:restart-staging', function () {
    run("sudo supervisorctl restart horizon-staging");
})->onStage('staging');

task('horizon:restart-prod', function () {
    run("sudo supervisorctl restart horizon-prod");
})->onStage('prod');
