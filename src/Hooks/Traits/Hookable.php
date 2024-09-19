<?php

declare(strict_types=1);

/**
 * Hookable trait file.
 *
 * SPDX-FileCopyrightText: 2023-2024  Temple University <kleinweb@temple.edu>
 * SPDX-FileCopyrightText: 2020-2024  Alley
 * SPDX-License-Identifier: GPL-3.0-or-later.
 *
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * @see <https://github.com/alleyinteractive/mantle-framework/blob/e9a58055847645efce2f186c2f758bdace376071/src/mantle/support/traits/trait-hookable.php>
 */

namespace Kleinweb\Lib\Hooks\Traits;

use Illuminate\Support\Collection;
use Kleinweb\Lib\Hooks\Attributes\Action;
use Kleinweb\Lib\Hooks\Attributes\Filter;
use ReflectionClass;
use Webmozart\Assert\Assert;

/**
 * Register all hooks on a class.
 */
trait Hookable
{
    /**
     * Boot all actions and attribute methods.
     *
     * Collects all of the attribute-based `#[Action]` and `#[Filter]` methods
     * and registers them with the respective WordPress hooks.
     */
    protected function registerHooks(): void
    {
        $this->collectAttributeHooks()
            ->unique()
            ->each(
                function (array $item) {
                    $callable = [$this, $item['method']];
                    Assert::isCallable($callable);

                    if ($item['type'] === 'action') {
                        \add_action($item['hook'], $callable, $item['priority'], 999);
                    } else {
                        \add_filter($item['hook'], $callable, $item['priority'], 999);
                    }
                },
            );
    }

    /**
     * Collect all attribute actions on the service provider.
     *
     * Allow methods with the `#[Action]` attribute to automatically register
     * WordPress hooks.
     *
     * @return Collection<int, array{type: string, hook: string, method: string, priority: int}>
     */
    protected function collectAttributeHooks(): Collection
    {
        $items = new Collection();
        $class = new ReflectionClass(static::class);

        foreach ($class->getMethods() as $method) {
            foreach ($method->getAttributes(Action::class) as $attribute) {
                $instance = $attribute->newInstance();

                $items->push(
                    [
                        'type'     => 'action',
                        'hook'     => $instance->hook,
                        'method'   => $method->getName(),
                        'priority' => $instance->priority,
                    ],
                );
            }

            foreach ($method->getAttributes(Filter::class) as $attribute) {
                $instance = $attribute->newInstance();

                $items->push(
                    [
                        'type'     => 'filter',
                        'hook'     => $instance->hook,
                        'method'   => $method->getName(),
                        'priority' => $instance->priority,
                    ],
                );
            }
        }

        return $items;
    }
}
