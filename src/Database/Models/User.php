<?php

declare(strict_types=1);

namespace Kleinweb\Sitekit\Database\Models;

use Kleinweb\Sitekit\Database\Contracts\CoreObject;
use Kleinweb\Sitekit\Support\CoreObjects;
use Webmozart\Assert\Assert;
use WP_User;

use function is_int;

/** @phpstan-consistent-constructor */
final class User extends Model implements CoreObject
{
    public static function getEntityName(): string
    {
        return 'user';
    }

    /**
     * Booted model instances.
     *
     * @var static[]
     */
    protected static array $booted = [];

    /** @param WP_User $coreObject Core user object for the modelled entity. */
    public function __construct(protected WP_User $coreObject) {}

    /**
     * User ID.
     */
    public function id(): int
    {
        return $this->getCoreObject()->ID;
    }

    /**
     * User display name.
     *
     * Alias of {@link User::displayName()}.
     */
    public function name(): string
    {
        return $this->displayName();
    }

    /**
     * User display name.
     */
    public function displayName(): string
    {
        return $this->getCoreObject()->display_name;
    }

    /**
     * User email address.
     */
    public function email(): string
    {
        return $this->getCoreObject()->user_email;
    }

    /**
     * User login name.
     */
    public function slug(): string
    {
        return $this->getCoreObject()->user_login;
    }

    /**
     * User description or bio.
     */
    public function description(): string
    {
        return $this->getCoreObject()->user_description;
    }

    /**
     * Parent user model.
     */
    public function parent(): ?Model
    {
        return null;
    }

    /**
     * User profile permalink.
     */
    public function permalink(): string
    {
        return get_author_posts_url($this->id());
    }

    /**
     * Get the core {@link \WP_User} object for the model.
     */
    public function getCoreObject(): WP_User
    {
        return $this->coreObject;
    }

    /**
     * Find a user model by ID or core object.
     *
     * @param int|WP_User $identifier user identifier
     */
    public static function find($identifier): ?static
    {
        $user = is_int($identifier)
            ? CoreObjects::getUser($identifier)
            : $identifier;

        if (!$user) {
            return null;
        }

        return self::newFromExisting($user);
    }

    /**
     * Create a new instance of the model from an existing record in the
     * database.
     *
     * @param WP_User|int $existing existing user object or ID
     */
    public static function newFromExisting($existing): static
    {
        $user = ($existing instanceof WP_User)
            ? $existing
            : CoreObjects::getUser($existing);
        Assert::isInstanceOf($user, WP_User::class);
        $id = $user->ID;
        if (!isset(self::$booted[$id])) {
            $entity = new static($user);
            self::$booted[$id] = $entity;
        }

        return self::$booted[$id];
    }
}
