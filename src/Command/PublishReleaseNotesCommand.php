<?php

/**
 * Packagist release publisher.
 *
 * @package    packagist-release-publisher
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @copyright  2018 netzmacht David Molineus
 * @license    LGPL-3.0-or-later https://github.com/netzmacht/tapatalk-client-api/blob/master/LICENSE
 * @filesource
 */

declare(strict_types=1);

namespace App\Command;

use App\Packagist\PackageReleases;
use App\Packagist\Release;
use App\Publisher\Publisher;
use App\Publisher\PublisherConfiguration;
use App\Publisher\PublisherFactory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class PublishReleaseNoteCommand
 */
final class PublishReleaseNotesCommand extends AbstractCommand
{
    /**
     * Publisher factory.
     *
     * @var PublisherFactory
     */
    private $publisherFactory;

    /**
     * The file system.
     *
     * @var Filesystem
     */
    private $filesystem;

    /**
     * Package releases.
     *
     * @var PackageReleases
     */
    private $packageReleases;

    /**
     * File storing since date.
     *
     * @var string
     */
    private $lastRunFile;

    /**
     * PublishReleaseNoteCommand constructor.
     *
     * @param PublisherFactory $publisherFactory The publisher factory.
     * @param Filesystem       $filesystem       Filesystem.
     * @param PackageReleases  $packageReleases  Package releases.
     * @param string           $lastRunFile      File storing since date.
     */
    public function __construct(
        PublisherFactory $publisherFactory,
        Filesystem $filesystem,
        PackageReleases $packageReleases,
        string $lastRunFile
    ) {
        parent::__construct('publish-notes');

        $this->publisherFactory = $publisherFactory;
        $this->filesystem       = $filesystem;
        $this->packageReleases  = $packageReleases;
        $this->lastRunFile      = $lastRunFile;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->addArgument(
            'config',
            InputArgument::REQUIRED,
            'Path to the config.php file.'
        );

        $this->addOption(
            'since',
            's',
            InputOption::VALUE_REQUIRED,
            'Optional time reference, parseable by \DateTimeImmutable'
        );

        $this->addOption(
            'ignore-last-run',
            'i',
            InputOption::VALUE_NONE,
            'If true the last run is ignored.'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $since  = $this->getSince($input);
        $count  = 0;
        $config = $this->loadConfig($input);
        $publisher = $this->createPublisher($config);

        $output->writeln(sprintf('Check packages released since %s:', $since->format(DATE_ATOM)));

        foreach ($this->packageReleases->since($config['packages'], $since) as $release) {
            $publisher->publish($release);
            $count++;

            $this->logReleasedPublished($output, $release);
        }

        $this->logSummary($output, $count);

        $this->updateLastRun($input->getOption('ignore-last-run'));
    }

    /**
     * Get the sinc date.
     *
     * @param InputInterface $input The console input.
     *
     * @return \DateTimeInterface
     */
    private function getSince(InputInterface $input): \DateTimeInterface
    {
        $lastRun = null;

        if (!$input->getOption('ignore-last-run') && $this->filesystem->exists($this->lastRunFile)) {
            $content = file_get_contents($this->lastRunFile);
            $lastRun = new \DateTimeImmutable($content);
        }

        if ($input->getOption('since')) {
            $since = new \DateTimeImmutable($input->getOption('since'));

            if ($since > $lastRun) {
                return $since;
            }

            return $lastRun;
        }

        if ($lastRun) {
            return $lastRun;
        }

        $dateTime = new \DateTimeImmutable();
        $dateTime = $dateTime->setTime(0, 0);

        return $dateTime;
    }


    /**
     * @param array $config
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
     * Log the release published note.
     *
     * @param OutputInterface $output  The console output.
     * @param Release         $release The release.
     *
     * @return void
     */
    private function logReleasedPublished(OutputInterface $output, Release $release): void
    {
        $output->writeln(sprintf(' - Release "%s" published', $release), OutputInterface::VERBOSITY_VERBOSE);
    }

    /**
     * Log the release published note.
     *
     * @param OutputInterface $output The console output.
     * @param int             $count  The number of releases.
     *
     * @return void
     */
    private function logSummary(OutputInterface $output, int $count): void
    {
        if ($count) {
            $output->writeln(sprintf('%s release note published.', $count), OutputInterface::VERBOSITY_NORMAL);
        } else {
            $output->writeln(sprintf('No releases found.', $count), OutputInterface::VERBOSITY_VERBOSE);
        }
    }

    /**
     * Update the last run information.
     *
     * @param bool $ignore If true the last run is ignored.
     *
     * @return void
     */
    private function updateLastRun(bool $ignore): void
    {
        if ($ignore) {
            return;
        }

        $this->filesystem->dumpFile($this->lastRunFile, date(DATE_ATOM));
    }
}
