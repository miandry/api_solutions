<?php

namespace Drupal\api_solutions;

use Drupal\user\Entity\User;

/**
 * Class APIService.
 *
 * Token-based authentication using field_api_token on user entity
 * with HTTP-Only cookie support (inspired by mz_crud_new).
 */
class APIService
{

    /**
     * Check if a token is valid.
     *
     * @param string $token
     * @return bool
     */
    public function isTokenValid($token)
    {
        return (bool) $this->getUserByToken($token);
    }

    /**
     * Get user by field_api_token value.
     *
     * @param string $token
     * @return \Drupal\user\Entity\User|null
     */
    public function getUserByToken($token)
    {
        if (empty($token)) {
            return NULL;
        }
        $storage = \Drupal::entityTypeManager()->getStorage('user');
        $users = $storage->loadByProperties(['field_api_token' => $token]);
        return $users ? reset($users) : NULL;
    }

    /**
     * Validate a bearer token and return the user.
     *
     * @param string $token
     * @return \Drupal\user\Entity\User|null
     */
    public function validateBearerToken($token)
    {
        return $this->getUserByToken($token);
    }

    /**
     * Check if username already exists.
     *
     * @param string $name
     * @return bool
     */
    public function isUserNameExist($name)
    {
        $query = \Drupal::entityQuery('user')
            ->accessCheck(FALSE)
            ->condition('name', $name);
        $query->range(0, 1);
        $result = $query->execute();
        return !empty($result);
    }

    /**
     * Generate a new bearer token for a user and store it in field_api_token.
     *
     * @param \Drupal\user\Entity\User $user
     * @return string|false
     */
    public function generateBearerToken($user)
    {
        if (!is_object($user) || !$user->hasField('field_api_token')) {
            return false;
        }
        $token = bin2hex(random_bytes(32));
        $user->set('field_api_token', $token);
        $user->save();
        return $token;
    }

    /**
     * Legacy alias: generate token (calls generateBearerToken).
     *
     * @param object $user
     * @return string|false
     */
    public function generateToken($user)
    {
        return $this->generateBearerToken($user);
    }

    /**
     * Invalidate a token (clear field_api_token for the user owning it).
     *
     * @param string $token
     */
    public function invalidateBearerToken($token)
    {
        $user = $this->getUserByToken($token);
        if ($user && $user->hasField('field_api_token')) {
            $user->set('field_api_token', '');
            $user->save();
        }
    }

    /**
     * Invalidate all tokens for a given user (clear field_api_token).
     *
     * @param int $uid
     */
    public function invalidateUserTokens($uid)
    {
        $user = User::load($uid);
        if ($user && $user->hasField('field_api_token')) {
            $user->set('field_api_token', '');
            $user->save();
        }
    }

}
