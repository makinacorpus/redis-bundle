<?php

namespace MakinaCorpus\RedisBundle\Client;

/**
 * Predis client specific implementation
 */
class PredisFactory implements StandaloneFactoryInterface
{
    public function createClient($options = [])
    {
        if ($options['cluster']) {
            throw new \InvalidArgumentException("Cluster is not supported yet by the PredisFactory yet");
        }
        if ($options['persistent']) {
            throw new \InvalidArgumentException("Persistent connections are not supported yet by the PredisFactory yet");
        }

        if (!empty($options['socket'])) {
            $options['scheme'] = 'unix';
            $options['path'] = $options['socket'];
        }

        foreach ($options as $key => $value) {
            if (!isset($value)) {
                unset($options[$key]);
            }
        }

        $client = new \Predis\Client($options);

        if (isset($options['database']) && 0 !== $options['database']) {
            $client->select((int)$options['database']);
        }

        return $client;
    }
}
