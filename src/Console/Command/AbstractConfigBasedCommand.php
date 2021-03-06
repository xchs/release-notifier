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

namespace Netzmacht\ReleaseNotifier\Console\Command;

use Assert\Assert;
use Assert\InvalidArgumentException;
use Netzmacht\ReleaseNotifier\History\History;
use Netzmacht\ReleaseNotifier\History\LastRun;
use DateTimeImmutable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Webmozart\PathUtil\Path;
use function array_key_exists;

/**
 * Class AbstractCommand
 */
abstract class AbstractConfigBasedCommand extends Command
{
    /**
     * Last run information.
     *
     * @var History
     */
    protected $history;

    /**
     * AbstractConfigBasedCommand constructor.
     *
     * @param History     $history Last run information.
     * @param null|string $name    Command name.
     */
    public function __construct(History $history, ?string $name = null)
    {
        parent::__construct($name);

        $this->history = $history;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
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
     * Load the configuration.
     *
     * @param InputInterface $input The console input.
     *
     * @return array
     *
     * @throws InvalidArgumentException When invalid config file is given.
     */
    protected function loadConfig(InputInterface $input): array
    {
        $file   = $this->getConfigFileArgument($input);
        $config = include $file;

        Assert::that($config)
            ->isArray()
            ->keyExists('publishers')
            ->keyExists('packages');

        Assert::that($config['publishers'])->isArray();
        Assert::that($config['packages'])->isArray();

        if (array_key_exists('wait', $config)) {
            Assert::that($config['wait'])->nullOr()->integer();
        } else {
            $config['wait'] = null;
        }

        return $config;
    }

    /**
     * Get the normalized config file argument.
     *
     * @param InputInterface $input The input.
     *
     * @return string
     *
     * @throws InvalidArgumentException When invalid config file is given.
     */
    protected function getConfigFileArgument(InputInterface $input): string
    {
        $configFile = Path::normalize($input->getArgument('config'));

        if (!Path::isAbsolute($configFile)) {
            $configFile = Path::makeAbsolute($configFile, Path::normalize(getcwd()));
        }

        $configFile = Path::canonicalize($configFile);

        Assert::that($configFile)->file($configFile);

        return $configFile;
    }

    /**
     * Get the sinc date.
     *
     * @param InputInterface $input   The console input.
     * @param LastRun|null   $lastRun The last run.
     *
     * @return DateTimeImmutable
     *
     * @throws \Exception When creating date time fails.
     */
    protected function getSince(InputInterface $input, ?LastRun $lastRun): DateTimeImmutable
    {
        $lastModified = null;

        if ($lastRun && !$input->getOption('ignore-last-run')) {
            $lastModified = $lastRun->lastModified();
        }

        if ($input->getOption('since')) {
            $since = new DateTimeImmutable($input->getOption('since'));

            if ($since > $lastModified) {
                return $since;
            }

            return $lastModified;
        }

        if ($lastModified) {
            return $lastModified;
        }

        $dateTime = new DateTimeImmutable();
        $dateTime = $dateTime->setTime(0, 0);

        return $dateTime;
    }
}
