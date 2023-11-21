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

use InvalidArgumentException;
use DateTimeImmutable;
//use Linna\Authentication\UserMapperInterface;
use Linna\DataMapper\DomainObjectInterface;
use Linna\DataMapper\MapperAbstract;
use Linna\DataMapper\NullDomainObject;
use Linna\Storage\ExtendedPDO;
use PDO;
use PDOException;
use RuntimeException;
use stdClass;

/**
 * Role Mapper.
 */
class RoleExtendedMapper extends RoleMapper implements RoleExtendedMapperInterface
{
    public const FETCH_VOID = 4096;

    public const FETCH_WHOLE = 2048;

    protected const QUERY_BASE = 'SELECT role_id, name, description, active, created, last_update FROM role';

    private const EXCEPTION_MESSAGE = 'Domain Object parameter must be instance of EnhancedUser class';

    /**
     * Constructor.
     *
     * @param ExtendedPDO                 $pdo              Valid <code>PDO</code> instace to interact with the persistent storage.
     * @param PermissionMapperInterface   $permissionMapper Permission mapper.
     * @param EnhancedUserMapperInterface $userMapper       User Mapper
     * @param int                         $fetchMode        If set to FETCH_WHOLE, the <code>EnhancedUser</code> object
     *                                                      contains also an array of <code>Role</code> and <code>Permission</code> object,
     *                                                      if set to FETCH_VOID , the <code>EnhancedUser</code> object contains a void array
     *                                                      for permissions and roles.
     */
    public function __construct(
        /** @var ExtendedPDO Database Connection. */
        protected ExtendedPDO $pdo,

        /** @var PermissionMapperInterface Permission Mapper. */
        protected ?PermissionMapperInterface $permissionMapper,

        /** @var EnhancedUserMapperInterface Enhanced User Mapper. */
        protected ?EnhancedUserMapperInterface $userMapper,

        /** @var int Avoid to fetch permission and users for a role.*/
        private int $fetchMode = RoleMapper::FETCH_WHOLE
    ) {
        \assert(
            RoleMapper::FETCH_WHOLE === $fetchMode && $userMapper instanceof EnhancedUserMapperInterface && $permissionMapper instanceof PermissionMapperInterface,
            new InvalidArgumentException("RoleMapper::FETCH_WHOLE require a PermissionMapper and EnhancedUserMapper as arguments")
        );
    }

    //split role mapper in RoleMapper and EnhancedRoleMapper to avoid conflicts

