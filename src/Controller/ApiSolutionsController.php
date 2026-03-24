<?php

namespace Drupal\api_solutions\Controller;

use Drupal\api_solutions\ApiJsonParser;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\user\Entity\User;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Class ApiSolutionsController.
 */
class ApiSolutionsController extends ControllerBase implements ContainerInjectionInterface
{

    /**
     * The date formatter service.
     *
     * @var \Drupal\Core\Datetime\DateFormatterInterface
     */
    protected $dateFormatter;

    /**
     * The renderer service.
     *
     * @var \Drupal\Core\Render\RendererInterface
     */
    protected $renderer;

    /**
     * Constructs an ApiSolutionsController object.
     */
    public function __construct(DateFormatterInterface $date_formatter, RendererInterface $renderer)
    {
        $this->dateFormatter = $date_formatter;
        $this->renderer = $renderer;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('date.formatter'),
            $container->get('renderer')
        );
    }

    /**
     * Check if the request origin is allowed and return CORS headers.
     *
     * @return array|null CORS headers array if allowed, or null if blocked.
     */
    protected function getCorsHeaders()
    {
        $request = \Drupal::request();
        $origin = $request->headers->get('Origin');

        if (!$origin) {
            // No Origin header = same-origin request, allow it
            return [];
        }

        $config = \Drupal::config('api_solutions.settings');
        $allowed = $config->get('allowed_origins') ?: [];

        if (in_array($origin, $allowed, true)) {
            return [
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
                'Vary' => 'Origin',
            ];
        }

        // Origin not allowed
        return null;
    }

    /**
     * Return a 403 response for blocked origins.
     */
    protected function blockedOriginResponse()
    {
        return new JsonResponse([
            'status' => 'error',
            'message' => 'Origin not allowed',
        ], 403);
    }

    /**
     * Helper to get token from Authorization header.
     */
    protected function getTokenFromHeader()
    {
        $request = \Drupal::request();
        $auth_header = $request->headers->get('Authorization');

        // Alternative for some servers where Authorization header is stripped
        if (!$auth_header && function_exists('apache_request_headers')) {
            $all_headers = apache_request_headers();
            if (isset($all_headers['Authorization'])) {
                $auth_header = $all_headers['Authorization'];
            }
        }

        // Check $_SERVER directly as a last resort
        if (!$auth_header && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        }

        if ($auth_header && preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return trim($matches[1]);
        }

        // Fallback to token query parameter for convenience
        return $request->query->get('token');
    }

    /**
     * Save endpoint logic with cookie-based auth (fallback to header/body token).
     */
    public function save()
    {
        $json = [];
        $method = \Drupal::request()->getMethod();
        $id = null;
        $message = "Request failed";

        if ($method == "POST") {
            // 1. Try cookie first, then Authorization header, then body token
            $token = \Drupal::request()->cookies->get('auth_token');
       
            if (!$token) {
                $token = $this->getTokenFromHeader();
            }
             

            $content = \Drupal::request()->getContent();
            if (!empty($content)) {
                $content = json_decode($content, TRUE);

                if (!$token) {
                    $token = $content["token"] ?? NULL;
                }
                        
                $service = \Drupal::service('api_solutions.api_crud');
                $user = ($token) ? $service->validateBearerToken($token) : null;
                if ($user) {
                    $entity_type = $content["entity_type"] ?? '';
                    $bundle = $content["bundle"] ?? '';

                    // Check role permission before saving
                    $permission_error = $this->checkSavePermission($user, $entity_type, $bundle, $content);
                    if ($permission_error) {
                        return new JsonResponse([
                            'message' => $permission_error,
                            'status' => 'error'
                        ], 403);
                    }

                    // Auto-set uid from authenticated user for nodes
                    if ($entity_type == 'node' && !isset($content['uid'])) {
                        $content['uid'] = $user->id();
                    }

                    unset($content["bundle"]);
                    unset($content["entity_type"]);
                    unset($content["token"]);
                    unset($content["author"]);

                    $elemt = \Drupal::service('api_solutions.crud')->save($entity_type, $bundle, $content);
                    if (is_object($elemt)) {
                        $id = $elemt->id();
                    } else {
                        $message = "Erreur lors de la sauvegarde";
                    }
                } else {
                    $message = "Token invalide ou session expiree";
                    $response = new JsonResponse(['message' => $message, 'status' => 'error'], 401);
                    if (\Drupal::request()->cookies->get('auth_token')) {
                        $response->headers->clearCookie('auth_token', '/');
                    }
                    return $response;
                }
            } else {
                $message = "Data not found";
            }
        } else {
            $message = "No POST request";
        }

        $json = ($id) ? ['item' => $id, 'status' => true] : ['message' => $message, 'status' => 'error'];
        return new JsonResponse($json);
    }

    /**
     * Generate token endpoint logic.
     */
    public function generateToken()
    {
        $method = \Drupal::request()->getMethod();
        $json = ['status' => false];
        if ($method == "POST") {
            $content = \Drupal::request()->getContent();
            if (!empty($content)) {
                $data = json_decode($content, TRUE);
                $name = $data['name'] ?? NULL;
                $password = $data['password'] ?? ($data['pass'] ?? NULL);
                if ($name && $password) {
                    $user = user_load_by_name($name);
                    if ($user && $user->isActive()) {
                        $password_hasher = \Drupal::service('password');
                        if ($password_hasher->check($password, $user->getPassword())) {
                            $service = \Drupal::service('api_solutions.api_crud');
                            $json = [
                                'status' => true,
                                'token' => $service->generateToken($user),
                                'id' => $user->id(),
                            ];
                        } else {
                            $json['message'] = "Invalid password";
                        }
                    } else {
                        $json['message'] = "User not found or inactive";
                    }
                } else {
                    $json['message'] = "Missing name or password";
                }
            }
        } else {
            $json['message'] = "Only POST method is allowed";
        }
        return new JsonResponse($json);
    }

    /**
     * Register logic with HTTP-Only cookie.
     */
    public function register()
    {
        $service = \Drupal::service('api_solutions.api_crud');
        $method = \Drupal::request()->getMethod();

        if ($method == "POST") {
            $content = \Drupal::request()->getContent();
            if (!empty($content)) {
                $data = json_decode($content, TRUE);
                $password = $data['pass'] ?? ($data['password'] ?? NULL);

                if (empty($data['name']) || empty($password)) {
                    return new JsonResponse(['status' => false, 'message' => 'Nom et mot de passe requis'], 400);
                }

                if ($service->isUserNameExist($data['name'])) {
                    return new JsonResponse([
                        'status' => false,
                        'name' => $data['name'],
                        'error' => 'Nom d\'utilisateur existe deja'
                    ], 400);
                }

                $user = User::create();
                $user->setPassword($password);
                $user->enforceIsNew();
                $user->setEmail($data['mail'] ?? "email@yahoo.fr");
                $user->set('status', 1);
                $user->setUsername($data['name']);
                if (isset($data['role'])) {
                    $user->addRole($data['role']);
                }

                $saved = $user->save();

                if ($saved) {
                    $token = $service->generateBearerToken($user);

                    $response = new JsonResponse([
                        'status' => true,
                        'message' => 'Inscription r' . "\xC3\xA9" . 'ussie',
                        'token' => $token,
                        'id' => $user->id(),
                        'name' => $user->getAccountName(),
                        'mail' => $user->getEmail(),
                    ]);

                    $cookie = new Cookie(
                        'auth_token',
                        $token,
                        time() + (30 * 24 * 3600),
                        '/',
                        null,
                        false,
                        true,
                        false,
                        'Lax'
                    );
                    $response->headers->setCookie($cookie);

                    return $response;
                } else {
                    return new JsonResponse(['status' => false, 'message' => 'Erreur lors de la cr' . "\xC3\xA9" . 'ation'], 500);
                }
            }
        }

        return new JsonResponse(['status' => false, 'message' => 'POST requis'], 405);
    }

    /**
     * Login logic with HTTP-Only cookie.
     */
    public function login()
    {
        $method = \Drupal::request()->getMethod();

        if ($method == "POST") {
            $content = \Drupal::request()->getContent();
            if (!empty($content)) {
                $data = json_decode($content, TRUE);

                if (empty($data['name'])) {
                    return new JsonResponse(['status' => false, 'message' => 'Nom d\'utilisateur requis'], 400);
                }

                $password = $data['pass'] ?? ($data['password'] ?? NULL);
                if (empty($password)) {
                    return new JsonResponse(['status' => false, 'message' => 'Mot de passe requis'], 400);
                }

                $user = user_load_by_name($data['name']);
                if (is_object($user) && $user->isActive()) {
                    $password_hasher = \Drupal::service('password');

                    if ($password_hasher->check($password, $user->getPassword())) {
                        $service = \Drupal::service('api_solutions.api_crud');
                        $token = $service->generateBearerToken($user);
                        $user_array = \Drupal::service('entity_parser.manager')->user_parser($user);

                        $response = new JsonResponse([
                            'status' => true,
                            'message' => 'Connexion r' . "\xC3\xA9" . 'ussie',
                            'token' => $token,
                            'id' => $user->id(),
                            'name' => $user->getAccountName(),
                            'mail' => $user->getEmail(),
                            'roles' => $user->getRoles(),
                            'data' => $user_array,
                        ]);

                        $cookie = new Cookie(
                            'auth_token',
                            $token,
                            time() + (30 * 24 * 3600),
                            '/',
                            null,
                            false,
                            true,
                            false,
                            'Lax'
                        );
                        $response->headers->setCookie($cookie);

                        return $response;
                    } else {
                        return new JsonResponse([
                            'status' => false,
                            'name' => $data['name'],
                            'error' => 'Mot de passe incorrect'
                        ], 401);
                    }
                } else {
                    return new JsonResponse([
                        'status' => false,
                        'name' => $data['name'],
                        'error' => 'Utilisateur non trouv' . "\xC3\xA9" . ' ou inactif'
                    ], 404);
                }
            }
        }

        return new JsonResponse(['status' => false, 'message' => 'POST requis'], 405);
    }

    /**
     * User edit logic.
     */
    public function userEdit()
    {
        $method = \Drupal::request()->getMethod();
        $json = ['status' => false];
        if ($method == "POST") {
            $content = \Drupal::request()->getContent();
            if (!empty($content)) {
                $data = json_decode($content, TRUE);
                $service = \Drupal::service('api_solutions.api_crud');

                $current_name = $data['author'] ?? ($data['name'] ?? NULL);
                $token = $this->getTokenFromHeader() ?: ($data['token'] ?? NULL);

                if ($token && $service->isTokenValid($token)) {
                    $user = user_load_by_name($current_name);
                    if ($user) {
                        $updated = false;

                        // Update Email
                        $email = $data['email'] ?? ($data['mail'] ?? NULL);
                        if ($email) {
                            $user->setEmail($email);
                            $updated = true;
                        }

                        // Update Username (Name)
                        $new_name = $data['new_name'] ?? NULL;
                        if ($new_name) {
                            $user->setUsername($new_name);
                            $updated = true;
                        }

                        // Update Password
                        $password = $data['password'] ?? ($data['pass'] ?? NULL);
                        if ($password) {
                            $user->setPassword($password);
                            $updated = true;
                        }

                        if ($updated) {
                            $json['status'] = (bool) $user->save();
                            $json['id'] = $user->id();
                        } else {
                            $json['message'] = "No valid fields provided for update (email, new_name, password)";
                        }
                    } else {
                        $json['message'] = "User not found";
                    }
                } else {
                    $json['message'] = "Authentication failed";
                }
            }
        } else {
            $json['message'] = "POST method is required ";
        }
        return new JsonResponse($json);
    }

    /**
     * uploader logic.
     */
    public function uploader()
    {
        $json = ['status' => false, 'message' => 'No file uploaded'];
        try {
            $uri_root = 'public://media_api/';
            $file_system = \Drupal::service('file_system');
            $file_system->prepareDirectory($uri_root, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);

            if (empty($_FILES)) {
                return new JsonResponse($json);
            }

            foreach ($_FILES as $fileItem) {
                if ($fileItem['error'] !== UPLOAD_ERR_OK) {
                    throw new \Exception('PHP Upload Error: ' . $fileItem['error']);
                }

                $data = file_get_contents($fileItem['tmp_name']);
                if ($data) {
                    $filename = $fileItem['name'];
                    // Clean filename to avoid issues with special characters
                    $uri = $uri_root . $filename;

                    // Save the file as a managed file in Drupal.
                    $file = file_save_data($data, $uri, \Drupal\Core\File\FileSystemInterface::EXISTS_RENAME);

                    if ($file) {
                        $json = [
                            'fid' => $file->id(),
                            'status' => true,
                            'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()),
                        ];

                        // Attempt to create Media entity but don't fail if it doesn't work
                        try {
                            $fields = [
                                'name' => $filename,
                                'field_media_image' => $file->id(),
                            ];
                            $media = \Drupal::service('api_solutions.crud')->save('media', 'image', $fields);
                            if (is_object($media)) {
                                $json['id'] = $media->id();

                                // Generate styles
                                $style_urls = [];
                                $styles = ['thumbnail', 'medium', 'large']; // Removed 'media_api' to be safer
                                foreach ($styles as $style_name) {
                                    $style = \Drupal\image\Entity\ImageStyle::load($style_name);
                                    if ($style) {
                                        $destination = $style->buildUri($file->getFileUri());
                                        if (!file_exists($destination)) {
                                            $style->createDerivative($file->getFileUri(), $destination);
                                        }
                                        $url = \Drupal::service('file_url_generator')->generateAbsoluteString($style->buildUrl($file->getFileUri()));
                                        $style_urls[$style_name] = $url;
                                    }
                                }
                                $json['styles'] = $style_urls;
                            }
                        } catch (\Exception $mediaEx) {
                            \Drupal::logger('api_solutions')->warning('Media entity creation failed but file saved: @msg', ['@msg' => $mediaEx->getMessage()]);
                        }
                    } else {
                        throw new \Exception('Failed to save file data to ' . $uri);
                    }
                } else {
                    throw new \Exception('Failed to read uploaded file contents.');
                }
            }
        } catch (\Exception $e) {
            \Drupal::logger('api_solutions')->error('Uploader exception: @msg', ['@msg' => $e->getMessage()]);
            $json = ['status' => false, 'message' => $e->getMessage()];
        }
        return new JsonResponse($json);
    }

    /**
     * apiListJson logic.
     */
    public function apiListJson()
    {
        $bundle = \Drupal::request()->get('bundle');
        $entitype = \Drupal::request()->get('entitype');
        $pager = \Drupal::request()->get('pager');
        $offset = \Drupal::request()->get('offset');
        $view = \Drupal::request()->get('view');
        $cat = \Drupal::request()->get('cat');
        $fields = \Drupal::request()->get('fields');

        $children = null;
        if ($cat) {
            $entity = \Drupal::service('drupal.helper')->helper->getEntityByAlias($cat);
            if (is_object($entity)) {
                $id = $entity->id();
                $children = \Drupal::service('drupal.helper')->helper->taxonomy_get_children($id);
            } else {
                $children = [-1];
            }
        }

        if ($bundle && $entitype) {
            if ($offset == null) {
                $offset = 10;
            }
            $key_bundle = \Drupal::entityTypeManager()->getDefinition($entitype)->getKey('bundle');
            $query = \Drupal::entityQuery($entitype)->condition($key_bundle, $bundle);
            if ($entitype == 'node') {
                $query->sort('promote', 'DESC');
                $query->sort('nid', 'DESC');
                $query->condition('status', '1');
            }
            if ($cat && $children) {
                $query->condition('field_catalogue', $children, 'IN');
            }
            if ($pager) {
                $query->range($offset * ($pager - 1), $offset);
            } else {
                $query->range(0, $offset);
            }
            $ids = $query->execute();
        } else {
            return new JsonResponse(['error' => 'Missing bundle or entitype'], 400);
        }

        $results = [];
        $parser = \Drupal::service('entity_parser.manager');
        foreach ($ids as $id) {
            if ($view == 'full') {
                $results[] = $parser->loader_entity_by_type($id, $entitype);
            } elseif (is_array($fields) || is_string($fields)) {
                $fields_array = is_string($fields) ? [$fields] : $fields;
                $results[] = $parser->loader_entity_by_type($id, $entitype, $fields_array);
            } else {
                $results[] = $id;
            }
        }
        return $this->responseCacheableJson($results);
    }

    /**
     * apiTerm logic.
     */
    public function apiTerm($vid)
    {
        $parser = new ApiJsonParser();
        $data = \Drupal::request()->query->all();
        $results = $parser->taxonomy_load_multi_by_vid($vid, $data);
        return new JsonResponse($results);
    }

    /**
     * apiMenu logic.
     */
    public function apiMenu()
    {
        $menu = \Drupal::service('simplify_menu.menu_items')->getMenuTree();
        return $this->responseCacheableJson($menu['menu_tree']);
    }

    /**
     * sendResetEmail logic.
     */
    public function sendResetEmail()
    {
        $method = \Drupal::request()->getMethod();
        if ($method == "POST") {
            $content = \Drupal::request()->getContent();
            $data = json_decode($content, TRUE);
            if (empty($data['email'])) {
                return new JsonResponse(['status' => 'error', 'message' => 'Email is required.'], 400);
            }
            $email = $data['email'];
            $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $email]);
            $user = reset($users);
            if ($user instanceof \Drupal\user\UserInterface) {
                $login_url = user_pass_reset_url($user);
                $subject = "Password reset request";
                $body = "Click here to reset: " . $login_url;
                $service = \Drupal::service("mz_email.default");
                if ($service && $service->sendMail($email, $body, $subject)) {
                    return new JsonResponse(['status' => 'success', 'message' => 'Email sent.']);
                }
            } else {
                return new JsonResponse(['status' => 'error', 'message' => 'User not found or invalid.']);
            }
        }
        return new JsonResponse(['status' => 'error'], 500);
    }

    /**
     * apiUserList logic.
     */
    public function apiUserList()
    {
        $page = \Drupal::request()->get('page') ?: 1;
        $limit = \Drupal::request()->get('limit') ?: 10;
        $offset = ($page - 1) * $limit;
        $filters = \Drupal::request()->get('filters') ?: [];

        $query = \Drupal::entityTypeManager()->getStorage('user')->getQuery()
            ->condition('status', 1)
            ->condition('uid', 0, '<>');
        $query->range($offset, $limit);
        foreach ($filters as $field => $value) {
            $query->condition($field, "%" . $value . "%", 'LIKE');
        }
        $uids = $query->execute();
        $results = [];
        foreach ($uids as $uid) {
            $results[] = \Drupal::service('entity_parser.manager')->user_parser($uid);
        }
        return new JsonResponse(["rows" => $results, "total" => count($results)]);
    }

    /**
     * apiListJsonV2 logic.
     */
    public function apiListJsonV2($entitype, $bundle)
    {
        $fields = \Drupal::request()->get('fields');
        $changes = \Drupal::request()->get('changes');
        $values = \Drupal::request()->get('values');
        $jsons = \Drupal::service('api_solutions.manager')->listQueryExecute($entitype, $bundle);
        $results = [];
        foreach ($jsons["rows"] as $id) {
            if (is_array($fields)) {
                $item = \Drupal::service('entity_parser.manager')->loader_entity_by_type($id, $entitype, $fields);
            } else {
                $item = \Drupal::service('entity_parser.manager')->loader_entity_by_type($id, $entitype);
            }

            // Handle field remapping (changes)
            if (is_array($changes)) {
                foreach ($changes as $old_key => $new_key) {
                    if (isset($item[$old_key])) {
                        $item[$new_key] = $item[$old_key];
                        unset($item[$old_key]);
                    }
                }
            }

            // Handle custom values injection
            if (is_array($values)) {
                foreach ($values as $f_key => $f_val) {
                    $item[$f_key] = $f_val;
                }
            }

            $results[] = $item;
        }
        return new JsonResponse(["rows" => $results, "total" => $jsons["total"]]);
    }

    /**
     * apiDetailsJsonV2 logic.
     */
    public function apiDetailsJsonV2($entitype, $bundle, $id)
    {
        $fields = \Drupal::request()->get('fields');
        if (is_array($fields)) {
            $item = \Drupal::service('entity_parser.manager')->loader_entity_by_type($id, $entitype, $fields);
        } else {
            $item = \Drupal::service('entity_parser.manager')->loader_entity_by_type($id, $entitype);
        }
        return new JsonResponse($item);
    }

    /**
     * Helper for cacheable JSON response.
     */
    protected function responseCacheableJson($data)
    {
        $config = $this->config('system.performance');
        $build = [
            '#cache' => [
                'max-age' => $config->get('cache.page.max_age'),
                'contexts' => ['url'],
            ]
        ];
        $response = new CacheableJsonResponse($data);
        $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
        return $response;
    }

    /**
     * Logout - invalidate token and remove HTTP-Only cookie.
     */
    public function logout()
    {
        $token = \Drupal::request()->cookies->get('auth_token');

        if ($token) {
            $service = \Drupal::service('api_solutions.api_crud');
            $service->invalidateBearerToken($token);
        }

        $response = new JsonResponse([
            'status' => true,
            'message' => 'Deconnexion reussie'
        ]);

        $response->headers->clearCookie('auth_token', '/');

        return $response;
    }

    /**
     * Check if user is authenticated via cookie.
     */
    public function checkAuth()
    {
        $token = \Drupal::request()->cookies->get('auth_token');

        if (!$token) {
            $token = $this->getTokenFromHeader();
        }

        if (!$token) {
            return new JsonResponse([
                'authenticated' => false,
                'message' => 'Non authentifie'
            ], 401);
        }

        $service = \Drupal::service('api_solutions.api_crud');
        $user = $service->validateBearerToken($token);

        if ($user) {
            $user_array = \Drupal::service('entity_parser.manager')->user_parser($user);
            return new JsonResponse([
                'authenticated' => true,
                'user' => [
                    'id' => $user->id(),
                    'name' => $user->getAccountName(),
                    'mail' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                    'data' => $user_array,
                ]
            ]);
        } else {
            $response = new JsonResponse([
                'authenticated' => false,
                'message' => 'Session expiree'
            ], 401);
            $response->headers->clearCookie('auth_token', '/');
            return $response;
        }
    }

    /**
     * Check if user has permission to save for the given entity_type/bundle.
     *
     * @param \Drupal\user\Entity\User $user
     * @param string $entity_type
     * @param string $bundle
     * @param array $content
     * @return string|null Error message if denied, null if allowed.
     */
    protected function checkSavePermission($user, $entity_type, $bundle, $content)
    {
        // Administrators bypass all permission checks
        if (in_array('administrator', $user->getRoles())) {
            return null;
        }

        $id_key = \Drupal::entityTypeManager()->getDefinition($entity_type)->getKey('id');
        $is_update = isset($content[$id_key]) && is_numeric($content[$id_key]);

        if ($entity_type == 'node') {
            if ($is_update) {
                // Check edit permissions
                $has_edit_any = $user->hasPermission("edit any {$bundle} content");
                $has_edit_own = $user->hasPermission("edit own {$bundle} content");
                if (!$has_edit_any && !$has_edit_own) {
                    return "Vous n'avez pas la permission de modifier ce contenu ({$bundle})";
                }
                // If only edit own, verify ownership
                if (!$has_edit_any && $has_edit_own) {
                    $node = \Drupal::entityTypeManager()->getStorage('node')->load($content[$id_key]);
                    if ($node && $node->getOwnerId() != $user->id()) {
                        return "Vous ne pouvez modifier que vos propres contenus ({$bundle})";
                    }
                }
            } else {
                if (!$user->hasPermission("create {$bundle} content")) {
                    return "Vous n'avez pas la permission de creer ce contenu ({$bundle})";
                }
            }
        } elseif ($entity_type == 'taxonomy_term') {
            if ($is_update) {
                if (!$user->hasPermission("edit terms in {$bundle}")) {
                    return "Vous n'avez pas la permission de modifier les termes ({$bundle})";
                }
            } else {
                if (!$user->hasPermission("create terms in {$bundle}")) {
                    return "Vous n'avez pas la permission de creer des termes ({$bundle})";
                }
            }
        } elseif ($entity_type == 'comment') {
            if (!$user->hasPermission('post comments')) {
                return "Vous n'avez pas la permission de poster des commentaires";
            }
        }

        // Other entity types: allow any authenticated user
        return null;
    }

    /**
     * Custom access check.
     */
    public function apiJsonAccess()
    {
        return AccessResult::allowed();
    }

}
