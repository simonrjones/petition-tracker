<?php
namespace Deployer;

require 'recipe/common.php';

// Project name
set('application', 'Petition tracker');

// Project repository
set('repository', 'git@bitbucket.org:simonrjones/petition-tracker.git');

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);

// Shared files/dirs between deploys
set('shared_dirs', ['data', 'web']);

set('allow_anonymous_stats', false);

// Custom
set('keep_releases', 10);

// Hosts

host('production')
    ->hostname('simonrjones')
    ->user('ec2-user')
    ->set('deploy_path','/data/var/www/vhosts/tracker.simonrjones.net/production');

// Tasks
desc('Deploy tracker.simonrjones.net');
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
//    'deploy:vendors',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
]);

// [Optional] If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');