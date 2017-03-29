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
class UpstreamTestBranchCommand extends TerminusCommand implements SiteAwareInterface {
  use SiteAwareTrait;

  /**
   * Test upstream changes on a specific multi-dev
   *
   * @authorize
   *
   * @command upstream:updates:test
   * @aliases upstream:test
   *
   * @param string $site_id Site in the format `site-name.env`
   *
   * @usage terminus upstream:updates:test <site>.<env>
   */
  public function pushBranch($site_env, $options = []){

  }

}