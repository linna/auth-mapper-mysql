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
use InvalidArgumentException;
use Linna\Authentication\Password;
use Linna\Authentication\User;
use Linna\Authentication\UserMapper;
use Linna\DataMapper\DomainObjectInterface;
use Linna\DataMapper\NullDomainObject;
use Linna\Storage\ExtendedPDO;
use PDO;
use PDOException;
//use PDOStatement;
use stdClass;

/**
 * EnhancedUserMapper.
 */
class EnhancedUserMapper extends UserMapper implements EnhancedUserMapperInterface
{
    public const FETCH_VOID = 4096;

    public const FETCH_WHOLE = 2048;

    private const EXCEPTION_MESSAGE = 'Domain Object parameter must be instance of EnhancedUser class';

    /**
     * Class Constructor.
     *
     * @param ExtendedPDO               $pdo              Valid <code>PDO</code> instace to interact with the persistent storage.
     * @param Password                  $password         <code>Password</code> instance to manage passwords.
     * @param PermissionMapperInterface $permissionMapper Permission mapper.
     * @param RoleMapperInterface       $roleMapper       Role mapper.
     * @param int                       $fetchMode        If set to FETCH_WHOLE, the <code>EnhancedUser</code> object
     *                                                    contains also an array of <code>Role</code> and <code>Permission</code> object,
     *                                                    if set to FETCH_VOID , the <code>EnhancedUser</code> object contains a void array
     *                                                    for permissions and roles.
     */
    public function __construct(
        //pdo for parent class
        ExtendedPDO $pdo,

        //password for parent class
        Password $password,

        /** @var PermissionMapperInterface Permission Mapper */
        protected PermissionMapperInterface $permissionMapper,

        /** @var RoleMapperInterface Role to user Mapper */
        protected RoleMapperInterface $roleMapper,

        /** @var int Avoid to fetch permission and roles for a user.*/
        private int $fetchMode = self::FETCH_WHOLE
    ) {
        parent::__construct(pdo: $pdo, passwordUtility: $password);
    }

    public function setFetchMode(int $fetchMode)
    {
        //var_dump(0 || ($arg & (~$void & ~$whole)));
        \assert(
            self::FETCH_VOID === $fetchMode || self::FETCH_WHOLE === $fetchMode,
            new InvalidArgumentException("Unknown fetch mode, should be EnhancedUserMapper::FETCH_VOID|EnhancedUserMapper::FETCH_WHOLE")
        );

        $this->fetchMode = $fetchMode;
    }

