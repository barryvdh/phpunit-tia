<?php

declare(strict_types=1);

namespace JMac\Testing\PhpUnit\Tia\Contracts;

/**
 * A resolver that reads the recorded graph, and whose answer for a path is
 * final: core skips the resolvers after it and its own sibling-directory
 * guess for any path it answers.
 *
 * Kept apart from Resolver so existing resolvers keep working unchanged.
 */
interface EdgeAwareResolver
{
    /**
     * Called with every changed path core couldn't map to a known source edge.
     *
     * @return list<string>|null the complete set of affected test files, even
     *                           an empty one, or null if this resolver has no
     *                           opinion
     */
    public function resolve(Edges $edges, string $projectRoot, string $changedRelativePath): ?array;
}
