<?php

namespace App\Security;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class ActionExpressionProvider implements ExpressionFunctionProviderInterface {

    public function getFunctions(): array {
        return [
            new ExpressionFunction(
                'is_granted_action',
                fn(string $code) => sprintf('is_granted(%s)', $code),
                fn(array $variables, string $code) => $variables['auth_checker']->isGranted($code),
            ),
        ];
    }
}
