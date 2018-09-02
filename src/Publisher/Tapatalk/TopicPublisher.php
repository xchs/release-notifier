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

namespace App\Publisher\Tapatalk;

use Netzmacht\Tapatalk\Client;

/**
 * Class TopicPublisher
 */
final class TopicPublisher extends AbstractPublisher
{
    /**
     * {@inheritdoc}
     */
    protected function createEntry(
        Client $client,
        array $configuration,
        string $subject,
        string $body
    ): void {
        $client->topics()->createNewTopic($configuration['forumId'], $subject, $body);
    }
}
