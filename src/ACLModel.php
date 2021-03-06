<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @see http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Pulsar;

/**
 * Class ACLModel.
 */
abstract class ACLModel extends Model
{
    const ERROR_NO_PERMISSION = 'no_permission';

    const LISTENER_PRIORITY = 1000;

    /**
     * @var Model
     */
    protected static $requester;

    /**
     * @var array
     */
    private $permissionsCache = [];

    /**
     * @var bool
     */
    private $permissionsDisabled = false;

    /**
     * Sets the requester.
     *
     * @param Model $requester
     */
    public static function setRequester(Model $requester)
    {
        static::$requester = $requester;
    }

    /**
     * Gets the requester.
     *
     * @return Model|null
     */
    public static function getRequester()
    {
        return static::$requester;
    }

    /**
     * Checks if the requesting model has a specific permission
     * on this object.
     *
     * @param string $permission
     * @param Model  $requester
     *
     * @return bool
     */
    public function can($permission, Model $requester)
    {
        if ($this->permissionsDisabled) {
            return true;
        }

        // cache when checking permissions
        $k = $permission.'.'.$requester;
        if (!isset($this->permissionsCache[$k])) {
            $this->permissionsCache[$k] = $this->hasPermission($permission, $requester);
        }

        return $this->permissionsCache[$k];
    }

    abstract protected function hasPermission($permission, Model $requester);

    /**
     * Disables all permissions checking in can() for this object
     * DANGER: this should only be used when objects are mutated from application code
     * Granting all permissions to anyone else, i.e. HTTP requests is dangerous.
     *
     * @return self
     */
    public function grantAllPermissions()
    {
        $this->permissionsDisabled = true;

        return $this;
    }

    /**
     * Ensures that permissions are enforced for this object.
     *
     * @return self
     */
    public function enforcePermissions()
    {
        $this->permissionsDisabled = false;

        return $this;
    }

    protected function initialize()
    {
        parent::initialize();

        // check the if the requester has the `create`
        // permission before creating
        static::creating(function (ModelEvent $event) {
            $model = $event->getModel();

            if (!$model->can('create', ACLModel::getRequester())) {
                $model->getApp()['errors']->push(['error' => ACLModel::ERROR_NO_PERMISSION]);

                $event->stopPropagation();
            }
        }, self::LISTENER_PRIORITY);

        // check the if the requester has the `edit`
        // permission before updating
        static::updating(function (ModelEvent $event) {
            $model = $event->getModel();

            if (!$model->can('edit', ACLModel::getRequester())) {
                $model->getApp()['errors']->push(['error' => ACLModel::ERROR_NO_PERMISSION]);

                $event->stopPropagation();
            }
        }, self::LISTENER_PRIORITY);

        // check the if the requester has the `delete`
        // permission before deleting
        static::deleting(function (ModelEvent $event) {
            $model = $event->getModel();

            if (!$model->can('delete', ACLModel::getRequester())) {
                $model->getApp()['errors']->push(['error' => ACLModel::ERROR_NO_PERMISSION]);

                $event->stopPropagation();
            }
        }, self::LISTENER_PRIORITY);
    }
}
