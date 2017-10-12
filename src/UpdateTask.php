<?php

namespace Pantheon\TerminusUpstreamDev;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

/**
 * Class UpdateTask
 */
class UpdateTask implements Task {

  /** @var  \Pantheon\TerminusUpstreamDev\Commands\SitePushUpdateCommand $class */
  private $class;

  /** @var string $site */
  private $site;

  /** @var  mixed $options */
  private $options;

  /**
   * UpdateTask constructor.
   *
   * @param $class
   */
  public function __construct($class, $site, $options) {
    $this->class = $class;
    $this->site = $site;
    $this->options = $options;
  }

  /**
   * {@inheritdoc}
   * @param \Amp\Parallel\Worker\Environment $environment
   */
  public function run(Environment $environment) {
    //$this->class->pushUpdate($this->site, $this->options);
    $this->class->getLogger()->notice('{site}', ['site' => $this->site['name']]);
  }

}
