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

use InvalidArgumentException;
use Linna\DataMapper\DomainObjectInterface;
use Linna\DataMapper\MapperAbstract;
use Linna\DataMapper\NullDomainObject;
use Linna\Storage\ExtendedPDO;
use PDO;
use RuntimeException;

/**
 * EnhancedAuthenticationMapper.
 */
class EnhancedAuthenticationMapper extends MapperAbstract implements EnhancedAuthenticationMapperInterface
{
    /**
     * @var ExtendedPDO Database Connection
     */
    protected ExtendedPDO $pdo;

    /**
     * @var string Constant part of SELECT query
     */
    protected string $baseQuery = 'SELECT login_attempt_id AS id, user_name AS userName, session_id AS sessionId, ip, date_time AS "when", created, last_update AS lastUpdate FROM login_attempt';

    /**
     * Constructor.
     *
     * @param ExtendedPDO $pdo
     */
    public function __construct(ExtendedPDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Fetch a login attempt by id.
     *
     * @param int $loginAttemptId
     *
     * @return DomainObjectInterface
     */
    public function fetchById(int $loginAttemptId): DomainObjectInterface
    {
        $pdos = $this->pdo->prepare("{$this->baseQuery} WHERE login_attempt_id = :id");

        $pdos->bindParam(':id', $loginAttemptId, PDO::PARAM_INT);
        $pdos->execute();

        $result = $pdos->fetchObject(LoginAttempt::class);

        return ($result instanceof LoginAttempt) ? $result : new NullDomainObject();
    }

    /**
     * Fetch all login attempts stored in data base.
     *
     * @return array
     */
    public function fetchAll(): array
    {
        $pdos = $this->pdo->prepare($this->baseQuery);

        $pdos->execute();

        $array = $pdos->fetchAll(PDO::FETCH_CLASS, LoginAttempt::class);

        return \array_combine(\array_column($array, 'id'), $array);
    }

    /**
     * Fetch login attempt with limit.
     *
     * @param int $offset   Offset of the first row to return
     * @param int $rowCount Maximum number of rows to return
     *
     * @return array
     */
    public function fetchLimit(int $offset, int $rowCount): array
    {
        $pdos = $this->pdo->prepare("{$this->baseQuery} ORDER BY date_time ASC LIMIT :offset, :rowcount");

        $pdos->bindParam(':offset', $offset, PDO::PARAM_INT);
        $pdos->bindParam(':rowcount', $rowCount, PDO::PARAM_INT);
        $pdos->execute();

        $array = $pdos->fetchAll(PDO::FETCH_CLASS, LoginAttempt::class);

        return \array_combine(\array_column($array, 'id'), $array);
    }

    /**
     * Return how many login attempts did with the same user in specified time.
     *
     * @param string $userName      User name
     * @param int    $timeInSeconds Attempts in the last specified seconds
     *
     * @return int
     */
    public function fetchAttemptsWithSameUser(string $userName, int $timeInSeconds): int
    {
        $pdos = $this->pdo->prepare('SELECT count(user_name) as attempts FROM login_attempt WHERE user_name = :user_name AND date_time > :time');

        $pdos->bindParam(':user_name', $userName, PDO::PARAM_STR);

        $time = (int) \date('YmdHis', \time() - $timeInSeconds);
        $pdos->bindParam(':time', $time, PDO::PARAM_INT);

        $pdos->execute();

        return (int) $pdos->fetch(PDO::FETCH_LAZY)->attempts;
    }

    /**
     * Return how many login attempts did with the same session in specified time.
     *
     * @param string $sessionId     Session id
     * @param int    $timeInSeconds Attempts in the last specified seconds
     *
     * @return int
     */
    public function fetchAttemptsWithSameSession(string $sessionId, int $timeInSeconds): int
    {
        $pdos = $this->pdo->prepare('SELECT count(session_id) as attempts FROM login_attempt WHERE session_id = :session_id AND date_time > :time');

        $pdos->bindParam(':session_id', $sessionId, PDO::PARAM_STR);

        $time = (int) \date('YmdHis', \time() - $timeInSeconds);
        $pdos->bindParam(':time', $time, PDO::PARAM_INT);

        $pdos->execute();

        return (int) $pdos->fetch(PDO::FETCH_LAZY)->attempts;
    }

    /**
     * Return how many login attempts did with the same session in specified time.
     *
     * @param string $ipAddress     Ip address
     * @param int    $timeInSeconds Attempts in the last specified seconds
     *
     * @return int
     */
    public function fetchAttemptsWithSameIp(string $ipAddress, int $timeInSeconds): int
    {
        $pdos = $this->pdo->prepare('SELECT count(ip) as attempts FROM login_attempt WHERE ip = :ip AND date_time > :time');

        $pdos->bindParam(':ip', $ipAddress, PDO::PARAM_STR);

        $time = (int) \date('YmdHis', \time() - $timeInSeconds);
        $pdos->bindParam(':time', $time, PDO::PARAM_INT);

        $pdos->execute();

        return (int) $pdos->fetch(PDO::FETCH_LAZY)->attempts;
    }

    /**
     * Remove old login attempts.
     *
     * @param int $timeInSeconds
     *
     * @return bool
     */
    public function deleteOldLoginAttempts(int $timeInSeconds): bool
    {
        $pdos = $this->pdo->prepare('DELETE FROM login_attempt WHERE date_time < :time');

        $time = (int) \date('YmdHis', \time() - $timeInSeconds);
        $pdos->bindParam(':time', $time, PDO::PARAM_INT);

        $pdos->execute();

        return true;
    }

    /**
     * Create a new instance of a LoginAttempt.
     *
     * @return DomainObjectInterface
     */
    protected function concreteCreate(): DomainObjectInterface
    {
        return new LoginAttempt();
    }

    /**
     * Insert a LoginAttempt object to persistent storage.
     * User object passed as reference, gain the id of the persistent
     * storage record.
     *
     * @param DomainObjectInterface $loginAttempt
     */
    protected function concreteInsert(DomainObjectInterface &$loginAttempt)
    {
        $this->checkDomainObjectType($loginAttempt);

        try {
            $pdos = $this->pdo->prepare('INSERT INTO login_attempt (user_name, session_id, ip, date_time) VALUES (:user_name, :session_id, :ip, :date_time)');

            $pdos->bindParam(':user_name', $loginAttempt->userName, PDO::PARAM_STR);
            $pdos->bindParam(':session_id', $loginAttempt->sessionId, PDO::PARAM_STR);
            $pdos->bindParam(':ip', $loginAttempt->ipAddress, PDO::PARAM_STR);
            $pdos->bindParam(':date_time', $loginAttempt->when, PDO::PARAM_STR);

            $pdos->execute();

            $loginAttempt->setId((int) $this->pdo->lastInsertId());
        } catch (RuntimeException $e) {
            echo 'Insert not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * Update a LoginAttempt object in persistent storage.
     *
     * @param DomainObjectInterface $loginAttempt
     */
    protected function concreteUpdate(DomainObjectInterface $loginAttempt)
    {
        $this->checkDomainObjectType($loginAttempt);

        try {
            $pdos = $this->pdo->prepare('UPDATE login_attempt SET user_name = :user_name,  session_id = :session_id, ip = :ip,  date_time = :date_time WHERE login_attempt_id = :id');

            $objId = $loginAttempt->getId();

            $pdos->bindParam(':id', $objId, PDO::PARAM_INT);

            $pdos->bindParam(':user_name', $loginAttempt->userName, PDO::PARAM_STR);
            $pdos->bindParam(':session_id', $loginAttempt->sessionId, PDO::PARAM_STR);
            $pdos->bindParam(':ip', $loginAttempt->ipAddress, PDO::PARAM_STR);
            $pdos->bindParam(':date_time', $loginAttempt->when, PDO::PARAM_STR);

            $pdos->execute();
        } catch (RuntimeException $e) {
            echo 'Update not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * Delete a LoginAttempt object from peristent Storage.
     * User object passed as reference, become NullDomainObject after
     * deletion.
     *
     * @param DomainObjectInterface $domainObject
     */
    protected function concreteDelete(DomainObjectInterface &$loginAttempt)
    {
        $this->checkDomainObjectType($loginAttempt);

        $objId = $loginAttempt->getId();

        try {
            $pdos = $this->pdo->prepare('DELETE FROM login_attempt WHERE login_attempt_id = :id');
            $pdos->bindParam(':id', $objId, PDO::PARAM_INT);
            $pdos->execute();

            $loginAttempt = new NullDomainObject();
        } catch (RuntimeException $e) {
            echo 'Delete not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * Check for valid domain Object.
     *
     * @param DomainObjectInterface $domainObject
     *
     * @throws InvalidArgumentException if the domain object isn't of the type required by mapper
     */
    protected function checkDomainObjectType(DomainObjectInterface $domainObject)
    {
        if (!($domainObject instanceof LoginAttempt)) {
            throw new InvalidArgumentException('Domain Object parameter must be instance of LoginAttempt class');
        }
    }
}
