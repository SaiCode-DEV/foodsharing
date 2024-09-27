<?php

/* If you make changes here please talk to an admin */

namespace Deployer;

require 'recipe/common.php';
require 'contrib/cachetool.php';

// Project name
set('application', 'foodsharing');

// Project repository
set('repository', 'git@gitlab.com:foodsharing-dev/foodsharing.git');

// Shared files/dirs between deploys
set('shared_files', ['config.inc.prod.php']);
set('shared_dirs', ['images', 'data', 'tmp', 'uploads', 'keys']);

// Writable dirs by web server
set('writable_dirs', ['tmp', 'cache', 'var']);
set('http_user', 'fs-php');
set('http_group', 'www-data');
set('remote_user', 'deploy');
set('deploy_path', '/var/www/{{alias}}');
set('cachetool', '/run/php-fpm-{{alias}}.sock');

// default timeout of 300 was failing sometimes
set('default_timeout', 600);

// Hosts
host('beta')
    ->setHostname('foodsharing.network');

host('production')
    ->setHostname('foodsharing.network');

// Tasks
desc('Create the revision information');
task('deploy:create_revision', function () {
    $revision = input()->getOption('revision');
    cd('{{release_path}}');
    run("./scripts/deploy-generate_revision $revision");
});

task('deploy:update_code', function () {
    upload(__DIR__ . '/', '{{release_path}}', [
        '--exclude=.git',
        '--exclude=client',
        '--exclude=migrations',
        '--exclude=deployer',
        '--compress-level=9'
    ]);
});

task('deploy:cache:warmup', function () {
    run('sudo -u {{http_user}} -g {{http_group}} FS_ENV=prod php-{{alias}} {{release_path}}/bin/console cache:warmup -e prod');
})->desc('Warmup symfony cache');

task('deploy:permissions', function () {
    run('
		sudo chgrp -R {{http_group}} {{release_path}};
		chmod 750 {{release_path}};
	');
})->desc('Allow only www-data to access the files');

desc('Deploy your project');
task('deploy', [
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:writable',
    'deploy:shared',
    'deploy:clear_paths',
    'deploy:permissions',
    'deploy:create_revision',
    'deploy:cache:warmup',
    'deploy:symlink',
    'cachetool:clear:opcache',
    'deploy:unlock',
    'deploy:cleanup',
    'deploy:success'
]);

// [Optional] If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');
