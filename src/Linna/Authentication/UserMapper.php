<?php

/**
 * Linna Framework.
 *
 * @author Sebastian Rapetti <sebastian.rapetti@tim.it>
 * @copyright (c) 2020, Sebastian Rapetti
 * @license http://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Linna\Authentication;

use DateTimeImmutable;
use InvalidArgumentException;
use Linna\DataMapper\DomainObjectAbstract;
use Linna\DataMapper\DomainObjectInterface;
use Linna\DataMapper\MapperAbstract;
use Linna\DataMapper\NullDomainObject;
use Linna\Storage\ExtendedPDO;
use PDO;
use RuntimeException;
use stdClass;

/**
 * UserMapper.
 */
class UserMapper extends MapperAbstract implements UserMapperInterface
{
    protected const QUERY_BASE = 'SELECT user_id , uuid, name, email, description, password, active, created, last_update FROM user';

    private const EXCEPTION_MESSAGE = 'Domain Object parameter must be instance of User class';

    /** @var Password Password util for user object */
    protected static Password $password;

    /**
     * Constructor.
     *
     * @param ExtendedPDO $pdo
     * @param Password    $password
     */
    public function __construct(
        /** @var ExtendedPDO Database Connection */
        protected ExtendedPDO $pdo,

        /** @var Password Password util for user object */
        Password $passwordUtility = new Password()
    ) {
        self::$password = $passwordUtility;
    }

    /**
     * Hydrate an array of objects.
     *
     * @param array<int, stdClass> $array The array containing the resultset from database.
     *
     * @return array<int, User>
     */
    private function hydrator(array $array): array
    {
        $tmp = [];

        foreach ($array as $value) {
            $tmp[] = new User(
                passwordUtility: self::$password,
                id:              $value->user_id,
                uuid:            $value->uuid,
                name:            $value->name,
                description:     $value->session_id,
                email:           $value->email,
                password:        $value->password,
                active:          $value->active,
                created:         new DateTimeImmutable($value->created),
                lastUpdate:      new DateTimeImmutable($value->last_update)
            );
        }

        return $tmp;
    }

    /**
     * Fetch a user by id.
     *
     * @param int|string $userId
     *
     * @return DomainObjectInterface
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

        //return result
        return new User(
            passwordUtility: self::$password,
            id:              $stdClass->user_id,
            uuid:            $stdClass->uuid,
            name:            $stdClass->name,
            description:     $stdClass->session_id,
            email:           $stdClass->email,
            password:        $stdClass->password,
            active:          $stdClass->active,
            created:         new DateTimeImmutable($stdClass->created),
            lastUpdate:      new DateTimeImmutable($stdClass->last_update)
        );
    }

    /**
     * Fetch a user by name.
     *
     * @param string $userName
     *
     * @return DomainObjectAbstract
     */
    public function fetchByName(string $userName): DomainObjectInterface
    {
        //handle user name
        $hashedUserName = md5($userName);

        //make query
        $stmt = $this->pdo->prepare(self::QUERY_BASE.' WHERE md5(name) = :name');
        $stmt->bindParam(':name', $hashedUserName, PDO::PARAM_STR);
        $stmt->execute();

        //fail fast
        if (($stdClass = $stmt->fetchObject()) === false) {
            return new NullDomainObject();
        }

        //return result
        return new User(
            passwordUtility: self::$password,
            id:              $stdClass->user_id,
            uuid:            $stdClass->uuid,
            name:            $stdClass->name,
            description:     $stdClass->session_id,
            email:           $stdClass->email,
            password:        $stdClass->password,
            active:          $stdClass->active,
            created:         new DateTimeImmutable($stdClass->created),
            lastUpdate:      new DateTimeImmutable($stdClass->last_update)
        );
    }

