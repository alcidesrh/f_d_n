<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

class GraphiQLRequestMatcher implements RequestMatcherInterface
{
    public function matches(Request $request): bool
    {
        if ($request->getPathInfo() !== '/graphql') {
            return false;
        }

        $referer = $request->headers->get('Referer');
        return $referer !== null && str_contains($referer, '/docs/graphiql');
    }
}
