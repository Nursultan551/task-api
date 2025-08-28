<?php

namespace app\models;

use yii\web\IdentityInterface;

class AuthIdentity implements IdentityInterface
{
    public int $id;
    public string $username;

    public function __construct(int $id, string $username)
    {
        $this->id = $id;
        $this->username = $username;
    }

    public static function findIdentity($id)
    {
        // Not used in bearer only flow
        return null;
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        // Not used because we provide a custom 'auth' closure in HttpBearerAuth
        return null;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return null;
    }

    public function validateAuthKey($authKey)
    {
        return false;
    }
}
