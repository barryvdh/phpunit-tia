<?php

declare(strict_types=1);

namespace JMac\Testing\PhpUnit\Tia\Contracts;

/**
 * A Resolver that also reads the recorded graph. Core hands it the graph
 * through useEdges() before the first resolve() call of a run.
 *
 * Kept apart from Resolver so existing resolvers keep working unchanged.
 */
interface EdgeAwareResolver extends Resolver
{
    public function useEdges(Edges $edges): void;
}
