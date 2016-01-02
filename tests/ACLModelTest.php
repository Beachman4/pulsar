<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Pulsar\ACLModel;

require_once 'tests/test_models.php';

class ACLModelTest extends PHPUnit_Framework_TestCase
{
    public static $requester;

    public static function setUpBeforeClass()
    {
        self::$requester = new Person(['id' => 1]);
        ACLModel::setRequester(self::$requester);
    }

    public function testRequester()
    {
        $requester = new Person(['id' => 2]);
        ACLModel::setRequester($requester);
        $this->assertEquals($requester, ACLModel::getRequester());
    }

    public function testCan()
    {
        $acl = new AclObject();

        $this->assertFalse($acl->can('whatever', new TestModel()));
        $this->assertTrue($acl->can('do nothing', new TestModel(['id' => 5])));
        $this->assertFalse($acl->can('do nothing', new TestModel()));
    }

    public function testCache()
    {
        $acl = new AclObject();

        for ($i = 0; $i < 10; ++$i) {
            $this->assertFalse($acl->can('whatever', new TestModel()));
        }
    }

    public function testGrantAll()
    {
        $acl = new AclObject();

        $acl->grantAllPermissions();

        $this->assertTrue($acl->can('whatever', new TestModel()));
    }

    public function testEnforcePermissions()
    {
        $acl = new AclObject();

        $this->assertEquals($acl, $acl->grantAllPermissions());
        $this->assertEquals($acl, $acl->enforcePermissions());

        $this->assertFalse($acl->can('whatever', new TestModel()));
    }

    public function testCreateNoPermission()
    {
        $model = new TestModelNoPermission();
        $this->assertFalse($model->create());

        $errors = $model->errors();
        $this->assertCount(1, $errors);
        $this->assertEquals(['pulsar.validation.no_permission'], $errors['create']);
    }

    public function testSetNoPermission()
    {
        $model = new TestModelNoPermission();
        $model->refreshWith(['id' => 5]);
        $this->assertFalse($model->set(['answer' => 42]));

        $errors = $model->errors();
        $this->assertCount(1, $errors);
        $this->assertEquals(['pulsar.validation.no_permission'], $errors['edit']);
    }

    public function testDeleteNoPermission()
    {
        $model = new TestModelNoPermission();
        $model->refreshWith(['id' => 5]);
        $this->assertFalse($model->delete());

        $errors = $model->errors();
        $this->assertCount(1, $errors);
        $this->assertEquals(['pulsar.validation.no_permission'], $errors['delete']);
    }
}
