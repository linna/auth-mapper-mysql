<?php

/**
 * Linna Framework.
 *
 * @author Sebastian Rapetti <sebastian.rapetti@tim.it>
 * @copyright (c) 2020, Sebastian Rapetti
 * @license http://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Linna\Tests;

use Linna\Authentication\Password;
use Linna\Authorization\EnhancedUserMapper;
use Linna\Authorization\PermissionMapper;
use Linna\Authorization\RoleMapper;
use Linna\Authorization\RoleToUserMapper;
use Linna\Authentication\UserMapper;
use Linna\Storage\StorageFactory;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Role To User Mapper Test.
 */
class RoleToUserMapperTest extends TestCase
{
    use RoleToUserMapperTrait;

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
        $password = new Password();

        $permissionMapper = new PermissionMapper($pdo);
        $role2userMapper = new RoleToUserMapper($pdo, $password);
        $userMapper = new UserMapper($pdo, $password);

        self::$pdo = $pdo;
        self::$permissionMapper = $permissionMapper;
        self::$roleMapper = new RoleMapper($pdo, $permissionMapper, $userMapper, $role2userMapper);
        self::$enhancedUserMapper = new EnhancedUserMapper($pdo, $password, $permissionMapper, $role2userMapper);
        self::$roleToUserMapper = $role2userMapper;
    }
}
