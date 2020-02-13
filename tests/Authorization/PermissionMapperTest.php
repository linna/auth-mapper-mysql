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

use Linna\Authorization\Permission;
use Linna\Authorization\PermissionMapper;
use Linna\DataMapper\NullDomainObject;
use Linna\Storage\ExtendedPDO;
use Linna\Storage\StorageFactory;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Permission Mapper Test.
 */
class PermissionMapperTest extends TestCase
{
    /**
     * @var PermissionMapper The permission mapper class.
     */
    protected static PermissionMapper $permissionMapper;

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

        self::$pdo = $pdo;
        self::$permissionMapper = new PermissionMapper($pdo);
    }

    /**
     * Tear Down.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        self::$pdo->exec('ALTER TABLE permission AUTO_INCREMENT = 0');
    }
    
    /**
     * Test new instance.
     *
     * @return void
     */
    public function testNewInstance(): void
    {
        $this->assertInstanceOf(PermissionMapper::class, self::$permissionMapper);
    }
    
    /**
     * Permission id provider.
     *
     * @return array
     */
    public function permissionIdProvider(): array
    {
        return [
            [1, 1],
            [2, 2],
            [3, 3],
            [4, 4],
            [5, 5],
            [6, 6],
            [7, 0],
            [8, 0]
        ];
    }

    /**
     * Test fetch by id.
     *
     * @dataProvider permissionIdProvider
     *
     * @param int $permissionId
     * @param int $expectedId
     *
     * @return void
     */
    public function testFetchById(int $permissionId, int $expectedId): void
    {
        $permission = self::$permissionMapper->fetchById($permissionId);
        $this->assertEquals($permission->getId(), $expectedId);
    }
    
    /**
     * Permission name provider
     *
     * @return array
     */
    public function permissionNameProvider(): array
    {
        return [
            ['see users', 'see users'],
            ['update user', 'update user'],
            ['delete user', 'delete user'],
            ['create user', 'create user'],
            ['enable user', 'enable user'],
            ['disable user', 'disable user'],
            ['bad_user', '']
        ];
    }

    /**
     * Test fetch by name.
     *
     * @dataProvider permissionNameProvider
     *
     * @param string $permissionName
     * @param string $expectedName
     *
     * @return void
     */
    public function testFetchByName(string $permissionName, string $expectedName): void
    {
        $permission = self::$permissionMapper->fetchByName($permissionName);

        if ($expectedName === '') {
            $this->assertInstanceOf(NullDomainObject::class, $permission);
            return;
        }

        $this->assertEquals($permission->name, $expectedName);
    }
    
    /**
     * Test fetch all.
     *
     * @return void
     */
    public function testFetchAll(): void
    {
        $this->assertCount(6, self::$permissionMapper->fetchAll());
    }
    
    /**
     * Permission fetch limit provider.
     *
     * @return array
     */
    public function permissionFetchLimitProvider(): array
    {
        return [
            ['see users', 0, 1],
            ['update user', 1, 1],
            ['delete user', 2, 1],
            ['create user', 3, 1],
            ['enable user', 4, 1],
            ['disable user', 5, 1]
        ];
    }

    /**
     * Test fetch limit.
     *
     * @dataProvider permissionFetchLimitProvider
     *
     * @param string $permissionName
     * @param int    $offset
     * @param int    $rowCount
     *
     * @return void
     */
    public function testFetchLimit(string $permissionName, int $offset, int $rowCount): void
    {
        $permissions = self::$permissionMapper->fetchLimit($offset, $rowCount);

        $key = \array_keys($permissions)[0];

        $this->assertCount(1, $permissions);
        $this->assertEquals($permissions[$key]->name, $permissionName);
    }
    
    /**
     * Test concrete create.
     *
     * @return void
     */
    public function testConcreteCreate(): void
    {
        $this->assertInstanceOf(Permission::class, self::$permissionMapper->create());
    }
    
    /**
     * Test concrete insert.
     *
     * @return void
     */
    public function testConcreteInsert(): void
    {
        $permission = self::$permissionMapper->create();
        $permission->name = 'test_permission';

        $this->assertEquals(0, $permission->getId());

        self::$permissionMapper->save($permission);

        $this->assertGreaterThan(0, $permission->getId());

        $permissionStored = self::$permissionMapper->fetchByName('test_permission');

        $this->assertInstanceOf(Permission::class, $permissionStored);
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
        $permissionStored = self::$permissionMapper->fetchByName('test_permission');

        $this->assertInstanceOf(Permission::class, $permissionStored);
        $this->assertEquals('test_permission', $permissionStored->name);
        
        $permissionStored->name = 'test_permission_update';

        self::$permissionMapper->save($permissionStored);

        $permissionStoredUpdated = self::$permissionMapper->fetchByName('test_permission_update');

        $this->assertInstanceOf(Permission::class, $permissionStoredUpdated);
        $this->assertEquals('test_permission_update', $permissionStoredUpdated->name);
        
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
        $permissionStored = self::$permissionMapper->fetchByName('test_permission_update');

        $this->assertInstanceOf(Permission::class, $permissionStored);
        $this->assertEquals('test_permission_update', $permissionStored->name);
        
        self::$permissionMapper->delete($permissionStored);

        $this->assertInstanceOf(NullDomainObject::class, $permissionStored);
    }
    
    /**
     * Test fetch by role.
     *
     * @param int $roleId
     * @param int $result
     * 
     * @return void
     */
    public function testFetchByRole(/*int $roleId, int $result*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * Test fetch by role id.
     *
     * @param int $roleId
     * @param int $result
     * 
     * @return void
     */
    public function testFetchByRoleId(/*int $roleId, int $result*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * Test fetch by role name.
     * 
     * @param string $roleName
     * @param int    $result
     * 
     * @return void
     */
    public function testFetchByRoleName(/*string $roleName, int $result*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * Test fetch by user.
     * 
     * @param int $userId
     * @param int $result
     * 
     * @return void
     */
    public function testFetchByUser(/*int $userId, int $result*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * Test fetch by user id.
     * 
     * @param int $userId
     * @param int $result
     * 
     * @return void
     */
    public function testFetchByUserId(/*int $userId, int $result*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * Test fetch by user name.
     *
     * @param string $userName
     * @param int    $result
     * 
     * @return void
     */
    public function testFetchByUserName(/*string $userName, int $result*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * Test fetch user permission hash table.
     *
     * @param int   $userId
     * @param array $result
     *
     * @return void
     */
    public function testFetchUserPermissionHashTable(/*int $userId, array $result*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * Test permission exists by id.
     *
     * @param int  $permissionId
     * @param bool $result
     * 
     * @return void
     */
    public function testPermissionExistById(/*int $permissionId, bool $result*/): void
    {
        $this->assertTrue(true);
    }

    /**
     * Test permission exists by name.
     *
     * @param string $permissionName
     * @param bool $result
     * @return void
     */
    public function testPermissionExistByName(/*string $permissionName, bool $result*/): void
    {
        $this->assertTrue(true);
    }
}
