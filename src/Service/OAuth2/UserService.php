<?php
declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Model\OAuth2\UserModel;
use App\Service\Api\UserConnectorInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserService implements UserRepositoryInterface, UserProviderInterface
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
        if (!$user instanceof UserModel) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        $id = $user->getIdentifier();
        $user = $this->userConnector->getUserEntityById($id);
        if ($user === null) {
            throw new UserNotFoundException(sprintf('Unable to find a user with id %s', $id));
        }

        return $user;
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass(string $class): bool
    {
        return UserModel::class === $class || is_subclass_of($class, UserModel::class);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userConnector->getUserEntityById($identifier);
        if ($user === null) {
            throw new UserNotFoundException(sprintf('Unable to find a user with id %s', $identifier));
        }

        return $user;
    }

    public function getUserEntityByUserCredentials(
        $username, $password, $grantType, ClientEntityInterface $clientEntity): ?UserEntityInterface
    {
        return $this->userConnector->getUserEntityByUserCredentials($username, $password, $grantType, $clientEntity);
    }
}