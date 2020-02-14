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
//use Linna\Authentication\User;
use Linna\Authentication\UserMapper;
use Linna\Authorization\EnhancedUser;
use Linna\Authorization\EnhancedUserMapper;
use Linna\Authorization\Permission;
use Linna\Authorization\PermissionMapper;
use Linna\Authorization\Role;
use Linna\Authorization\RoleMapper;
use Linna\Authorization\RoleToUserMapper;
use Linna\DataMapper\NullDomainObject;
use Linna\Storage\ExtendedPDO;
use Linna\Storage\StorageFactory;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Role Mapper Test.
 */
class RoleMapperTest extends TestCase
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
    }

    /**
     * Tear Down.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        self::$pdo->exec('ALTER TABLE role AUTO_INCREMENT = 0');
    }

    /**
     * Test new instance.
     */
    public function testNewInstance(): void
    {
        $this->assertInstanceOf(RoleMapper::class, self::$roleMapper);
    }

    public function testFetchById(/*int $roleId*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testFetchAll(): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testFetchLimit(/*int $offset, int $rowCount*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testFetchByPermission(/*Permission $permission*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testFetchByPermissionId(/*int $permissionId*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testFetchByPermissionName(/*string $permissionName*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testFetchByUser(/*EnhancedUser $user*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testFetchByUserId(/*int $userId*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testFetchByUserName(/*string $userName*/): void
    {
        $this->assertTrue(true);
    }


    /**
     * {@inheritdoc}
     */
    public function testGrantPermission(/*Role &$role, Permission $permission*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testGrantPermissionById(/*Role &$role, int $permissionId*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testGrantPermissionByName(/*Role &$role, string $permissionName*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testRevokePermission(/*Role &$role, Permission $permission*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testRevokePermissionById(/*Role &$role, int $permissionId*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testRevokePermissionByName(/*Role &$role, string $permissionName*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testAddUser(/*Role &$role, EnhancedUser $user*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testAddUserById(/*Role &$role, int $userId*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testAddUserByName(/*Role &$role, string $userName*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testRemoveUser(/*Role &$role, EnhancedUser $user*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testRemoveUserById(/*Role &$role, int $userId*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * {@inheritdoc}
     */
    public function testRemoveUserByName(/*Role &$role, string $userName*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * Test concrete create.
     *
     * @return void
     */
    public function testConcreteCreate(): void
    {
        $this->assertInstanceOf(Role::class, self::$roleMapper->create());
    }

    /**
     * Test concrete insert.
     *
     * @return void
     */
    public function testConcreteInsert(): void
    {
        $role = self::$roleMapper->create();
        $role->name = 'test_role';

        $this->assertEquals(0, $role->getId());

        self::$roleMapper->save($role);

        $this->assertGreaterThan(0, $role->getId());

        $roleStored = self::$roleMapper->fetchByName('test_role');

        $this->assertInstanceOf(Role::class, $roleStored);
    }

    /**
     * Test concrete update.
     *
     * @depends testConcreteInsert
     *
     * @return void
     */
    public function testConcreteUpdate(): void
    {
        $roleStored = self::$roleMapper->fetchByName('test_role');

        $this->assertInstanceOf(Role::class, $roleStored);
        $this->assertEquals('test_role', $roleStored->name);

        $roleStored->name = 'test_role_update';

        self::$roleMapper->save($roleStored);

        $roleStoredUpdated = self::$roleMapper->fetchByName('test_role_update');

        $this->assertInstanceOf(Role::class, $roleStoredUpdated);
        $this->assertEquals('test_role_update', $roleStoredUpdated->name);
    }

    /**
     * Test concrete delete.
     *
     * @depends testConcreteInsert
     *
     * @return void
     */
    public function testConcreteDelete(): void
    {
        $roleStored = self::$roleMapper->fetchByName('test_role_update');

        $this->assertInstanceOf(Role::class, $roleStored);
        $this->assertEquals('test_role_update', $roleStored->name);

        self::$roleMapper->delete($roleStored);

        $this->assertInstanceOf(NullDomainObject::class, $roleStored);
    }
}
