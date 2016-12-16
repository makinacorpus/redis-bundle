<?php

namespace MakinaCorpus\RedisBundle\Hydrator;

/**
 * Hydrator chain optimizes the use of many hydrators by respecting the
 * execution order and stopping object propagation when data is broken
 */
class HydratorChain implements HydratorInterface
{
    private $hydrators = [];

    /**
     * Hydrators
     *
     * @param HydratorInterface[] $hydrators
     */
    public function __construct(array $hydrators = [])
    {
        $this->hydrators = $hydrators;
    }

    public function append(HydratorInterface $hydrator)
    {
        array_push($this->hydrators, $hydrator);
    }

    public function prepend(HydratorInterface $hydrator)
    {
        array_unshift($this->hydrators, $hydrator);
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data, &$flags)
    {
        if ($this->hydrators) {
            foreach ($this->hydrators as $hydrator) {
                $data = $hydrator->encode($data, $flags);
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data, $flags)
    {
        if ($this->hydrators) {
            foreach (array_reverse($this->hydrators) as $hydrator) {
                $data = $hydrator->decode($data, $flags);
            }
        }

        return $data;
    }
}
