<?php
declare(strict_types=1);

namespace App\Security;

use App\Api\Contract\UserConnectorInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{

    public function __construct(
        private readonly UserConnectorInterface $userConnector,
    )
    {
    }

    /**
     * Refreshes the user after being reloaded from the session.
     *
     * When a user is logged in, at the beginning of each request, the
     * User object is loaded from the session and then this method is
     * called. Your job is to make sure the user's data is still fresh by,
     * for example, re-querying for fresh User data.
     *
     * If your firewall is "stateless: true" (for a pure API), this
     * method is not called.
     */
    public function refreshUser(UserInterface $user): UserInterface|UserEntityInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        $id = $user->getUserIdentifier();
        $user = $this->userConnector->getUserById($id);
        if ($user === null) {
            throw new UserNotFoundException(sprintf('Unable to find a user with id %s', $id));
        }

        return new User($user->getId());
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userConnector->getUserById($identifier);
        if ($user === null) {
            throw new UserNotFoundException(sprintf('Unable to find a user with id %s', $identifier));
        }

        return new User($user->getId());
    }
}