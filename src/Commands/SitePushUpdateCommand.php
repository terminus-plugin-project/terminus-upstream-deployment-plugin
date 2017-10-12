<?php

namespace Pantheon\TerminusUpstreamDev\Commands;

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Exceptions\TerminusProcessException;
use Pantheon\TerminusUpstreamDev\UpdateTask;
use Symfony\Component\Console\Helper\ProgressBar;

use Amp\Coroutine;
use Amp\Loop;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Promise;

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
   * @param array $options
   *
   * @option string $message Deploy message to include in test and live environments (optional)
   * @option boolean $skip_backups Skip taking backups before deploying updates (optional)
   * @option boolean $skip_updatedb Skip Updatedb Process (optional - Drupal only)
   * @option boolean $git Use the upstream git repo to pull changes from (optional)
   * @option string $repo The repository to use for updates (optional)
   * @option string $branch The branch of the repository to apply updates from (optional)
   *
   * @usage terminus site:update <site_id> --message="<message>"
   * @usage terminus site:update <site_id> --skip_backups
   * @usage terminus site:update <site_id> --message="<message>" --git
   * @usage terminus site:update <site_id> --message="<message>" --git --repo="<repo>"
   * @usage terminus site:update <site_id> --message="<message>" --git --repo="<repo>" --branch="<branch>"
   */
    public function pushUpdate($site_id, $options = [
    'message' => '',
    'skip_backups' => false,
    'skip_updatedb' => false,
    'git' => false,
    'repo' => null,
    'branch' => 'master'
    ])
    {
        $start = time();
        $site = $this->sites->get($site_id);
        $data = $site->serialize();
        $git_location = '/tmp/'.$data['name'];

        $list_envs = ['dev', 'test', 'live'];
        $output = $this->output;

        $this->log()->notice('{site} Starting', ['site' => $data['name']]);

        foreach ($list_envs as $current_env) {
            if ($site->getEnvironments()->get($current_env)->isInitialized()) {
                if (!$options['skip_backups']) {
                    $this->log()->notice(
                        '{site}: {env} creating backup',
                        ['site' => $data['name'] ,'env' => $current_env]
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
                        ['site' => $data['name'] ,'env' => $current_env]
                    );
                }

                if ($current_env == 'dev') {
                    $env = $site->getEnvironments()->get('dev');
                    if ($env->get('connection_mode') !== 'git') {
                      $change_count = count((array)$env->diffstat());
                      if ($change_count > 0) {
                        $this->log()->notice('{site}: Code uncommitted. Committing now.', ['site' => $site->get('name')]);
                        $workflow = $env->commitChanges($options['message']);
                        $progress = new ProgressBar($output);
                        $progress->setFormat('[%bar%] %elapsed:6s% %memory:6s%');
                        $progress->start();
                        while (!$workflow->checkProgress()) {
                          $progress->advance();
                        }
                        $progress->finish();
                      }


                      $this->log()->notice('{site}: Changing connection mode to git', ['site' => $site->get('name')]);
                      $workflow = $env->changeConnectionMode('git');
                      if (is_string($workflow)) {
                        $this->log()->notice($workflow);
                      } else {
                        while (!$workflow->checkProgress()) {
                          // TODO: (ajbarry) Add workflow progress output
                        }
                        $this->log()->notice($workflow->getMessage());
                      }
                    }

                    if ($options['git']) {
                        $this->passthru("rm -rf {$git_location}");
                        $site_git = $site->getEnvironments()->get('dev')->gitConnectionInfo()['url'];
                        $this->passthru("git clone {$site_git} {$git_location}");

                        $repo = $options['repo'];
                        if (is_null($repo)) {
                            $upstream_data = $site->getUpstream();
                            $repo = $upstream_data->get('url');
                        }
                        $branch = $options['branch'];

                        $message = (empty($options['message']) ? '' : "-m '{$options['message']}'");
                        $this->passthru("git -C '{$git_location}/.git' --work-tree='{$git_location}' remote add upstream {$repo}");
                        $this->passthru("git -C '{$git_location}/.git' --work-tree='{$git_location}' fetch upstream");
                        $this->passthru("git -C '{$git_location}/.git' --work-tree='{$git_location}' merge --no-ff --no-edit --commit {$message} -X theirs upstream/{$branch}");
                        $this->passthru("git -C '{$git_location}/.git' --work-tree='{$git_location}' push origin master");
                        $this->passthru("rm -rf {$git_location}");
                    } else {
                        $env = $site->getEnvironments()->get('dev');
                        $updates = (array)$env->getUpstreamStatus()->getUpdates()->update_log;
                        $count = count($updates);
                        if ($count > 0) {
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
                } else {
                    $env = $site->getEnvironments()->get($current_env);
                    if ($env->hasDeployableCode()) {
                      $this->log()->notice(
                        '{site}: {env} deploying updates',
                        ['site' => $data['name'], 'env' => $current_env]
                      );
                      $env->deploy([
                        'updatedb' => !$options['skip_updatedb'],
                        'clear_cache' => 1,
                        'annotation' => $options['message']
                      ]);
                    }else{
                      $this->log()->notice(
                        '{site}: {env} has no deployable code',
                        ['site' => $data['name'], 'env' => $current_env]
                      );
                    }
                }

                if ($data['framework'] == 'drupal' && !$options['skip_updatedb']) {
                    $commands = [
                    ['message' => '{site}: {env} drush updatedb', 'commands' => ['updatedb' ,'-y']],
                    ['message' => '{site}: {env} drush clear cache', 'commands' => ['cc' ,'all']]
                    ];
                    foreach ($commands as $command) {
                        try {
                            $this->log()->notice(
                                $command['message'],
                                ['site' => $data['name'], 'env' => $current_env]
                            );
                            $site->getEnvironments()->get($current_env)->sendCommandViaSsh('drush ' . implode(' ', $command['commands']));
                        } catch (TerminusProcessException $e) {
                            $this->log()->error(
                                $command['message'],
                                ['site' => $data['name'], 'env' => $current_env]
                            );
                        }
                    }
                }

                // Reset the environments
                $site->unsetEnvironments();
                // Sleeping for 15 seconds between each environment
                sleep(15);
            }
        }

        $this->log()->notice(
            'Completed Upstream Update for {site}',
            ['site' => $data['name']]
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
            ]
        );

        return true;
    }

  /**
   * Call passthru; throw an exception on failure.
   *
   * @param string $command
   * @param string $loggedCommand
   *
   * @throws \Pantheon\Terminus\Exceptions\TerminusException
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

  /**
   * Mass push updates from upstream to highest environment
   *
   * @authorize
   *
   * @command site:massupdate
   * @aliases massupdate
   *
   * @option string $org Organization Name or ID to pull list of sites from (optional)
   * @option string $tag Tags of sites to pull list from. Can only be used in conjunction with $org (optional)
   * @option string $team Team Name or ID to filter list of sites from (optional)
   * @option string $sites List of sites to run as part of the update process (optional)
   * @option string $include List of sites to include as part of the running process. (optional)
   * @option string $exclude List of sites to exclude from running updates on. (optional)
   * @option string $message Deploy message to include in test and live environments (optional)
   * @option boolean $skip_backups Skip taking backups before deploying updates (optional)
   * @option boolean $skip_updatedb Skip Updatedb Process (optional - Drupal only)
   * @option boolean $git Use the upstream git repo to pull changes from (optional)
   * @option string $repo The repository to use for updates (optional)
   * @option string $branch The branch of the repository to apply updates from (optional)
   * @option boolean $use-queue To queue these items and run them asynchronously (optional)
   * @option int $max-workers The max number of workers to run in the queue (optional)
   *
   * @usage terminus site:massupdate --message="<message>"
   * @usage terminus site:massupdate --skip_backups
   * @usage terminus site:massupdate --message="<message>" --git
   * @usage terminus site:massupdate --message="<message>" --git --repo="<repo>"
   * @usage terminus site:massupdate --message="<message>" --git --repo="<repo>" --branch="<branch>"
   *
   * @param array $options
   *
   * @throws \Pantheon\Terminus\Exceptions\TerminusException
   */
  public function pushMassUpdate($options = [
    'tag' => null,
    'org' => null,
    'team' => false,
    'owner' => null,
    'sites' => null,
    'include' => null,
    'exclude' => null,
    'message' => null,
    'skip_backups' => false,
    'skip_updatedb' => false,
    'repo' => null,
    'git' => false,
    'branch' => 'master',
    'use-queue' => false,
    'max-workers' => 4
  ])
  {
    $sites_include = array();
    // Add in additional sites.
    if(!is_null($options['include'])) {
      $tmp_sites_include = explode(',', $options['include']);
      $sites_include = [];
      if (count($tmp_sites_include) > 0) {
        foreach ($tmp_sites_include AS $site) {
          $item = $this->sites->get($site)->serialize();
          $sites_include[$item['id']] = $item;
        }
      }
    }

    // Check to see if the user has access to Organization
    $org = null;
    if (!is_null($organization = $options['org'])) {
      $org_object = $this->session()->getUser()->getOrganizationMemberships()->get($organization)->getOrganization();
      $org = $org_object->id;
    }

    // Filter sites, if necessary.
    $this->sites->fetch([
      'org_id' => $org,
      'team_only' => $options['team'],
    ]);

    if (!is_null($options['sites'])){
      $site_list = explode(',', $options['sites']);
      $this->sites->filter(function($site) use ($site_list) {
        if(array_search($site->id, $site_list) === FALSE && array_search($site->getName(), $site_list) === FALSE){
          return false;
        }
        return true;
      });
    }

    if (isset($options['owner']) && !is_null($owner = $options['owner'])) {
      if ($owner == 'me') {
        $owner = $this->session()->getUser()->id;
      }
      $this->sites->filterByOwner($owner);
    }

    if(!is_null($tag = $options['tag'])) {
      if(is_null($org)){
        throw new TerminusException('--tag option is only allowed when used with --org option.');
      }
      $this->sites->filterByTag($tag);
    }

    // Remove sites that marked for excluding.
    if (!is_null($options['exclude'])) {
      $exclude = explode(',', $options['exclude']);
      $this->sites->filter(function ($site) use ($exclude) {
        if (array_search($site->id, $exclude) !== FALSE || array_search($site->getName(), $exclude) !== FALSE) {
          return false;
        }
        return true;
      });
    }

    $sites = $sites = $this->sites->serialize();

    // Merge all filtered sites and all included sites.
    $sites = array_merge($sites, $sites_include);

    if($options['use-queue']){
      $upstreamTest = &$this;

      Loop::run(function () use ($upstreamTest, $sites, $options) {
        $pool = new DefaultPool(1, $options['max-workers']);
        $pool->start();
        $coroutines = [];

        foreach ($sites as $site) {
          $coroutines[] = function () use ($upstreamTest, $site, $options, $pool) {
            yield $pool->enqueue(new UpdateTask($upstreamTest, $site, $options));
          };
        }

        $coroutines = array_map(function (callable $coroutine): Coroutine {
          return new Coroutine($coroutine());
        }, $coroutines);


        echo "Promise \r\n";
        yield Promise\all($coroutines);
        return yield $pool->shutdown();
      });
    }
    else {
      foreach($sites AS $site) {
        //$upstreamTest->pushUpdate($site->id, $options);
        $this->log()->notice('{site}', ['site' => $site['name']]);
      }
    }
  }

  /**
   * @return \Psr\Log\LoggerInterface
   */
  public function getLogger() {
    return $this->log();
  }
}
