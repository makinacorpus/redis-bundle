<?php

namespace MakinaCorpus\RedisBundle\Client;

/**
 * Predis client specific implementation
 */
class PredisFactory implements StandaloneFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createClient($options = [])
    {
        if ($options['cluster']) {

            throw new \Exception("Cluster is not supported by the standalone manager yet");

            /*
            if ($options['persistent']) {
                throw new \InvalidArgumentException("Persistent connections are not supported by the \RedisCluster yet");
            }

            $args = [];
            $args[] = null; // Unnamed cluster connection
            // next parameter is an array of host:port strings
            // next parameter is timeout
            // next parameter is read_timeout
            // next parameter is persistent (boolean)

            $client = new \RedisCluster(...$args);
             */

        } else if (1 < count($options['host'])) {

            throw new \Exception("\\RedisArray is not supported by the standalone manager yet");

        } else {

            if ($options['failover']) {
                throw new \InvalidArgumentException("'failover' is only supported with cluster");
            }
            if ($options['read_timeout']) {
                throw new \InvalidArgumentException("'read_timeout' is not supported with yet");
            }

            /** @var $dsn Dsn */
            $dsn = $options['host'][0];
            $options['host'] = $dsn->getHost();
            $options['database'] = $dsn->getDatabase();
            $options['port'] = $dsn->getPort();

            // @todo Lots of missing stuff
        }

        foreach ($options as $key => $value) {
            if (!isset($value)) {
                unset($options[$key]);
            }
        }

        // I'm not sure why but the error handler is driven crazy if timezone
        // is not set at this point.
        // Hopefully Drupal will restore the right one this once the current
        // account has logged in.
        date_default_timezone_set(@date_default_timezone_get());

        $client = new \Predis\Client($options);

        if (isset($options['password'])) {
            throw new \InvalidArgumentException("'auth' is not supported yet");
        }
        if (isset($options['base']) && 0 !== $options['base']) {
            $client->select((int)$options['base']);
        }

        return $client;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'predis';
    }
}
