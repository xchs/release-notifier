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

namespace App\Packagist;

use App\Config\Configuration;
use App\Packagist\Release;
use Composer\Semver\Semver;
use Zend\Feed\Reader\Feed\FeedInterface;
use Zend\Feed\Reader\Http\ClientInterface;
use Zend\Feed\Reader\Reader;

/**
 * Class PackageReleases
 */
final class PackageReleases
{
    /**
     * Http client.
     *
     * @var ClientInterface
     */
    private $client;

    /**
     * Packages configuration.
     *
     * @var Configuration
     */
    private $configuration;

    /**
     * PackageReleases constructor.
     *
     * @param ClientInterface $client        Http client.
     * @param Configuration   $configuration Packages configuration.
     */
    public function __construct(ClientInterface $client, Configuration $configuration)
    {
        $this->client        = $client;
        $this->configuration = $configuration;

        Reader::setHttpClient($client);
    }

    /**
     * Get packages since a defined time.
     *
     * @param \DateTimeInterface $dateTime The date since when the packages should be collected.
     *
     * @return Release[]|iterable
     */
    public function since(\DateTimeInterface $dateTime): iterable
    {
        $releases = [];

        foreach ($this->configuration->packages() as $package) {
            $channel     = Reader::import(sprintf('feeds/package.%s.rss', $package->packageName()));
            $previousMap = $this->buildPreviousMap($channel);

            /** @var \Zend\Feed\Reader\Entry\Rss $item */
            foreach ($channel as $item) {
                if ($item->getDateCreated() > $dateTime) {
                    $version    = explode(' ', $item->getId(), 2)[1];
                    $releases[] = Release::fromStringAndLink(
                        $item->getId(),
                        $item->getLink(),
                        $previousMap[$version]
                    );
                }
            }
        }

        return $releases;
    }

    /**
     * Build a previous versions map.
     *
     * @param FeedInterface $channel The feed channel.
     *
     * @return array
     */
    private function buildPreviousMap(FeedInterface $channel): array
    {
        $map      = [];
        $versions = [];

        /** @var \Zend\Feed\Reader\Entry\Rss $item */
        foreach ($channel as $item) {
            $version       = explode(' ', $item->getId(), 2)[1];
            $versions[]    = $version;
            $map[$version] = null;
        }

        $versions = Semver::sort($versions);

        $length = count($versions);
        for ($index = 1; $index < $length; $index++) {
            $map[$versions[$index]] = Version::fromString($versions[($index - 1)]);
        }

        return $map;
    }
}
