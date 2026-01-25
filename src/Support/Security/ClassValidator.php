<?php

namespace Dcat\Admin\Support\Security;

use Dcat\Admin\Actions\Action;
use Dcat\Admin\Contracts\LazyRenderable;
use Dcat\Admin\Exception\AdminException;
use Dcat\Admin\Traits\InteractsWithApi;
use Dcat\Admin\Widgets\Form;

/**
 * Class validator for preventing arbitrary class instantiation attacks.
 *
 * This class validates that dynamically loaded classes extend/implement
 * the expected base classes, preventing remote code execution attacks.
 */
class ClassValidator
{
    /**
     * Allowed base classes/interfaces for each controller type.
     *
     * @var array<string, array<string>>
     */
    protected static array $allowedBases = [
        'renderable' => [LazyRenderable::class],
        'action' => [Action::class],
        'form' => [Form::class],
        'value' => [], // Uses trait check instead
    ];

    /**
     * Validate a class name against allowed base classes.
     *
     * @param  string  $class  The fully qualified class name
     * @param  string  $type   The type: 'renderable', 'action', 'form', or 'value'
     * @return string  The normalized class name
     *
     * @throws AdminException
     */
    public static function validate(string $class, string $type): string
    {
        // Normalize class name (convert underscores to backslashes, remove leading backslash)
        $class = ltrim(str_replace('_', '\\', $class), '\\');

        // Check if class exists
        if (! class_exists($class)) {
            throw new AdminException("Class [{$class}] does not exist.");
        }

        // Check explicit allowlist from config (takes precedence if defined)
        $allowlist = config("admin.security.class_allowlist.{$type}", []);
        if (! empty($allowlist) && in_array($class, $allowlist, true)) {
            return $class;
        }

        // For 'value' type, check for InteractsWithApi trait
        if ($type === 'value') {
            if (! static::usesTrait($class, InteractsWithApi::class)) {
                throw new AdminException(
                    "Class [{$class}] is not a valid value handler. ".
                    'It must use the InteractsWithApi trait.'
                );
            }

            return $class;
        }

        // For other types, check class hierarchy
        $bases = static::$allowedBases[$type] ?? [];

        foreach ($bases as $base) {
            if (is_a($class, $base, true)) {
                return $class;
            }
        }

        throw new AdminException(
            "Class [{$class}] is not a valid {$type}. ".
            'It must extend/implement one of: '.implode(', ', $bases)
        );
    }

    /**
     * Check if a class uses a specific trait (including parent classes).
     *
     * @param  string  $class  The class to check
     * @param  string  $trait  The trait to look for
     * @return bool
     */
    protected static function usesTrait(string $class, string $trait): bool
    {
        $traits = [];

        do {
            $traits = array_merge($traits, class_uses($class, true) ?: []);
        } while ($class = get_parent_class($class));

        return isset($traits[$trait]);
    }
}
