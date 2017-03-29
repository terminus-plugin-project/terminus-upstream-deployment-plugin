<?php

namespace Pantheon\TerminusUpstreamDev\Commands;

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Exceptions\TerminusProcessException;
use Pantheon\Terminus\Commands\Backup;
use Pantheon\Terminus\Commands\Upstream;
use Pantheon\Terminus\Commands\Env;
use Pantheon\Terminus\Commands\Remote\DrushCommand;

/**
 * Class SitePushUpdateCommand
 * Pushes updates starting with Upstream and up to highest environment
 */
class SitePushUpdateCommand extends TerminusCommand implements SiteAwareInterface
{
  use SiteAwareTrait;

  /**
   * Pushed updates from upstream to highest environment
   *
   * @authorize
   * 
   * @command site:update
   * @aliases update
   *
   * @param string $site_id Site in the format `site-name`
   * @option string $message Deploy message to include in test and live environments (optional)
   * @option boolean $skip_backups Skip taking backups before deploying updates (optional)
   * @option boolean $use_git Use the upstream git repo to pull changes from (optional)
   * @option string $repo The repository to use for updates (optional)
   * @option string $branch The branch of the repository to apply updates from (optional)
   *
   * @usage terminus site:update <site_id> --message="<message>"
   * @usage terminus site:update <site_id> --skip_backups
   * @usage terminus site:update <site_id> --message="<message>" --use_git
   * @usage terminus site:update <site_id> --message="<message>" --use_git --repo="<repo>"
   * @usage terminus site:update <site_id> --message="<message>" --use_git --repo="<repo>" --branch="<branch>"
   */
  public function pushUpdate($site_id, $options = [
    'message' => '',
    'skip_backups' => FALSE,
    'use_git' => FALSE,
    'repo' => NULL,
    'branch' => 'master'
  ]){
    $start = time();
    $site = $this->sites->get($site_id);
    $data = $site->serialize();
    $envs = $site->getEnvironments()->serialize();
    $git_location = '/tmp/'.$data['name'];

    $id = $data['id'];

    $drush = new DrushCommand($this->sites);
    $drush->setLogger($this->logger);
    $drush->setSites($this->sites);

    $backup = new Backup\CreateCommand($this->sites);
    $backup->setLogger($this->logger);
    $backup->setSites($this->sites);

    $deploy = new Env\DeployCommand($this->sites);
    $deploy->setLogger($this->logger);
    $deploy->setSites($this->sites);

    $upstream = new Upstream\Updates\ApplyCommand($this->sites);
    $upstream->setLogger($this->logger);
    $upstream->setSites($this->sites);

    $list_envs = ['dev', 'test', 'live'];

    foreach($list_envs AS $current_env) {
      if ($envs[$current_env]['initialized']) {
        if (!$options['skip_backups']) {
          $this->log()->notice(
            '{site}: {env} creating backup',
            ['site' => $data['label'] ,'env' => $current_env]
          );
          $backup->create($site_id . '.' . $current_env);
        }

        if($current_env == 'dev') {
          //Following should only run for the dev environment
          if ($options['use_git']) {
            $this->passthru('git clone ssh://codeserver.dev.' . $id . '@codeserver.dev.' . $id . '.drush.in:2222/~/repository.git ' . $git_location);
            $repo = $options['repo'];
            if(is_null($repo)){
              $upstream_info = explode(':', $data['upstream']);
              $upstream_data = $this->session()->getUser()->getUpstreams()->get($upstream_info[0])->serialize();
              $repo = $upstream_data['upstream'];
            }
            $branch = $options['branch'];

            $message = ($options['message'] == '' ? '' : '-m "' . $options['message'] . '""');
            $this->passthru("git --git-dir='{$git_location}/.git' pull --no-edit --commit -X theirs {$message} {$repo} {$branch}" );
            $this->passthru("git --git-dir='{$git_location}/.git' pull push origin master");
            $this->passthru('rm -rf ' . $git_location);
          }else{
            $upstream->applyUpstreamUpdates($site_id.'.dev', ['accept-upstream' => true]);
          }
        }else {
          //Following command does not run for dev environment
          $this->log()->notice(
            '{site}: {env} deploying updates',
            ['site' => $data['label'], 'env' => $current_env]
          );
          $deploy->deploy($site_id . '.' . $current_env, [
            'note' => $options['message'],
          ]);
        }

        if ($data['framework'] == 'drupal') {
          $commands = [
            ['message' => '{site}: {env} drush updatedb', 'commands' => ['updatedb' ,'-y']],
            ['message' => '{site}: {env} drush clear cache', 'commands' => ['cc' ,'all']]
          ];
          foreach($commands AS $command) {
            try {
              $this->log()->notice(
                $command['message'],
                ['site' => $data['label'], 'env' => $current_env]
              );
              $drush->drushCommand($site_id . '.' . $current_env, $command['commands']);
            }catch(TerminusProcessException $e){
              $this->log()->error(
                $command['message'],
                ['site' => $data['label'], 'env' => $current_env]
              );
            }
          }
        }
      }
    }

    $this->log()->notice(
      'Completed Upstream Update for {site}', ['site' => $data['label']]
    );
    $end = time();

    $diff = $end - $start;
    $this->log()->notice(
      "Started: {start}\r\nEnded: {end}\r\nTotal Time: {hours} hours {minutes} minutes {seconds} seconds",
      [
        'start' => date('Y-m-d H:i:s', $start),
        'end' => date('Y-m-d H:i:s', $end),
        'hours' => intval($diff / 3600),
        'minutes' => intval(($diff % 3600) / 60),
        'seconds' => intval($diff % 60)
      ]);
  }

  /**
   * Call passthru; throw an exception on failure.
   *
   * @param string $command
   */
  protected function passthru($command, $loggedCommand = '')
  {
    $result = 0;
    $loggedCommand = empty($loggedCommand) ? $command : $loggedCommand;
    // TODO: How noisy do we want to be?
    $this->log()->notice("Running {cmd}", ['cmd' => $loggedCommand]);
    passthru($command, $result);
    if ($result != 0) {
      throw new TerminusException('Command `{command}` failed with exit code {status}', ['command' => $loggedCommand, 'status' => $result]);
    }
  }
}
