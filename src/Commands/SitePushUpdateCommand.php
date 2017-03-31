<?php

namespace Pantheon\TerminusUpstreamDev\Commands;

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Exceptions\TerminusProcessException;
use Symfony\Component\Console\Helper\ProgressBar;

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
    $git_location = '/tmp/'.$data['name'];

    $id = $data['id'];

    $list_envs = ['dev', 'test', 'live'];
    $output = $this->output;

    foreach($list_envs AS $current_env) {
      if ($site->getEnvironments()->get($current_env)->isInitialized()) {
        if (!$options['skip_backups']) {
          $this->log()->notice(
            '{site}: {env} creating backup',
            ['site' => $data['label'] ,'env' => $current_env]
          );
          $workflow = $site->getEnvironments()->get($current_env)->getBackups()->create();

          $progress = new ProgressBar($output);
          $progress->setFormat('[%bar%] %elapsed:6s% %memory:6s%');
          $progress->start();
          while (!$workflow->checkProgress()) {
            $progress->advance();
          }
          $progress->finish();

          $this->log()->notice(
            '{site}: {env} backup created',
            ['site' => $data['label'] ,'env' => $current_env]
          );
        }

        if($current_env == 'dev') {
          if ($options['use_git']) {
            $this->passthru("git clone ssh://codeserver.dev.{$id}@codeserver.dev.{$id}.drush.in:2222/~/repository.git {$git_location}");
            $repo = $options['repo'];
            if(is_null($repo)){
              $upstream_data = $site->getUpstream();
              $repo = $upstream_data->get('url');
            }
            $branch = $options['branch'];

            $this->passthru("git --git-dir='{$git_location}/.git' pull --no-edit --commit -X theirs {$repo} {$branch}" );
            $this->passthru("git --git-dir='{$git_location}/.git' push origin master");
            $this->passthru('rm -rf ' . $git_location);
          }else{
            $env = $site->getEnvironments()->get('dev');
            $updates = $env->getUpstreamStatus()->getUpdates();
            $count = count($updates);
            if ($count) {
              $this->log()->notice(
                'Applying {count} upstream update(s) to the {env} environment of {site_id}...',
                ['count' => $count, 'env' => $env->id, 'site_id' => $site->get('name'),]
              );

              $workflow = $env->applyUpstreamUpdates(false, true);

              $progress = new ProgressBar($output);
              $progress->setFormat('[%bar%] %elapsed:6s% %memory:6s%');
              $progress->start();
              while (!$workflow->checkProgress()) {
                $progress->advance();
              }
              $progress->finish();

              $this->log()->notice($workflow->getMessage());
            } else {
              $this->log()->warning('There are no available updates for this site.');
            }
          }
        }else {
          //Following command does not run for dev environment
          $this->log()->notice(
            '{site}: {env} deploying updates',
            ['site' => $data['label'], 'env' => $current_env]
          );

          $site->getEnvironments()->get($current_env)->deploy([
            'updatedb' => 0,
            'clear_cache' => 0,
            'annotation' => $options['message']
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
              $site->getEnvironments()->get($current_env)->sendCommandViaSsh('drush ' . implode(' ', $command['commands']));
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
