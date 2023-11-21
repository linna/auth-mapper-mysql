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

use Linna\Storage\StorageFactory;
use PDO;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

/**
 * Enhanced Authentication Mapper Test.
 */
class EnhancedAuthenticationMapperTest extends TestCase
{
    use EnhancedAuthenticationMapperTrait;

    /** @var string Date format string. */
    protected static string $date_format = 'Y-m-d H:i:s';

    /**
     * Setup.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        $options = [
            'dsn'      => $GLOBALS['pdo_mysql_dsn'],
            'user'     => $GLOBALS['pdo_mysql_user'],
            'password' => $GLOBALS['pdo_mysql_password'],
            'options'  => [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            ],
        ];

        $pdo = (new StorageFactory('pdo', $options))->get();

        self::$pdo = $pdo;
        self::$enhancedAuthenticationMapper = new EnhancedAuthenticationMapper($pdo);

        self::$pdo->exec('DELETE FROM login_attempt');
        self::$pdo->exec('ALTER TABLE login_attempt AUTO_INCREMENT = 0');
        //self::$pdo->exec('ALTER TABLE login_attempt AUTO_INCREMENT = 0');

        //populate data base
        self::createFakeData();
    }

    /**
     * Insert fake data into poersistent storage for tests.
     *
     * @return void
     */
    protected static function createFakeData(): void
    {
        $loginAttempt = [
            ['root', 'mbvi2lgdpcj6vp3qemh2estei2', '192.168.1.2'],
            ['root', 'mbvi2lgdpcj6vp3qemh2estei2', '192.168.1.2'],
            ['root', 'mbvi2lgdpcj6vp3qemh2estei2', '192.168.1.2'],
            ['root', 'mbvi2lgdpcj6vp3qemh2estei2', '192.168.1.2'],
            ['root', 'mbvi2lgdpcj6vp3qemh2estei2', '192.168.1.2'],
            ['root', 'mbvi2lgdpcj6vp3qemh2estei2', '192.168.1.2'],
            ['admin', 'mbvi2lgdpcj6vp3qemh2estei2', '192.168.1.2'],
            ['admin', 'mbvi2lgdpcj6vp3qemh2estei2', '192.168.1.2'],
            ['admin', 'mbvi2lgdpcj6vp3qemh2estei2', '192.168.1.2'],
            ['admin', 'mbvi2lgdpcj6vp3qemh2estei2', '192.168.1.2'],
            ['admin', 'mbvi2lgdpcj6vp3qemh2estei2', '192.168.1.2'],
            ['admin', 'mbvi2lgdpcj6vp3qemh2estei2', '192.168.1.2'],
            ['administrator', 'vaqgvpochtif8gh888q6vnlch5', '192.168.1.2'],
            ['administrator', 'vaqgvpochtif8gh888q6vnlch5', '192.168.1.2'],
            ['administrator', 'vaqgvpochtif8gh888q6vnlch5', '192.168.1.2'],
            ['administrator', 'vaqgvpochtif8gh888q6vnlch5', '192.168.1.2'],
            ['administrator', 'vaqgvpochtif8gh888q6vnlch5', '192.168.1.2'],
            ['administrator', 'vaqgvpochtif8gh888q6vnlch5', '192.168.1.2'],
            ['poweruser', 'vaqgvpochtif8gh888q6vnlch5', '192.168.1.2'],
            ['poweruser', 'vaqgvpochtif8gh888q6vnlch5', '192.168.1.2'],
            ['poweruser', 'vaqgvpochtif8gh888q6vnlch5', '192.168.1.2'],
            ['poweruser', 'vaqgvpochtif8gh888q6vnlch5', '192.168.1.2'],
            ['poweruser', 'vaqgvpochtif8gh888q6vnlch5', '192.168.1.2'],
            ['poweruser', 'vaqgvpochtif8gh888q6vnlch5', '192.168.1.2'],
            ['fooroot', '3hto06tko273jjc1se0v1aqvvn', '192.168.1.3'],
            ['fooroot', '3hto06tko273jjc1se0v1aqvvn', '192.168.1.3'],
            ['fooroot', '3hto06tko273jjc1se0v1aqvvn', '192.168.1.3'],
            ['fooroot', '3hto06tko273jjc1se0v1aqvvn', '192.168.1.3'],
        ];

        $timeSliding = \count($loginAttempt);

        foreach ($loginAttempt as $data) {
            $loginAttempt = new LoginAttempt(
                userName: $data[0],
                sessionId: $data[1],
                ipAddress: $data[2],
                when: new DateTimeImmutable("-{$timeSliding} seconds"),
                created: new DateTimeImmutable("-{$timeSliding} seconds"),
                lastUpdate: new DateTimeImmutable("-{$timeSliding} seconds")
            );

            $timeSliding--;
            self::$enhancedAuthenticationMapper->save($loginAttempt);
        }
    }

    /**
     * Tear Down.
     *
     * @return void
     */
    /*public static function tearDownAfterClass(): void
    {
        //self::$pdo->exec('DELETE FROM login_attempt');
        //self::$pdo->exec('ALTER TABLE login_attempt AUTO_INCREMENT = 0');
    }*/
}
