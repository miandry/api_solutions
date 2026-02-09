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
     * Helper to get token from Authorization header.
     */
    protected function getTokenFromHeader()
    {
        $headers = \Drupal::request()->headers->all();
        $auth_header = \Drupal::request()->headers->get('Authorization');
        if ($auth_header && preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return $matches[1];
        }
        return NULL;
    }

    /**
     * Save endpoint logic.
     */
    public function save()
    {
        $json = [];
        $method = \Drupal::request()->getMethod();
        $id = null;
        $message = "Request failed";
        if ($method == "POST") {
            $content = \Drupal::request()->getContent();
            if (!empty($content)) {
                $content = json_decode($content, TRUE);
                $service = \Drupal::service('api_solutions.api_crud');

                $token = $this->getTokenFromHeader() ?: ($content["token"] ?? NULL);
                $is_valid = ($token) ? $service->isTokenValid($token) : false;

                if ($is_valid) {
                    $entity_type = $content["entity_type"];
                    $bundle = $content["bundle"];
                    unset($content["bundle"]);
                    unset($content["entity_type"]);
                    unset($content["token"]);
                    $elemt = \Drupal::service('api_solutions.crud')->save($entity_type, $bundle, $content);
                    if (is_object($elemt)) {
                        $id = $elemt->id();
                    }
                } else {
                    $message = "Author token is not valid or missing";
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
     * Register logic.
     */
    public function register()
    {
        $service = \Drupal::service('api_solutions.api_crud');
        $method = \Drupal::request()->getMethod();
        $json['status'] = false;
        if ($method == "POST") {
            $content = \Drupal::request()->getContent();
            if (!empty($content)) {
                $data = json_decode($content, TRUE);
                $password = $data['pass'] ?? ($data['password'] ?? NULL);
                if ($data['name'] && $password) {
                    $status = $service->isUserNameExist($data['name']);
                    if ($status) {
                        $json['name'] = $data['name'];
                        $json['error'] = 'Username exist deja';
                    } else {
                        $json['name'] = $data['name'];
                        $user = User::create();
                        $user->setPassword($password);
                        $user->enforceIsNew();
                        $user->setEmail($data['mail'] ?? "email@yahoo.fr");
                        $user->set('status', 1);
                        $user->setUsername($data['name']);
                        if (isset($data['role'])) {
                            $user->addRole($data['role']);
                        }
                        $json['status'] = (bool) $user->save();
                        $json['token'] = $service->generateToken($user);
                        $json['id'] = $user->id();
                    }
                }
            }
        }
        return new JsonResponse($json);
    }

    /**
     * Login logic.
     */
    public function login()
    {
        $method = \Drupal::request()->getMethod();
        $json['status'] = false;
        if ($method == "POST") {
            $content = \Drupal::request()->getContent();
            if (!empty($content)) {
                $data = json_decode($content, TRUE);
                $json['name'] = $data['name'];
                $user = user_load_by_name($data['name']);
                if (is_object($user)) {
                    $user_array = \Drupal::service('entity_parser.manager')->user_parser($user);
                    $password_hasher = \Drupal::service('password');
                    $password = $data['pass'] ?? ($data['password'] ?? NULL);
                    $json['mail'] = $user_array['mail'];
                    $service = \Drupal::service('api_solutions.api_crud');
                    $json['token'] = $service->generateToken($user);
                    $json['status'] = ($password_hasher->check($password, $user->getPassword()));
                    if ($json['status']) {
                        $json['id'] = $user->id();
                        $json['data'] = $user_array;
                    } else {
                        $json = [
                            'mail' => $user_array['mail'],
                            'name' => $data['name'],
                            'status' => false,
                            'error' => "Failed Authentification"
                        ];
                    }
                }
            }
        }
        return new JsonResponse($json);
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
        $json = ['status' => false];
        $uri_root = 'public://media_api/';
        $file_system = \Drupal::service('file_system');
        $file_system->prepareDirectory($uri_root, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);

        foreach ($_FILES as $fileItem) {
            $data = file_get_contents($fileItem['tmp_name']);
            if ($data) {
                $filename = $fileItem['name'];
                $uri = $uri_root . $filename;

                // Save the file as a managed file in Drupal.
                $file = file_save_data($data, $uri, \Drupal\Core\File\FileSystemInterface::EXISTS_RENAME);

                if ($file) {
                    $fields = [
                        'name' => $filename,
                        'field_media_image' => $file->id(), // Using the optimized numeric ID support
                    ];

                    $media = \Drupal::service('api_solutions.crud')->save('media', 'image', $fields);

                    if (is_object($media)) {
                        $style_urls = [];
                        $styles = ['thumbnail', 'medium', 'large', 'media_api'];
                        \Drupal::logger('api_solutions')->info('Starting image style generation for file: @file', ['@file' => $file->getFileUri()]);

                        foreach ($styles as $style_name) {
                            $style = \Drupal\image\Entity\ImageStyle::load($style_name);
                            if ($style) {
                                // Ensure the style is physically generated on the server.
                                $destination = $style->buildUri($file->getFileUri());
                                if (!file_exists($destination)) {
                                    $style->createDerivative($file->getFileUri(), $destination);
                                }

                                // Produce absolute URL.
                                $url = \Drupal::service('file_url_generator')->generateAbsoluteString($style->buildUrl($file->getFileUri()));
                                $style_urls[$style_name] = $url;

                                \Drupal::logger('api_solutions')->info('Style @style generated: @url', [
                                    '@style' => $style_name,
                                    '@url' => $url,
                                ]);
                            } else {
                                \Drupal::logger('api_solutions')->warning('Style @style could not be loaded.', ['@style' => $style_name]);
                            }
                        }

                        $json = [
                            'id' => $media->id(),
                            'status' => true,
                            'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()),
                            'styles' => $style_urls,
                        ];
                    }
                }
            }
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
     * Custom access check.
     */
    public function apiJsonAccess()
    {
        return AccessResult::allowed();
    }

}