    public function setFetchMode(int $fetchMode)
    {
        //var_dump(0 || ($arg & (~$void & ~$whole)));
        \assert(
            RoleMapper::FETCH_VOID === $fetchMode || RoleMapper::FETCH_WHOLE === $fetchMode,
            new InvalidArgumentException("Unknown fetch mode, should be RoleMapper::FETCH_VOID|RoleMapper::FETCH_WHOLE")
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

        // return role with users and permissions
        if ($this->fetchMode === self::FETCH_WHOLE) {

            $this->userMapper->setFetchMode(EnhancedUserMapper::FETCH_VOID);

            foreach ($array as $value) {

                //get users and permissions
                $users = $this->userMapper->fetchByRoleId($value->roleId);
                $permissions = $this->permissionMapper->fetchByRoleId($value->roleId);

                $tmp[] = new Role(
                    id:              $value->role_id,
                    name:            $value->name,
                    description:     $value->description,
                    active:          $value->active,
                    created:         new DateTimeImmutable($value->created),
                    lastUpdate:      new DateTimeImmutable($value->last_update),
                    users:           $users,
                    permissions:     $permissions
                );
            }

            $this->userMapper->setFetchMode(EnhancedUserMapper::FETCH_WHOLE);

            return $tmp;
        }

        // return only the role
        foreach ($array as $value) {
            $tmp[] = new Role(
                id:              $value->role_id,
                name:            $value->name,
                description:     $value->description,
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
    public function fetchById(int|string $roleId): DomainObjectInterface
    {
        //make query
        $stmt = $this->pdo->prepare(self::QUERY_BASE.' WHERE role_id = :id');
        $stmt->bindParam(':id', $roleId, PDO::PARAM_INT);
        $stmt->execute();

        //fail fast
        if (($stdClass = $stmt->fetchObject()) === false) {
            return new NullDomainObject();
        }

        //get roles and permissions
        $users = $this->userMapper->fetchByRoleId($roleId);
        $permissions = $this->permissionMapper->fetchByRoleId($roleId);

        //return result
        return new Role(
            id:              $stdClass->role_id,
            name:            $stdClass->name,
            description:     $stdClass->description,
            active:          $stdClass->active,
            created:         new DateTimeImmutable($stdClass->created),
            lastUpdate:      new DateTimeImmutable($stdClass->last_update),
            users:           $users,
            permissions:     $permissions
        );
    }

    //add fetch by name to role mapper interface in framework
    /**
     * {@inheritdoc}
     */
    public function fetchByName(string $roleName): DomainObjectInterface
    {
        //make query
        $stmt = $this->pdo->prepare(self::QUERY_BASE.' WHERE name = :name');
        $stmt->bindParam(':id', $roleId, PDO::PARAM_INT);
        $stmt->execute();

        //fail fast
        if (($stdClass = $stmt->fetchObject()) === false) {
            return new NullDomainObject();
        }

        //get roles and permissions
        $users = $this->userMapper->fetchByRoleId($roleId);
        $permissions = $this->permissionMapper->fetchByRoleId($roleId);

        //return result
        return new Role(
            id:              $stdClass->role_id,
            name:            $stdClass->name,
            description:     $stdClass->description,
            active:          $stdClass->active,
            created:         new DateTimeImmutable($stdClass->created),
            lastUpdate:      new DateTimeImmutable($stdClass->last_update),
            users:           $users,
            permissions:     $permissions
        );
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
        $stmt = $this->pdo->prepare('
        SELECT 
            r.role_id, r.name, r.description, r.active, r.created, r.last_update
        FROM
            role AS r
                INNER JOIN
            role_permission AS rp ON r.role_id = rp.role_id
        WHERE
            rp.permission_id = :id');

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

        return $this->fetchByPermissionId((int)$permission->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function fetchByUser(EnhancedUser $user): array
    {
        return $this->fetchByUserId($user->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function fetchByUserId(int|string $userId): array
    {
        //make query
        $stmt = $this->pdo->prepare('
        SELECT 
            r.role_id, r.name, r.description, r.active, r.created, r.last_update
        FROM
            role AS r
                INNER JOIN
            user_role AS ur ON r.role_id = ur.role_id
        WHERE
            ur.user_id = :id');

        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
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
    public function fetchByUserName(string $userName): array
    {
        $user = $this->userMapper->fetchByName($userName);

        return $this->fetchByUserId($user->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function grantPermission(Role &$role, Permission $permission)
    {
        $this->grantPermissionById($role, $permission->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function grantPermissionById(Role &$role, int|string $permissionId)
    {
        //get values to be passed as reference
        $roleId = $role->getId();

        try {
            //make query
            $stmt = $this->pdo->prepare('INSERT INTO role_permission (role_id, permission_id) VALUES (:role_id, :permission_id)');

            $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
            $stmt->bindParam(':permission_id', $permissionId, PDO::PARAM_INT);
            $stmt->execute();

            //update current object
            $role = $this->fetchById($roleId);
        } catch (PDOException $e) {
            echo 'Insert not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    public function grantPermissionByName(Role &$role, string $permissionName)
    {
        $permission = $this->permissionMapper->fetchByName($permissionName);

        $this->grantPermissionById($role, $permission->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function revokePermission(Role &$role, Permission $permission)
    {
        $this->revokePermissionById($role, $permission->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function revokePermissionById(Role &$role, int|string $permissionId)
    {
        //get values to be passed as reference
        $roleId = $role->getId();

        try {
            //make query
            $stmt = $this->pdo->prepare('DELETE FROM role_permission WHERE role_id = :role_id AND permission_id = :permission_id');

            $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
            $stmt->bindParam(':permission_id', $permissionId, PDO::PARAM_INT);
            $stmt->execute();

            //update current object
            $role = $this->fetchById($roleId);
        } catch (PDOException $e) {
            echo 'Deletion not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    public function revokePermissionByName(Role &$role, string $permissionName)
    {
        $permission = $this->permissionMapper->fetchByName($permissionName);

        $this->revokePermissionById($role, $permission->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function addUser(Role &$role, EnhancedUser $user)
    {
        $this->addUserById($role, $user->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function addUserById(Role &$role, int|string $userId)
    {
        //get values to be passed as reference
        $roleId = $role->getId();

        try {
            //make query
            $stmt = $this->pdo->prepare('INSERT INTO user_role (user_id, role_id) VALUES (:user_id, :role_id)');

            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
            $stmt->execute();

            $role = $this->fetchById($roleId);
        } catch (PDOException $e) {
            echo 'Insert not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addUserByName(Role &$role, string $userName)
    {
        $user = $this->userMapper->fetchByName($userName);

        $this->addUserById($role, $user->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function removeUser(Role &$role, EnhancedUser $user)
    {
        $this->removeUserById($role, $user->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function removeUserById(Role &$role, int|string $userId)
    {
        //get values to be passed as reference
        $roleId = $role->getId();

        try {
            //make query
            $stmt = $this->pdo->prepare('DELETE FROM user_role WHERE role_id = :role_id AND user_id = :user_id');

            $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $role = $this->fetchById($roleId);
        } catch (PDOException $e) {
            echo 'Deletion not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeUserByName(Role &$role, string $userName)
    {
        $user = $this->userMapper->fetchByName($userName);

        $this->removeUserById($role, $user->getId());
    }

    /**
     * {@inheritdoc}
     */
    protected function concreteCreate(): DomainObjectInterface
    {
        return new Role();
    }

    /**
     * {@inheritdoc}
     */
    protected function concreteInsert(DomainObjectInterface &$role): void
    {
        \assert($role instanceof Role, new InvalidArgumentException(self::EXCEPTION_MESSAGE));

        //get value to be passed as reference
        $created = $role->created->format(DATE_ATOM);
        $lastUpdate = $role->lastUpdate->format(DATE_ATOM);

        try {
            //make query
            $stmt = $this->pdo->prepare('INSERT INTO role (name, description, active, created, last_update) VALUES (:name, :description, :active, :created, :last_update)');

            $stmt->bindParam(':name', $role->name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $role->name, PDO::PARAM_STR);
            $stmt->bindParam(':active', $role->active, PDO::PARAM_INT);
            $stmt->bindParam(':created', $created, PDO::PARAM_STR);
            $stmt->bindParam(':last_update', $lastUpdate, PDO::PARAM_STR);

            $stmt->execute();

            $role->setId((int) $this->pdo->lastInsertId());
        } catch (RuntimeException $e) {
            echo 'Insert not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function concreteUpdate(DomainObjectInterface $role): void
    {
        \assert($role instanceof Role, new InvalidArgumentException(self::EXCEPTION_MESSAGE));

        //get value to be passed as reference
        $objId = $role->getId();
        $lastUpdate = $role->lastUpdate->format(DATE_ATOM);

        try {
            //make query
            $stmt = $this->pdo->prepare('UPDATE role SET name = :name, description = :description, active = :active, last_update = :last_update  WHERE (role_id = :id)');

            $stmt->bindParam(':id', $objId, PDO::PARAM_INT);
            $stmt->bindParam(':name', $role->name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $role->description, PDO::PARAM_STR);
            $stmt->bindParam(':active', $role->active, PDO::PARAM_INT);
            $stmt->bindParam(':last_update', $lastUpdate, PDO::PARAM_STR);

            $stmt->execute();
        } catch (RuntimeException $e) {
            echo 'Update not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function concreteDelete(DomainObjectInterface &$role): void
    {
        \assert($role instanceof Role, new InvalidArgumentException(self::EXCEPTION_MESSAGE));

        //get value to be passed as reference
        $objId = $role->getId();

        try {
            //make query
            $stmt = $this->pdo->prepare('DELETE FROM role WHERE role_id = :id');

            $stmt->bindParam(':id', $objId, PDO::PARAM_INT);
            $stmt->execute();

            $role = new NullDomainObject();
        } catch (RuntimeException $e) {
            echo 'Delete not compled, ', $e->getMessage(), "\n";
        }
    }
}