    /**
     * Hydrate an array of objects.
     *
     * @param array<int, stdClass> $array The array containing the resultset from database.
     *
     * @return array<int, EnhancedUser>
     */
    private function hydrator(array $array): array
    {
        $tmp = [];

        // return user with roles and permissions
        if ($this->fetchMode === self::FETCH_WHOLE) {

            $this->roleMapper->setFetchMode(RoleMapper::FETCH_VOID);

            foreach ($array as $value) {

                //get roles and permissions
                $roles = $this->roleMapper->fetchByUserId($value->user_id);
                $permissions = $this->permissionMapper->fetchByUserId($value->user_id);

                $tmp[] = new EnhancedUser(//pass password as argument
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

            $this->roleMapper->setFetchMode(RoleMapper::FETCH_WHOLE);

            return $tmp;
        }

        // return only the user
        foreach ($array as $value) {

            $tmp[] = new EnhancedUser(
                passwordUtility: parent::$password,
                id:              $value->user_id,
                uuid:            $value->uuid,
                name:            $value->name,
                description:     $value->description,
                email:           $value->email,
                password:        $value->password,
                active:          $value->active,
                created:         new DateTimeImmutable($value->created),
                lastUpdate:      new DateTimeImmutable($value->last_update),
            );
        }

        return $tmp;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchById(int|string $userId): DomainObjectInterface
    {
        //make query
        $stmt = $this->pdo->prepare(self::QUERY_BASE.' WHERE user_id = :id');
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        //fail fast
        if (($stdClass = $stmt->fetchObject()) === false) {
            return new NullDomainObject();
        }

        //get roles and permissions
        $roles = $this->roleMapper->fetchByUserId($userId);
        $permissions = $this->permissionMapper->fetchByUserId($userId);

        //return result
        return new EnhancedUser(
            passwordUtility: parent::$password,
            id:              $stdClass->user_id,
            uuid:            $stdClass->uuid,
            name:            $stdClass->name,
            description:     $stdClass->description,
            email:           $stdClass->email,
            password:        $stdClass->password,
            active:          $stdClass->active,
            created:         new DateTimeImmutable($stdClass->created),
            lastUpdate:      new DateTimeImmutable($stdClass->last_update),
            roles:           $roles,
            permissions:     $permissions
        );
    }

    /**
     * {@inheritdoc}
     */
    public function fetchByName(string $userName): DomainObjectInterface
    {
        //use parent fetch by name to get user
        $user = parent::fetchByName($userName);

        //if user is valid get the EnhancedUser
        if ($user instanceof User) {
            return $this->fetchById($user->getId());
        }

        return new NullDomainObject();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(): array
    {
        //make query
        $stmt = $this->pdo->prepare(self::QUERY_BASE);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_CLASS, stdClass::class);

        //fail fast, returns the empty array
        if (\count($result) === 0) {
            return $result;
        }

        //return result
        return self::hydrator($result);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchLimit(int $offset, int $rowCount): array
    {
        //make query
        $stmt = $this->pdo->prepare(self::QUERY_BASE.' ORDER BY name ASC LIMIT :offset, :rowcount');
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':rowcount', $rowCount, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_CLASS, stdClass::class);

        //fail fast, returns the empty array
        if (\count($result) === 0) {
            return $result;
        }

        //return result
        return self::hydrator($result);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchByPermission(Permission $permission): array
    {
        return $this->fetchByPermissionId($permission->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function fetchByPermissionId(int|string $permissionId): array
    {
        //make query
        $stmt = $this->pdo->prepare('
        (SELECT 
            u.user_id, u.uuid, u.name, u.email, u.description, u.password, u.active, u.created, u.last_update
        FROM
            user AS u
                INNER JOIN
            user_permission AS up ON u.user_id = up.user_id
        WHERE
            up.permission_id = :id) 
        UNION 
        (SELECT 
            u.user_id, u.uuid, u.name, u.email, u.description, u.password, u.active, u.created, u.last_update
        FROM
            user AS u
                INNER JOIN
            user_role AS ur ON u.user_id = ur.user_id
                INNER JOIN
            role AS r ON ur.role_id = r.role_id
                INNER JOIN
            role_permission AS rp ON r.role_id = rp.role_id
        WHERE
            rp.permission_id = :id)');

        $stmt->bindParam(':id', $permissionId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_CLASS, stdClass::class);

        //fail fast, returns the empty array
        if (\count($result) === 0) {
            return $result;
        }

        //return result
        return self::hydrator($result);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchByPermissionName(string $permissionName): array
    {
        $permission = $this->permissionMapper->fetchByName($permissionName);

        return $this->fetchByPermissionId($permission->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function fetchByRole(Role $role): array
    {
        return $this->fetchByRoleId($role->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function fetchByRoleId(int|string $roleId): array
    {
        //make query
        $stmt = $this->pdo->prepare('
        SELECT 
            u.user_id, u.uuid, u.name, u.email, u.description, u.password, u.active, u.created, u.last_update
        FROM
            user AS u
                INNER JOIN
            user_role AS ur ON u.user_id = ur.user_id
        WHERE
            ur.role_id = :id');

        $stmt->bindParam(':id', $roleId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_CLASS, stdClass::class);

        //fail fast, returns the empty array
        if (\count($result) === 0) {
            return $result;
        }

        //return result
        return self::hydrator($result);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchByRoleName(string $roleName): array
    {
        //make query
        $stmt = $this->pdo->prepare('
        SELECT 
            u.user_id, u.uuid, u.name, u.email, u.description, u.password, u.active, u.created, u.last_update
        FROM
            user AS u
                INNER JOIN
            user_role AS ur
                INNER JOIN
            role AS r ON u.user_id = ur.user_id
                AND ur.role_id = r.role_id
        WHERE
            r.name = :id');

        $stmt->bindParam(':name', $roleName, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_CLASS, stdClass::class);

        //fail fast, returns the empty array
        if (\count($result) === 0) {
            return $result;
        }

        //return result
        return self::hydrator($result);
    }

    /**
     * {@inheritdoc}
     */
    public function grantPermission(EnhancedUser &$user, Permission $permission)
    {
        $this->grantPermissionById($user, $permission->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function grantPermissionById(EnhancedUser &$user, string|int $permissionId)
    {
        //get values to be passed as reference
        $userId = $user->getId();

        try {
            //make query
            $stmt = $this->pdo->prepare('INSERT INTO user_permission (user_id, permission_id) VALUES (:user_id, :permission_id)');

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
    public function grantPermissionByName(EnhancedUser &$user, string $permissionName)
    {
        $permission = $this->permissionMapper->fetchByName($permissionName);

        $this->grantPermissionById($user, $permission->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function revokePermission(EnhancedUser &$user, Permission $permission)
    {
        $this->revokePermissionById($user, $permission->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function revokePermissionById(EnhancedUser &$user, int|string $permissionId)
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
    public function revokePermissionByName(EnhancedUser &$user, string $permissionName)
    {
        $permission = $this->permissionMapper->fetchByName($permissionName);

        $this->revokePermissionById($user, $permission->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function addRole(EnhancedUser &$user, Role $role)
    {
        $this->addRoleById($user, $role->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function addRoleById(EnhancedUser &$user, int|string $roleId)
    {
        //get values to be passed as reference
        $userId = $user->getId();

        try {
            //make query
            $stmt = $this->pdo->prepare('INSERT INTO user_role (user_id, role_id) VALUES (:user_id, :role_id)');

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
    public function addRoleByName(EnhancedUser &$user, string $roleName)
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
    public function removeRole(EnhancedUser &$user, Role $role)
    {
        $this->removeRoleById($user, $role->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function removeRoleById(EnhancedUser &$user, int|string $roleId)
    {
        //get values to be passed as reference
        $userId = $user->getId();

        try {
            //make query
            $stmt = $this->pdo->prepare('DELETE FROM user_role WHERE role_id = :role_id AND user_id = :user_id');

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
    public function removeRoleByName(EnhancedUser &$user, string $roleName)
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
        return new EnhancedUser(passwordUtility: parent::$password);
    }
}
