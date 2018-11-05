<?php

/**
 * Linna Framework.
 *
 * @author Sebastian Rapetti <sebastian.rapetti@alice.it>
 * @copyright (c) 2018, Sebastian Rapetti
 * @license http://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Linna\Tests;

use Linna\Authentication\Password;
use Linna\Authentication\UserMapper;
use Linna\Storage\StorageFactory;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * User Mapper Test.
 */
class UserMapperTest extends TestCase
{
    /**
     * @var UserMapper The enhanced authentication mapper class
     */
    protected $userMapper;

    /**
     * Setup.
     */
    public function setUp(): void
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

        $this->userMapper = new UserMapper((new StorageFactory('pdo', $options))->get(), new Password());
    }

    /**
     * Test new instance.
     */
    public function testNewInstance()
    {
        $this->assertInstanceOf(UserMapper::class, $this->userMapper);
    }
}