    /**
     * Fetch all users stored in data base.
     *
     * @return array
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
     * Fetch users with limit.
     *
     * @param int $offset   Offset of the first row to return
     * @param int $rowCount Maximum number of rows to return
     *
     * @return array
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
     * Create a new instance of a User.
     *
     * @return DomainObjectInterface
     */
    protected function concreteCreate(): DomainObjectInterface
    {
        return new User(passwordUtility: $this->password);
    }

    /**
     * Insert a User object to persistent storage.
     * User object passed as reference, gain the id of the persistent
     * storage record.
     *
     * @param DomainObjectInterface $user
     *
     * @return void
     */
    protected function concreteInsert(DomainObjectInterface &$user): void
    {
        \assert($user instanceof User, new InvalidArgumentException(self::EXCEPTION_MESSAGE));

        //get value to be passed as reference
        $created = $user->created->format(DATE_ATOM);
        $lastUpdate = $user->lastUpdate->format(DATE_ATOM);

        try {
            //make query
            $stmt = $this->pdo->prepare('INSERT INTO user (uuid, name, email, description, password, created, last_update) 
                VALUES (:uuid, :name, :email, :description, :password, :created, :last_update)');
            $stmt->bindParam(':uuid', $user->uuid, PDO::PARAM_STR);
            $stmt->bindParam(':name', $user->name, PDO::PARAM_STR);
            $stmt->bindParam(':email', $user->email, PDO::PARAM_STR);
            $stmt->bindParam(':description', $user->description, PDO::PARAM_STR);
            $stmt->bindParam(':password', $user->password, PDO::PARAM_STR);
            $stmt->bindParam(':created', $created, PDO::PARAM_STR);
            $stmt->bindParam(':last_update', $lastUpdate, PDO::PARAM_STR);

            $stmt->execute();

            $user->setId((int) $this->pdo->lastInsertId());
        } catch (RuntimeException $e) {
            echo 'Insert not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * Update a User object in persistent storage.
     *
     * @param DomainObjectInterface $user
     *
     * @return void
     */
    protected function concreteUpdate(DomainObjectInterface $user): void
    {
        \assert($user instanceof User, new InvalidArgumentException(self::EXCEPTION_MESSAGE));

        //get value to be passed as reference
        $objId = $user->getId();
        $lastUpdate = $user->lastUpdate->format(DATE_ATOM);

        try {
            //make query
            $stmt = $this->pdo->prepare('UPDATE user 
                SET name = :name, email = :email, description = :description,  password = :password, active = :active, last_update = :last_update 
                WHERE user_id = :id');

            $stmt->bindParam(':id', $objId, PDO::PARAM_INT);
            $stmt->bindParam(':name', $user->name, PDO::PARAM_STR);
            $stmt->bindParam(':email', $user->email, PDO::PARAM_STR);
            $stmt->bindParam(':description', $user->description, PDO::PARAM_STR);
            $stmt->bindParam(':password', $user->password, PDO::PARAM_STR);
            $stmt->bindParam(':active', $user->active, PDO::PARAM_INT);
            $stmt->bindParam(':last_update', $lastUpdate, PDO::PARAM_STR);

            $stmt->execute();
        } catch (RuntimeException $e) {
            echo 'Update not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * Delete a User object from peristent Storage.
     * User object passed as reference, become NullDomainObject after
     * deletion.
     *
     * @param DomainObjectInterface $domainObject
     *
     * @return void
     */
    protected function concreteDelete(DomainObjectInterface &$user): void
    {
        \assert($user instanceof User, new InvalidArgumentException(self::EXCEPTION_MESSAGE));

        //get value to be passed as reference
        $objId = $user->getId();

        try {
            $stmt = $this->pdo->prepare('DELETE FROM user WHERE user_id = :id');
            $stmt->bindParam(':id', $objId, PDO::PARAM_INT);
            $stmt->execute();

            $user = new NullDomainObject();
        } catch (RuntimeException $e) {
            echo 'Delete not compled, ', $e->getMessage(), "\n";
        }
    }
}
