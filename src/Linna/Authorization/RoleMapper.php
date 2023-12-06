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
use Linna\DataMapper\DomainObjectInterface;
use Linna\DataMapper\MapperAbstract;
use Linna\DataMapper\NullDomainObject;
use Linna\Storage\ExtendedPDO;
use PDO;
use RuntimeException;
use stdClass;

/**
 * Role Mapper.
 */
class RoleMapper extends MapperAbstract implements RoleMapperInterface
{
    protected const QUERY_BASE = 'SELECT role_id, name, description, active, created, last_update FROM role';

    private const EXCEPTION_MESSAGE = 'Domain Object parameter must be instance of EnhancedUser class';

    /**
     * Constructor.
     *
     * @param ExtendedPDO $pdo Valid <code>PDO</code> instace to interact with the persistent storage.
     *
     */
    public function __construct(
        /** @var ExtendedPDO Database Connection. */
        protected ExtendedPDO $pdo,
    ) {
    }

    /**
     * Hydrate an array of objects.
     *
     * @param array<int, stdClass> $array The array containing the resultset from database.
     *
     * @return array<int, EnhancedUser>
     */
    protected function hydrator(array $array): array
    {
        $tmp = [];

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
     * Hydrate an object.
     *
     * @param object $object The object containing the resultset from database.
     *
     * @return DomainObjectInterface
     */
    protected function hydratorSingle(object $object): DomainObjectInterface
    {
        return new Role(
            id:              $object->role_id,
            name:            $object->name,
            description:     $object->description,
            active:          $object->active,
            created:         new DateTimeImmutable($object->created),
            lastUpdate:      new DateTimeImmutable($object->last_update),
        );
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

        //return result
        return $this->hydratorSingle($stdClass);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchByName(string $roleName): DomainObjectInterface
    {
        //handle user name
        $hashedRoleName = md5($roleName);

        //make query
        $stmt = $this->pdo->prepare(self::QUERY_BASE.' WHERE md5(name) = :name');
        $stmt->bindParam(':name', $hashedRoleName, PDO::PARAM_STR);
        $stmt->execute();

        //fail fast
        if (($stdClass = $stmt->fetchObject()) === false) {
            return new NullDomainObject();
        }

        //return result
        return $this->hydratorSingle($stdClass);
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
        return $this->hydrator($result);
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
        return $this->hydrator($result);
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
        return $this->hydrator($result);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchByPermissionName(string $permissionName): array
    {
        //make query
        $stmt = $this->pdo->prepare('
        SELECT 
            r.role_id, r.name, r.description, r.active, r.created, r.last_update
        FROM
            role AS r
                INNER JOIN
            role_permission AS rp ON r.role_id = rp.role_id
                INNER JOIN
            permission as p ON rp.permission_id = p.permission_id
        WHERE
            p.name = :name');

        $stmt->bindParam(':name', $permissionName, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_CLASS, stdClass::class);

        //fail fast, returns the empty array
        if (\count($result) === 0) {
            return $result;
        }

        //return result
        return $this->hydrator($result);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchByUser(User $user): array
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
        return $this->hydrator($result);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchByUserName(string $userName): array
    {
        //make query
        $stmt = $this->pdo->prepare('
        SELECT 
            r.role_id, r.name, r.description, r.active, r.created, r.last_update
        FROM
            role AS r
                INNER JOIN
            user_role AS ur ON r.role_id = ur.role_id
                INNER JOIN
            user AS u ON ur.user_id = u.user_id
        WHERE
            u.name = :name');

        $stmt->bindParam(':name', $userName, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_CLASS, stdClass::class);

        //fail fast, returns the empty array
        if (\count($result) === 0) {
            return $result;
        }

        //return result
        return $this->hydrator($result);
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
