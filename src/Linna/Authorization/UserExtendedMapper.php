<?php

/**
 * Linna Framework.
 *
 * @author Sebastian Rapetti <sebastian.rapetti@tim.it>
 * @copyright (c) 2020, Sebastian Rapetti
 * @license http://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Linna\Authorization;

use DateTimeImmutable;
use Linna\Authentication\Password;
use Linna\DataMapper\DomainObjectInterface;
use Linna\Storage\ExtendedPDO;
use PDO;
use PDOException;
use stdClass;

/**
 * UserExtendedMapper.
 *
 * class user
 * {
 *     protected function hydrator()
 *     {
 *         return 'user hydrator';
 *     }
 *
 *     public function test()
 *     {
 *         return $this->hydrator();
 *     }
 * }
 *
 *
 * class userEx extends user
 * {
 *     protected function hydrator()
 *     {
 *         return 'userEx hydrator';
 *     }
 * }
 *
 * var_dump((new user)->test()); // string(13) "user hydrator"
 * var_dump((new userEx)->test()); // string(15) "userEx hydrator"
 *
 * The hydrator system works as shown into the above example.
 */
class UserExtendedMapper extends UserMapper implements UserExtendedMapperInterface
{
    /**
     * Class Constructor.
     *
     * @param ExtendedPDO               $pdo              Valid <code>PDO</code> instace to interact with the persistent storage.
     * @param Password                  $password         <code>Password</code> instance to manage passwords.
     * @param PermissionMapperInterface $permissionMapper Permission mapper.
     * @param RoleMapperInterface       $roleMapper       Role mapper.
     */
    public function __construct(
        //pdo for parent class
        ExtendedPDO $pdo,

        //password for parent class
        Password $password,

        /** @var PermissionMapperInterface Permission Mapper */
        protected PermissionMapperInterface $permissionMapper,

        /** @var RoleMapperInterface Role Mapper */
        protected RoleMapperInterface $roleMapper
    ) {
        parent::__construct(pdo: $pdo, passwordUtility: $password);
    }

    /**
     * Hydrate an array of objects.
     *
     * @param array<int, stdClass> $array The array containing the resultset from database.
     *
     * @return array<int, UserExtended>
     */
    protected function hydrator(array $array): array
    {
        $tmp = [];

        // return only the user
        foreach ($array as $value) {

            //get roles and permissions
            $roles = $this->roleMapper->fetchByUserId($value->user_id);
            $permissions = $this->permissionMapper->fetchByUserId($value->user_id);

            $tmp[] = new UserExtended(
                passwordUtility: parent::$password, // from parent user mapper
                id:              $value->user_id,
                uuid:            $value->uuid,
                name:            $value->name,
                description:     $value->description,
                email:           $value->email,
                password:        $value->password,
                active:          $value->active,
                created:         new DateTimeImmutable($value->created),
                lastUpdate:      new DateTimeImmutable($value->last_update),
                roles:           $roles,
                permissions:     $permissions
            );
        }

        return $tmp;
    }

    /**
     * Hydrate an object.
     *
     * @param object $object The object containing the resultset from database.
     *
     * @return DomainObjectInterface
     */
    protected function hydratorSingle(object $object): DomainObjectInterface
    {
        //get roles and permissions
        $roles = $this->roleMapper->fetchByUserId($object->user_id);
        $permissions = $this->permissionMapper->fetchByUserId($object->user_id);

        return new UserExtended(
            passwordUtility: parent::$password,
            id:              $object->user_id,
            uuid:            $object->uuid,
            name:            $object->name,
            description:     $object->description,
            email:           $object->email,
            password:        $object->password,
            active:          $object->active,
            created:         new DateTimeImmutable($object->created),
            lastUpdate:      new DateTimeImmutable($object->last_update),
            roles:           $roles,
            permissions:     $permissions
        );
    }

