<?php


namespace Drupal\api_solutions;

/**
 * Class APIService.
 */
class APIService
{
    public function isTokenValid($token)
    {
        $user = NULL;
        $storage = \Drupal::entityTypeManager()->getStorage('user');

        $users = $storage->loadByProperties(['field_api_token' => $token]);
        $user = $users ? reset($users) : NULL;
        if (!$user instanceof \Drupal\user\UserInterface || !$user->isActive()) {
            return false;
        }
        return true;
    }
    public function isUserNameExist($name)
    {
        $query = \Drupal::entityQuery('user')
            ->accessCheck(FALSE)
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
        if ($user->hasField('field_api_token')) {
            $token = $user->get('field_api_token')->value;
            if (empty($token)) {
                $token = \Drupal\Component\Utility\Crypt::hashBase64($user->getAccountName() . $user->getPassword());
                $user->set('field_api_token', $token);
                $user->save();
            }
            return $token;
        }
        return false;
    }

}
