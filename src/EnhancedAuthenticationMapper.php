<?php

/**
 * Linna Framework.
 *
 * @author Sebastian Rapetti <sebastian.rapetti@alice.it>
 * @copyright (c) 2018, Sebastian Rapetti
 * @license http://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Linna\Authentication;

use InvalidArgumentException;
use Linna\Authentication\EnhancedAuthenticationMapperInterface;
use Linna\Authentication\LoginAttempt;
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
    protected $pdo;

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
     * {@inheritdoc}
     */
    public function fetchById(int $loginAttemptId): DomainObjectInterface
    {
        $pdos = $this->pdo->prepare('SELECT login_attempt_id AS objectId, user_name AS userName, session_id AS sessionId, ip, date_time AS when, last_update AS lastUpdate FROM login_attempt WHERE login_attempt_id = :id');

        $pdos->bindParam(':id', $loginAttemptId, PDO::PARAM_INT);
        $pdos->execute();

        $result = $pdos->fetchObject(LoginAttempt::class);

        return ($result instanceof LoginAttempt) ? $result : new NullDomainObject();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(): array
    {
        $pdos = $this->pdo->prepare('SELECT login_attempt_id AS objectId, user_name AS userName, session_id AS sessionId, ip, date_time AS when, last_update AS lastUpdate FROM login_attempt');

        $pdos->execute();

        return $pdos->fetchAll(PDO::FETCH_CLASS, LoginAttempt::class);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchLimit(int $offset, int $rowCount): array
    {
        $pdos = $this->pdo->prepare('SELECT login_attempt_id AS objectId, user_name AS userName, session_id AS sessionId, ip, date_time AS when, last_update AS lastUpdate FROM login_attempt ORDER BY date_time ASC LIMIT :offset, :rowcount');

        $pdos->bindParam(':offset', $offset, PDO::PARAM_INT);
        $pdos->bindParam(':rowcount', $rowCount, PDO::PARAM_INT);
        $pdos->execute();

        return $pdos->fetchAll(PDO::FETCH_CLASS, LoginAttempt::class);
    }

    /**
     * Return how many login attempts did with the same user in specified time.
     *
     * @param string $userName      User name
     * @param int    $timeInSeconds Attempts in the last specified seconds
     */

    public function fetchAttemptsWithSameUser(string $userName, int $timeInSeconds): int
    {
        $pdos = $this->pdo->prepare('SELECT count(user_name) as attempts FROM login_attempt WHERE user_name = :user_name AND date_time > :time');

        $pdos->bindParam(':user_name', $userName, PDO::PARAM_STR);

        $time = (int) date('YmdHis', time() - $timeInSeconds);
        $pdos->bindParam(':time', $time, PDO::PARAM_INT);

        $pdos->execute();

        return (int) $pdos->fetch(PDO::FETCH_LAZY)->attempts;
    }

    /**
     * Return how many login attempts did with the same session in specified time.
     *
     * @param string $sessionId     Session id
     * @param int    $timeInSeconds Attempts in the last specified seconds
     */
    public function fetchAttemptsWithSameSession(string $sessionId, int $timeInSeconds): int
    {
        $pdos = $this->pdo->prepare('SELECT count(session_id) as attempts FROM login_attempt WHERE session_id = :session_id AND date_time > :time');

        $pdos->bindParam(':session_id', $sessionId, PDO::PARAM_STR);

        $time = (int) date('YmdHis', time() - $timeInSeconds);
        $pdos->bindParam(':time', $time, PDO::PARAM_INT);

        $pdos->execute();

        return (int) $pdos->fetch(PDO::FETCH_LAZY)->attempts;
    }

    /**
     * Return how many login attempts did with the same session in specified time.
     *
     * @param string $ipAddress     Ip address
     * @param int    $timeInSeconds Attempts in the last specified seconds
     */
    public function fetchAttemptsWithSameIp(string $ipAddress, int $timeInSeconds): int
    {
        $pdos = $this->pdo->prepare('SELECT count(ip) as attempts FROM login_attempt WHERE ip = :ip AND date_time > :time');

        $pdos->bindParam(':ip', $ipAddress, PDO::PARAM_STR);

        $time = (int) date('YmdHis', time() - $timeInSeconds);
        $pdos->bindParam(':time', $time, PDO::PARAM_INT);

        $pdos->execute();

        return (int) $pdos->fetch(PDO::FETCH_LAZY)->attempts;
    }

    /**
     * Remove old login attempts
     *
     * @param int $timeInSeconds
     */
    public function deleteOldLoginAttempts(int $timeInSeconds): bool
    {
        $pdos = $this->pdo->prepare('DELETE FROM login_attempt WHERE date_time < :time');

        $time = (int) date('YmdHis', time() - $timeInSeconds);
        $pdos->bindParam(':time', $time, PDO::PARAM_INT);

        $pdos->execute();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function concreteCreate(): DomainObjectInterface
    {
        return new LoginAttempt();
    }

    /**
     * {@inheritdoc}
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

            $loginAttempt->setId((int)$this->pdo->lastInsertId());
            //return (int) $this->pdo->lastInsertId();
        } catch (RuntimeException $e) {
            echo 'Insert not compled, ', $e->getMessage(), "\n";
        }
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    protected function checkDomainObjectType(DomainObjectInterface $domainObject)
    {
        if (!($domainObject instanceof LoginAttempt)) {
            throw new InvalidArgumentException('Domain Object parameter must be instance of LoginAttempt class');
        }
    }
}