    /**
     * {@inheritdoc}
     */
    public function grantPermission(UserExtended &$user, Permission $permission)
    {
        $this->grantPermissionById($user, $permission->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function grantPermissionById(UserExtended &$user, string|int $permissionId)
    {
        //get values to be passed as reference
        $userId = $user->getId();

        try {
            //make query
            $stmt = $this->pdo->prepare('INSERT INTO user_permission (user_id, permission_id) VALUES (:user_id, :permission_id) ON DUPLICATE KEY UPDATE user_id = :user_id, permission_id = :permission_id');

            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':permission_id', $permissionId, PDO::PARAM_INT);
            $stmt->execute();

            //update current object
            $user = $this->fetchById($userId);
        } catch (PDOException $e) {
            echo 'Insert not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    public function grantPermissionByName(UserExtended &$user, string $permissionName)
    {
        $permission = $this->permissionMapper->fetchByName($permissionName);

        $this->grantPermissionById($user, $permission->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function revokePermission(UserExtended &$user, Permission $permission)
    {
        $this->revokePermissionById($user, $permission->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function revokePermissionById(UserExtended &$user, int|string $permissionId)
    {
        //get values to be passed as reference
        $userId = $user->getId();

        try {
            //make query
            $stmt = $this->pdo->prepare('DELETE FROM user_permission WHERE user_id = :user_id AND permission_id = :permission_id');

            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':permission_id', $permissionId, PDO::PARAM_INT);
            $stmt->execute();

            //update current object
            $user = $this->fetchById($userId);
        } catch (PDOException $e) {
            echo 'Deletion not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    public function revokePermissionByName(UserExtended &$user, string $permissionName)
    {
        $permission = $this->permissionMapper->fetchByName($permissionName);

        $this->revokePermissionById($user, $permission->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function addRole(UserExtended &$user, Role $role)
    {
        $this->addRoleById($user, $role->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function addRoleById(UserExtended &$user, int|string $roleId)
    {
        //get values to be passed as reference
        $userId = $user->getId();

        try {
            //make query
            $stmt = $this->pdo->prepare('INSERT INTO user_role (user_id, role_id) VALUES (:user_id, :role_id)  ON DUPLICATE KEY UPDATE user_id = :user_id, role_id = :role_id');

            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
            $stmt->execute();

            //update current object
            $user = $this->fetchById($userId);
        } catch (PDOException $e) {
            echo 'Insert not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addRoleByName(UserExtended &$user, string $roleName)
    {
        //get values to be passed as reference
        $userId = $user->getId();

        try {
            //make query
            $stmt = $this->pdo->prepare('INSERT INTO user_role (user_id, role_id)
            VALUES (:user_id, (SELECT role_id FROM role WHERE name = :role_name))');

            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':role_name', $roleName, PDO::PARAM_STR);
            $stmt->execute();

            //update current object
            $user = $this->fetchById($userId);
        } catch (PDOException $e) {
            echo 'Insert not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeRole(UserExtended &$user, Role $role)
    {
        $this->removeRoleById($user, $role->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function removeRoleById(UserExtended &$user, int|string $roleId)
    {
        //get values to be passed as reference
        $userId = $user->getId();

        try {
            //make query
            $stmt = $this->pdo->prepare('DELETE FROM user_role WHERE role_id = :role_id AND user_id = :user_id');

            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':role_id', $roleName, PDO::PARAM_INT);
            $stmt->execute();

            //update current object
            $user = $this->fetchById($userId);
        } catch (PDOException $e) {
            echo 'Deletion not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeRoleByName(UserExtended &$user, string $roleName)
    {
        //get values to be passed as reference
        $userId = $user->getId();

        try {
            //make query
            $stmt = $this->pdo->prepare('DELETE FROM user_role 
            WHERE role_id = (SELECT role_id FROM role WHERE name = :role_name) AND user_id = :user_id');

            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':role_name', $roleName, PDO::PARAM_STR);
            $stmt->execute();

            //update current object
            $user = $this->fetchById($userId);
        } catch (PDOException $e) {
            echo 'Deletion not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function concreteCreate(): DomainObjectInterface
    {
        return new UserExtended(passwordUtility: parent::$password);
    }
}
