<?php


namespace Drupal\api_solutions;

/**
 * Class APIService.
 */
class APIService
{
    public function isTokenValid($name, $token)
    {
        $user = user_load_by_name($name);
        if (!is_object($user)) {
            return false;
        }
        $hashed_password = $user->getPassword();
        $token_new = \Drupal\Component\Utility\Crypt::hashBase64($hashed_password);
        return ($token_new == $token);
    }
    public function isUserNameExist($name)
    {
        $query = \Drupal::entityQuery('user')
            ->condition('name', $name);
        $query->range(0, 1);
        $result = $query->execute();
        if (!empty($result)) {
            return true;
        }
        return false;
    }
    public function generateToken($user)
    {
        if (!is_object($user)) {
            return false;
        }
        $hashed_password = $user->getPassword();
        $token_new = \Drupal\Component\Utility\Crypt::hashBase64($hashed_password);
        return $token_new;
    }

}
