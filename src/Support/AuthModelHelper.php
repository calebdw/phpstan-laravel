<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use function array_keys;
use function array_reduce;
use function in_array;
use function is_array;

final class AuthModelHelper
{
    public function __construct(private ContainerHelper $containerHelper)
    {
    }

    /**
     * @param list<string>|string|null $guard
     *
     * @return list<class-string>
     */
    public function getModels(array|string|null $guard = null): array
    {
        $config    = $this->containerHelper->getConfigRepository();
        $guards    = $config?->get('auth.guards');
        $providers = $config?->get('auth.providers');

        if (! is_array($guards) || ! is_array($providers)) {
            return [];
        }

        return array_reduce(
            (array) ($guard ?? array_keys($guards)),
            static function ($carry, $name) use ($guards, $providers) {
                $provider = $guards[$name]['provider'] ?? null;

                if (! $provider) {
                    return $carry;
                }

                $authModel = $providers[$provider]['model'] ?? null;

                if (! $authModel || in_array($authModel, $carry, strict: true)) {
                    return $carry;
                }

                $carry[] = $authModel;

                return $carry;
            },
            initial: [],
        );
    }
}
