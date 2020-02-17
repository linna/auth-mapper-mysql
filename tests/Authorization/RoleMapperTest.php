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

    /**
     * Permission id provider.
     *
     * @return array
     */
    public function roleIdProvider(): array
    {
        return [
            [1, 1],
            [2, 2],
            [3, 3],
            [4, 0],
            [5, 0]
        ];
    }

    /**
     * Test fetch by id.
     *
     * @dataProvider roleIdProvider
     *
     * @param int $roleId
     * @param int $expectedId
     *
     * @return void
     */
    public function testFetchById(int $roleId, int $expectedId): void
    {
        $role = self::$roleMapper->fetchById($roleId);

        //$this->assertInstanceOf(Role::class, $role);
        $this->assertEquals($role->getId(), $expectedId);
    }

    /**
     * Role name provider
     *
     * @return array
     */
    public function roleNameProvider(): array
    {
        return [
            ['Administrator', 'Administrator'],
            ['Power Users', 'Power Users'],
            ['Users', 'Users'],
            ['bad_role', '']
        ];
    }

    /**
     * Test fetch by name.
     *
     * @dataProvider roleNameProvider
     *
     * @param string $roleName
     * @param string $expectedName
     *
     * @return void
     */
    public function testFetchByName(string $roleName, string $expectedName): void
    {
        $role = self::$roleMapper->fetchByName($roleName);

        if ($expectedName === '') {
            $this->assertInstanceOf(NullDomainObject::class, $role);
            return;
        }

        $this->assertEquals($role->name, $expectedName);
    }

    /**
     * Test fetch all.
     *
     * @return void
     */
    public function testFetchAll(): void
    {
        $this->assertCount(3, self::$roleMapper->fetchAll());
    }

    /**
     * Role fetch limit provider.
     *
     * @return array
     */
    public function roleFetchLimitProvider(): array
    {
        return [
            ['Administrator', 0, 1],
            ['Power Users', 1, 1],
            ['Users', 2, 1]
        ];
    }

    /**
     * Test fetch limit.
     *
     * @dataProvider roleFetchLimitProvider
     *
     * @param string $roleName
     * @param int    $offset
     * @param int    $rowCount
     *
     * @return void
     */
    public function testFetchLimit(string $roleName, int $offset, int $rowCount): void
    {
        $role = self::$roleMapper->fetchLimit($offset, $rowCount);

        $key = \array_keys($role)[0];

        $this->assertCount(1, $role);
        $this->assertEquals($role[$key]->name, $roleName);
    }

    /**
     * Permission id provider.
     *
     * @return array
     */
    public function permissionIdProvider(): array
    {
        return [
            [1, 3],
            [2, 2],
            [3, 1],
            [4, 1],
            [5, 2],
            [6, 2],
            [7, 0]
        ];
    }

    /**
     * Test fetch by permission.
     *
     * @dataProvider permissionIdProvider
     *
     * @param int $permissionId
     * @param int $result
     *
     * @return void
     */
    public function testFetchByPermission(int $permissionId, int $result): void
    {
        $permission = self::$permissionMapper->fetchById($permissionId);

        if ($permission instanceof Permission) {
            $this->assertCount($result, self::$roleMapper->fetchByPermission($permission));
        }

        if ($permission instanceof NullDomainObject) {
            $this->assertSame($permissionId, 7);
            $this->assertSame($result, 0);
        }
    }

    /**
     * Test fetch by permission id.
     *
     * @dataProvider permissionIdProvider
     *
     * @param int $permissionId
     * @param int $result
     *
     * @return void
     */
    public function testFetchByPermissionId(int $permissionId, int $result): void
    {
        $this->assertCount($result, self::$roleMapper->fetchByPermissionId($permissionId));
    }

    /**
     * Permission name provider.
     *
     * @return array
     */
    public function permissionNameProvider(): array
    {
        return [
            ['see users', 3],
            ['update user', 2],
            ['delete user', 1],
            ['create user', 1],
            ['enable user', 2],
            ['disable user', 2],
            ['unknown permission', 0]
        ];
    }

    /**
     * Test fetch by permission name.
     *
     * @dataProvider permissionNameProvider
     *
     * @param string $permissionName
     * @param int    $result
     *
     * @return void
     */
    public function testFetchByPermissionName(string $permissionName, int $result): void
    {
        $this->assertCount($result, self::$roleMapper->fetchByPermissionName($permissionName));
    }

    /**
     * User id provider.
     *
     * @return array
     */
    public function userIdProvider(): array
    {
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
            $this->assertCount($result, self::$roleMapper->fetchByUser($user));
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
        $this->assertCount($result, self::$roleMapper->fetchByUserId($userId));
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
        $this->assertCount($result, self::$roleMapper->fetchByUserName($userName));
    }


    /**
     * Test grant permission.
     *
     * @return void
     */
    public function testGrantPermission(): void
    {
        $role = self::$roleMapper->fetchById(3);
        $permission = self::$permissionMapper->fetchById(6);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertInstanceOf(Permission::class, $permission);

        self::$roleMapper->grantPermission($role, $permission);

        $this->assertTrue($role->can($permission));

        self::$roleMapper->revokePermission($role, $permission);

        $this->assertFalse($role->can($permission));
    }

    /**
     * Test grant permission by id.
     *
     * @return void
     */
    public function testGrantPermissionById(): void
    {
        $role = self::$roleMapper->fetchById(3);
        $permission = self::$permissionMapper->fetchById(6);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertInstanceOf(Permission::class, $permission);

        self::$roleMapper->grantPermissionById($role, $permission->id);

        $this->assertTrue($role->canById($permission->id));

        self::$roleMapper->revokePermissionById($role, $permission->id);

        $this->assertFalse($role->canById($permission->id));
    }

    /**
     * Test grant permission by name.
     *
     * @return void
     */
    public function testGrantPermissionByName(): void
    {
        $role = self::$roleMapper->fetchById(3);
        $permission = self::$permissionMapper->fetchById(6);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertInstanceOf(Permission::class, $permission);

        self::$roleMapper->grantPermissionByName($role, $permission->name);

        $this->assertTrue($role->canByName($permission->name));

        self::$roleMapper->revokePermissionByName($role, $permission->name);

        $this->assertFalse($role->canByName($permission->name));
    }

    /**
     * Test revoke permission.
     *
     * @return void
     */
    public function testRevokePermission(): void
    {
        $role = self::$roleMapper->fetchById(3);
        $permission = self::$permissionMapper->fetchById(6);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertInstanceOf(Permission::class, $permission);

        $this->assertFalse($role->can($permission));

        self::$roleMapper->grantPermission($role, $permission);

        $this->assertTrue($role->can($permission));

        self::$roleMapper->revokePermission($role, $permission);

        $this->assertFalse($role->can($permission));
    }

    /**
     * Test revoke permission by id.
     *
     * @return void
     */
    public function testRevokePermissionById(): void
    {
        $role = self::$roleMapper->fetchById(3);
        $permission = self::$permissionMapper->fetchById(6);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertInstanceOf(Permission::class, $permission);

        $this->assertFalse($role->canById($permission->id));

        self::$roleMapper->grantPermissionById($role, $permission->id);

        $this->assertTrue($role->canById($permission->id));

        self::$roleMapper->revokePermissionById($role, $permission->id);

        $this->assertFalse($role->canById($permission->id));
    }

    /**
     * Test revoke permission by name.
     *
     * @return void
     */
    public function testRevokePermissionByName(): void
    {
        $role = self::$roleMapper->fetchById(3);
        $permission = self::$permissionMapper->fetchById(6);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertInstanceOf(Permission::class, $permission);

        $this->assertFalse($role->canByName($permission->name));

        self::$roleMapper->grantPermissionByName($role, $permission->name);

        $this->assertTrue($role->canByName($permission->name));

        self::$roleMapper->revokePermissionByName($role, $permission->name);

        $this->assertFalse($role->canByName($permission->name));
    }

    /**
     * Test add user.
     *
     * @return void
     */
    public function testAddUser(): void
    {
        $role = self::$roleMapper->fetchById(2);
        $user = self::$enhancedUserMapper->fetchById(7);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertInstanceOf(EnhancedUser::class, $user);

        $this->assertFalse($role->isUserInRole($user));

        self::$roleMapper->addUser($role, $user);

        $this->assertTrue($role->isUserInRole($user));

        self::$roleMapper->removeUser($role, $user);

        $this->assertFalse($role->isUserInRole($user));
    }

    /**
     * Test add user by id.
     *
     * @return void
     */
    public function testAddUserById(): void
    {
        $role = self::$roleMapper->fetchById(2);
        $user = self::$enhancedUserMapper->fetchById(7);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertInstanceOf(EnhancedUser::class, $user);

        $this->assertFalse($role->isUserInRoleById($user->id));

        self::$roleMapper->addUserById($role, $user->id);

        $this->assertTrue($role->isUserInRoleById($user->id));

        self::$roleMapper->removeUserById($role, $user->id);

        $this->assertFalse($role->isUserInRoleById($user->id));
    }

    /**
     * Test add user by id.
     *
     * @return void
     */
    public function testAddUserByName(): void
    {
        $role = self::$roleMapper->fetchById(2);
        $user = self::$enhancedUserMapper->fetchById(7);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertInstanceOf(EnhancedUser::class, $user);

        $this->assertFalse($role->isUserInRoleByName($user->name));

        self::$roleMapper->addUserByName($role, $user->name);

        $this->assertTrue($role->isUserInRoleByName($user->name));

        self::$roleMapper->removeUserByName($role, $user->name);

        $this->assertFalse($role->isUserInRoleByName($user->name));
    }

    /**
     * Test remove user.
     *
     * @return void
     */
    public function testRemoveUser(): void
    {
        $role = self::$roleMapper->fetchById(2);
        $user = self::$enhancedUserMapper->fetchById(7);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertInstanceOf(EnhancedUser::class, $user);

        $this->assertFalse($role->isUserInRole($user));

        self::$roleMapper->addUser($role, $user);

        $this->assertTrue($role->isUserInRole($user));

        self::$roleMapper->removeUser($role, $user);

        $this->assertFalse($role->isUserInRole($user));
    }

    /**
     * Test remove user by id.
     *
     * @return void
     */
    public function testRemoveUserById(): void
    {
        $role = self::$roleMapper->fetchById(2);
        $user = self::$enhancedUserMapper->fetchById(7);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertInstanceOf(EnhancedUser::class, $user);

        $this->assertFalse($role->isUserInRoleById($user->id));

        self::$roleMapper->addUserById($role, $user->id);

        $this->assertTrue($role->isUserInRoleById($user->id));

        self::$roleMapper->removeUserById($role, $user->id);

        $this->assertFalse($role->isUserInRoleById($user->id));
    }

    /**
     * Test remove user by name.
     *
     * @return void
     */
    public function testRemoveUserByName(): void
    {
        $role = self::$roleMapper->fetchById(2);
        $user = self::$enhancedUserMapper->fetchById(7);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertInstanceOf(EnhancedUser::class, $user);

        $this->assertFalse($role->isUserInRoleByName($user->name));

        self::$roleMapper->addUserByName($role, $user->name);

        $this->assertTrue($role->isUserInRoleByName($user->name));

        self::$roleMapper->removeUserByName($role, $user->name);

        $this->assertFalse($role->isUserInRoleByName($user->name));
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
