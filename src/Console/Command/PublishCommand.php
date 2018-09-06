<?php

/**
 * Release notifier.
 *
 * @package    release-notifier
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @copyright  2018 netzmacht David Molineus
 * @license    LGPL-3.0-or-later https://github.com/netzmacht/release-notifier/blob/master/LICENSE
 * @filesource
 */

declare(strict_types=1);

namespace App\Console\Command;

use App\History\History;
use App\History\LastRun;
use App\Package\Releases;
use App\Publisher\DelegatingPublisher;
use App\Publisher\Publisher;
use App\Publisher\PublisherConfiguration;
use App\Publisher\PublisherFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PublishReleaseNoteCommand
 */
final class PublishCommand extends AbstractConfigBasedCommand
{
    /**
     * Publisher factory.
     *
     * @var PublisherFactory
     */
    private $publisherFactory;

    /**
     * Package releases.
     *
     * @var Releases
     */
    private $packageReleases;

    /**
     * PublishReleaseNoteCommand constructor.
     *
     * @param PublisherFactory $publisherFactory The publisher factory.
     * @param Releases         $packageReleases  Package releases.
     * @param History          $history          Last run information.
     */
    public function __construct(
        PublisherFactory $publisherFactory,
        Releases $packageReleases,
        History $history
    ) {
        parent::__construct($history, 'publish');

        $this->publisherFactory = $publisherFactory;
        $this->packageReleases  = $packageReleases;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setDescription('Publish new released packages based on a configuration file');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configFile = $this->getConfigFileArgument($input);
        $config     = $this->loadConfig($input);
        $publisher  = $this->createPublisher($config);
        $total      = 0;

        foreach ($config['packages'] as $package) {
            $lastRun  = $this->history->get($configFile, $package['package']);
            $since    = $this->getSince($input, $lastRun);
            $releases = $this->packageReleases->since($package['package'], $since);

            $count  = count($releases);
            $total += $count;

            $output->writeln(
                sprintf(
                    '%s New releases of "%s" since %s',
                    $count,
                    $package['package'],
                    $since->format(DATE_ATOM)
                )
            );

            foreach ($releases as $release) {
                $publisher->publish($release);

                $output->writeln(
                    sprintf(' - %s release published', $release->version()),
                    OutputInterface::VERBOSITY_VERBOSE
                );
            }

            $this->updateLastRun(
                $this->getConfigFileArgument($input),
                $package['package'],
                LastRun::now($releases->lastModified()),
                $input->getOption('ignore-last-run')
            );
        }

        $output->writeln(sprintf('%s releases published', $total));
    }

    /**
     * Create the publisher.
     *
     * @param array $config The configuration.
     *
     * @return Publisher
     */
    private function createPublisher(array $config): Publisher
    {
        $publishers = [];

        foreach ($config['publishers'] as $publisherConfig) {
            $publishers[] = $this->publisherFactory->create(
                PublisherConfiguration::fromArray($publisherConfig),
                $config['packages']
            );
        }

        return new DelegatingPublisher($publishers);
    }

    /**
     * Update the last run information.
     *
     * @param string  $configurationFile The configuration file.
     * @param string  $package           The current package.
     * @param LastRun $lastRun           Last run information.
     * @param bool    $ignore            If true the last run is ignored.
     *
     * @return void
     */
    private function updateLastRun(string $configurationFile, string $package, LastRun $lastRun, bool $ignore): void
    {
        if ($ignore) {
            return;
        }

        $this->history->update($configurationFile, $package, $lastRun);
    }
}
