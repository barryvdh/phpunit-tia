<?php

declare(strict_types=1);

namespace JMac\Testing\PhpUnit\Tia\Contracts;

/**
 * A Resolver that also reads the recorded graph. Core hands it the graph
 * through useEdges() before the first resolve() call of a run, and asks
 * through handles() whether its answer for a path is complete.
 *
 * Kept apart from Resolver so existing resolvers keep working unchanged.
 */
interface EdgeAwareResolver extends Resolver
{
    public function useEdges(Edges $edges): void;

    /**
     * Whether resolve() gave the complete set of affected tests for this
     * path, even an empty one. Core then skips the resolvers after this one
     * and its own sibling-directory guess for the path. Called right after
     * resolve() for the same path.
     */
    public function handles(string $changedRelativePath): bool;
}
