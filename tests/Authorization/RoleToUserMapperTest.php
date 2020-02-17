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
use Linna\Authorization\EnhancedUser;
use Linna\Authorization\EnhancedUserMapper;
//use Linna\Authorization\Permission;
use Linna\Authorization\PermissionMapper;
use Linna\Authorization\Role;
use Linna\Authorization\RoleMapper;
use Linna\Authorization\RoleToUserMapper;
use Linna\Authentication\UserMapper;
use Linna\DataMapper\NullDomainObject;
use Linna\Storage\ExtendedPDO;
use Linna\Storage\StorageFactory;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Role To User Mapper Test.
 */
class RoleToUserMapperTest extends TestCase
{
    /**
     * @var EnhancedUserMapper The enhanced user mapper class.
     */
    protected static EnhancedUserMapper $enhancedUserMapper;

    /**
     * @var PermissionMapper The permission mapper class.
     */
    protected static PermissionMapper $permissionMapper;

    /**
     * @var RoleMapper The role mapper class.
     */
    protected static RoleMapper $roleMapper;

    /**
     * @var RoleToUserMapper The role to user mapèper test.
     */
    protected static RoleToUserMapper $roleToUserMapper;

    /**
     * @var ExtendedPDO Database connection.
     */
    protected static ExtendedPDO $pdo;

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

    /**
     * Test new instance.
     */
    public function testNewInstance()
    {
        $this->assertInstanceOf(RoleToUserMapper::class, self::$roleToUserMapper);
    }
    
    /**
     * Role id provider.
     *
     * @return array
     */
    public function roleIdProvider(): array
    {
        return [
            [1, 1],
            [2, 2],
            [3, 4],
            [4, 0]
        ];
    }
    
    /**
     * Test fetch by role.
     *
     * @dataProvider roleIdProvider
     *
     * @param int $roleId
     * @param int $result
     *
     * @return void
     */
    public function testFetchByRole(int $roleId, int $result): void
    {
        $role = self::$roleMapper->fetchById($roleId);

        if ($role instanceof Role) {
            $this->assertCount($result, self::$roleToUserMapper->fetchByRole($role));
        }

        if ($role instanceof NullDomainObject) {
            $this->assertSame($roleId, 4);
            $this->assertSame($result, 0);
        }
    }

    /**
     * Test fetch by role id.
     *
     * @dataProvider roleIdProvider
     *
     * @param int $roleId
     * @param int $result
     *
     * @return void
     */
    public function testFetchByRoleId(int $roleId, int $result): void
    {
        $this->assertCount($result, self::$roleToUserMapper->fetchByRoleId($roleId));
    }

    /**
     * Role name provider.
     *
     * @return array
     */
    public function roleNameProvider(): array
    {
        return [
            ['Administrator', 1],
            ['Power Users', 2],
            ['Users', 4],
            ['Other', 0]
        ];
    }

    /**
     * Test fetch by role name.
     *
     * @dataProvider roleNameProvider
     *
     * @param string $roleName
     * @param int    $result
     *
     * @return void
     */
    public function testFetchByRoleName(string $roleName, int $result): void
    {
        $this->assertCount($result, self::$roleToUserMapper->fetchByRoleName($roleName));
    }

    /**
     * User id provider.
     *
     * @return array
     */
    public function userIdProvider(): array
    {
        //all users have only a group
        return [
            [1, 1],
            [2, 1],
            [3, 1],
            [4, 1],
            [5, 1],
            [6, 1],
            [7, 1],
            [8, 0]
        ];
    }

    /**
     * Test fetch by user.
     *
     * @dataProvider userIdProvider
     *
     * @param int $userId
     * @param int $result
     *
     * @return void
     */
    public function testFetchByUser(int $userId, int $result): void
    {
        $user = self::$enhancedUserMapper->fetchById($userId);

        if ($user instanceof EnhancedUser) {
            $this->assertCount($result, self::$roleToUserMapper->fetchByUser($user));
        }

        if ($user instanceof NullDomainObject) {
            $this->assertSame($userId, 8);
            $this->assertSame($result, 0);
        }
    }

    /**
     * Test fetch by user id.
     *
     * @dataProvider userIdProvider
     *
     * @param int $userId
     * @param int $result
     *
     * @return void
     */
    public function testFetchByUserId(int $userId, int $result): void
    {
        $this->assertCount($result, self::$roleToUserMapper->fetchByUserId($userId));
    }

    /**
     * User name provider.
     *
     * @return array
     */
    public function userNameProvider(): array
    {
        return [
            ['root', 1],
            ['User_0', 1],
            ['User_1', 1],
            ['User_2', 1],
            ['User_3', 1],
            ['User_4', 1],
            ['User_5', 1],
            ['other_user', 0]
        ];
    }

    /**
     * Test fetch by user name.
     *
     * @dataProvider userNameProvider
     *
     * @param string $userName
     * @param int    $result
     *
     * @return void
     */
    public function testFetchByUserName(string $userName, int $result): void
    {
        $this->assertCount($result, self::$roleToUserMapper->fetchByUserName($userName));
    }
}
